<?php

namespace App\Http\Controllers\Director;

use App\Casts\DynamicValue;
use App\Enums\ActivityType;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Director\UserRequest;
use App\Models\Course;
use App\Models\CourseAssignment;
use App\Models\Scope;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** CRUD de usuarios no-directores (Operadores/Preceptores y Docentes) y delegación de cursos/ámbitos. */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $roleFilter = $request->input('role_filter');

        $users = User::query()
            ->whereIn('role', [Role::Operador->value, Role::Docente->value])
            ->when(in_array($roleFilter, [Role::Operador->value, Role::Docente->value], true), fn ($q) => $q->where('role', $roleFilter))
            ->withCount(['assignments', 'attendances'])
            ->with(['assignments.course', 'assignments.scopeRef'])
            ->when($request->string('q')->trim()->toString(), function ($q, $term) {
                $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")
                    ->orWhere('username', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%"));
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $scopeLabels = Scope::pluck('name', 'slug');

        return view('director.users.index', [
            'users' => $users,
            'q' => $request->input('q'),
            'roleFilter' => $roleFilter,
            'scopeLabels' => $scopeLabels,
        ]);
    }

    public function create()
    {
        return view('director.users.form', $this->formData(new User(['active' => true, 'role' => Role::Operador])));
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = DB::transaction(function () use ($request) {
            $role = Role::from($request->validated('account_role'));

            $user = User::create([
                'name' => $request->validated('name'),
                'first_name' => $request->validated('first_name'),
                'last_name' => $request->validated('last_name'),
                'username' => $request->validated('username'),
                'email' => $request->validated('email'),
                'password' => $request->validated('password'),
                'role' => $role,
                'active' => $request->boolean('active', true),
            ]);

            $this->syncAssignments($user, $request->assignmentPairs());

            return $user;
        });

        return redirect()->route('director.users.index')
            ->with('status', "Usuario «{$user->name}» creado.")
            ->with('credentials', ['username' => $user->username, 'password' => $request->validated('password'), 'name' => $user->name]);
    }

    public function edit(User $user)
    {
        $this->ensureManageable($user);

        return view('director.users.form', $this->formData($user));
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->ensureManageable($user);

        DB::transaction(function () use ($request, $user) {
            $data = $request->safe()->only(['name', 'first_name', 'last_name', 'username', 'email']);
            $data['role'] = Role::from($request->validated('account_role'));
            $data['active'] = $request->boolean('active');

            if ($request->filled('password')) {
                $data['password'] = $request->validated('password');
            }

            $user->update($data);
            $this->syncAssignments($user, $request->assignmentPairs());
        });

        $redirect = redirect()->route('director.users.index')->with('status', "Usuario «{$user->name}» actualizado.");

        return $request->filled('password')
            ? $redirect->with('credentials', ['username' => $user->username, 'password' => $request->validated('password'), 'name' => $user->name])
            : $redirect;
    }

    public function toggleActive(User $user): RedirectResponse
    {
        $this->ensureManageable($user);
        $user->update(['active' => ! $user->active]);

        return back()->with('status', $user->active ? "«{$user->name}» fue activado." : "«{$user->name}» fue desactivado lógicamente y ya no puede ingresar.");
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->ensureManageable($user);

        if ($user->attendances()->exists() || $user->teacherAttendances()->exists() || $user->justifications()->exists()) {
            return back()->withErrors([
                'delete' => "«{$user->name}» tiene registros de asistencia asociados; por preservación histórica no se puede eliminar. Desactivá la cuenta en su lugar.",
            ]);
        }

        $user->delete();

        return back()->with('status', 'Usuario eliminado.');
    }

    private function ensureManageable(User $user): void
    {
        abort_if($user->role === Role::Director, 404);
    }

    private function formData(User $user): array
    {
        $selected = $user->exists
            ? $user->assignments()->get()->groupBy(fn ($a) => $a->activity_type->value)
                ->map(fn ($g) => $g->pluck('course_id')->all())->all()
            : [];

        $scopes = Scope::active()->ordered()->get();
        $activities = $scopes->isNotEmpty()
            ? $scopes->map(fn (Scope $s) => new DynamicValue($s->slug, $s->name))->all()
            : ActivityType::cases();

        return [
            'user' => $user,
            'courses' => Course::with(['specialty', 'shiftRef'])->active()->ordered()->get(),
            'activities' => $activities,
            'selected' => old('assignments', $selected),
        ];
    }

    /** @param  array<int, array{0:int,1:string}>  $pairs */
    private function syncAssignments(User $user, array $pairs): void
    {
        $wanted = collect($pairs)->mapWithKeys(fn ($p) => ["{$p[0]}|{$p[1]}" => $p]);

        $user->assignments()->get()->each(function (CourseAssignment $a) use ($wanted) {
            if (! $wanted->has("{$a->course_id}|{$a->activity_type->value}")) {
                $a->delete();
            }
        });

        foreach ($wanted as [$courseId, $activitySlug]) {
            $course = Course::find($courseId);
            $user->assignCourse($courseId, $activitySlug, $course?->shift_id);
        }
    }
}
