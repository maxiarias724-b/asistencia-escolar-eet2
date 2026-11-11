<?php

namespace App\Models;

use App\Enums\QrInterventionStatus;
use App\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrIntervention extends Model
{
    use Auditable, HasFactory;

    public const STATUS_EVALUACION = 'evaluacion';
    public const STATUS_INTERVENCION_ACTIVA = 'intervencion_activa';
    public const STATUS_REVINCULADO = 'revinculado';
    public const STATUS_ABANDONO = 'abandono';

    protected $fillable = [
        'student_id',
        'initiated_by',
        'detection_date',
        'consecutive_absences_flag',
        'territorial_team_contact',
        'home_visit_date',
        'pedagogical_support_details',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'consecutive_absences_flag' => 'integer',
            'status' => QrInterventionStatus::class,
        ];
    }

    protected function detectionDate(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->startOfDay() : null,
            set: fn ($value) => $value ? Carbon::parse($value)->toDateString() : null,
        );
    }

    protected function homeVisitDate(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->startOfDay() : null,
            set: fn ($value) => filled($value) ? Carbon::parse($value)->toDateString() : null,
        );
    }

    /* ----------------------------- Relaciones ----------------------------- */

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    /* ------------------------------ Accessors ----------------------------- */

    protected function statusLabel(): Attribute
    {
        return Attribute::get(fn () => $this->status instanceof QrInterventionStatus
            ? $this->status->label()
            : (QrInterventionStatus::tryFrom((string) $this->status)?->label() ?? (string) $this->status));
    }

    protected function statusBadge(): Attribute
    {
        return Attribute::get(fn () => $this->status instanceof QrInterventionStatus
            ? $this->status->badgeClass()
            : (QrInterventionStatus::tryFrom((string) $this->status)?->badgeClass() ?? 'bg-slate-100 text-slate-700'));
    }

    /* -------------------------------- Scopes ------------------------------ */

    /**
     * Filtra los expedientes según la matriz de permisos:
     * - Director: acceso total a toda la escuela.
     * - Preceptor (Operador): solo lectura de alumnos pertenecientes a sus cursos asignados en `course_user`.
     * - Docente: acceso denegado (información reservada).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->active || $user->isDocente()) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isDirector()) {
            return $query;
        }

        if ($user->isOperador()) {
            $courseIds = $user->assignedCourseIds();

            return $query->whereHas('student', function (Builder $studentQuery) use ($courseIds): void {
                $studentQuery->whereIn('course_id', $courseIds);
            });
        }

        return $query->whereRaw('1 = 0');
    }

    public function auditLabel(): string
    {
        return 'Expediente Programa QR #'.$this->getKey().' (Alumno #'.$this->student_id.' · '.$this->status_label.')';
    }
}
