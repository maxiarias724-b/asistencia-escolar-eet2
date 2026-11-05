<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'description',
        'old_values',
        'new_values',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public static function record(
        string $action,
        string $description,
        string $auditableType = User::class,
        ?int $auditableId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null,
    ): self {
        return self::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()?->ip(),
        ]);
    }

    public function getEntityShortNameAttribute(): string
    {
        return match ($this->auditable_type) {
            User::class => 'Usuario',
            Specialty::class => 'Especialidad',
            Shift::class => 'Turno',
            Scope::class => 'Ámbito',
            Activity::class => 'Actividad',
            Course::class => 'Curso',
            CourseAssignment::class => 'Asignación',
            Student::class => 'Estudiante',
            Teacher::class => 'Docente',
            Attendance::class, StudentAttendance::class => 'Asistencia Estudiante',
            TeacherAttendance::class => 'Asistencia Docente',
            Justification::class => 'Justificación',
            NonWorkingDay::class => 'Calendario Escolar',
            default => class_basename($this->auditable_type),
        };
    }
}
