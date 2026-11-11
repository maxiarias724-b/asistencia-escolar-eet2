<?php

namespace App\Services;

use App\Casts\DynamicValue;
use App\Enums\ActivityType;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Scope;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Arma el "Registro de asistencia mensual" (réplica del reporte de SINIDE) de un curso y una actividad.
 *
 *  ASIST. = días hábiles considerados - (J + INJ)
 *  J.     = inasistencia justificada del mes     INJ. = inasistencia injustificada del mes (C=1, M=½, Q=¼)
 *  Anterior* = inasistencias acumuladas del ciclo lectivo hasta el mes anterior
 *  TOTAL  = inasistencias totales del mes (J + INJ)
 *
 * Para el mes en curso los días considerados llegan hasta hoy (no se premia a los días que aún no ocurrieron).
 */
class MonthlyRegister
{
    /** Mes en que arranca el ciclo lectivo (marzo). */
    public const SCHOOL_YEAR_START_MONTH = 3;

    public function __construct(private readonly SchoolCalendar $calendar)
    {
    }

    public function build(Course $course, ActivityType|DynamicValue|Scope $activity, Carbon $month): array
    {
        if ($activity instanceof Scope) {
            $activity = new DynamicValue($activity->slug, $activity->name);
        }
        $month = $month->copy()->startOfMonth()->startOfDay();
        $end = $month->copy()->endOfMonth()->startOfDay();
        $today = now()->startOfDay();

        $days = $this->calendar->days($month);
        foreach ($days as &$day) {
            $day['future'] = Carbon::parse($day['date'])->gt($today);
            $day['today'] = $day['date'] === $today->toDateString();
        }
        unset($day);

        $workingDays = collect($days)->where('working', true);
        $baseDays = $workingDays->where('future', false)->count();

        $attendances = Attendance::query()
            ->where('course_id', $course->id)
            ->where('activity_type', $activity->value)
            ->where('date', '>=', $month->toDateString())
            ->where('date', '<=', $end->toDateString())
            ->with(['justification.justifiedBy', 'recorder'])
            ->get();

        $byStudent = $attendances->groupBy('student_id');

        $students = Student::query()
            ->where('course_id', $course->id)
            ->where(fn ($q) => $q->where('status', 'activo')->orWhereIn('id', $byStudent->keys()))
            ->alphabetical()
            ->get();

        $previous = $this->previousAbsences($course, $activity, $month);

        $rows = $students->map(fn (Student $s) => $this->row($s, $byStudent->get($s->id, collect()), $previous[$s->id] ?? 0, $baseDays, $course, $activity))->values();

        $datesWithRecords = $attendances->map(fn ($a) => $a->date->toDateString())->unique();
        $pending = $rows->isEmpty() ? [] : $workingDays
            ->where('future', false)
            ->filter(fn ($d) => ! $datesWithRecords->contains($d['date']))
            ->pluck('n')->values()->all();

        $totalAsist = round($rows->sum('asist'), 2);
        $totalInasist = round($rows->sum('total'), 2);
        $activeCount = $students->filter(fn (Student $s) => $s->status->value === 'activo')->count();
        $leftCount = $students->count() - $activeCount;

        return [
            'course' => ['id' => $course->id, 'name' => $course->name, 'full_name' => $course->full_name],
            'activity' => ['value' => $activity->value, 'label' => $activity->label()],
            'month' => $month->format('Y-m'),
            'month_label' => ucfirst($month->translatedFormat('F \d\e Y')),
            'days' => $days,
            'working_days' => $workingDays->count(),
            'base_days' => $baseDays,
            'rows' => $rows->all(),
            'pending_days' => $pending,
            'footer' => [
                'enrolled' => $students->count(),
                'entered' => 0,
                'left' => $leftCount,
                'remaining' => $activeCount,
                'total_asist' => $totalAsist,
                'total_inasist' => $totalInasist,
                'media' => $baseDays > 0 ? round($totalAsist / $baseDays, 2) : 0,
                'percent' => ($rows->count() * $baseDays) > 0 ? round($totalAsist / ($rows->count() * $baseDays) * 100, 2) : 0,
            ],
        ];
    }

    private function row(Student $student, Collection $records, float $previous, int $baseDays, Course $course, ActivityType|DynamicValue $activity): array
    {
        $cells = [];
        $justified = 0.0;
        $unjustified = 0.0;
        $observations = [];

        foreach ($records->sortBy('date') as $a) {
            $cells[$a->date->day] = $this->cell($a, $course, $activity);

            if ($a->status === AttendanceStatus::Justificada) {
                $justified += (float) $a->absence_value;
            } else {
                $unjustified += (float) $a->absence_value;
            }

            if (filled($a->observation)) {
                $observations[] = $a->date->day.': '.$a->observation;
            }
        }

        $total = $justified + $unjustified;

        return [
            'id' => $student->id,
            'name' => $student->last_name.', '.$student->first_name,
            'dni' => $student->dni,
            'active' => $student->status->value === 'activo',
            'status_label' => $student->status->label(),
            'cells' => $cells,
            'asist' => round(max(0, $baseDays - $total), 2),
            'j' => round($justified, 2),
            'inj' => round($unjustified, 2),
            'anterior' => round($previous, 2),
            'total' => round($total, 2),
            'observations' => implode(' · ', $observations),
        ];
    }

    private function cell(Attendance $a, Course $course, ActivityType|DynamicValue $activity): array
    {
        $j = $a->justification;

        return [
            'id' => $a->id,
            'status' => $a->status->value,
            'code' => $a->status->code(),
            'observation' => $a->observation,
            'recorded_by' => $a->recorder?->name,
            'recorded_by_role' => $a->recorder?->role?->label(),
            'recorded_at' => $a->updated_at?->format('d/m/Y H:i'),
            'justification' => $j ? [
                'reason' => $j->reason_text,
                'has_certificate' => $j->hasCertificate(),
                'certificate_url' => $j->hasCertificate()
                    ? route('attendance.certificate', ['activity' => $activity->value, 'course' => $course->id, 'attendance' => $a->id])
                    : null,
                'certificate_is_pdf' => $j->hasCertificate() && strtolower(pathinfo($j->certificate_path, PATHINFO_EXTENSION)) === 'pdf',
                'by' => $j->justifiedBy?->name,
                'at' => $j->justified_at?->format('d/m/Y H:i'),
            ] : null,
        ];
    }

    /** Inasistencias acumuladas por alumno desde el inicio del ciclo lectivo hasta el mes anterior. */
    private function previousAbsences(Course $course, ActivityType|DynamicValue $activity, Carbon $month): array
    {
        $yearStart = $month->copy()->month(self::SCHOOL_YEAR_START_MONTH)->startOfMonth();
        if ($yearStart->gt($month)) {
            $yearStart->subYear();
        }

        if ($yearStart->gte($month)) {
            return [];
        }

        return Attendance::query()->toBase()
            ->selectRaw('student_id, sum(absence_value) as value')
            ->where('course_id', $course->id)
            ->where('activity_type', $activity->value)
            ->where('date', '>=', $yearStart->toDateString())
            ->where('date', '<', $month->toDateString())
            ->groupBy('student_id')
            ->pluck('value', 'student_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }
}
