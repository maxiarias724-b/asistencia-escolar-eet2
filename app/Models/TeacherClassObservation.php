<?php

namespace App\Models;

use App\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherClassObservation extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'student_id',
        'teacher_id',
        'activity_id',
        'date',
        'observation_text',
    ];

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

    /**
     * Atributo auxiliar para comparar fechas en formato Y-m-d sobre colecciones Eloquent.
     */
    protected function dateString(): Attribute
    {
        return Attribute::get(fn () => $this->date?->toDateString());
    }

    /* ----------------------------- Relaciones ----------------------------- */

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    /* -------------------------------- Scopes ------------------------------ */

    public function scopeForDate(Builder $query, Carbon|string $date): Builder
    {
        $ymd = $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();

        return $query->where('date', $ymd);
    }

    public function scopeBetweenDates(Builder $query, Carbon|string $from, Carbon|string $to): Builder
    {
        $fromYmd = $from instanceof Carbon ? $from->toDateString() : Carbon::parse($from)->toDateString();
        $toYmd = $to instanceof Carbon ? $to->toDateString() : Carbon::parse($to)->toDateString();

        return $query->whereBetween('date', [$fromYmd, $toYmd]);
    }

    public function scopeForCourse(Builder $query, int $courseId): Builder
    {
        return $query->whereHas('student', function (Builder $studentQuery) use ($courseId): void {
            $studentQuery->where('course_id', $courseId);
        });
    }

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
        return 'Observación Docente #'.$this->getKey().' (Alumno #'.$this->student_id.' · '.$this->date?->format('d/m/Y').')';
    }
}
