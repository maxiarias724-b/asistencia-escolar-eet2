<?php

namespace App\Http\Controllers;

use App\Casts\DynamicValue;
use App\Enums\ActivityType;
use App\Enums\AttendanceStatus;
use App\Enums\StudentStatus;
use App\Http\Requests\StoreAttendanceRequest;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\CourseAssignment;
use App\Models\Scope;
use App\Models\Student;
use App\Services\MonthlyRegister;
use App\Services\SchoolCalendar;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Registro mensual de asistencia (grilla estilo SINIDE).
 * Todas las rutas pasan por `course.assignment`, que bloquea cursos/actividades no asignados.
 */
class AttendanceController extends Controller
{
    public function __construct(
        private readonly MonthlyRegister $register,
        private readonly SchoolCalendar $calendar,
    ) {
    }

    /** Selector Ámbito -> Curso. Si llegan filtros por query, se resuelve el acceso vía middleware. */
    public function index(Request $request)
    {
        if ($request->filled('activity') && $request->filled('course_id')) {
            return redirect()->route('attendance.show', [
                'activity' => $request->string('activity')->toString(),
                'course' => $request->input('course_id'),
            ]);
        }

        if ($request->user()->isDirector()) {
            $firstCourse = Course::active()->ordered()->first();
            $firstScope = Scope::active()->ordered()->value('slug') ?? ActivityType::Clase->value;

            if ($firstCourse) {
                return redirect()->route('attendance.show', [
                    'activity' => $firstScope,
                    'course' => $firstCourse->id,
                ]);
            }

            return redirect()->route('director.dashboard');
        }

        return redirect()->route('operator.home');
    }

    /**
     * `$course` llega como ID crudo (sin route-model binding) a propósito:
     * el middleware decide primero si el usuario puede ver el curso, y recién después se consulta,
     * para no revelar qué cursos existen.
     */
    public function show(Request $request, string $activity, int $course)
    {
        [$activity, $course] = $this->resolve($activity, $course);

        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        // Mes y fecha tomados del reloj del servidor, salvo que se navegue a otro mes.
        $month = $request->filled('month')
            ? Carbon::createFromFormat('Y-m-d', $request->input('month').'-01')
            : now();

        return view('attendance.show', [
            'course' => $course,
            'activity' => $activity,
            'register' => $this->register->build($course, $activity, $month),
            'options' => $this->accessibleOptions($request),
            'config' => [
                'csrf' => csrf_token(),
                'urls' => [
                    'store' => route('attendance.store', [$activity->value, $course->id]),
                    'destroy' => route('attendance.destroy', [$activity->value, $course->id]),
                    'fillDay' => route('attendance.fill-day', [$activity->value, $course->id]),
                    'justify' => route('attendance.justify', [$activity->value, $course->id]),
                ],
            ],
            'today' => now(),
            'prevMonth' => $month->copy()->startOfMonth()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->startOfMonth()->addMonth()->format('Y-m'),
        ]);
    }

    /** Crea o modifica el estado/observación de un alumno en un día (también retroactivo). */
    public function store(StoreAttendanceRequest $request, string $activity, int $course): JsonResponse
    {
        [$activity, $course] = $this->resolve($activity, $course);
        $data = $request->validated();

        $date = Carbon::createFromFormat('Y-m-d', $data['date'])->startOfDay();
        $this->assertTakeable($date);
        $student = $this->activeStudent($course, (int) $data['student_id']);
        $status = AttendanceStatus::from($data['status']);

        $existing = Attendance::where([
            'student_id' => $student->id, 'date' => $date->toDateString(), 'activity_type' => $activity->value,
        ])->with('justification')->first();

        // La justificada solo se alcanza por el modal obligatorio (texto o certificado).
        if ($status === AttendanceStatus::Justificada && $existing?->status !== AttendanceStatus::Justificada) {
            throw ValidationException::withMessages([
                'status' => 'Para marcar una inasistencia como justificada usá el botón «Justificar» e ingresá un texto o adjuntá un certificado.',
            ]);
        }

        $dropsJustification = $existing?->status === AttendanceStatus::Justificada && $status !== AttendanceStatus::Justificada;
        if ($dropsJustification && ! $request->boolean('confirm_unjustify')) {
            return response()->json([
                'message' => 'Al cambiar el estado se eliminará la justificación (texto y certificado). ¿Confirmás?',
                'requires_confirmation' => true,
            ], 409);
        }

        DB::transaction(function () use ($existing, $dropsJustification, $student, $course, $activity, $date, $status, $data, $request) {
            if ($dropsJustification) {
                $existing->justification?->delete(); // también borra el archivo
            }

            Attendance::updateOrCreate(
                ['student_id' => $student->id, 'date' => $date->toDateString(), 'activity_type' => $activity->value],
                [
                    'course_id' => $course->id,
                    'user_id' => $request->user()->id,
                    'status' => $status->value,
                    'observation' => filled($data['observation'] ?? null) ? trim($data['observation']) : null,
                ]
            );
        });

        return $this->payload($course, $activity, $date, 'Registro guardado.');
    }

    /** Quita el registro de un alumno en un día (vuelve a quedar sin cargar). */
    public function destroy(Request $request, string $activity, int $course): JsonResponse
    {
        [$activity, $course] = $this->resolve($activity, $course);

        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $date = Carbon::createFromFormat('Y-m-d', $data['date'])->startOfDay();
        $student = Student::where('course_id', $course->id)->find($data['student_id']);
        abort_if(! $student, 404);

        $attendance = Attendance::where([
            'student_id' => $student->id, 'date' => $date->toDateString(), 'activity_type' => $activity->value, 'course_id' => $course->id,
        ])->first();

        DB::transaction(function () use ($attendance) {
            $attendance?->justification?->delete();
            $attendance?->delete();
        });

        return $this->payload($course, $activity, $date, 'Registro eliminado.');
    }

    /** Marca "Presente" a todos los alumnos activos que aún no tienen registro ese día. */
    public function fillDay(Request $request, string $activity, int $course): JsonResponse
    {
        [$activity, $course] = $this->resolve($activity, $course);

        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $date = Carbon::createFromFormat('Y-m-d', $data['date'])->startOfDay();
        $this->assertTakeable($date);

        DB::transaction(function () use ($course, $activity, $date, $request) {
            $already = Attendance::where('course_id', $course->id)
                ->where('activity_type', $activity->value)
                ->where('date', $date->toDateString())
                ->pluck('student_id');

            $course->students()->active()->whereNotIn('id', $already)->get()->each(
                fn (Student $s) => Attendance::create([
                    'student_id' => $s->id,
                    'course_id' => $course->id,
                    'user_id' => $request->user()->id,
                    'date' => $date->toDateString(),
                    'activity_type' => $activity->value,
                    'status' => AttendanceStatus::Presente->value,
                ])
            );
        });

        return $this->payload($course, $activity, $date, 'Se marcó Presente a los alumnos sin registro.');
    }

    /* ------------------------------------------------------------------ */

    /** @return array{0: ActivityType|DynamicValue, 1: Course} */
    private function resolve(string $activity, int $course): array
    {
        return [
            Scope::resolveSlug($activity) ?? abort(404),
            Course::findOrFail($course),
        ];
    }

    private function payload(Course $course, ActivityType|DynamicValue $activity, Carbon $date, string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'register' => $this->register->build($course, $activity, $date),
        ]);
    }

    /** No se toma asistencia a futuro ni en días sin actividad (fines de semana, feriados, recesos). */
    private function assertTakeable(Carbon $date): void
    {
        if ($date->gt(now()->startOfDay())) {
            throw ValidationException::withMessages(['date' => 'No se puede cargar asistencia en una fecha futura.']);
        }

        if (! $this->calendar->isWorkingDay($date)) {
            throw ValidationException::withMessages(['date' => 'Ese día no es hábil (fin de semana, feriado o día sin actividad).']);
        }
    }

    private function activeStudent(Course $course, int $studentId): Student
    {
        $student = Student::where('course_id', $course->id)->find($studentId);

        if (! $student) {
            throw ValidationException::withMessages(['student_id' => 'El alumno no pertenece a este curso.']);
        }

        if ($student->status !== StudentStatus::Activo) {
            throw ValidationException::withMessages(['student_id' => "El alumno está en estado «{$student->status->label()}» y no admite asistencia."]);
        }

        return $student;
    }

    /** Combinaciones ámbito + curso que el usuario puede abrir (para el selector). */
    private function accessibleOptions(Request $request): array
    {
        $user = $request->user();
        $scopes = Scope::active()->ordered()->get();
        $scopeList = $scopes->isNotEmpty()
            ? $scopes->map(fn (Scope $s) => new DynamicValue($s->slug, $s->name))
            : collect(ActivityType::cases());

        $pairs = $user->isDirector()
            ? Course::active()->ordered()->get()->flatMap(fn (Course $c) => $scopeList->map(fn ($a) => [$a, $c]))
            : $user->assignments()->with('course')->get()->map(fn (CourseAssignment $a) => [$a->activity_type, $a->course]);

        return $pairs->map(fn ($p) => [
            'activity' => $p[0]->value,
            'activity_label' => $p[0]->label(),
            'course_id' => $p[1]->id,
            'course_label' => $p[1]->full_name,
            'sort' => sprintf('%02d-%s', $p[1]->year, $p[1]->division),
        ])->sortBy('sort')->values()->all();
    }
}
