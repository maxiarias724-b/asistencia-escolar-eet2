<?php

namespace App\Http\Controllers;

use App\Enums\QrInterventionStatus;
use App\Http\Middleware\CheckCourseAssignment;
use App\Http\Requests\QrInterventionRequest;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\QrIntervention;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Módulo Programa QR ("Quiero Regresar") - Actualización 1.1 EET N.º 2.
 * Prevención del abandono y revinculación escolar mediante articulación territorial y visitas domiciliarias.
 * - Director: Acceso total (Ver, Crear y Editar expedientes de toda la escuela).
 * - Preceptor (Operador): Solo lectura de expedientes de sus cursos asignados (`course_user`).
 * - Docente: Acceso denegado (información socioeducativa reservada).
 */
class QrInterventionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        Gate::forUser($user)->authorize('viewAny', QrIntervention::class);

        $allowedCourseIds = $user->isDirector() ? null : $user->assignedCourseIds();

        if ($allowedCourseIds !== null && $request->filled('course_id')) {
            $requestedCourseId = (int) $request->input('course_id');
            if (! in_array($requestedCourseId, $allowedCourseIds, true)) {
                return $this->denyCourse($request);
            }
        }

        $q = $request->string('q')->trim()->toString();
        $courseId = $request->integer('course_id') ?: null;
        $statusFilter = $request->string('status')->trim()->toString();
        $from = $request->string('from')->trim()->toString();
        $to = $request->string('to')->trim()->toString();

        $baseVisibleQuery = QrIntervention::query()->visibleTo($user);

        $interventions = (clone $baseVisibleQuery)
            ->with(['student.course.specialty', 'student.course.shiftRef', 'initiator'])
            ->when($courseId, function ($query) use ($courseId): void {
                $query->whereHas('student', fn ($sq) => $sq->where('course_id', $courseId));
            })
            ->when($statusFilter !== '', fn ($query) => $query->where('status', $statusFilter))
            ->when($from !== '', fn ($query) => $query->whereDate('detection_date', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('detection_date', '<=', $to))
            ->when($q !== '', function ($query) use ($q): void {
                $digits = preg_replace('/\D+/', '', $q);
                $query->where(function ($sub) use ($q, $digits): void {
                    $sub->where('territorial_team_contact', 'like', "%{$q}%")
                        ->orWhere('pedagogical_support_details', 'like', "%{$q}%")
                        ->orWhereHas('student', function ($sq) use ($q, $digits): void {
                            $sq->where('last_name', 'like', "%{$q}%")
                                ->orWhere('first_name', 'like', "%{$q}%")
                                ->orWhere('dni', 'like', "%{$q}%")
                                ->orWhere('file_number', 'like', "%{$q}%");

                            if ($digits !== '' && $digits !== $q) {
                                $sq->orWhere('dni', 'like', "%{$digits}%");
                            }
                        });
                });
            })
            ->orderByDesc('detection_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $countsByStatus = (clone $baseVisibleQuery)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($v) => (int) $v);

        $summary = [
            'total' => (int) $countsByStatus->sum(),
            'evaluacion' => (int) ($countsByStatus[QrInterventionStatus::Evaluacion->value] ?? 0),
            'intervencion_activa' => (int) ($countsByStatus[QrInterventionStatus::IntervencionActiva->value] ?? 0),
            'revinculado' => (int) ($countsByStatus[QrInterventionStatus::Revinculado->value] ?? 0),
            'abandono' => (int) ($countsByStatus[QrInterventionStatus::Abandono->value] ?? 0),
        ];

        $coursesQuery = Course::with(['specialty', 'shiftRef'])->active()->ordered();
        if ($allowedCourseIds !== null) {
            $coursesQuery->whereIn('id', $allowedCourseIds);
        }

        return view('qr.index', [
            'interventions' => $interventions,
            'summary' => $summary,
            'courses' => $coursesQuery->get(),
            'statuses' => QrInterventionStatus::cases(),
            'filters' => [
                'q' => $q,
                'course_id' => $courseId,
                'status' => $statusFilter,
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }

    public function show(Request $request, QrIntervention $qrIntervention)
    {
        $qrIntervention->load(['student.course.specialty', 'student.course.shiftRef', 'initiator']);
        Gate::forUser($request->user())->authorize('view', $qrIntervention);

        $recentAbsences = Attendance::query()
            ->with(['scopeRef', 'activity', 'justification'])
            ->where('student_id', $qrIntervention->student_id)
            ->where('absence_value', '>', 0)
            ->orderByDesc('date')
            ->limit(15)
            ->get();

        $totalUnjustified = (float) Attendance::query()
            ->where('student_id', $qrIntervention->student_id)
            ->unjustifiedAbsences()
            ->sum('absence_value');

        $otherInterventions = QrIntervention::query()
            ->with('initiator')
            ->where('student_id', $qrIntervention->student_id)
            ->whereKeyNot($qrIntervention->id)
            ->orderByDesc('detection_date')
            ->get();

        return view('qr.show', [
            'intervention' => $qrIntervention,
            'recentAbsences' => $recentAbsences,
            'totalUnjustified' => $totalUnjustified,
            'otherInterventions' => $otherInterventions,
        ]);
    }

    public function create(Request $request)
    {
        Gate::forUser($request->user())->authorize('create', QrIntervention::class);

        $studentId = $request->integer('student_id') ?: null;
        $defaultAbsences = $request->integer('consecutive_absences_flag') ?: 3;

        $intervention = new QrIntervention([
            'student_id' => $studentId,
            'detection_date' => now()->toDateString(),
            'consecutive_absences_flag' => $defaultAbsences,
            'status' => QrInterventionStatus::Evaluacion,
        ]);

        return view('qr.create', $this->formData($intervention));
    }

    public function store(QrInterventionRequest $request): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('create', QrIntervention::class);

        $data = $request->validated();

        $intervention = QrIntervention::create([
            'student_id' => (int) $data['student_id'],
            'initiated_by' => (int) $request->user()->id,
            'detection_date' => $data['detection_date'],
            'consecutive_absences_flag' => (int) $data['consecutive_absences_flag'],
            'territorial_team_contact' => filled($data['territorial_team_contact'] ?? null) ? trim($data['territorial_team_contact']) : null,
            'home_visit_date' => $data['home_visit_date'] ?? null,
            'pedagogical_support_details' => filled($data['pedagogical_support_details'] ?? null) ? trim($data['pedagogical_support_details']) : null,
            'status' => $data['status'],
        ]);

        $studentName = $intervention->student?->full_name ?? ('#'.$intervention->student_id);

        return redirect()->route('qr.show', $intervention)
            ->with('status', "Expediente del Programa QR «Quiero Regresar» iniciado para «{$studentName}».");
    }

    public function edit(Request $request, QrIntervention $qrIntervention)
    {
        $qrIntervention->load(['student.course.specialty', 'student.course.shiftRef', 'initiator']);
        Gate::forUser($request->user())->authorize('update', $qrIntervention);

        return view('qr.edit', $this->formData($qrIntervention));
    }

    public function update(QrInterventionRequest $request, QrIntervention $qrIntervention): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('update', $qrIntervention);

        $data = $request->validated();

        $qrIntervention->update([
            'student_id' => (int) $data['student_id'],
            'detection_date' => $data['detection_date'],
            'consecutive_absences_flag' => (int) $data['consecutive_absences_flag'],
            'territorial_team_contact' => filled($data['territorial_team_contact'] ?? null) ? trim($data['territorial_team_contact']) : null,
            'home_visit_date' => $data['home_visit_date'] ?? null,
            'pedagogical_support_details' => filled($data['pedagogical_support_details'] ?? null) ? trim($data['pedagogical_support_details']) : null,
            'status' => $data['status'],
        ]);

        $studentName = $qrIntervention->student?->full_name ?? ('#'.$qrIntervention->student_id);

        return redirect()->route('qr.show', $qrIntervention)
            ->with('status', "Seguimiento del Programa QR de «{$studentName}» actualizado correctamente.");
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(QrIntervention $intervention): array
    {
        $students = Student::with(['course.specialty', 'course.shiftRef'])
            ->where(function ($query) use ($intervention): void {
                $query->where('active', true);
                if ($intervention->student_id) {
                    $query->orWhere('id', $intervention->student_id);
                }
            })
            ->alphabetical()
            ->get();

        $courses = Course::with(['specialty', 'shiftRef'])->active()->ordered()->get();

        return [
            'intervention' => $intervention,
            'students' => $students,
            'courses' => $courses,
            'statuses' => QrInterventionStatus::cases(),
        ];
    }

    private function denyCourse(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => CheckCourseAssignment::DENIED_MESSAGE], 403);
        }

        return response()->view('errors.access-denied', ['message' => CheckCourseAssignment::DENIED_MESSAGE], 403);
    }
}
