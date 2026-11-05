<?php

namespace App\Models;

use App\Casts\DynamicValue;
use App\Casts\ScopeTypeCast;
use App\Enums\ActivityType;
use App\Enums\AttendanceStatus;
use App\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Attendance extends Model
{
    use Auditable;

    protected $fillable = [
        'student_id',
        'course_id',
        'scope_id',
        'activity_id',
        'shift_id',
        'user_id',
        'date',
        'activity_type',
        'status',
        'absence_value',
        'observation',
    ];

    protected function casts(): array
    {
        return [
            'activity_type' => ScopeTypeCast::class,
            'status' => AttendanceStatus::class,
            'absence_value' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Attendance $attendance): void {
            // Valor ponderado SINIDE (C=1, M=½, Q=¼, P=0; J conserva el peso de la falta original).
            $status = $attendance->status;
            if ($status !== AttendanceStatus::Justificada) {
                $attendance->absence_value = $status->weight();
            } elseif ((float) $attendance->absence_value <= 0) {
                $attendance->absence_value = 1;
            }

            // Sincronización ámbito configurable (`scopes`) <-> slug (`activity_type`).
            $rawActivity = $attendance->getAttributes()['activity_type'] ?? null;
            if ($attendance->scope_id && blank($rawActivity)) {
                $scope = Scope::find($attendance->scope_id);
                if ($scope) {
                    $attendance->setAttribute('activity_type', $scope->slug);
                }
            } elseif (filled($rawActivity) && ! $attendance->scope_id) {
                $slug = (string) $rawActivity;
                $defaultName = ActivityType::tryFrom($slug)?->label() ?? ucfirst(str_replace(['_', '-'], ' ', $slug));
                $scope = self::withoutAuditing(fn () => Scope::firstOrCreate(
                    ['slug' => $slug],
                    ['name' => $defaultName, 'active' => true]
                ));
                $attendance->scope_id = $scope->id;
            }

            // Si no se especificó turno explícito, hereda el turno del curso.
            if (! $attendance->shift_id && $attendance->course_id) {
                $attendance->shift_id = Course::query()->whereKey($attendance->course_id)->value('shift_id');
            }
        });
    }

    /**
     * `date` es una fecha pura (sin hora): se guarda siempre como Y-m-d en cualquier motor.
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->startOfDay() : null,
            set: fn ($value) => $value ? Carbon::parse($value)->toDateString() : null,
        );
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function scopeRef(): BelongsTo
    {
        return $this->belongsTo(Scope::class, 'scope_id');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    /** Usuario que tomó o modificó por última vez el registro. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function justification(): HasOne
    {
        return $this->hasOne(Justification::class);
    }

    public function scopeForGrid(Builder $query, int $courseId, ActivityType|Scope|DynamicValue|string $activity, string $from, string $to): Builder
    {
        $slug = match (true) {
            $activity instanceof Scope => $activity->slug,
            $activity instanceof ActivityType, $activity instanceof DynamicValue => $activity->value,
            default => (string) $activity,
        };

        return $query->where('course_id', $courseId)
            ->where('activity_type', $slug)
            ->whereBetween('date', [$from, $to]);
    }

    /** Faltas que siguen computando como injustificadas (C, M, Q; la justificada no). */
    public function scopeUnjustifiedAbsences(Builder $query): Builder
    {
        return $query->whereIn('status', [
            AttendanceStatus::Ausente->value,
            AttendanceStatus::MediaFalta->value,
            AttendanceStatus::Tardanza->value,
        ]);
    }

    public function auditLabel(): string
    {
        return 'Asistencia #'.$this->getKey().' (Alumno #'.$this->student_id.' · '.$this->date?->format('d/m/Y').')';
    }
}
