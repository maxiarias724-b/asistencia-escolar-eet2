<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Scope;
use App\Models\Student;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Servicio centralizado de Consultas, Estadísticas, Planillas Mensuales y Exportaciones (Excel, CSV y PDF).
 */
class AttendanceReportService
{
    public function __construct(
        private readonly MonthlyRegister $monthlyRegister,
        private readonly SpreadsheetWriter $spreadsheetWriter,
    ) {
    }

    /** @return array<int, int>|null `null` cuando el usuario es Director. */
    public function allowedCourseIds(User $user): ?array
    {
        if ($user->isDirector()) {
            return null;
        }

        return $user->assignments()->pluck('course_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    public function canAccessCourse(User $user, int $courseId, ?string $scopeSlug = null): bool
    {
        if ($user->isDirector()) {
            return true;
        }

        return $user->canManageAttendance($courseId, $scopeSlug);
    }

    /** Traduce el estado a sigla institucional (`P`, `A`, `T`, `M`, `IJ`) o SINIDE (`P`, `C`, `Q`, `M`, `J`). */
    public function codeFor(AttendanceStatus|string $status, string $notation = 'patij'): string
    {
        $enum = $status instanceof AttendanceStatus ? $status : AttendanceStatus::from($status);

        if ($notation === 'sinide') {
            return $enum->code();
        }

        return match ($enum) {
            AttendanceStatus::Presente => 'P',
            AttendanceStatus::Ausente => 'A',
            AttendanceStatus::Tardanza => 'T',
            AttendanceStatus::MediaFalta => 'M',
            AttendanceStatus::Justificada => 'IJ',
        };
    }

    /* ============================ 1. Consulta Histórica ============================ */

    public function historicalBaseQuery(array $filters, User $user): Builder
    {
        $allowed = $this->allowedCourseIds($user);

        return Attendance::query()
            ->with(['student', 'course.specialty', 'course.shiftRef', 'scopeRef', 'user', 'justification'])
            ->whereBetween('date', [$filters['from'], $filters['to']])
            ->when($allowed !== null, fn (Builder $q) => $q->whereIn('course_id', $allowed))
            ->when($filters['course_id'] ?? null, fn (Builder $q, $v) => $q->where('course_id', (int) $v))
            ->when($filters['scope'] ?? null, fn (Builder $q, $v) => $q->where('activity_type', $v))
            ->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->where('status', $v))
            ->when($filters['shift_id'] ?? null, fn (Builder $q, $v) => $q->whereHas('course', fn ($cq) => $cq->where('shift_id', (int) $v)))
            ->when($filters['specialty_id'] ?? null, fn (Builder $q, $v) => $q->whereHas('course', fn ($cq) => $cq->where('specialty_id', (int) $v)))
            ->when($filters['q'] ?? null, function (Builder $q, string $term) {
                $q->whereHas('student', function ($sq) use ($term) {
                    $sq->where('last_name', 'like', "%{$term}%")
                        ->orWhere('first_name', 'like', "%{$term}%")
                        ->orWhere('dni', 'like', "%{$term}%")
                        ->orWhere('file_number', 'like', "%{$term}%");
                });
            });
    }

    public function historicalKpis(Builder $query): array
    {
        $counts = (clone $query)->reorder()->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $total = (int) $counts->sum();
        $present = (int) ($counts[AttendanceStatus::Presente->value] ?? 0);

        return [
            'total' => $total,
            'presente' => $present,
            'ausente' => (int) ($counts[AttendanceStatus::Ausente->value] ?? 0),
            'tardanza' => (int) ($counts[AttendanceStatus::Tardanza->value] ?? 0) + (int) ($counts[AttendanceStatus::MediaFalta->value] ?? 0),
            'justificada' => (int) ($counts[AttendanceStatus::Justificada->value] ?? 0),
            'percent_present' => $total > 0 ? round($present / $total * 100, 1) : 0.0,
        ];
    }

    public function exportHistorical(string $format, Builder $query, array $filters): Response
    {
        $records = $query->orderByDesc('date')->orderBy('course_id')->limit(5000)->get();

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.pdf.historical', [
                'records' => $records,
                'filters' => $filters,
                'generatedAt' => now(),
            ])->setPaper('a4', 'landscape');

            return $pdf->download('consulta-asistencia-eet2.pdf');
        }

        $headers = ['Fecha', 'DNI', 'Estudiante', 'Curso', 'Turno', 'Ámbito', 'Sigla', 'Estado', 'Valor Falta', 'Observación', 'Registrado por'];
        $rows = $records->map(fn (Attendance $a) => [
            $a->date->format('d/m/Y'),
            $a->student?->dni ?? '',
            $a->student?->full_name ?? '',
            $a->course?->name ?? '',
            $a->course?->shiftRef?->name ?? $a->course?->shift?->label() ?? '',
            $a->scopeRef?->name ?? $a->activity_type->label(),
            $this->codeFor($a->status, 'patij'),
            $a->status->label(),
            number_format((float) $a->absence_value, 2, '.', ''),
            $a->observation ?? '',
            $a->user?->name ?? '',
        ])->all();

        return $format === 'csv'
            ? $this->spreadsheetWriter->downloadCsv('consulta-asistencia-eet2', $headers, $rows)
            : $this->spreadsheetWriter->downloadXlsx('consulta-asistencia-eet2', $headers, $rows, 'Consulta Asistencia');
    }

    /* ======================== 2. Ranking de Inasistencias ========================= */

    /** @return Collection<int, array<string, mixed>> */
    public function buildAbsenceRanking(array $filters, User $user): Collection
    {
        $allowed = $this->allowedCourseIds($user);

        $aggregated = Attendance::query()->toBase()
            ->selectRaw("
                student_id,
                SUM(CASE WHEN status = 'justificada' THEN absence_value ELSE 0 END) AS justified_sum,
                SUM(CASE WHEN status IN ('ausente', 'tardanza', 'media_falta') THEN absence_value ELSE 0 END) AS unjustified_sum,
                SUM(absence_value) AS total_sum,
                SUM(CASE WHEN status = 'tardanza' THEN 1 ELSE 0 END) AS tardanzas_count
            ")
            ->whereBetween('date', [$filters['from'], $filters['to']])
            ->when($allowed !== null, fn ($q) => $q->whereIn('course_id', $allowed))
            ->when($filters['course_id'] ?? null, fn ($q, $v) => $q->where('course_id', (int) $v))
            ->when($filters['scope'] ?? null, fn ($q, $v) => $q->where('activity_type', $v))
            ->groupBy('student_id')
            ->havingRaw('SUM(absence_value) > 0')
            ->get()
            ->keyBy('student_id');

        if ($aggregated->isEmpty()) {
            return collect();
        }

        $students = Student::query()
            ->with(['course.specialty', 'course.shiftRef'])
            ->whereIn('id', $aggregated->keys()->all())
            ->when($filters['shift_id'] ?? null, fn ($q, $v) => $q->whereHas('course', fn ($cq) => $cq->where('shift_id', (int) $v)))
            ->when($filters['specialty_id'] ?? null, fn ($q, $v) => $q->whereHas('course', fn ($cq) => $cq->where('specialty_id', (int) $v)))
            ->get()
            ->keyBy('id');

        return $aggregated->map(function ($agg, $studentId) use ($students) {
            $student = $students->get($studentId);
            if (! $student) {
                return null;
            }

            $total = round((float) $agg->total_sum, 2);
            $unjustified = round((float) $agg->unjustified_sum, 2);
            $justified = round((float) $agg->justified_sum, 2);

            $risk = match (true) {
                $total >= 25 => ['label' => 'Riesgo Libre (≥25)', 'badge' => 'bg-red-100 text-red-800'],
                $total >= 15 => ['label' => 'Crítico (≥15)', 'badge' => 'bg-orange-100 text-orange-800'],
                $total >= 10 => ['label' => 'Alerta (≥10)', 'badge' => 'bg-amber-100 text-amber-800'],
                default => ['label' => 'Seguimiento', 'badge' => 'bg-slate-100 text-slate-700'],
            };

            return [
                'student' => $student,
                'unjustified' => $unjustified,
                'justified' => $justified,
                'total' => $total,
                'tardanzas' => (int) $agg->tardanzas_count,
                'risk_label' => $risk['label'],
                'risk_badge' => $risk['badge'],
            ];
        })
            ->filter()
            ->sortByDesc(fn ($item) => sprintf('%08.2f-%08.2f', $item['total'], $item['unjustified']))
            ->values();
    }

    public function exportRanking(string $format, Collection $ranking, array $filters): Response
    {
        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.pdf.ranking', [
                'ranking' => $ranking,
                'filters' => $filters,
                'generatedAt' => now(),
            ])->setPaper('a4', 'portrait');

            return $pdf->download('ranking-inasistencias-eet2.pdf');
        }

        $headers = ['Posición', 'DNI', 'Estudiante', 'Curso', 'Especialidad', 'Turno', 'Injustificadas', 'Justificadas', 'Total Faltas', 'Tardanzas', 'Nivel de Alerta'];
        $rows = $ranking->values()->map(fn (array $r, int $i) => [
            $i + 1,
            $r['student']->dni ?? '',
            $r['student']->full_name,
            $r['student']->course?->name ?? '',
            $r['student']->course?->specialty?->name ?? '',
            $r['student']->course?->shiftRef?->name ?? $r['student']->course?->shift?->label() ?? '',
            number_format($r['unjustified'], 2, '.', ''),
            number_format($r['justified'], 2, '.', ''),
            number_format($r['total'], 2, '.', ''),
            $r['tardanzas'],
            $r['risk_label'],
        ])->all();

        return $format === 'csv'
            ? $this->spreadsheetWriter->downloadCsv('ranking-inasistencias-eet2', $headers, $rows)
            : $this->spreadsheetWriter->downloadXlsx('ranking-inasistencias-eet2', $headers, $rows, 'Ranking Inasistencias');
    }

    /* =================== 3. Historial Individual del Estudiante =================== */

    public function buildStudentHistory(Student $student, string $from, string $to, ?string $scopeSlug = null): array
    {
        $student->load(['course.specialty', 'course.shiftRef']);

        $records = Attendance::query()
            ->with(['scopeRef', 'user', 'justification.justifiedBy'])
            ->where('student_id', $student->id)
            ->whereBetween('date', [$from, $to])
            ->when($scopeSlug, fn ($q) => $q->where('activity_type', $scopeSlug))
            ->orderByDesc('date')
            ->get();

        $presentCount = $records->where('status', AttendanceStatus::Presente)->count();
        $justifiedSum = round((float) $records->where('status', AttendanceStatus::Justificada)->sum('absence_value'), 2);
        $unjustifiedSum = round((float) $records->filter(fn (Attendance $a) => $a->status->countsAsUnjustifiedAbsence())->sum('absence_value'), 2);
        $totalAbsences = round($justifiedSum + $unjustifiedSum, 2);

        $byScope = $records->groupBy(fn (Attendance $a) => $a->scopeRef?->name ?? $a->activity_type->label())->map(function (Collection $items) {
            return [
                'total_records' => $items->count(),
                'present' => $items->where('status', AttendanceStatus::Presente)->count(),
                'justified' => round((float) $items->where('status', AttendanceStatus::Justificada)->sum('absence_value'), 2),
                'unjustified' => round((float) $items->filter(fn (Attendance $a) => $a->status->countsAsUnjustifiedAbsence())->sum('absence_value'), 2),
            ];
        })->all();

        return [
            'student' => $student,
            'records' => $records,
            'summary' => [
                'total_records' => $records->count(),
                'present_count' => $presentCount,
                'justified_sum' => $justifiedSum,
                'unjustified_sum' => $unjustifiedSum,
                'total_absences' => $totalAbsences,
                'tardanzas_count' => $records->where('status', AttendanceStatus::Tardanza)->count(),
                'percent_present' => $records->count() > 0 ? round($presentCount / $records->count() * 100, 1) : 0.0,
            ],
            'by_scope' => $byScope,
        ];
    }

    public function exportStudentHistory(string $format, array $history, string $from, string $to): Response
    {
        /** @var Student $student */
        $student = $history['student'];
        $safeDni = $student->dni ?: $student->id;

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.pdf.student-history', $history + [
                'from' => $from,
                'to' => $to,
                'generatedAt' => now(),
            ])->setPaper('a4', 'portrait');

            return $pdf->download("historial-estudiante-{$safeDni}.pdf");
        }

        $headers = ['Fecha', 'Ámbito', 'Sigla', 'Estado', 'Valor Inasistencia', 'Observación', 'Motivo Justificación', 'Cargado por'];
        $rows = $history['records']->map(fn (Attendance $a) => [
            $a->date->format('d/m/Y'),
            $a->scopeRef?->name ?? $a->activity_type->label(),
            $this->codeFor($a->status, 'patij'),
            $a->status->label(),
            number_format((float) $a->absence_value, 2, '.', ''),
            $a->observation ?? '',
            $a->justification?->reason_text ?? ($a->justification?->hasCertificate() ? 'Certificado adjunto' : ''),
            $a->user?->name ?? '',
        ])->all();

        return $format === 'csv'
            ? $this->spreadsheetWriter->downloadCsv("historial-estudiante-{$safeDni}", $headers, $rows)
            : $this->spreadsheetWriter->downloadXlsx("historial-estudiante-{$safeDni}", $headers, $rows, 'Historial Estudiante');
    }

    /* ================= 4. Planilla Mensual (01..31) y Exportación ================= */

    public function buildMonthlySheet(Course $course, Scope $scope, Carbon $month, string $notation = 'patij'): array
    {
        $register = $this->monthlyRegister->build($course, $scope, $month);

        foreach ($register['rows'] as &$row) {
            foreach ($row['cells'] as $dayNum => &$cell) {
                $cell['display_code'] = $this->codeFor($cell['status'], $notation);
            }
            unset($cell);
        }
        unset($row);

        $register['notation'] = $notation;

        return $register;
    }

    public function exportMonthlySheet(string $format, Course $course, Scope $scope, Carbon $month, string $notation = 'patij'): Response
    {
        $sheet = $this->buildMonthlySheet($course, $scope, $month, $notation);
        $baseName = sprintf('planilla-mensual-%s-%s-%s', $course->year.'-'.$course->division, $scope->slug, $month->format('Y-m'));

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.pdf.monthly-sheet', [
                'course' => $course,
                'scope' => $scope,
                'sheet' => $sheet,
                'generatedAt' => now(),
            ])->setPaper('a4', 'landscape');

            return $pdf->download("{$baseName}.pdf");
        }

        $dayHeaders = array_map(fn (array $d) => str_pad((string) $d['n'], 2, '0', STR_PAD_LEFT), $sheet['days']);
        $headers = array_merge(
            ['Apellido y Nombre', 'DNI'],
            $dayHeaders,
            ['ASIST.', 'J.', 'INJ.', 'Anterior*', 'TOTAL', 'OBSERVACIONES']
        );

        $rows = [];
        foreach ($sheet['rows'] as $r) {
            $line = [$r['name'], $r['dni'] ?? ''];
            foreach ($sheet['days'] as $d) {
                if (! $d['working']) {
                    $line[] = '-';
                } else {
                    $line[] = $r['cells'][$d['n']]['display_code'] ?? '';
                }
            }
            $line[] = number_format((float) $r['asist'], 2, '.', '');
            $line[] = number_format((float) $r['j'], 2, '.', '');
            $line[] = number_format((float) $r['inj'], 2, '.', '');
            $line[] = number_format((float) $r['anterior'], 2, '.', '');
            $line[] = number_format((float) $r['total'], 2, '.', '');
            $line[] = $r['observations'];
            $rows[] = $line;
        }

        // Anexo Institucional al pie de la Planilla Mensual: Observaciones Docentes de Clase
        $rows[] = [];
        $rows[] = ['ANEXO INSTITUCIONAL: OBSERVACIONES DOCENTES DE CLASE (INCIDENCIAS POR MATERIA)'];
        $rows[] = ['Fecha', 'Alumno', 'Materia', 'Docente', 'Observación'];

        if (! empty($sheet['teacher_observations'])) {
            foreach ($sheet['teacher_observations'] as $tObs) {
                $rows[] = [
                    $tObs['date_formatted'] ?? '',
                    $tObs['student_name'] ?? '',
                    $tObs['activity_name'] ?? '',
                    $tObs['teacher_name'] ?? '',
                    $tObs['observation_text'] ?? '',
                ];
            }
        } else {
            $rows[] = ['Sin observaciones docentes registradas en el período.', '', '', '', ''];
        }

        return $format === 'csv'
            ? $this->spreadsheetWriter->downloadCsv($baseName, $headers, $rows)
            : $this->spreadsheetWriter->downloadXlsx($baseName, $headers, $rows, 'Planilla '.$month->format('m-Y'));
    }
}
