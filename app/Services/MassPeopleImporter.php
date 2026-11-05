<?php

namespace App\Services;

use App\Enums\StudentStatus;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Servicio de Importación Masiva Excel (.xlsx) y CSV en 9 Pasos para Estudiantes y Docentes.
 *
 * Pasos cubiertos:
 *  1. Descarga de plantilla oficial (.xlsx / .csv)
 *  2. Recepción de archivo subido
 *  3. Validación estructural de encabezados/columnas
 *  4. Validación de formato y dominio por fila (DNI, CUIL, fechas, curso, estado)
 *  5. Detección de duplicados internos en archivo y coincidencias por DNI en BD
 *  6. Construcción de Vista Previa con contadores detallados
 *  7. Verificación de confirmación explícita por token
 *  8. Guardado transaccional Upsert por DNI (actualiza si existe, crea si no existe)
 *  9. Generación de resumen final auditado
 */
class MassPeopleImporter
{
    private const STUDENT_HEADERS = [
        'dni' => ['dni', 'documento', 'nro documento', 'numero de documento', 'nro doc'],
        'cuil' => ['cuil', 'cuit'],
        'file_number' => ['legajo', 'nro legajo', 'numero legajo', 'legajo institucional'],
        'sinide_code' => ['codigo sinide', 'cod sinide', 'sinide', 'id sinide'],
        'last_name' => ['apellido', 'apellidos'],
        'first_name' => ['nombre', 'nombres'],
        'full_name' => ['apellido y nombre', 'apellidos y nombres', 'apellido y nombres', 'alumno', 'estudiante'],
        'birth_date' => ['fecha de nacimiento', 'fecha nacimiento', 'nacimiento', 'fecha nac'],
        'year' => ['ano', 'anio', 'curso ano', 'grado ano'],
        'division' => ['division', 'div', 'seccion'],
        'course_label' => ['curso', 'curso y division', 'curso division'],
        'shift' => ['turno'],
        'status' => ['estado', 'estado de matricula', 'matricula', 'condicion', 'activo'],
    ];

    private const TEACHER_HEADERS = [
        'dni' => ['dni', 'documento', 'nro documento', 'numero de documento', 'nro doc'],
        'cuil' => ['cuil', 'cuit'],
        'file_number' => ['legajo', 'nro legajo', 'legajo docente'],
        'last_name' => ['apellido', 'apellidos'],
        'first_name' => ['nombre', 'nombres'],
        'full_name' => ['apellido y nombre', 'apellidos y nombres', 'docente', 'profesor'],
        'email' => ['email', 'correo', 'correo electronico', 'e mail'],
        'phone' => ['telefono', 'celular', 'tel', 'contacto'],
        'specialty_area' => ['area especialidad', 'especialidad', 'area', 'cargo', 'materia'],
        'status' => ['estado', 'activo', 'condicion', 'situacion'],
    ];

    private const STUDENT_STATUS_MAP = [
        'activo' => 'activo', 'activa' => 'activo', 'regular' => 'activo', 'si' => 'activo', '1' => 'activo',
        'inactivo' => 'baja', 'inactiva' => 'baja', 'baja' => 'baja', 'no' => 'baja', '0' => 'baja',
        'libre' => 'libre',
        'pase' => 'pase',
    ];

    private const TEACHER_ACTIVE_MAP = [
        'activo' => true, 'activa' => true, 'si' => true, '1' => true, 'true' => true,
        'inactivo' => false, 'inactiva' => false, 'baja' => false, 'no' => false, '0' => false, 'false' => false,
    ];

    public function __construct(
        private readonly SpreadsheetReader $reader,
        private readonly SpreadsheetWriter $writer,
    ) {
    }

    /**
     * Paso 1: Genera y descarga la plantilla oficial en formato `.xlsx` o `.csv`.
     */
    public function downloadTemplate(string $entity, string $format = 'xlsx'): Response
    {
        if ($entity === 'teachers') {
            $headers = ['DNI', 'CUIL', 'Legajo', 'Apellidos', 'Nombres', 'Email', 'Teléfono', 'Área/Especialidad', 'Estado'];
            $sample = [
                ['28456123', '20-28456123-4', 'DOC-101', 'Salazar', 'Roberto Carlos', 'rsalazar@eet2jujuy.edu.ar', '388-4210011', 'Taller de Informática', 'ACTIVO'],
                ['31789456', '27-31789456-2', 'DOC-102', 'Quispe', 'Mariana Soledad', 'mquispe@eet2jujuy.edu.ar', '388-4332211', 'Matemática Aplicada', 'ACTIVO'],
            ];
            $filename = 'plantilla-oficial-docentes-eet2';
            $sheet = 'Plantilla Docentes';
        } else {
            $headers = ['DNI', 'CUIL', 'Legajo', 'Código SINIDE', 'Apellidos', 'Nombres', 'Fecha de Nacimiento', 'Año', 'División', 'Turno', 'Estado'];
            $sample = [
                ['46234567', '20-46234567-9', 'LEG-2026-001', 'SIN-1001', 'Aramayo', 'Mateo Gabriel', '14/05/2010', '3', '1', 'Mañana', 'ACTIVO'],
                ['47890123', '27-47890123-5', 'LEG-2026-002', 'SIN-1002', 'Calisaya', 'Valentina Luján', '22/09/2011', '1', '1', 'Mañana', 'ACTIVO'],
            ];
            $filename = 'plantilla-oficial-estudiantes-sinide-eet2';
            $sheet = 'Plantilla Estudiantes';
        }

        return strtolower($format) === 'csv'
            ? $this->writer->downloadCsv($filename, $headers, $sample)
            : $this->writer->downloadXlsx($filename, $headers, $sample, $sheet);
    }

    /**
     * Pasos 2 y 3: Lee el archivo `.xlsx`/`.csv`, valida encabezados y extrae registros crudos normalizados.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parse(UploadedFile $file, string $entity): array
    {
        $rows = $this->reader->read($file);
        if (count($rows) < 2) {
            throw new RuntimeException('El archivo está vacío o no contiene filas de datos debajo del encabezado.');
        }

        $dictionary = $entity === 'teachers' ? self::TEACHER_HEADERS : self::STUDENT_HEADERS;
        [$headerIndex, $map] = $this->locateHeader($rows, $dictionary, $entity);

        $records = [];
        foreach (array_slice($rows, $headerIndex + 1, null, true) as $idx => $row) {
            $get = fn (string $field): string => isset($map[$field]) ? trim((string) ($row[$map[$field]] ?? '')) : '';

            $last = $get('last_name');
            $first = $get('first_name');
            $nameError = null;

            if ($last === '' && $first === '' && isset($map['full_name'])) {
                $full = $get('full_name');
                if ($full !== '') {
                    if (str_contains($full, ',')) {
                        [$last, $first] = array_map('trim', explode(',', $full, 2));
                    } else {
                        $parts = preg_split('/\s+/', $full) ?: [];
                        if (count($parts) >= 2) {
                            $last = array_shift($parts);
                            $first = implode(' ', $parts);
                        } else {
                            $nameError = 'Formato de nombre incompleto (indicá Apellido y Nombre).';
                        }
                    }
                }
            }

            $dni = $this->digits($get('dni'));
            $cuil = $this->digits($get('cuil'));

            if ($dni === '' && $last === '' && $first === '' && ! $nameError) {
                continue;
            }

            $base = [
                'line' => $idx + 1,
                'dni' => $dni,
                'cuil' => $cuil,
                'file_number' => $get('file_number') ?: null,
                'last_name' => $this->properCase($last),
                'first_name' => $this->properCase($first),
                'status_raw' => $get('status'),
                'name_error' => $nameError,
            ];

            if ($entity === 'teachers') {
                $records[] = $base + [
                    'email' => Str::lower($get('email')) ?: null,
                    'phone' => $get('phone') ?: null,
                    'specialty_area' => $get('specialty_area') ?: null,
                ];
            } else {
                $records[] = $base + [
                    'sinide_code' => $get('sinide_code') ?: null,
                    'birth_raw' => $get('birth_date'),
                    'year_raw' => $get('year'),
                    'division_raw' => $get('division'),
                    'course_raw' => $get('course_label'),
                    'shift_raw' => $get('shift'),
                ];
            }
        }

        if ($records === []) {
            throw new RuntimeException('No se encontraron registros útiles debajo del encabezado.');
        }

        return $records;
    }

    /**
     * Pasos 4, 5 y 6: Valida cada fila, detecta duplicados y arma el reporte de Vista Previa.
     *
     * @param  array<int, array<string, mixed>>  $records
     * @return array{rows: array<int, array<string, mixed>>, summary: array<string, int>}
     */
    public function analyze(string $entity, array $records, User $actor, ?int $defaultCourseId = null): array
    {
        $rows = $entity === 'teachers'
            ? $this->analyzeTeachers($records)
            : $this->analyzeStudents($records, $actor, $defaultCourseId);

        $counts = collect($rows)->countBy('action');

        $summary = [
            'total' => count($rows),
            'valid_new' => (int) ($counts['crear'] ?? 0),
            'valid_update' => (int) ($counts['actualizar'] ?? 0),
            'unchanged' => (int) ($counts['sin_cambios'] ?? 0),
            'errors' => (int) ($counts['error'] ?? 0),
        ];
        $summary['ready'] = $summary['valid_new'] + $summary['valid_update'];

        return [
            'rows' => $rows,
            'summary' => $summary,
        ];
    }

    /**
     * Pasos 8 y 9: Aplica el guardado transaccional Upsert por DNI y devuelve las métricas finales.
     *
     * @param  array<int, array<string, mixed>>  $analyzedRows
     * @return array{created: int, updated: int, unchanged: int, errors: int, total: int}
     */
    public function apply(string $entity, array $analyzedRows, User $actor, string $filename): array
    {
        $result = [
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'errors' => 0,
            'total' => count($analyzedRows),
        ];

        DB::transaction(function () use ($entity, $analyzedRows, &$result) {
            foreach ($analyzedRows as $row) {
                if ($row['action'] === 'crear') {
                    if ($entity === 'teachers') {
                        Teacher::updateOrCreate(['dni' => $row['data']['dni']], $row['data']);
                    } else {
                        Student::updateOrCreate(['dni' => $row['data']['dni']], $row['data']);
                    }
                    $result['created']++;
                } elseif ($row['action'] === 'actualizar') {
                    if ($entity === 'teachers') {
                        Teacher::updateOrCreate(['dni' => $row['data']['dni']], $row['data']);
                    } else {
                        Student::updateOrCreate(['dni' => $row['data']['dni']], $row['data']);
                    }
                    $result['updated']++;
                } elseif ($row['action'] === 'sin_cambios') {
                    $result['unchanged']++;
                } else {
                    $result['errors']++;
                }
            }
        });

        AuditLog::record(
            action: 'import',
            description: sprintf(
                'Importación masiva de %s («%s»): %d creados, %d actualizados por DNI, %d sin cambios, %d con error.',
                $entity === 'teachers' ? 'Docentes' : 'Estudiantes',
                $filename,
                $result['created'],
                $result['updated'],
                $result['unchanged'],
                $result['errors'],
            ),
            auditableType: $entity === 'teachers' ? Teacher::class : Student::class,
            newValues: $result + ['entity' => $entity, 'filename' => $filename],
            userId: $actor->id,
        );

        return $result;
    }

    /* ========================== Análisis de Estudiantes ========================== */

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array<int, array<string, mixed>>
     */
    private function analyzeStudents(array $records, User $actor, ?int $defaultCourseId): array
    {
        $courses = Course::with(['shiftRef', 'specialty'])->active()->get();
        $allowedCourseIds = $actor->isDirector()
            ? null
            : $actor->assignments()->pluck('course_id')->map(fn ($id) => (int) $id)->unique()->all();

        $dnis = array_values(array_filter(array_column($records, 'dni')));
        $cuils = array_values(array_filter(array_map(fn ($r) => $this->formatCuil($r['cuil']), $records)));

        $existingByDni = $dnis ? Student::with('course')->whereIn('dni', $dnis)->get()->keyBy('dni') : collect();
        $existingByCuil = $cuils ? Student::whereIn('cuil', $cuils)->get()->keyBy('cuil') : collect();

        $seenDni = [];
        $seenCuil = [];
        $analyzed = [];

        foreach ($records as $r) {
            $errors = [];

            if ($r['dni'] === '' || ! preg_match('/^\d{7,9}$/', $r['dni'])) {
                $errors[] = 'DNI obligatorio y válido (7 a 9 dígitos numéricos).';
            } elseif (isset($seenDni[$r['dni']])) {
                $errors[] = "DNI duplicado dentro del archivo (aparece en fila {$seenDni[$r['dni']]}).";
            } else {
                $seenDni[$r['dni']] = $r['line'];
            }

            if ($r['name_error']) {
                $errors[] = $r['name_error'];
            } elseif ($r['last_name'] === '' || $r['first_name'] === '') {
                $errors[] = 'Apellido y Nombre son obligatorios.';
            }

            $cuil = null;
            if ($r['cuil'] !== '') {
                if (strlen($r['cuil']) !== 11) {
                    $errors[] = 'CUIL inválido (debe tener 11 dígitos).';
                } else {
                    $cuil = $this->formatCuil($r['cuil']);
                    if (isset($seenCuil[$cuil])) {
                        $errors[] = "CUIL duplicado dentro del archivo (fila {$seenCuil[$cuil]}).";
                    } else {
                        $seenCuil[$cuil] = $r['line'];
                    }
                }
            }

            $birth = null;
            if ($r['birth_raw'] !== '') {
                $birth = $this->parseDate($r['birth_raw']);
                if (! $birth) {
                    $errors[] = "Fecha de nacimiento inválida («{$r['birth_raw']}»).";
                } elseif (Carbon::parse($birth)->isFuture()) {
                    $errors[] = 'La fecha de nacimiento no puede ser futura.';
                }
            }

            $status = StudentStatus::Activo->value;
            if ($r['status_raw'] !== '') {
                $key = Str::lower(Str::ascii(trim($r['status_raw'])));
                if (! isset(self::STUDENT_STATUS_MAP[$key])) {
                    $errors[] = "Estado desconocido («{$r['status_raw']}»). Usá ACTIVO, INACTIVO, BAJA, PASE o LIBRE.";
                } else {
                    $status = self::STUDENT_STATUS_MAP[$key];
                }
            }

            $target = $r['dni'] !== '' ? $existingByDni->get($r['dni']) : null;

            $course = $this->resolveStudentCourse($r, $courses, $defaultCourseId, $target);
            if (! $course) {
                $errors[] = 'No se pudo identificar un Curso/División activo válido (verificá Año, División y Turno o elegí un curso por defecto).';
            } elseif ($allowedCourseIds !== null && ! in_array($course->id, $allowedCourseIds, true)) {
                $errors[] = "No tenés permiso asignado sobre el curso {$course->name}.";
            }

            if ($target && $allowedCourseIds !== null && ! in_array((int) $target->course_id, $allowedCourseIds, true)) {
                $errors[] = "El DNI {$r['dni']} ya pertenece a un estudiante del curso ".($target->course?->name ?? '#'.$target->course_id).' fuera de tu asignación.';
            }

            if ($cuil && ($cuilOwner = $existingByCuil->get($cuil)) && (! $target || $cuilOwner->id !== $target->id)) {
                $errors[] = "El CUIL {$cuil} ya está registrado en otro estudiante (DNI {$cuilOwner->dni}).";
            }

            $payload = [
                'dni' => $r['dni'],
                'last_name' => $r['last_name'],
                'first_name' => $r['first_name'],
                'course_id' => $course?->id,
                'status' => $status,
                'active' => $status === StudentStatus::Activo->value,
            ];

            if ($cuil !== null) {
                $payload['cuil'] = $cuil;
            }
            if ($r['file_number'] !== null) {
                $payload['file_number'] = $r['file_number'];
            }
            if ($r['sinide_code'] !== null) {
                $payload['sinide_code'] = $r['sinide_code'];
            }
            if ($birth !== null) {
                $payload['birth_date'] = $birth;
            }

            $action = 'error';
            if ($errors === []) {
                if (! $target) {
                    $action = 'crear';
                } elseif ($this->studentUnchanged($target, $payload)) {
                    $action = 'sin_cambios';
                } else {
                    $action = 'actualizar';
                }
            }

            $analyzed[] = [
                'line' => $r['line'],
                'dni' => $r['dni'],
                'full_name' => trim(($r['last_name'] ?: '—').', '.($r['first_name'] ?: '—')),
                'extra' => $course ? $course->name.' ('.($course->shiftRef?->name ?? $course->shift->label()).')' : 'Curso sin resolver',
                'status_label' => $payload['active'] ? 'ACTIVO' : 'INACTIVO ('.strtoupper($status).')',
                'data' => $payload,
                'action' => $action,
                'errors' => $errors,
                'target_id' => $target?->id,
            ];
        }

        return $analyzed;
    }

    /**
     * @param  array<string, mixed>  $r
     * @param  Collection<int, Course>  $courses
     */
    private function resolveStudentCourse(array $r, Collection $courses, ?int $defaultCourseId, ?Student $existing): ?Course
    {
        $year = null;
        $division = null;

        if ($r['year_raw'] !== '' && preg_match('/([1-7])/', (string) $r['year_raw'], $mY)) {
            $year = (int) $mY[1];
        }
        if ($r['division_raw'] !== '' && preg_match('/([1-9][0-9]*)/', (string) $r['division_raw'], $mD)) {
            $division = $mD[1];
        }

        if (($year === null || $division === null) && $r['course_raw'] !== '') {
            if (preg_match('/([1-7])\D+([1-9][0-9]*)/', (string) $r['course_raw'], $mC)) {
                $year = (int) $mC[1];
                $division = $mC[2];
            }
        }

        if ($year !== null && $division !== null) {
            $candidates = $courses->filter(fn (Course $c) => $c->year === $year && (string) $c->division === (string) $division);

            if ($candidates->count() > 1 && $r['shift_raw'] !== '') {
                $shiftNorm = Str::slug(Str::ascii($r['shift_raw']), '_');
                $filtered = $candidates->filter(function (Course $c) use ($shiftNorm) {
                    $slug = $c->shiftRef?->slug ?? $c->shift->value;
                    $name = Str::slug(Str::ascii($c->shiftRef?->name ?? $c->shift->label()), '_');

                    return $slug === $shiftNorm || str_contains($name, $shiftNorm);
                });
                if ($filtered->isNotEmpty()) {
                    return $filtered->first();
                }
            }

            return $candidates->first();
        }

        if ($defaultCourseId !== null) {
            return $courses->firstWhere('id', $defaultCourseId);
        }

        if ($existing?->course_id) {
            return $courses->firstWhere('id', $existing->course_id);
        }

        return null;
    }

    private function studentUnchanged(Student $student, array $payload): bool
    {
        foreach ($payload as $key => $val) {
            $cur = $student->getAttribute($key);
            if ($cur instanceof \BackedEnum) {
                $cur = $cur->value;
            } elseif ($cur instanceof Carbon) {
                $cur = $cur->toDateString();
            } elseif (is_bool($cur)) {
                if ($cur !== (bool) $val) {
                    return false;
                }

                continue;
            }

            if ((string) ($cur ?? '') !== (string) ($val ?? '')) {
                return false;
            }
        }

        return true;
    }

    /* =========================== Análisis de Docentes ============================ */

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array<int, array<string, mixed>>
     */
    private function analyzeTeachers(array $records): array
    {
        $dnis = array_values(array_filter(array_column($records, 'dni')));
        $cuils = array_values(array_filter(array_map(fn ($r) => $this->formatCuil($r['cuil']), $records)));

        $existingByDni = $dnis ? Teacher::whereIn('dni', $dnis)->get()->keyBy('dni') : collect();
        $existingByCuil = $cuils ? Teacher::whereIn('cuil', $cuils)->get()->keyBy('cuil') : collect();

        $seenDni = [];
        $seenCuil = [];
        $analyzed = [];

        foreach ($records as $r) {
            $errors = [];

            if ($r['dni'] === '' || ! preg_match('/^\d{7,9}$/', $r['dni'])) {
                $errors[] = 'DNI obligatorio y válido (7 a 9 dígitos numéricos).';
            } elseif (isset($seenDni[$r['dni']])) {
                $errors[] = "DNI duplicado dentro del archivo (aparece en fila {$seenDni[$r['dni']]}).";
            } else {
                $seenDni[$r['dni']] = $r['line'];
            }

            if ($r['name_error']) {
                $errors[] = $r['name_error'];
            } elseif ($r['last_name'] === '' || $r['first_name'] === '') {
                $errors[] = 'Apellido y Nombre son obligatorios.';
            }

            $cuil = null;
            if ($r['cuil'] !== '') {
                if (strlen($r['cuil']) !== 11) {
                    $errors[] = 'CUIL inválido (debe tener 11 dígitos).';
                } else {
                    $cuil = $this->formatCuil($r['cuil']);
                    if (isset($seenCuil[$cuil])) {
                        $errors[] = "CUIL duplicado dentro del archivo (fila {$seenCuil[$cuil]}).";
                    } else {
                        $seenCuil[$cuil] = $r['line'];
                    }
                }
            }

            if ($r['email'] !== null && ! filter_var($r['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Email inválido («{$r['email']}»).";
            }

            $active = true;
            if ($r['status_raw'] !== '') {
                $key = Str::lower(Str::ascii(trim($r['status_raw'])));
                if (! array_key_exists($key, self::TEACHER_ACTIVE_MAP)) {
                    $errors[] = "Estado desconocido («{$r['status_raw']}»). Usá ACTIVO o INACTIVO.";
                } else {
                    $active = self::TEACHER_ACTIVE_MAP[$key];
                }
            }

            $target = $r['dni'] !== '' ? $existingByDni->get($r['dni']) : null;

            if ($cuil && ($cuilOwner = $existingByCuil->get($cuil)) && (! $target || $cuilOwner->id !== $target->id)) {
                $errors[] = "El CUIL {$cuil} ya pertenece a otro docente (DNI {$cuilOwner->dni}).";
            }

            $payload = [
                'dni' => $r['dni'],
                'last_name' => $r['last_name'],
                'first_name' => $r['first_name'],
                'active' => $active,
            ];

            if ($cuil !== null) {
                $payload['cuil'] = $cuil;
            }
            if ($r['file_number'] !== null) {
                $payload['file_number'] = $r['file_number'];
            }
            if ($r['email'] !== null) {
                $payload['email'] = $r['email'];
            }
            if ($r['phone'] !== null) {
                $payload['phone'] = $r['phone'];
            }
            if ($r['specialty_area'] !== null) {
                $payload['specialty_area'] = $r['specialty_area'];
            }

            $action = 'error';
            if ($errors === []) {
                if (! $target) {
                    $action = 'crear';
                } elseif ($this->teacherUnchanged($target, $payload)) {
                    $action = 'sin_cambios';
                } else {
                    $action = 'actualizar';
                }
            }

            $analyzed[] = [
                'line' => $r['line'],
                'dni' => $r['dni'],
                'full_name' => trim(($r['last_name'] ?: '—').', '.($r['first_name'] ?: '—')),
                'extra' => $r['specialty_area'] ?: ($r['email'] ?: 'Docente institucional'),
                'status_label' => $active ? 'ACTIVO' : 'INACTIVO',
                'data' => $payload,
                'action' => $action,
                'errors' => $errors,
                'target_id' => $target?->id,
            ];
        }

        return $analyzed;
    }

    private function teacherUnchanged(Teacher $teacher, array $payload): bool
    {
        foreach ($payload as $key => $val) {
            $cur = $teacher->getAttribute($key);
            if (is_bool($cur)) {
                if ($cur !== (bool) $val) {
                    return false;
                }

                continue;
            }
            if ((string) ($cur ?? '') !== (string) ($val ?? '')) {
                return false;
            }
        }

        return true;
    }

    /* ================================ Helpers ================================ */

    /**
     * @param  array<int, array<int, string>>  $rows
     * @param  array<string, array<int, string>>  $dictionary
     * @return array{0: int, 1: array<string, int>}
     */
    private function locateHeader(array $rows, array $dictionary, string $entity): array
    {
        foreach (array_slice($rows, 0, 15, true) as $i => $row) {
            $map = [];
            foreach ($row as $col => $cell) {
                $norm = $this->normalizeHeader((string) $cell);
                foreach ($dictionary as $field => $aliases) {
                    if (in_array($norm, $aliases, true) && ! isset($map[$field])) {
                        $map[$field] = $col;
                    }
                }
            }

            $hasName = isset($map['full_name']) || (isset($map['last_name']) && isset($map['first_name']));
            if (isset($map['dni']) && $hasName) {
                return [$i, $map];
            }
        }

        $expected = $entity === 'teachers'
            ? 'DNI, Apellidos, Nombres (y opcionalmente CUIL, Legajo, Email, Teléfono, Área/Especialidad, Estado)'
            : 'DNI, Apellidos, Nombres, Año, División (y opcionalmente CUIL, Legajo, Código SINIDE, Fecha de Nacimiento, Turno, Estado)';

        throw new RuntimeException("Encabezado inválido o faltante. Descargá la plantilla oficial; columnas requeridas: {$expected}.");
    }

    private function normalizeHeader(string $label): string
    {
        $label = Str::lower(Str::ascii($label));
        $label = preg_replace('/[^a-z0-9]+/', ' ', $label) ?? '';

        return trim($label);
    }

    private function digits(string $value): string
    {
        $value = preg_replace('/\.0+$/', '', trim($value)) ?? '';

        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function formatCuil(string $digits): ?string
    {
        return strlen($digits) === 11
            ? substr($digits, 0, 2).'-'.substr($digits, 2, 8).'-'.substr($digits, 10, 1)
            : null;
    }

    private function properCase(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        return ($value !== '' && $value === mb_strtoupper($value))
            ? mb_convert_case($value, MB_CASE_TITLE, 'UTF-8')
            : $value;
    }

    private function parseDate(string $raw): ?string
    {
        $raw = trim($raw);

        if (preg_match('/^\d{4,6}(\.\d+)?$/', $raw)) {
            $serial = (float) $raw;
            if ($serial > 1000 && $serial < 80000) {
                return Carbon::create(1899, 12, 30)->addDays((int) floor($serial))->toDateString();
            }
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y', 'd.m.Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $raw);
            } catch (\Throwable) {
                continue;
            }

            if ($date && $date->format($format) === $raw) {
                return $date->toDateString();
            }
        }

        return null;
    }
}
