<?php

namespace App\Http\Controllers\People;

use App\Http\Controllers\Controller;
use App\Http\Requests\People\TeacherRequest;
use App\Models\Teacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Padrón institucional de Docentes con buscador multicriterio y baja lógica estricta.
 * Prohibido el borrado físico (`DELETE`) para conservar el historial de asistencia docente.
 */
class TeacherController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->string('q')->trim()->toString();
        $activeFilter = $request->input('active', '');

        $teachers = Teacher::query()
            ->withCount('attendances')
            ->when($activeFilter === '1' || $activeFilter === '0', fn ($query) => $query->where('active', $activeFilter === '1'))
            ->when($q !== '', function ($query) use ($q) {
                $digits = preg_replace('/\D+/', '', $q);
                $tokens = preg_split('/[\s,]+/', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];

                $query->where(function ($sub) use ($q, $digits, $tokens) {
                    $sub->where('last_name', 'like', "%{$q}%")
                        ->orWhere('first_name', 'like', "%{$q}%")
                        ->orWhere('dni', 'like', "%{$q}%")
                        ->orWhere('cuil', 'like', "%{$q}%")
                        ->orWhere('file_number', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('specialty_area', 'like', "%{$q}%");

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

        return view('people.teachers.index', [
            'teachers' => $teachers,
            'filters' => [
                'q' => $q,
                'active' => $activeFilter,
            ],
        ]);
    }

    public function create()
    {
        return view('people.teachers.form', [
            'teacher' => new Teacher(['active' => true]),
        ]);
    }

    public function store(TeacherRequest $request): RedirectResponse
    {
        $teacher = Teacher::create($request->safe()->except(['cuil_digits']) + [
            'active' => $request->boolean('active', true),
        ]);

        return redirect()->route('people.teachers.index')
            ->with('status', "Docente «{$teacher->full_name}» (DNI {$teacher->dni}) registrado correctamente.");
    }

    public function edit(Teacher $teacher)
    {
        return view('people.teachers.form', [
            'teacher' => $teacher,
        ]);
    }

    public function update(TeacherRequest $request, Teacher $teacher): RedirectResponse
    {
        $teacher->update($request->safe()->except(['cuil_digits']) + [
            'active' => $request->boolean('active', true),
        ]);

        return redirect()->route('people.teachers.index')
            ->with('status', "Datos del docente «{$teacher->full_name}» actualizados.");
    }

    public function toggleActive(Teacher $teacher): RedirectResponse
    {
        $teacher->update(['active' => ! $teacher->active]);

        $msg = $teacher->active
            ? "Docente «{$teacher->full_name}» reactivado (ACTIVO)."
            : "Docente «{$teacher->full_name}» dado de baja lógica (INACTIVO). Su historial permanece resguardado.";

        return back()->with('status', $msg);
    }
}
