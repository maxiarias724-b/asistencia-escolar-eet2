<?php

namespace App\Http\Controllers;

use App\Http\Middleware\CheckCourseAssignment;
use App\Http\Requests\DisciplinaryRecordRequest;
use App\Models\Course;
use App\Models\DisciplinaryRecord;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Gestión de Amonestaciones (Disciplina) - Actualización 1.1 EET N.º 2.
 * - Director: Ver, Crear, Editar y Baja lógica en toda la institución.
 * - Preceptor (Operador) y Docente: Solo lectura de estudiantes de sus cursos asignados (`course_user`).
 */
class DisciplinaryRecordController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        Gate::forUser($user)->authorize('viewAny', DisciplinaryRecord::class);

        $allowedCourseIds = $user->isDirector() ? null : $user->assignedCourseIds();

        if ($allowedCourseIds !== null && $request->filled('course_id')) {
            $requestedCourseId = (int) $request->input('course_id');
            if (! in_array($requestedCourseId, $allowedCourseIds, true)) {
                return $this->denyCourse($request);
            }
        }

        $q = $request->string('q')->trim()->toString();
        $courseId = $request->integer('course_id') ?: null;
        $from = $request->string('from')->trim()->toString();
        $to = $request->string('to')->trim()->toString();
        $activeFilter = $request->input('active', '');

        $baseVisibleQuery = DisciplinaryRecord::query()->visibleTo($user);

        $records = (clone $baseVisibleQuery)
            ->with(['student.course.specialty', 'student.course.shiftRef', 'recorder'])
            ->when($courseId, function ($query) use ($courseId): void {
                $query->whereHas('student', fn ($sq) => $sq->where('course_id', $courseId));
            })
            ->when($from !== '', fn ($query) => $query->whereDate('date', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('date', '<=', $to))
            ->when($activeFilter === '1' || $activeFilter === '0', fn ($query) => $query->where('active', $activeFilter === '1'))
            ->when($q !== '', function ($query) use ($q): void {
                $digits = preg_replace('/\D+/', '', $q);
                $query->where(function ($sub) use ($q, $digits): void {
                    $sub->where('reason', 'like', "%{$q}%")
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
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // Acumulado de amonestaciones vigentes por estudiante para mostrar en la tabla y en alertas.
        $studentTotals = (clone $baseVisibleQuery)
            ->active()
            ->selectRaw('student_id, SUM(amount) as total_amount')
            ->groupBy('student_id')
            ->pluck('total_amount', 'student_id')
            ->map(fn ($v) => (int) $v);

        $summary = [
            'active_records' => (clone $baseVisibleQuery)->active()->count(),
            'active_points' => (int) (clone $baseVisibleQuery)->active()->sum('amount'),
            'students_at_risk' => $studentTotals->filter(fn (int $total) => $total >= 15)->count(),
        ];

        $coursesQuery = Course::with(['specialty', 'shiftRef'])->active()->ordered();
        if ($allowedCourseIds !== null) {
            $coursesQuery->whereIn('id', $allowedCourseIds);
        }

        return view('disciplinary.index', [
            'records' => $records,
            'studentTotals' => $studentTotals,
            'summary' => $summary,
            'courses' => $coursesQuery->get(),
            'filters' => [
                'q' => $q,
                'course_id' => $courseId,
                'from' => $from,
                'to' => $to,
                'active' => $activeFilter,
            ],
        ]);
    }

    public function create(Request $request)
    {
        Gate::forUser($request->user())->authorize('create', DisciplinaryRecord::class);

        $record = new DisciplinaryRecord([
            'student_id' => $request->integer('student_id') ?: null,
            'date' => now()->toDateString(),
            'amount' => 1,
            'active' => true,
        ]);

        return view('disciplinary.create', $this->formData($record));
    }

    public function store(DisciplinaryRecordRequest $request): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('create', DisciplinaryRecord::class);

        $data = $request->validated();

        $record = DisciplinaryRecord::create([
            'student_id' => (int) $data['student_id'],
            'recorded_by' => (int) $request->user()->id,
            'date' => $data['date'],
            'amount' => (int) $data['amount'],
            'reason' => trim($data['reason']),
            'active' => true,
        ]);

        $studentName = $record->student?->full_name ?? ('#'.$record->student_id);

        return redirect()->route('disciplinary.index')
            ->with('status', "Amonestación registrada para «{$studentName}» ({$record->amount} amon.).");
    }

    public function edit(Request $request, DisciplinaryRecord $disciplinaryRecord)
    {
        Gate::forUser($request->user())->authorize('update', $disciplinaryRecord);

        return view('disciplinary.edit', $this->formData($disciplinaryRecord));
    }

    public function update(DisciplinaryRecordRequest $request, DisciplinaryRecord $disciplinaryRecord): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('update', $disciplinaryRecord);

        $data = $request->validated();

        $disciplinaryRecord->update([
            'student_id' => (int) $data['student_id'],
            'date' => $data['date'],
            'amount' => (int) $data['amount'],
            'reason' => trim($data['reason']),
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : $disciplinaryRecord->active,
        ]);

        $studentName = $disciplinaryRecord->student?->full_name ?? ('#'.$disciplinaryRecord->student_id);

        return redirect()->route('disciplinary.index')
            ->with('status', "Amonestación de «{$studentName}» actualizada correctamente.");
    }

    public function destroy(Request $request, DisciplinaryRecord $disciplinaryRecord): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('delete', $disciplinaryRecord);

        $disciplinaryRecord->update(['active' => false]);
        $studentName = $disciplinaryRecord->student?->full_name ?? ('#'.$disciplinaryRecord->student_id);

        return redirect()->route('disciplinary.index')
            ->with('status', "Amonestación de «{$studentName}» dada de baja lógica. Se conserva en el historial para auditoría.");
    }

    public function toggleActive(Request $request, DisciplinaryRecord $disciplinaryRecord): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('delete', $disciplinaryRecord);

        $activating = ! $disciplinaryRecord->active;
        $disciplinaryRecord->update(['active' => $activating]);

        $studentName = $disciplinaryRecord->student?->full_name ?? ('#'.$disciplinaryRecord->student_id);
        $msg = $activating
            ? "Amonestación de «{$studentName}» reactivada."
            : "Amonestación de «{$studentName}» dada de baja lógica.";

        return back()->with('status', $msg);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(DisciplinaryRecord $record): array
    {
        $students = Student::with(['course.specialty', 'course.shiftRef'])
            ->where(function ($query) use ($record): void {
                $query->where('active', true);
                if ($record->student_id) {
                    $query->orWhere('id', $record->student_id);
                }
            })
            ->alphabetical()
            ->get();

        $courses = Course::with(['specialty', 'shiftRef'])->active()->ordered()->get();

        return [
            'record' => $record,
            'students' => $students,
            'courses' => $courses,
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
