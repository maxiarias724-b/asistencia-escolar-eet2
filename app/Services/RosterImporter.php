<?php

namespace App\Services;

use App\Enums\StudentStatus;
use App\Models\Course;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Importación de la nómina de un curso desde CSV/Excel con la estructura de SINIDE:
 *   DNI/CUIL, Apellido(s), Nombre(s) [o "Apellido y Nombre"], Fecha de nacimiento, Estado de matrícula, Código SINIDE.
 *
 * Flujo en dos pasos: parse() + analyze() generan una vista previa sin tocar la BD;
 * apply() persiste solo las filas válidas.
 */
class RosterImporter
{
    /** Encabezados reconocidos (normalizados: sin tildes, minúsculas, sin signos) por campo. */
    private const HEADERS = [
        'dni' => ['dni', 'documento', 'nro documento', 'numero de documento', 'nro doc', 'n documento', 'nro de documento'],
        'cuil' => ['cuil', 'cuit', 'cuil cuit'],
        'last_name' => ['apellido', 'apellidos'],
        'first_name' => ['nombre', 'nombres'],
        'full_name' => ['apellido y nombre', 'apellido y nombres', 'apellidos y nombres', 'apellidos y nombre', 'apellido nombre', 'apellido nombres', 'alumno', 'alumno a', 'apellido y nombre alumno a'],
        'birth_date' => ['fecha de nacimiento', 'fecha nacimiento', 'nacimiento', 'fecha nac', 'f nacimiento', 'fec nacimiento'],
        'status' => ['estado', 'estado de matricula', 'estado matricula', 'matricula', 'situacion', 'condicion'],
        'sinide_code' => ['codigo sinide', 'codigo', 'cod sinide', 'id sinide', 'sinide'],
    ];

    private const STATUS_MAP = [
        'activo' => 'activo', 'activa' => 'activo', 'regular' => 'activo', 'inscripto' => 'activo', 'inscripta' => 'activo',
        'matriculado' => 'activo', 'matriculada' => 'activo',
        'libre' => 'libre',
        'pase' => 'pase', 'pasa' => 'pase',
        'baja' => 'baja', 'egresado' => 'baja', 'egresada' => 'baja', 'abandono' => 'baja',
    ];

    public function __construct(private readonly SpreadsheetReader $reader)
    {
    }

    /**
     * Lee el archivo y normaliza cada fila a un registro.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parse(UploadedFile $file): array
    {
        $rows = $this->reader->read($file);
        [$headerIndex, $map] = $this->findHeader($rows);

        $records = [];
        foreach (array_slice($rows, $headerIndex + 1, null, true) as $i => $row) {
            $get = fn (string $field) => isset($map[$field]) ? trim((string) ($row[$map[$field]] ?? '')) : '';

            $last = $get('last_name');
            $first = $get('first_name');
            $nameError = null;

            if (isset($map['full_name']) && $last === '' && $first === '') {
                $full = $get('full_name');
                if ($full !== '') {
                    if (str_contains($full, ',')) {
                        [$last, $first] = array_map('trim', explode(',', $full, 2));
                    } else {
                        $nameError = 'El nombre debe venir como «Apellido, Nombre».';
                    }
                }
            }

            $record = [
                'line' => $i + 1,
                'last_name' => $this->properCase($last),
                'first_name' => $this->properCase($first),
                'dni' => $this->digits($get('dni')),
                'cuil' => $this->digits($get('cuil')),
                'birth_raw' => $get('birth_date'),
                'status_raw' => $get('status'),
                'sinide_code' => $get('sinide_code') ?: null,
                'name_error' => $nameError,
            ];

            // Filas completamente vacías de datos útiles (totales, pies de página): se ignoran.
            if ($record['last_name'] === '' && $record['first_name'] === '' && $record['dni'] === '' && ! $nameError) {
                continue;
            }

            $records[] = $record;
        }

        if ($records === []) {
            throw new RuntimeException('El archivo no tiene filas de alumnos debajo del encabezado.');
        }

        return $records;
    }

    /**
     * Valida y decide la acción de cada registro respecto del curso.
     *
     * @param  array<int, array<string, mixed>>  $records
     * @return array<int, array<string, mixed>>
     */
    public function analyze(Course $course, array $records): array
    {
        $inCourse = Student::where('course_id', $course->id)->get();
        $byName = $inCourse->keyBy(fn (Student $s) => $this->nameKey($s->last_name, $s->first_name));

        $dnis = array_values(array_filter(array_column($records, 'dni')));
        $cuils = array_values(array_filter(array_map(fn ($r) => $this->formatCuil($r['cuil']), $records)));

        $dniOwners = $dnis ? Student::with('course')->whereIn('dni', $dnis)->get()->keyBy('dni') : collect();
        $cuilOwners = $cuils ? Student::whereIn('cuil', $cuils)->get()->keyBy('cuil') : collect();

        $seenDni = [];
        $seenName = [];
        $result = [];

        foreach ($records as $r) {
            $errors = [];
            $warnings = [];

            if ($r['name_error']) {
                $errors[] = $r['name_error'];
            } elseif ($r['last_name'] === '' || $r['first_name'] === '') {
                $errors[] = 'Falta el apellido o el nombre.';
            }

            if ($r['dni'] !== '' && ! preg_match('/^\d{7,9}$/', $r['dni'])) {
                $errors[] = 'DNI inválido (deben ser 7 a 9 dígitos).';
            }

            $cuil = null;
            if ($r['cuil'] !== '') {
                if (strlen($r['cuil']) !== 11) {
                    $errors[] = 'CUIL inválido (deben ser 11 dígitos).';
                } else {
                    $cuil = $this->formatCuil($r['cuil']);
                }
            }

            $birth = null;
            if ($r['birth_raw'] !== '') {
                $birth = $this->parseDate($r['birth_raw']);
                if (! $birth) {
                    $errors[] = "Fecha de nacimiento ilegible («{$r['birth_raw']}»).";
                } elseif (Carbon::parse($birth)->isFuture()) {
                    $errors[] = 'La fecha de nacimiento es futura.';
                }
            }

            $status = null;
            if ($r['status_raw'] !== '') {
                $status = self::STATUS_MAP[Str::lower(Str::ascii($r['status_raw']))] ?? null;
                if (! $status) {
                    $errors[] = "Estado de matrícula desconocido («{$r['status_raw']}»).";
                }
            }

            $nameKey = $this->nameKey($r['last_name'], $r['first_name']);

            if ($r['dni'] !== '' && isset($seenDni[$r['dni']])) {
                $errors[] = "DNI repetido en el archivo (línea {$seenDni[$r['dni']]}).";
            } elseif ($r['dni'] === '' && $nameKey !== '|' && isset($seenName[$nameKey])) {
                $errors[] = "Alumno repetido en el archivo (línea {$seenName[$nameKey]}).";
            }
            if ($r['dni'] !== '') {
                $seenDni[$r['dni']] ??= $r['line'];
            }
            $seenName[$nameKey] ??= $r['line'];

            // --- Coincidencia con la base -------------------------------------------------------
            $target = null;
            if ($r['dni'] !== '' && ($owner = $dniOwners->get($r['dni']))) {
                if ($owner->course_id !== $course->id) {
                    $errors[] = "El DNI ya está registrado en {$owner->course->name} ({$owner->last_name}, {$owner->first_name}).";
                } else {
                    $target = $owner;
                }
            }

            if (! $target && ! $errors) {
                $candidate = $byName->get($nameKey);
                if ($candidate && ($candidate->dni === null || $candidate->dni === '' || $r['dni'] === '' || $candidate->dni === $r['dni'])) {
                    $target = $candidate;
                }
            }

            if ($cuil && ($owner = $cuilOwners->get($cuil)) && (! $target || $owner->id !== $target->id)) {
                $errors[] = "El CUIL ya pertenece a otro alumno ({$owner->last_name}, {$owner->first_name}).";
            }

            $data = array_filter([
                'last_name' => $r['last_name'],
                'first_name' => $r['first_name'],
                'dni' => $r['dni'] ?: null,
                'cuil' => $cuil,
                'birth_date' => $birth,
                'status' => $status,
                'sinide_code' => $r['sinide_code'],
            ], fn ($v) => $v !== null && $v !== '');

            $action = 'error';
            if (! $errors) {
                $action = $target ? 'actualizar' : 'crear';

                if ($target && $this->unchanged($target, $data)) {
                    $action = 'sin_cambios';
                }
            }

            $result[] = [
                'line' => $r['line'],
                'data' => $data,
                'action' => $action,
                'errors' => $errors,
                'warnings' => $warnings,
                'target_id' => $target?->id,
            ];
        }

        return $result;
    }

    /**
     * Persiste las filas válidas.
     *
     * @param  array<int, array<string, mixed>>  $rows  resultado de analyze()
     * @return array{created:int, updated:int, unchanged:int, errors:int}
     */
    public function apply(Course $course, array $rows): array
    {
        $count = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'errors' => 0];

        DB::transaction(function () use ($course, $rows, &$count) {
            foreach ($rows as $row) {
                switch ($row['action']) {
                    case 'crear':
                        Student::create($row['data'] + ['course_id' => $course->id, 'status' => StudentStatus::Activo->value]);
                        $count['created']++;
                        break;
                    case 'actualizar':
                        Student::where('course_id', $course->id)->findOrFail($row['target_id'])->update($row['data']);
                        $count['updated']++;
                        break;
                    case 'sin_cambios':
                        $count['unchanged']++;
                        break;
                    default:
                        $count['errors']++;
                }
            }
        });

        return $count;
    }

    /* ------------------------------------------------------------------ */

    /** @return array{0:int, 1:array<string,int>} */
    private function findHeader(array $rows): array
    {
        foreach (array_slice($rows, 0, 15, true) as $i => $row) {
            $map = [];
            foreach ($row as $col => $label) {
                $key = $this->normalizeHeader((string) $label);
                foreach (self::HEADERS as $field => $aliases) {
                    if (in_array($key, $aliases, true) && ! isset($map[$field])) {
                        $map[$field] = $col;
                    }
                }
            }

            if (isset($map['full_name']) || (isset($map['last_name']) && isset($map['first_name']))) {
                return [$i, $map];
            }
        }

        throw new RuntimeException(
            'No se encontró el encabezado. El archivo debe tener las columnas «Apellido» y «Nombre» (o «Apellido y Nombre»), y opcionalmente DNI, CUIL, Fecha de nacimiento, Estado de matrícula y Código SINIDE.'
        );
    }

    private function normalizeHeader(string $label): string
    {
        $label = Str::lower(Str::ascii($label));
        $label = preg_replace('/[^a-z0-9]+/', ' ', $label);

        return trim($label);
    }

    private function digits(string $value): string
    {
        $value = preg_replace('/\.0+$/', '', trim($value)); // "45123456.0" que deja Excel
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function formatCuil(string $digits): ?string
    {
        return strlen($digits) === 11
            ? substr($digits, 0, 2).'-'.substr($digits, 2, 8).'-'.substr($digits, 10, 1)
            : null;
    }

    private function properCase(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value));

        // SINIDE suele exportar en mayúsculas: se pasa a "Título" solo si todo está en mayúsculas.
        return ($value !== '' && $value === mb_strtoupper($value)) ? mb_convert_case($value, MB_CASE_TITLE) : $value;
    }

    private function nameKey(?string $last, ?string $first): string
    {
        $norm = fn (?string $v) => trim(preg_replace('/\s+/', ' ', Str::lower(Str::ascii((string) $v))));

        return $norm($last).'|'.$norm($first);
    }

    private function parseDate(string $raw): ?string
    {
        $raw = trim($raw);

        // Número de serie de Excel (p. ej. 38453).
        if (preg_match('/^\d{4,6}(\.\d+)?$/', $raw)) {
            $serial = (float) $raw;
            if ($serial > 1000 && $serial < 80000) {
                return Carbon::create(1899, 12, 30)->addDays((int) floor($serial))->toDateString();
            }
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y', 'd.m.Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $raw);
            } catch (\Throwable) {
                continue;
            }

            if ($date && $date->format($format) === $raw) {
                return $date->toDateString();
            }
        }

        return null;
    }

    /** ¿Los datos importados ya coinciden con lo guardado? */
    private function unchanged(Student $student, array $data): bool
    {
        foreach ($data as $field => $value) {
            $current = $student->{$field};
            $current = $current instanceof \BackedEnum ? $current->value : ($current instanceof Carbon ? $current->toDateString() : $current);

            if ((string) $current !== (string) $value) {
                return false;
            }
        }

        return true;
    }
}
