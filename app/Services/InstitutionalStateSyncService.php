<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Sincroniza el estado institucional completo (usuarios creados por el Director,
 * asignaciones de preceptores/operadores, padrón docente/estudiantil, asistencias,
 * justificaciones, amonestaciones, intervenciones QR y observaciones docentes)
 * hacia y desde `database/seeders/data/institutional_state.json`.
 *
 * Permite que los usuarios y registros creados en una computadora impacten
 * automáticamente en todas las demás computadoras al actualizar el proyecto o ejecutar seeders.
 */
class InstitutionalStateSyncService
{
    /**
     * Tablas institucionales sincronizadas en orden de integridad referencial.
     *
     * @var array<int, string>
     */
    private const TABLES = [
        'shifts',
        'specialties',
        'scopes',
        'activities',
        'courses',
        'non_working_days',
        'users',
        'course_user',
        'students',
        'teachers',
        'attendances',
        'justifications',
        'teacher_attendances',
        'disciplinary_records',
        'qr_interventions',
        'teacher_class_observations',
    ];

    private static bool $syncInProgress = false;

    private static bool $exportScheduled = false;

    public static function isSyncInProgress(): bool
    {
        return self::$syncInProgress;
    }

    public function snapshotPath(): string
    {
        return database_path('seeders/data/institutional_state.json');
    }

    public function hashFilePath(): string
    {
        return storage_path('framework/institutional_state.hash');
    }

    /**
     * Programa una única exportación al finalizar el ciclo de la petición actual
     * cuando ocurre una mutación en modelos auditables fuera de pruebas unitarias.
     */
    public function scheduleExport(): void
    {
        if (self::$syncInProgress || app()->runningUnitTests()) {
            return;
        }

        if (self::$exportScheduled) {
            return;
        }

        self::$exportScheduled = true;

        app()->terminating(function (): void {
            try {
                $this->exportSnapshot();
            } catch (\Throwable) {
                // Evita interrumpir la respuesta HTTP si el sistema de archivos está bloqueado.
            } finally {
                self::$exportScheduled = false;
            }
        });
    }

    /**
     * Exporta el estado actual de la base de datos institucional al archivo JSON versionable.
     */
    public function exportSnapshot(?string $customPath = null): bool
    {
        if (self::$syncInProgress) {
            return false;
        }

        $targetPath = $customPath ?? $this->snapshotPath();

        foreach (['users', 'courses', 'attendances'] as $requiredTable) {
            if (! Schema::hasTable($requiredTable)) {
                return false;
            }
        }

        $tablesPayload = [];

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $rows = DB::table($table)
                ->orderBy('id')
                ->get()
                ->map(function (object $row) use ($table): array {
                    $item = (array) $row;
                    if ($table === 'users') {
                        unset($item['remember_token']);
                    }

                    return $item;
                })
                ->all();

            $tablesPayload[$table] = $rows;
        }

        $payload = [
            'meta' => [
                'institution' => 'EET N° 2 "Prof. Jesús Raúl Salazar"',
                'version' => '1.2',
                'exported_at' => now()->toIso8601String(),
            ],
            'tables' => $tablesPayload,
        ];

        $directory = dirname($targetPath);
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        File::put($targetPath, $json."\n");

        if ($customPath === null) {
            $this->writeSyncedHash($targetPath);
        }

        return true;
    }

    /**
     * Importa automáticamente el snapshot institucional si el archivo JSON cambió
     * o si faltan usuarios/registros en la base de datos local de esta computadora.
     */
    public function importSnapshotIfNewer(): bool
    {
        if (self::$syncInProgress || app()->runningUnitTests()) {
            return false;
        }

        $path = $this->snapshotPath();
        if (! File::exists($path)) {
            return false;
        }

        try {
            if (! Schema::hasTable('users') || ! Schema::hasTable('attendances')) {
                return false;
            }
        } catch (\Throwable) {
            return false;
        }

        $currentSignature = $this->buildStateSignature($path);
        if (! $currentSignature) {
            return false;
        }

        $storedSignature = File::exists($this->hashFilePath())
            ? trim((string) File::get($this->hashFilePath()))
            : null;

        if ($storedSignature === $currentSignature) {
            return false;
        }

        return $this->importSnapshot(true);
    }

    /**
     * Importa el archivo `institutional_state.json` sincronizando todas las tablas institucionales.
     */
    public function importSnapshot(bool $force = false, ?string $customPath = null): bool
    {
        if (self::$syncInProgress) {
            return false;
        }

        $path = $customPath ?? $this->snapshotPath();
        if (! File::exists($path)) {
            return false;
        }

        $raw = File::get($path);
        $decoded = json_decode($raw, true);

        if (! is_array($decoded) || ! isset($decoded['tables']) || ! is_array($decoded['tables'])) {
            return false;
        }

        self::$syncInProgress = true;

        try {
            DB::transaction(function () use ($decoded): void {
                Schema::disableForeignKeyConstraints();

                try {
                    foreach (array_reverse(self::TABLES) as $table) {
                        if (! isset($decoded['tables'][$table]) || ! is_array($decoded['tables'][$table])) {
                            continue;
                        }

                        if (! Schema::hasTable($table)) {
                            continue;
                        }

                        DB::table($table)->delete();
                    }

                    foreach (self::TABLES as $table) {
                        if (! isset($decoded['tables'][$table]) || ! is_array($decoded['tables'][$table])) {
                            continue;
                        }

                        if (! Schema::hasTable($table)) {
                            continue;
                        }

                        $rows = $decoded['tables'][$table];
                        if ($rows === []) {
                            continue;
                        }

                        $validColumns = array_flip(Schema::getColumnListing($table));
                        $filteredRows = array_map(
                            fn (array $row): array => array_intersect_key($row, $validColumns),
                            $rows
                        );

                        foreach (array_chunk($filteredRows, 200) as $chunk) {
                            DB::table($table)->insert($chunk);
                        }
                    }
                } finally {
                    Schema::enableForeignKeyConstraints();
                }
            });

            if ($customPath === null) {
                $this->writeSyncedHash($path);
            }

            return true;
        } finally {
            self::$syncInProgress = false;
        }
    }

    private function buildStateSignature(string $snapshotPath): ?string
    {
        $hash = @md5_file($snapshotPath);
        if (! $hash) {
            return null;
        }

        $usersCount = DB::table('users')->count();
        $attendancesCount = DB::table('attendances')->count();

        return "{$hash}:{$usersCount}:{$attendancesCount}";
    }

    private function writeSyncedHash(string $snapshotPath): void
    {
        $signature = $this->buildStateSignature($snapshotPath);
        if (! $signature) {
            return;
        }

        $hashPath = $this->hashFilePath();
        $dir = dirname($hashPath);
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put($hashPath, $signature);
    }
}
