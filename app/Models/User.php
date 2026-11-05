<?php

namespace App\Models;

use App\Casts\DynamicValue;
use App\Enums\ActivityType;
use App\Enums\Role;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Auditable, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'username',
        'email',
        'password',
        'role',
        'active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
            'active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if (blank($user->name) && (filled($user->first_name) || filled($user->last_name))) {
                $user->name = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
            } elseif (filled($user->name) && blank($user->first_name) && blank($user->last_name)) {
                $clean = trim(preg_replace('/\s*\(.*\)\s*$/', '', $user->name));
                $parts = preg_split('/\s+/', $clean, 2) ?: [];
                $user->first_name = $parts[0] ?? $user->name;
                $user->last_name = $parts[1] ?? '';
            }
        });
    }

    /* ----------------------------- Relaciones ----------------------------- */

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_user')
            ->using(CourseAssignment::class)
            ->withPivot(['id', 'activity_type', 'scope_id', 'shift_id'])
            ->withTimestamps();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CourseAssignment::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function teacherAttendances(): HasMany
    {
        return $this->hasMany(TeacherAttendance::class, 'recorded_by');
    }

    public function justifications(): HasMany
    {
        return $this->hasMany(Justification::class, 'justified_by');
    }

    public function recordedAuditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /* ------------------------------- Helpers ------------------------------ */

    public function isDirector(): bool
    {
        return $this->role === Role::Director;
    }

    public function isOperador(): bool
    {
        return $this->role === Role::Operador;
    }

    public function isDocente(): bool
    {
        return $this->role === Role::Docente;
    }

    /**
     * ¿Puede este usuario gestionar la asistencia de este curso y ámbito?
     * El Director tiene acceso total; el Operador solo lo asignado explícitamente; el Docente solo consulta.
     */
    public function canManageAttendance(
        Course|int $course,
        ActivityType|Scope|DynamicValue|string|int|null $activity = null,
        ?int $shiftId = null,
    ): bool {
        if (! $this->active) {
            return false;
        }

        if ($this->isDirector()) {
            return true;
        }

        if (! $this->isOperador()) {
            return false;
        }

        $courseId = $course instanceof Course ? $course->getKey() : $course;

        $query = $this->assignments()->where('course_id', $courseId);

        if ($activity !== null) {
            if (is_int($activity)) {
                $query->where('scope_id', $activity);
            } else {
                $slug = match (true) {
                    $activity instanceof Scope => $activity->slug,
                    $activity instanceof ActivityType, $activity instanceof DynamicValue => $activity->value,
                    default => (string) $activity,
                };
                $scopeId = $activity instanceof Scope ? $activity->id : Scope::query()->where('slug', $slug)->value('id');

                $query->where(function ($q) use ($slug, $scopeId) {
                    $q->where('activity_type', $slug);
                    if ($scopeId) {
                        $q->orWhere('scope_id', $scopeId);
                    }
                });
            }
        }

        if ($shiftId !== null) {
            $query->where(fn ($q) => $q->whereNull('shift_id')->orWhere('shift_id', $shiftId));
        }

        return $query->exists();
    }

    /** Asigna (idempotente) un curso + ámbito (+ turno opcional) al operador. */
    public function assignCourse(
        Course|int $course,
        ActivityType|Scope|DynamicValue|string $activity,
        Shift|int|null $shift = null,
    ): CourseAssignment {
        $courseId = $course instanceof Course ? $course->getKey() : $course;
        $slug = match (true) {
            $activity instanceof Scope => $activity->slug,
            $activity instanceof ActivityType, $activity instanceof DynamicValue => $activity->value,
            default => (string) $activity,
        };
        $scopeId = $activity instanceof Scope
            ? $activity->id
            : Scope::query()->where('slug', $slug)->value('id');

        $shiftId = $shift instanceof Shift ? $shift->getKey() : $shift;

        $assignment = $this->assignments()->firstOrCreate(
            [
                'course_id' => $courseId,
                'activity_type' => $slug,
            ],
            [
                'scope_id' => $scopeId,
                'shift_id' => $shiftId,
            ]
        );

        if (($scopeId && $assignment->scope_id !== $scopeId) || ($shiftId !== null && $assignment->shift_id !== $shiftId)) {
            $assignment->update(array_filter([
                'scope_id' => $scopeId,
                'shift_id' => $shiftId,
            ], fn ($v) => $v !== null));
        }

        return $assignment;
    }

    public function auditLabel(): string
    {
        return "Usuario «{$this->name}» ({$this->username})";
    }
}
