<?php

namespace App\Http\Controllers\People;

use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Middleware\CheckCourseAssignment;
use App\Http\Requests\People\StudentRequest;
use App\Models\Course;
use App\Models\Shift;
use App\Models\Specialty;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Padrón institucional de Estudiantes (SINIDE) con buscador multicriterio y baja lógica estricta.
 * Prohibido el borrado físico (`DELETE`) para preservar la integridad histórica de asistencias.
 */
class StudentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $allowedCourseIds = $this->allowedCourseIds($user);

        if ($user->isOperador() && $request->filled('course_id')) {
            $requestedCourseId = (int) $request->input('course_id');
            if (! in_array($requestedCourseId, $allowedCourseIds, true)) {
                return $this->denyCourse($request);
            }
        }

        $q = $request->string('q')->trim()->toString();
        $courseId = $request->integer('course_id') ?: null;
        $specialtyId = $request->integer('specialty_id') ?: null;
        $shiftId = $request->integer('shift_id') ?: null;
        $activeFilter = $request->input('active', '');
        $statusFilter = $request->string('status')->trim()->toString();

        $students = Student::query()
            ->with(['course.specialty', 'course.shiftRef'])
            ->withCount('attendances')
            ->when($allowedCourseIds !== null, fn ($query) => $query->whereIn('course_id', $allowedCourseIds))
            ->when($courseId, fn ($query) => $query->where('course_id', $courseId))
            ->when($specialtyId, fn ($query) => $query->whereHas('course', fn ($cq) => $cq->where('specialty_id', $specialtyId)))
            ->when($shiftId, fn ($query) => $query->whereHas('course', fn ($cq) => $cq->where('shift_id', $shiftId)))
            ->when($activeFilter === '1' || $activeFilter === '0', fn ($query) => $query->where('active', $activeFilter === '1'))
            ->when($statusFilter !== '', fn ($query) => $query->where('status', $statusFilter))
            ->when($q !== '', function ($query) use ($q) {
                $digits = preg_replace('/\D+/', '', $q);
                $tokens = preg_split('/[\s,]+/', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];

                $query->where(function ($sub) use ($q, $digits, $tokens) {
                    $sub->where('last_name', 'like', "%{$q}%")
                        ->orWhere('first_name', 'like', "%{$q}%")
                        ->orWhere('dni', 'like', "%{$q}%")
                        ->orWhere('cuil', 'like', "%{$q}%")
                        ->orWhere('file_number', 'like', "%{$q}%")
                        ->orWhere('sinide_code', 'like', "%{$q}%");

                    if (count($tokens) >= 2) {
                        $sub->orWhere(function ($multi) use ($tokens) {
                            foreach ($tokens as $tok) {
                                $multi->where(fn ($w) => $w->where('last_name', 'like', "%{$tok}%")->orWhere('first_name', 'like', "%{$tok}%"));
                            }
                        });
                    }

                    if ($digits !== '' && $digits !== $q) {
                        $sub->orWhere('dni', 'like', "%{$digits}%")
                            ->orWhereRaw("REPLACE(cuil, '-', '') LIKE ?", ["%{$digits}%"]);
                    }
                });
            })
            ->alphabetical()
            ->paginate(25)
            ->withQueryString();

        $coursesQuery = Course::with(['specialty', 'shiftRef'])->active()->ordered();
        if ($allowedCourseIds !== null) {
            $coursesQuery->whereIn('id', $allowedCourseIds);
        }

        return view('people.students.index', [
            'students' => $students,
            'courses' => $coursesQuery->get(),
            'specialties' => Specialty::active()->ordered()->get(),
            'shifts' => Shift::active()->ordered()->get(),
            'statuses' => StudentStatus::cases(),
            'filters' => [
                'q' => $q,
                'course_id' => $courseId,
                'specialty_id' => $specialtyId,
                'shift_id' => $shiftId,
                'active' => $activeFilter,
                'status' => $statusFilter,
            ],
        ]);
    }

    public function create(Request $request)
    {
        return view('people.students.form', $this->formData(
            $request->user(),
            new Student([
                'active' => true,
                'status' => StudentStatus::Activo,
                'course_id' => $request->integer('course_id') ?: null,
            ])
        ));
    }

    public function store(StudentRequest $request)
    {
        if (! $this->canAccessCourse($request->user(), (int) $request->validated('course_id'))) {
            return $this->denyCourse($request);
        }

        $student = Student::create($request->safe()->except(['cuil_digits']));

        return redirect()->route('people.students.index', ['course_id' => $student->course_id])
            ->with('status', "Estudiante «{$student->full_name}» (DNI {$student->dni}) registrado correctamente.");
    }

    public function edit(Request $request, Student $student)
    {
        if (! $this->canAccessCourse($request->user(), (int) $student->course_id)) {
            return $this->denyCourse($request);
        }

        return view('people.students.form', $this->formData($request->user(), $student));
    }

    public function update(StudentRequest $request, Student $student)
    {
        if (
            ! $this->canAccessCourse($request->user(), (int) $student->course_id)
            || ! $this->canAccessCourse($request->user(), (int) $request->validated('course_id'))
        ) {
            return $this->denyCourse($request);
        }

        $student->update($request->safe()->except(['cuil_digits']));

        return redirect()->route('people.students.index', ['course_id' => $student->course_id])
            ->with('status', "Datos de «{$student->full_name}» actualizados.");
    }

    public function toggleActive(Request $request, Student $student)
    {
        if (! $this->canAccessCourse($request->user(), (int) $student->course_id)) {
            return $this->denyCourse($request);
        }

        $activating = ! $student->active;
        $student->update([
            'status' => $activating ? StudentStatus::Activo : StudentStatus::Baja,
            'active' => $activating,
        ]);

        $msg = $activating
            ? "Estudiante «{$student->full_name}» reactivado (estado ACTIVO)."
            : "Estudiante «{$student->full_name}» dado de baja lógica (INACTIVO). Su historial de asistencias permanece intacto.";

        return back()->with('status', $msg);
    }

    /** @return array<int, int>|null `null` si es Director (sin restricción de curso). */
    private function allowedCourseIds(User $user): ?array
    {
        if ($user->isDirector()) {
            return null;
        }

        return $user->assignments()->pluck('course_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    private function canAccessCourse(User $user, int $courseId): bool
    {
        if ($user->isDirector()) {
            return true;
        }

        return $user->canManageAttendance($courseId);
    }

    private function denyCourse(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => CheckCourseAssignment::DENIED_MESSAGE], 403);
        }

        return response()->view('errors.access-denied', ['message' => CheckCourseAssignment::DENIED_MESSAGE], 403);
    }

    private function formData(User $user, Student $student): array
    {
        $allowed = $this->allowedCourseIds($user);
        $courses = Course::with(['specialty', 'shiftRef'])
            ->where(function ($q) use ($student) {
                $q->where('active', true);
                if ($student->course_id) {
                    $q->orWhere('id', $student->course_id);
                }
            })
            ->when($allowed !== null, fn ($q) => $q->whereIn('id', $allowed))
            ->ordered()
            ->get();

        return [
            'student' => $student,
            'courses' => $courses,
            'statuses' => StudentStatus::cases(),
        ];
    }
}
