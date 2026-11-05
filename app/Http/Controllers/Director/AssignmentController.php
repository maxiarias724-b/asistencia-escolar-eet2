<?php

namespace App\Http\Controllers\Director;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseAssignment;
use App\Models\Scope;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Gestión dedicada de Asignaciones de Responsabilidades (`Preceptores`):
 * vincula `Operador/Preceptor + Ámbito + Curso/División + Turno`.
 */
class AssignmentController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'scope_id' => ['nullable', 'integer', 'exists:scopes,id'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
        ]);

        $assignments = CourseAssignment::query()
            ->with(['user', 'course.specialty', 'course.shiftRef', 'scopeRef', 'shift'])
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['scope_id'] ?? null, fn ($q, $v) => $q->where('scope_id', $v))
            ->when($filters['course_id'] ?? null, fn ($q, $v) => $q->where('course_id', $v))
            ->orderBy('user_id')
            ->orderBy('scope_id')
            ->orderBy('course_id')
            ->paginate(25)
            ->withQueryString();

        return view('director.assignments.index', [
            'assignments' => $assignments,
            'operators' => User::where('role', Role::Operador->value)->where('active', true)->orderBy('name')->get(),
            'scopes' => Scope::active()->ordered()->get(),
            'courses' => Course::with(['specialty', 'shiftRef'])->active()->ordered()->get(),
            'shifts' => Shift::active()->ordered()->get(),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => [
                'required', 'integer',
                \Illuminate\Validation\Rule::exists('users', 'id')->where('role', Role::Operador->value),
            ],
            'scope_id' => ['required', 'integer', 'exists:scopes,id'],
            'course_ids' => ['required', 'array', 'min:1'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
        ], [
            'user_id.required' => 'Seleccioná un preceptor u operador.',
            'user_id.exists' => 'El usuario seleccionado no es un operador válido.',
            'scope_id.required' => 'Seleccioná el ámbito de responsabilidad.',
            'course_ids.required' => 'Seleccioná al menos un curso.',
        ]);

        $user = User::findOrFail($data['user_id']);
        $scope = Scope::findOrFail($data['scope_id']);
        $explicitShiftId = $data['shift_id'] ?? null;

        $count = 0;
        foreach (array_unique($data['course_ids']) as $courseId) {
            $course = Course::findOrFail($courseId);
            $user->assignCourse($course, $scope, $explicitShiftId ?: $course->shift_id);
            $count++;
        }

        return redirect()->route('director.assignments.index')
            ->with('status', "Se asignaron {$count} curso(s) en el ámbito «{$scope->name}» a «{$user->name}».");
    }

    public function destroy(CourseAssignment $assignment): RedirectResponse
    {
        $label = ($assignment->user?->name ?? 'Operador').' — '.($assignment->course?->name ?? 'Curso');
        $assignment->delete();

        return back()->with('status', "Asignación revocada ({$label}).");
    }
}
