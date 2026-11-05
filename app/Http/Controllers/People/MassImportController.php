<?php

namespace App\Http\Controllers\People;

use App\Http\Controllers\Controller;
use App\Http\Middleware\CheckCourseAssignment;
use App\Models\Course;
use App\Services\MassPeopleImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Controlador del flujo guiado de Importación Masiva Excel/CSV en 9 Pasos (Estudiantes y Docentes).
 */
class MassImportController extends Controller
{
    public function __construct(private readonly MassPeopleImporter $importer)
    {
    }

    /** Pasos 1 y 2: Selección de entidad, descarga de plantilla oficial y subida de archivo. */
    public function index(Request $request)
    {
        $user = $request->user();
        $entity = $request->string('entity', 'students')->toString();

        if (! in_array($entity, ['students', 'teachers'], true)) {
            $entity = 'students';
        }
        if ($entity === 'teachers' && ! $user->isDirector()) {
            $entity = 'students';
        }

        $coursesQuery = Course::with(['specialty', 'shiftRef'])->active()->ordered();
        if (! $user->isDirector()) {
            $allowed = $user->assignments()->pluck('course_id')->unique()->all();
            $coursesQuery->whereIn('id', $allowed);
        }

        return view('people.import.index', [
            'entity' => $entity,
            'courses' => $coursesQuery->get(),
        ]);
    }

    /** Paso 1: Descarga de plantilla oficial `.xlsx` o `.csv`. */
    public function template(Request $request, string $entity)
    {
        abort_unless(in_array($entity, ['students', 'teachers'], true), 404);
        abort_if($entity === 'teachers' && ! $request->user()->isDirector(), 403);

        $format = $request->string('format', 'xlsx')->toString();

        return $this->importer->downloadTemplate($entity, $format);
    }

    /** Pasos 3, 4, 5 y 6: Validación integral y pantalla de Vista Previa antes de persistir. */
    public function preview(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'entity' => ['required', Rule::in(['students', 'teachers'])],
            'default_course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'file' => ['required', 'file', 'extensions:xlsx,csv,txt', 'max:5120'],
        ], [
            'file.required' => 'Seleccioná un archivo Excel (.xlsx) o CSV (.csv).',
            'file.extensions' => 'El archivo debe tener extensión .xlsx o .csv.',
            'file.max' => 'El archivo no puede superar los 5 MB.',
        ]);

        $entity = $data['entity'];
        abort_if($entity === 'teachers' && ! $user->isDirector(), 403);

        $defaultCourseId = isset($data['default_course_id']) ? (int) $data['default_course_id'] : null;
        if ($entity === 'students' && $defaultCourseId !== null && ! $user->isDirector() && ! $user->canManageAttendance($defaultCourseId)) {
            return response()->view('errors.access-denied', ['message' => CheckCourseAssignment::DENIED_MESSAGE], 403);
        }

        try {
            $records = $this->importer->parse($request->file('file'), $entity);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        $analysis = $this->importer->analyze($entity, $records, $user, $defaultCourseId);
        $token = (string) Str::uuid();
        $filename = $request->file('file')->getClientOriginalName();

        $request->session()->put('mass_people_import', [
            'token' => $token,
            'entity' => $entity,
            'default_course_id' => $defaultCourseId,
            'filename' => $filename,
            'records' => $records,
        ]);

        return view('people.import.preview', [
            'entity' => $entity,
            'filename' => $filename,
            'token' => $token,
            'rows' => $analysis['rows'],
            'summary' => $analysis['summary'],
        ]);
    }

    /** Pasos 7 y 8: Confirmación final por token y ejecución transaccional del Upsert por DNI. */
    public function confirm(Request $request): RedirectResponse
    {
        $user = $request->user();
        $pending = $request->session()->get('mass_people_import');

        if (! $pending || ! hash_equals((string) ($pending['token'] ?? ''), (string) $request->input('token'))) {
            return redirect()->route('people.import.index')
                ->withErrors(['file' => 'La sesión de vista previa expiró o ya fue procesada. Volvé a subir el archivo.']);
        }

        $entity = $pending['entity'];
        abort_if($entity === 'teachers' && ! $user->isDirector(), 403);

        // Re-ejecuta el análisis al confirmar para garantizar consistencia frente a cambios concurrentes.
        $analysis = $this->importer->analyze($entity, $pending['records'], $user, $pending['default_course_id']);
        $result = $this->importer->apply($entity, $analysis['rows'], $user, $pending['filename']);

        $request->session()->forget('mass_people_import');
        $request->session()->put('mass_people_import_result', [
            'entity' => $entity,
            'filename' => $pending['filename'],
            'result' => $result,
            'completed_at' => now()->format('d/m/Y H:i:s'),
        ]);

        return redirect()->route('people.import.result')
            ->with('status', "Importación completada: {$result['created']} creados, {$result['updated']} actualizados por DNI.");
    }

    /** Paso 9: Resumen final de la importación ejecutada. */
    public function result(Request $request)
    {
        $report = $request->session()->get('mass_people_import_result');

        if (! $report) {
            return redirect()->route('people.import.index');
        }

        return view('people.import.result', $report);
    }
}
