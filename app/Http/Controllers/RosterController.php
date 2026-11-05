<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Enums\StudentStatus;
use App\Models\Course;
use App\Models\Student;
use App\Services\RosterImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Nómina de alumnos de un curso (estructura SINIDE): carga manual e importación CSV/Excel.
 * Va detrás de `course.assignment`: solo se opera sobre cursos asignados por el Director.
 */
class RosterController extends Controller
{
    public function __construct(private readonly RosterImporter $importer)
    {
    }

    /** Redirige a la nómina SINIDE del primer curso habilitado para el usuario. */
    public function landing(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->isDirector()) {
            $course = Course::active()->ordered()->first();
            $scopeSlug = \App\Models\Scope::active()->ordered()->value('slug') ?? ActivityType::Clase->value;
            if ($course) {
                return redirect()->route('roster.index', ['activity' => $scopeSlug, 'course' => $course->id]);
            }

            return redirect()->route('director.dashboard');
        }

        $first = $user->assignments()->orderBy('id')->first();
        if ($first) {
            return redirect()->route('roster.index', ['activity' => $first->activity_type->value, 'course' => $first->course_id]);
        }

        return redirect()->route('operator.home');
    }

    public function index(Request $request, string $activity, int $course)
    {
        [$activity, $course] = $this->resolve($activity, $course);

        $students = $course->students()
            ->withCount('attendances')
            ->alphabetical()
            ->get();

        return view('roster.index', [
            'course' => $course,
            'activity' => $activity,
            'students' => $students,
            'statuses' => StudentStatus::cases(),
            'counts' => $students->countBy(fn (Student $s) => $s->status->value),
        ]);
    }

    public function store(Request $request, string $activity, int $course): RedirectResponse
    {
        [$activity, $course] = $this->resolve($activity, $course);
        $data = $this->validated($request, $course);

        Student::create($data + ['course_id' => $course->id]);

        return back()->with('status', "Alumno «{$data['last_name']}, {$data['first_name']}» agregado a {$course->name}.");
    }

    public function update(Request $request, string $activity, int $course, int $student): RedirectResponse
    {
        [$activity, $course] = $this->resolve($activity, $course);
        $student = Student::where('course_id', $course->id)->findOrFail($student);

        $student->update($this->validated($request, $course, $student));

        return back()->with('status', "Alumno «{$student->last_name}, {$student->first_name}» actualizado.");
    }

    public function destroy(Request $request, string $activity, int $course, int $student): RedirectResponse
    {
        [$activity, $course] = $this->resolve($activity, $course);
        $student = Student::where('course_id', $course->id)->findOrFail($student);

        // Se conserva el historial: con asistencias cargadas solo corresponde cambiar el estado de matrícula.
        if ($student->attendances()->exists()) {
            return back()->withErrors([
                'roster' => "«{$student->last_name}, {$student->first_name}» tiene asistencias registradas y no puede eliminarse. Cambiá su estado a Baja o Pase.",
            ]);
        }

        $student->delete();

        return back()->with('status', 'Alumno eliminado de la nómina.');
    }

    /* ------------------------------ Importación ------------------------------ */

    public function import(Request $request, string $activity, int $course)
    {
        [$activity, $course] = $this->resolve($activity, $course);

        $request->validate([
            'file' => ['required', 'file', 'extensions:csv,txt,xlsx', 'max:5120'],
        ], [
            'file.required' => 'Elegí un archivo CSV o Excel (.xlsx).',
            'file.extensions' => 'El archivo debe ser CSV o Excel (.xlsx). Si tenés un PDF de SINIDE, exportalo a Excel/CSV primero.',
            'file.max' => 'El archivo no puede superar los 5 MB.',
        ]);

        try {
            $records = $this->importer->parse($request->file('file'));
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        $rows = $this->importer->analyze($course, $records);
        $token = (string) Str::uuid();

        $request->session()->put("roster_import.{$course->id}", [
            'token' => $token,
            'records' => $records,
            'filename' => $request->file('file')->getClientOriginalName(),
        ]);

        return view('roster.import-preview', [
            'course' => $course,
            'activity' => $activity,
            'rows' => $rows,
            'token' => $token,
            'filename' => $request->file('file')->getClientOriginalName(),
            'summary' => collect($rows)->countBy('action'),
        ]);
    }

    public function confirmImport(Request $request, string $activity, int $course): RedirectResponse
    {
        [$activity, $course] = $this->resolve($activity, $course);

        $pending = $request->session()->get("roster_import.{$course->id}");

        if (! $pending || ! hash_equals($pending['token'], (string) $request->input('token'))) {
            return redirect()->route('roster.index', ['activity' => $activity->value, 'course' => $course->id])
                ->withErrors(['roster' => 'La vista previa de importación venció. Volvé a subir el archivo.']);
        }

        // Se re-analiza al confirmar para trabajar con el estado actual de la base.
        $result = $this->importer->apply($course, $this->importer->analyze($course, $pending['records']));
        $request->session()->forget("roster_import.{$course->id}");

        return redirect()->route('roster.index', ['activity' => $activity->value, 'course' => $course->id])
            ->with('status', "Importación finalizada: {$result['created']} nuevos, {$result['updated']} actualizados, {$result['unchanged']} sin cambios, {$result['errors']} omitidos por errores.");
    }

    public function template()
    {
        // Punto y coma + BOM para que Excel (configuración regional es-AR) lo abra con tildes correctas.
        $csv = "\xEF\xBB\xBF"
            ."DNI;CUIL;Apellido;Nombre;Fecha de nacimiento;Estado de matrícula;Código SINIDE\r\n"
            ."45123456;20-45123456-3;Pérez;Juan Carlos;15/03/2010;Activo;SIN-0001\r\n"
            .";;Gómez;María Luján;02/11/2010;Activo;\r\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla-nomina-sinide.csv"',
        ]);
    }

    /* ------------------------------------------------------------------ */

    /** @return array{0: ActivityType|\App\Casts\DynamicValue, 1: Course} */
    private function resolve(string $activity, int $course): array
    {
        return [\App\Models\Scope::resolveSlug($activity) ?? abort(404), Course::findOrFail($course)];
    }

    private function validated(Request $request, Course $course, ?Student $student = null): array
    {
        // Se normalizan DNI/CUIL antes de validar (el usuario puede tipear puntos o guiones).
        $request->merge([
            'dni' => ($d = preg_replace('/\D+/', '', (string) $request->input('dni'))) !== '' ? $d : null,
            'cuil' => ($c = preg_replace('/\D+/', '', (string) $request->input('cuil'))) !== '' ? $c : null,
        ]);

        $data = $request->validate([
            'last_name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'dni' => ['nullable', 'digits_between:7,9', Rule::unique('students', 'dni')->ignore($student)],
            'cuil' => ['nullable', 'digits:11'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'status' => ['required', Rule::enum(StudentStatus::class)],
            'sinide_code' => ['nullable', 'string', 'max:50'],
        ], [
            'last_name.required' => 'El apellido es obligatorio.',
            'first_name.required' => 'El nombre es obligatorio.',
            'dni.digits_between' => 'El DNI debe tener entre 7 y 9 dígitos.',
            'dni.unique' => 'Ya existe un alumno con ese DNI.',
            'cuil.digits' => 'El CUIL debe tener 11 dígitos.',
            'birth_date.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
        ]);

        // Sin DNI, se evita cargar dos veces al mismo alumno dentro del curso.
        if (empty($data['dni'])) {
            $duplicate = Student::where('course_id', $course->id)
                ->when($student, fn ($q) => $q->where('id', '!=', $student->id))
                ->whereRaw('LOWER(last_name) = ?', [mb_strtolower($data['last_name'])])
                ->whereRaw('LOWER(first_name) = ?', [mb_strtolower($data['first_name'])])
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'last_name' => 'Ya existe un alumno con ese apellido y nombre en este curso. Si son homónimos, cargá el DNI para distinguirlos.',
                ]);
            }
        }

        if (! empty($data['cuil'])) {
            // Se guarda con el formato XX-XXXXXXXX-X (igual que el importador y el seeder).
            $data['cuil'] = substr($data['cuil'], 0, 2).'-'.substr($data['cuil'], 2, 8).'-'.substr($data['cuil'], 10, 1);

            $taken = Student::where('cuil', $data['cuil'])
                ->when($student, fn ($q) => $q->where('id', '!=', $student->id))
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages(['cuil' => 'Ya existe un alumno con ese CUIL.']);
            }
        }

        return $data;
    }
}
