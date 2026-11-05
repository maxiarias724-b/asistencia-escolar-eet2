<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Justification;
use App\Models\Scope;
use App\Models\TeacherAttendance;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Consultas agregadas del Dashboard Global del Director.
 * El % de asistencia usa el método ponderado de SINIDE: (registros - inasistencia ponderada) / registros,
 * donde C = 1, M = ½, Q = ¼ y J conserva el valor de la falta original.
 */
class AttendanceStatistics
{
    public readonly Carbon $from;

    public readonly Carbon $to;

    public function __construct(
        ?string $month = null,
        private readonly ?int $courseId = null,
        private readonly ?string $activity = null,
    ) {
        $base = $month && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)
            ? Carbon::createFromFormat('Y-m-d', $month.'-01')
            : now();

        $this->from = $base->copy()->startOfMonth()->startOfDay();
        $this->to = $base->copy()->endOfMonth()->startOfDay();
    }

    private function base(): Builder
    {
        return Attendance::query()
            ->whereBetween('date', [$this->from->toDateString(), $this->to->toDateString()])
            ->when($this->courseId, fn ($q) => $q->where('course_id', $this->courseId))
            ->when(filled($this->activity), fn ($q) => $q->where('activity_type', $this->activity));
    }

    private static function rate(int $total, float $absence): ?float
    {
        return $total > 0 ? round((($total - $absence) / $total) * 100, 1) : null;
    }

    /** Métricas en vivo del día actual (estudiantes y docentes) según el reloj del servidor. */
    public function todayTotals(): array
    {
        $today = now()->toDateString();

        $studentRows = Attendance::query()->toBase()
            ->select('status', DB::raw('count(*) as total'))
            ->where('date', $today)
            ->groupBy('status')
            ->pluck('total', 'status');

        $students = [];
        foreach (AttendanceStatus::cases() as $status) {
            $students[$status->value] = (int) ($studentRows[$status->value] ?? 0);
        }
        $students['total'] = array_sum($students);

        $teacherRows = TeacherAttendance::query()->toBase()
            ->select('status', DB::raw('count(*) as total'))
            ->where('date', $today)
            ->groupBy('status')
            ->pluck('total', 'status');

        $teachers = [];
        foreach (AttendanceStatus::cases() as $status) {
            $teachers[$status->value] = (int) ($teacherRows[$status->value] ?? 0);
        }
        $teachers['total'] = array_sum($teachers);

        return [
            'date' => $today,
            'students' => $students,
            'teachers' => $teachers,
        ];
    }

    /** Totales por estado + inasistencia ponderada + % de asistencia del período. */
    public function totals(): array
    {
        $rows = $this->base()->toBase()
            ->select('status', DB::raw('count(*) as total'), DB::raw('sum(absence_value) as value'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $result = [];
        $absence = 0.0;
        foreach (AttendanceStatus::cases() as $status) {
            $row = $rows[$status->value] ?? null;
            $result[$status->value] = (int) ($row->total ?? 0);
            $absence += (float) ($row->value ?? 0);
        }

        $total = array_sum($result);
        $result['total'] = $total;
        $result['absence_value'] = round($absence, 2);
        $result['attendance_rate'] = self::rate($total, $absence);
        $result['observations'] = (clone $this->base())->whereNotNull('observation')->count();

        return $result;
    }

    /** Matriz por curso o por ámbito configurable (`scopes`): conteo de cada estado + % asistencia ponderado. */
    public function breakdown(string $column): Collection
    {
        $rows = $this->base()->toBase()
            ->select($column, 'status', DB::raw('count(*) as total'), DB::raw('sum(absence_value) as value'))
            ->groupBy($column, 'status')
            ->get()
            ->groupBy($column);

        $courses = $column === 'course_id' ? Course::ordered()->get()->keyBy('id') : null;
        $scopeNames = $column === 'activity_type' ? Scope::pluck('name', 'slug') : null;

        return $rows->map(function ($group, $key) use ($courses, $scopeNames, $column) {
            $by = $group->keyBy('status');
            $count = fn (string $s) => (int) ($by[$s]->total ?? 0);
            $total = (int) $group->sum('total');
            $absence = (float) $group->sum('value');

            $label = $column === 'course_id'
                ? ($courses[$key]->full_name ?? "Curso #{$key}")
                : ($scopeNames[$key] ?? ActivityType::tryFrom((string) $key)?->label() ?? ucfirst(str_replace(['_', '-'], ' ', (string) $key)));

            return [
                'label' => $label,
                'sort' => $column === 'course_id'
                    ? sprintf('%02d-%s-%s', $courses[$key]->year ?? 0, $courses[$key]->division ?? '', $courses[$key]->shift?->value ?? '')
                    : (string) $key,
                'presente' => $count('presente'),
                'ausente' => $count('ausente'),
                'tardanza' => $count('tardanza'),
                'media_falta' => $count('media_falta'),
                'justificada' => $count('justificada'),
                'total' => $total,
                'rate' => self::rate($total, $absence),
            ];
        })->sortBy('sort')->values();
    }

    /** Alumnos con más inasistencia injustificada (ponderada) en el período. */
    public function topUnjustified(int $limit = 5): Collection
    {
        return $this->base()
            ->unjustifiedAbsences()
            ->select('student_id', DB::raw('count(*) as total'), DB::raw('sum(absence_value) as value'))
            ->groupBy('student_id')
            ->orderByDesc('value')
            ->limit($limit)
            ->with('student.course')
            ->get();
    }

    public function recentObservations(int $limit = 6): Collection
    {
        return $this->base()
            ->whereNotNull('observation')
            ->with(['student', 'course', 'user'])
            ->latest('date')->latest('id')
            ->limit($limit)
            ->get();
    }

    public function recentJustifications(int $limit = 6): Collection
    {
        return Justification::query()
            ->whereHas('attendance', function ($q) {
                $q->whereBetween('date', [$this->from->toDateString(), $this->to->toDateString()])
                    ->when($this->courseId, fn ($qq) => $qq->where('course_id', $this->courseId))
                    ->when(filled($this->activity), fn ($qq) => $qq->where('activity_type', $this->activity));
            })
            ->with(['attendance.student', 'attendance.course', 'justifiedBy'])
            ->latest('justified_at')
            ->limit($limit)
            ->get();
    }
}
