<?php

namespace App\Models;

use App\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplinaryRecord extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'student_id',
        'recorded_by',
        'date',
        'amount',
        'reason',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * `date` se persiste siempre como fecha pura Y-m-d y se hidrata como Carbon al inicio del día.
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->startOfDay() : null,
            set: fn ($value) => $value ? Carbon::parse($value)->toDateString() : null,
        );
    }

    /* ----------------------------- Relaciones ----------------------------- */

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /* -------------------------------- Scopes ------------------------------ */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Filtra los registros según la matriz de permisos:
     * - Director: todos los registros de la institución.
     * - Preceptor (Operador) y Docente: exclusivamente alumnos de sus cursos asignados en `course_user`.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->active) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isDirector()) {
            return $query;
        }

        if ($user->isOperador() || $user->isDocente()) {
            $courseIds = $user->assignedCourseIds();

            return $query->whereHas('student', function (Builder $studentQuery) use ($courseIds): void {
                $studentQuery->whereIn('course_id', $courseIds);
            });
        }

        return $query->whereRaw('1 = 0');
    }

    public function auditLabel(): string
    {
        return 'Amonestación #'.$this->getKey().' (Alumno #'.$this->student_id.' · '.$this->amount.' amon. · '.$this->date?->format('d/m/Y').')';
    }
}
