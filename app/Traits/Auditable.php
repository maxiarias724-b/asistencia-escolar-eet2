<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Registra automáticamente en `audit_logs` las operaciones `created`, `updated` y `deleted`
 * de cualquier modelo institucional, omitiendo campos sensibles (`password`, `remember_token`).
 */
trait Auditable
{
    protected static bool $auditingEnabled = true;

    protected static ?bool $auditTableReady = null;

    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            self::writeAuditLog($model, 'created', null, self::sanitizeAuditValues($model->getAttributes()));
        });

        static::updated(function (Model $model): void {
            $changes = self::sanitizeAuditValues($model->getChanges());
            if ($changes === []) {
                return;
            }

            $original = Arr::only(self::sanitizeAuditValues($model->getOriginal()), array_keys($changes));
            self::writeAuditLog($model, 'updated', $original, $changes);
        });

        static::deleted(function (Model $model): void {
            self::writeAuditLog($model, 'deleted', self::sanitizeAuditValues($model->getOriginal()), null);
        });
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /** Ejecuta un bloque desactivando temporalmente el registro en `audit_logs`. */
    public static function withoutAuditing(callable $callback): mixed
    {
        $previous = self::$auditingEnabled;
        self::$auditingEnabled = false;

        try {
            return $callback();
        } finally {
            self::$auditingEnabled = $previous;
        }
    }

    public static function flushAuditTableCache(): void
    {
        self::$auditTableReady = null;
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    private static function writeAuditLog(Model $model, string $action, ?array $old, ?array $new): void
    {
        if (! self::$auditingEnabled || ! self::hasAuditTable()) {
            return;
        }

        $actionVerb = match ($action) {
            'created' => 'Creación',
            'updated' => 'Actualización',
            'deleted' => 'Eliminación',
            default => ucfirst($action),
        };

        $entityLabel = method_exists($model, 'auditLabel')
            ? $model->auditLabel()
            : class_basename($model).' #'.$model->getKey();

        $request = app()->bound('request') ? request() : null;

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $model::class,
            'auditable_id' => is_numeric($model->getKey()) ? (int) $model->getKey() : null,
            'description' => "{$actionVerb} de {$entityLabel}",
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => $request?->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function sanitizeAuditValues(array $values): array
    {
        unset($values['password'], $values['remember_token'], $values['created_at'], $values['updated_at']);

        foreach ($values as $key => $value) {
            if ($value instanceof \BackedEnum) {
                $values[$key] = $value->value;
            } elseif ($value instanceof \DateTimeInterface) {
                $values[$key] = $value->format('Y-m-d H:i:s');
            } elseif ($value instanceof \Stringable) {
                $values[$key] = (string) $value;
            }
        }

        return $values;
    }

    private static function hasAuditTable(): bool
    {
        if (self::$auditTableReady === true) {
            return true;
        }

        try {
            if (Schema::hasTable('audit_logs')) {
                return self::$auditTableReady = true;
            }

            return false;
        } catch (\Throwable) {
            return false;
        }
    }
}
