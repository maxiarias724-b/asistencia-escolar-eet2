<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Course;
use App\Models\Student;
use App\Models\TeacherClassObservation;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherObservationController extends Controller
{
    /**
     * Nómina de cursos asignados al Docente con estado de asistencia (solo lectura)
     * y carga de observaciones áulicas por materia/fecha mediante modal interactivo.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user && $user->active && ($user->isDocente() || $user->isDirector()), 403);

        $filters = $request->validate([
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $selectedDate = isset($filters['date']) && filled($filters['date'])
            ? Carbon::createFromFormat('Y-m-d', $filters['date'])->startOfDay()
            : now()->startOfDay();

        if ($selectedDate->isFuture()) {
            $selectedDate = now()->startOfDay();
        }

        $dateYmd = $selectedDate->toDateString();

        $coursesQuery = Course::with(['specialty', 'shiftRef'])->active()->ordered();
        if (! $user->isDirector()) {
            $coursesQuery->whereIn('id', $user->assignedCourseIds());
        }
        $courses = $coursesQuery->get();

        $selectedCourseId = isset($filters['course_id']) ? (int) $filters['course_id'] : null;
        if ($selectedCourseId !== null && ! $courses->contains('id', $selectedCourseId)) {
            abort(403, 'No tenés asignado el curso solicitado.');
        }

        if ($selectedCourseId === null && $courses->isNotEmpty()) {
            $selectedCourseId = (int) $courses->first()->id;
        }

        $selectedCourse = $selectedCourseId
            ? $courses->firstWhere('id', $selectedCourseId)
            : null;

        $search = trim((string) ($filters['q'] ?? ''));

        $students = collect();
        if ($selectedCourse) {
            $students = Student::query()
                ->where('course_id', $selectedCourse->id)
                ->active()
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($sub) use ($search): void {
                        $sub->where('last_name', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('dni', 'like', "%{$search}%")
                            ->orWhere('file_number', 'like', "%{$search}%");
                    });
                })
                ->with([
                    'attendances' => function ($q) use ($dateYmd): void {
                        $q->where('date', $dateYmd)->with(['scopeRef', 'user:id,name,role']);
                    },
                    'teacherClassObservations' => function ($q) use ($dateYmd): void {
                        $q->where('date', $dateYmd)
                            ->with(['teacher:id,name', 'activity:id,name'])
                            ->orderByDesc('id');
                    },
                ])
                ->alphabetical()
                ->get();
        }

        $activities = Activity::with('scopeRef')->active()->ordered()->get();

        return view('docentes.nomina', [
            'courses' => $courses,
            'selectedCourse' => $selectedCourse,
            'selectedDate' => $selectedDate,
            'dateYmd' => $dateYmd,
            'search' => $search,
            'students' => $students,
            'activities' => $activities,
        ]);
    }

    /**
     * Registra una nueva observación docente para un alumno en una fecha y materia determinada.
     * Soporta petición AJAX/Fetch (JSON) y envío tradicional por formulario.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->active && ($user->isDocente() || $user->isDirector()), 403);

        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'activity_id' => ['nullable', 'integer', 'exists:activities,id'],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'observation_text' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'student_id.required' => 'Seleccioná un estudiante válido.',
            'date.required' => 'Indicá la fecha de la observación.',
            'date.before_or_equal' => 'No se pueden cargar observaciones en fechas futuras.',
            'observation_text.required' => 'Escribí el detalle de la observación docente.',
            'observation_text.min' => 'La observación debe tener al menos 3 caracteres.',
            'observation_text.max' => 'La observación no puede superar los 1000 caracteres.',
        ]);

        $student = Student::findOrFail((int) $validated['student_id']);

        if (! $user->hasAssignedCourse($student->course_id)) {
            abort(403, 'No tenés permiso para registrar observaciones sobre estudiantes de un curso no asignado.');
        }

        $observation = TeacherClassObservation::create([
            'student_id' => $student->id,
            'teacher_id' => $user->id,
            'activity_id' => $validated['activity_id'] ?? null,
            'date' => $validated['date'],
            'observation_text' => trim($validated['observation_text']),
        ]);

        $observation->load(['teacher:id,name', 'activity:id,name']);

        $payload = [
            'id' => $observation->id,
            'student_id' => $observation->student_id,
            'teacher_id' => $observation->teacher_id,
            'teacher_name' => $observation->teacher?->name ?? $user->name,
            'activity_id' => $observation->activity_id,
            'activity_name' => $observation->activity?->name ?? 'General / Sin materia específica',
            'date' => $observation->date?->toDateString(),
            'date_formatted' => $observation->date?->format('d/m/Y'),
            'observation_text' => $observation->observation_text,
            'created_at_human' => $observation->created_at?->format('H:i') ?? now()->format('H:i'),
        ];

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'message' => 'Observación docente registrada correctamente.',
                'observation' => $payload,
            ], 201);
        }

        return back()->with('status', "Observación registrada para {$student->full_name}.");
    }

    /**
     * Permite al docente autor (o al Director) eliminar una observación cargada por error.
     */
    public function destroy(Request $request, TeacherClassObservation $teacherObservation): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->active, 403);

        $canDelete = $user->isDirector() || ((int) $teacherObservation->teacher_id === (int) $user->id);
        abort_unless($canDelete, 403, 'Solo el docente que redactó la observación o la Dirección pueden eliminarla.');

        $teacherObservation->delete();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'message' => 'Observación eliminada.',
            ]);
        }

        return back()->with('status', 'Observación docente eliminada.');
    }
}
