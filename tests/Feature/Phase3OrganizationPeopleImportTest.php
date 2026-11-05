<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Http\Middleware\CheckCourseAssignment;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Scope;
use App\Models\Shift;
use App\Models\Specialty;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\User;
use App\Services\SpreadsheetWriter;
use Database\Seeders\OrganizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class Phase3OrganizationPeopleImportTest extends TestCase
{
    use RefreshDatabase;

    private User $director;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrganizationSeeder::class);

        $this->director = User::factory()->create([
            'username' => 'director_f3',
            'role' => Role::Director,
            'active' => true,
        ]);

        $this->operator = User::factory()->create([
            'username' => 'preceptor_f3',
            'role' => Role::Operador,
            'active' => true,
        ]);
    }

    public function test_director_can_manage_organization_entities_with_logical_deactivation(): void
    {
        // 1. Especialidad
        $this->actingAs($this->director)
            ->post(route('organization.specialties.store'), [
                'name' => 'Tecnicatura en Informática',
                'code' => 'INF',
                'active' => 1,
            ])
            ->assertRedirect();

        $specialty = Specialty::where('code', 'INF')->firstOrFail();
        $this->assertTrue($specialty->active);

        $this->actingAs($this->director)
            ->patch(route('organization.specialties.toggle', $specialty))
            ->assertRedirect();
        $this->assertFalse($specialty->fresh()->active);

        // Reactivar para asociar a un curso
        $specialty->update(['active' => true]);

        // 2. Turno
        $this->actingAs($this->director)
            ->post(route('organization.shifts.store'), [
                'name' => 'Contraturno Taller',
                'slug' => 'contraturno',
                'active' => 1,
            ])
            ->assertRedirect();

        $shift = Shift::where('slug', 'contraturno')->firstOrFail();

        // 3. Ámbito dinámico
        $this->actingAs($this->director)
            ->post(route('organization.scopes.store'), [
                'name' => 'Prácticas Profesionalizantes',
                'slug' => 'practicas_prof',
                'active' => 1,
            ])
            ->assertRedirect();

        $scope = Scope::where('slug', 'practicas_prof')->firstOrFail();

        // 4. Materia / Actividad dentro del nuevo ámbito
        $this->actingAs($this->director)
            ->post(route('organization.activities.store'), [
                'scope_id' => $scope->id,
                'name' => 'Pasantías Externas 6°',
                'active' => 1,
            ])
            ->assertRedirect();

        $activity = Activity::where('scope_id', $scope->id)->firstOrFail();
        $this->assertSame('Pasantías Externas 6°', $activity->name);

        // 5. Curso / División
        $this->actingAs($this->director)
            ->post(route('organization.courses.store'), [
                'year' => 6,
                'division' => '4',
                'specialty_id' => $specialty->id,
                'shift_id' => $shift->id,
                'active' => 1,
            ])
            ->assertRedirect();

        $course = Course::where('year', 6)->where('division', '4')->firstOrFail();
        $this->assertSame($specialty->id, $course->specialty_id);
        $this->assertSame($shift->id, $course->shift_id);

        // Baja lógica del curso (nunca DELETE físico)
        $this->actingAs($this->director)
            ->patch(route('organization.courses.toggle', $course))
            ->assertRedirect();
        $this->assertFalse($course->fresh()->active);

        // Un operador no puede acceder al módulo Organización
        $this->actingAs($this->operator)
            ->get(route('organization.courses.index'))
            ->assertForbidden();
    }

    public function test_director_can_assign_and_revoke_preceptor_responsibilities(): void
    {
        $specialty = Specialty::where('code', 'CONST')->firstOrFail();
        $shift = Shift::where('slug', 'manana')->firstOrFail();
        $scope = Scope::where('slug', 'taller')->firstOrFail();

        $courseA = Course::create(['year' => 4, 'division' => '1', 'specialty_id' => $specialty->id, 'shift_id' => $shift->id, 'shift' => $shift->slug]);
        $courseB = Course::create(['year' => 4, 'division' => '2', 'specialty_id' => $specialty->id, 'shift_id' => $shift->id, 'shift' => $shift->slug]);

        $this->actingAs($this->director)
            ->post(route('director.assignments.store'), [
                'user_id' => $this->operator->id,
                'scope_id' => $scope->id,
                'course_ids' => [$courseA->id, $courseB->id],
                'shift_id' => $shift->id,
            ])
            ->assertRedirect(route('director.assignments.index'));

        $this->assertTrue($this->operator->fresh()->canManageAttendance($courseA, 'taller'));
        $this->assertTrue($this->operator->fresh()->canManageAttendance($courseB, 'taller'));

        $assignment = $this->operator->assignments()->where('course_id', $courseB->id)->firstOrFail();

        $this->actingAs($this->director)
            ->delete(route('director.assignments.destroy', $assignment))
            ->assertRedirect();

        $this->assertFalse($this->operator->fresh()->canManageAttendance($courseB, 'taller'));
    }

    public function test_students_crud_multicriteria_search_logical_deactivation_and_operator_scoping(): void
    {
        $specialty = Specialty::where('code', 'CB')->firstOrFail();
        $shift = Shift::where('slug', 'manana')->firstOrFail();

        $assignedCourse = Course::create(['year' => 1, 'division' => '1', 'specialty_id' => $specialty->id, 'shift_id' => $shift->id, 'shift' => $shift->slug]);
        $unassignedCourse = Course::create(['year' => 1, 'division' => '2', 'specialty_id' => $specialty->id, 'shift_id' => $shift->id, 'shift' => $shift->slug]);

        $this->operator->assignCourse($assignedCourse, 'clase', $shift->id);

        // Operador crea estudiante en su curso asignado
        $this->actingAs($this->operator)
            ->post(route('people.students.store'), [
                'dni' => '47.123.456',
                'cuil' => '20-47123456-8',
                'file_number' => 'LEG-900',
                'sinide_code' => 'SIN-900',
                'last_name' => 'Aramayo',
                'first_name' => 'Joaquín',
                'birth_date' => '2011-04-15',
                'course_id' => $assignedCourse->id,
                'status' => StudentStatus::Activo->value,
            ])
            ->assertRedirect();

        $student = Student::where('dni', '47123456')->firstOrFail();
        $this->assertTrue($student->active);
        $this->assertSame('20-47123456-8', $student->cuil);

        // Rechaza DNI duplicado
        $this->actingAs($this->director)
            ->post(route('people.students.store'), [
                'dni' => '47123456',
                'last_name' => 'Otro',
                'first_name' => 'Alumno',
                'course_id' => $assignedCourse->id,
                'status' => StudentStatus::Activo->value,
            ])
            ->assertSessionHasErrors('dni');

        // Operador bloqueado si intenta crear o filtrar un curso NO asignado
        $this->actingAs($this->operator)
            ->post(route('people.students.store'), [
                'dni' => '48999888',
                'last_name' => 'Intruso',
                'first_name' => 'Prueba',
                'course_id' => $unassignedCourse->id,
                'status' => StudentStatus::Activo->value,
            ])
            ->assertForbidden()
            ->assertSee(CheckCourseAssignment::DENIED_MESSAGE);

        // Registramos asistencia al estudiante y verificamos baja lógica sin perder historial
        Attendance::create([
            'student_id' => $student->id,
            'course_id' => $assignedCourse->id,
            'scope_id' => Scope::where('slug', 'clase')->value('id'),
            'user_id' => $this->operator->id,
            'date' => '2026-10-15',
            'activity_type' => 'clase',
            'status' => AttendanceStatus::Presente,
        ]);

        // Buscador multicriterio por legajo
        $this->actingAs($this->director)
            ->get(route('people.students.index', ['q' => 'LEG-900']))
            ->assertOk()
            ->assertSee('ARAMAYO, Joaquín');

        // Baja lógica (PATCH toggle)
        $this->actingAs($this->operator)
            ->patch(route('people.students.toggle', $student))
            ->assertRedirect();

        $student->refresh();
        $this->assertFalse($student->active);
        $this->assertSame(StudentStatus::Baja, $student->status);
        $this->assertSame(1, $student->attendances()->count());
    }

    public function test_teachers_crud_and_logical_deactivation_for_director_only(): void
    {
        $shift = Shift::where('slug', 'manana')->firstOrFail();

        $this->actingAs($this->operator)
            ->get(route('people.teachers.index'))
            ->assertForbidden();

        $this->actingAs($this->director)
            ->post(route('people.teachers.store'), [
                'dni' => '29.555.444',
                'cuil' => '27295554443',
                'file_number' => 'DOC-501',
                'last_name' => 'Vilte',
                'first_name' => 'Claudia Noemí',
                'email' => 'cvilte@eet2jujuy.edu.ar',
                'phone' => '388-4001122',
                'specialty_area' => 'Electrónica III',
                'active' => 1,
            ])
            ->assertRedirect(route('people.teachers.index'));

        $teacher = Teacher::where('dni', '29555444')->firstOrFail();
        $this->assertSame('27-29555444-3', $teacher->cuil);
        $this->assertTrue($teacher->active);

        TeacherAttendance::create([
            'teacher_id' => $teacher->id,
            'shift_id' => $shift->id,
            'recorded_by' => $this->director->id,
            'date' => '2026-10-15',
            'status' => AttendanceStatus::Presente,
        ]);

        // Baja lógica conserva asistencias docentes
        $this->actingAs($this->director)
            ->patch(route('people.teachers.toggle', $teacher))
            ->assertRedirect();

        $this->assertFalse($teacher->fresh()->active);
        $this->assertSame(1, $teacher->attendances()->count());
    }

    public function test_nine_step_xlsx_and_csv_importer_performs_preview_validation_and_dni_upsert(): void
    {
        $specialty = Specialty::where('code', 'ELEC')->firstOrFail();
        $shift = Shift::where('slug', 'manana')->firstOrFail();
        $course31 = Course::create(['year' => 3, 'division' => '1', 'specialty_id' => $specialty->id, 'shift_id' => $shift->id, 'shift' => $shift->slug]);

        // Estudiante preexistente con historial de asistencia (se actualizará por DNI sin perder su asistencia)
        $existingStudent = Student::create([
            'dni' => '46111222',
            'last_name' => 'Gómez',
            'first_name' => 'Pedro',
            'file_number' => 'LEG-VIEJO',
            'course_id' => $course31->id,
            'status' => StudentStatus::Activo,
            'active' => true,
        ]);

        Attendance::create([
            'student_id' => $existingStudent->id,
            'course_id' => $course31->id,
            'scope_id' => Scope::where('slug', 'clase')->value('id'),
            'user_id' => $this->director->id,
            'date' => '2026-10-10',
            'activity_type' => 'clase',
            'status' => AttendanceStatus::Ausente,
        ]);

        // Paso 1: Descarga de plantillas oficiales .xlsx y .csv
        $this->actingAs($this->director)
            ->get(route('people.import.template', ['entity' => 'students', 'format' => 'xlsx']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($this->director)
            ->get(route('people.import.template', ['entity' => 'teachers', 'format' => 'csv']))
            ->assertOk();

        // Construimos un archivo .XLSX real con 4 filas: 1 nueva, 1 actualización por DNI, 1 DNI repetido y 1 DNI inválido
        $writer = app(SpreadsheetWriter::class);
        $xlsxBinary = $writer->buildXlsxBinary(
            ['DNI', 'CUIL', 'Legajo', 'Código SINIDE', 'Apellidos', 'Nombres', 'Fecha de Nacimiento', 'Año', 'División', 'Turno', 'Estado'],
            [
                ['46111222', '20-46111222-3', 'LEG-NUEVO', 'SIN-501', 'Gómez', 'Pedro Ezequiel', '10/05/2010', '3', '1', 'Mañana', 'ACTIVO'],
                ['47222333', '27-47222333-4', 'LEG-502', 'SIN-502', 'Mamani', 'Lucía Belén', '22/08/2010', '3', '1', 'Mañana', 'ACTIVO'],
                ['47222333', '', 'LEG-DUP', '', 'Mamani', 'Duplicada', '22/08/2010', '3', '1', 'Mañana', 'ACTIVO'],
                ['123', '', 'LEG-ERR', '', 'SinDni', 'Valido', '22/08/2010', '3', '1', 'Mañana', 'ACTIVO'],
            ],
            'Nomina'
        );

        $tmpXlsx = tempnam(sys_get_temp_dir(), 'import_test_').'.xlsx';
        file_put_contents($tmpXlsx, $xlsxBinary);
        $uploadedXlsx = new UploadedFile($tmpXlsx, 'nomina_3ro_1ra.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        // Pasos 2 a 6: Subida y Vista Previa
        $previewResponse = $this->actingAs($this->director)
            ->post(route('people.import.preview'), [
                'entity' => 'students',
                'file' => $uploadedXlsx,
            ]);

        @unlink($tmpXlsx);

        $previewResponse->assertOk()
            ->assertViewIs('people.import.preview')
            ->assertViewHas('summary', function (array $summary): bool {
                return $summary['total'] === 4
                    && $summary['valid_new'] === 1
                    && $summary['valid_update'] === 1
                    && $summary['errors'] === 2;
            });

        // Todavía no se persistió nada antes de confirmar
        $this->assertDatabaseMissing('students', ['dni' => '47222333']);

        $token = session('mass_people_import.token');
        $this->assertNotEmpty($token);

        // Pasos 7, 8 y 9: Confirmación y Upsert por DNI + pantalla de resumen
        $this->actingAs($this->director)
            ->post(route('people.import.confirm'), ['token' => $token])
            ->assertRedirect(route('people.import.result'));

        $this->actingAs($this->director)
            ->get(route('people.import.result'))
            ->assertOk()
            ->assertSee('Proceso Upsert finalizado');

        // Verificamos creación y actualización sin pérdida de asistencias históricas
        $this->assertDatabaseHas('students', [
            'dni' => '47222333',
            'last_name' => 'Mamani',
            'first_name' => 'Lucía Belén',
            'course_id' => $course31->id,
        ]);

        $existingStudent->refresh();
        $this->assertSame('LEG-NUEVO', $existingStudent->file_number);
        $this->assertSame('Pedro Ezequiel', $existingStudent->first_name);
        $this->assertSame(1, $existingStudent->attendances()->count());

        $this->assertTrue(AuditLog::where('action', 'import')->exists());
    }

    public function test_nine_step_csv_importer_for_teachers_upserts_by_dni_and_blocks_operator(): void
    {
        $existingTeacher = Teacher::create([
            'dni' => '28111222',
            'last_name' => 'Salazar',
            'first_name' => 'Roberto',
            'specialty_area' => 'Matemática',
            'active' => true,
        ]);

        $csvContent = "\xEF\xBB\xBF"
            ."DNI;CUIL;Legajo;Apellidos;Nombres;Email;Teléfono;Área/Especialidad;Estado\r\n"
            ."28111222;20-28111222-4;DOC-801;Salazar;Roberto Carlos;rsalazar@eet2.edu.ar;3884112233;Física Aplicada;ACTIVO\r\n"
            ."31222333;27-31222333-5;DOC-802;Quispe;Mariana;mquispe@eet2.edu.ar;3884998877;Taller de Construcciones;ACTIVO\r\n";

        $tmpCsv = tempnam(sys_get_temp_dir(), 'teachers_csv_').'.csv';
        file_put_contents($tmpCsv, $csvContent);
        $uploadedCsv = new UploadedFile($tmpCsv, 'docentes_eet2.csv', 'text/csv', null, true);

        // Operador tiene prohibido importar docentes
        $this->actingAs($this->operator)
            ->post(route('people.import.preview'), [
                'entity' => 'teachers',
                'file' => $uploadedCsv,
            ])
            ->assertForbidden();

        // Director previsualiza y confirma Upsert por DNI
        $uploadedCsvDirector = new UploadedFile($tmpCsv, 'docentes_eet2.csv', 'text/csv', null, true);
        $this->actingAs($this->director)
            ->post(route('people.import.preview'), [
                'entity' => 'teachers',
                'file' => $uploadedCsvDirector,
            ])
            ->assertOk()
            ->assertViewHas('summary', fn (array $s) => $s['valid_new'] === 1 && $s['valid_update'] === 1 && $s['errors'] === 0);

        @unlink($tmpCsv);

        $token = session('mass_people_import.token');
        $this->actingAs($this->director)
            ->post(route('people.import.confirm'), ['token' => $token])
            ->assertRedirect(route('people.import.result'));

        $this->assertDatabaseHas('teachers', [
            'dni' => '31222333',
            'last_name' => 'Quispe',
            'specialty_area' => 'Taller de Construcciones',
        ]);

        $existingTeacher->refresh();
        $this->assertSame('Roberto Carlos', $existingTeacher->first_name);
        $this->assertSame('Física Aplicada', $existingTeacher->specialty_area);
    }
}
