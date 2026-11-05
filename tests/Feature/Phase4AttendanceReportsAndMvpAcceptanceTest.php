<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Http\Middleware\CheckCourseAssignment;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Justification;
use App\Models\NonWorkingDay;
use App\Models\Scope;
use App\Models\Shift;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\User;
use App\Services\SpreadsheetWriter;
use Carbon\Carbon;
use Database\Seeders\OrganizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Suite integral de validación de la FASE 4 y los 7 Casos de Prueba Institucionales del MVP 1.0
 * para la EET N.º 2 «Prof. Jesús Raúl Salazar».
 */
class Phase4AttendanceReportsAndMvpAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private User $director;

    private User $operator;

    private User $docente;

    private Course $course31;

    private Course $course52;

    private Scope $scopeClase;

    private Scope $scopeTaller;

    private Shift $shiftManana;

    private Activity $activityAula;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-19 09:30:00'); // Miércoles hábil
        $this->seed(OrganizationSeeder::class);

        $this->scopeClase = Scope::where('slug', 'clase')->firstOrFail();
        $this->scopeTaller = Scope::where('slug', 'taller')->firstOrFail();
        $this->shiftManana = Shift::where('slug', 'manana')->firstOrFail();
        $shiftTarde = Shift::where('slug', 'tarde')->firstOrFail();
        $specConst = \App\Models\Specialty::where('code', 'CONST')->firstOrFail();
        $specElec = \App\Models\Specialty::where('code', 'ELEC')->firstOrFail();
        $this->activityAula = Activity::where('scope_id', $this->scopeClase->id)->firstOrFail();

        $this->course31 = Course::create([
            'year' => 3,
            'division' => '1',
            'shift' => 'manana',
            'shift_id' => $this->shiftManana->id,
            'specialty_id' => $specConst->id,
            'active' => true,
        ]);

        $this->course52 = Course::create([
            'year' => 5,
            'division' => '2',
            'shift' => 'tarde',
            'shift_id' => $shiftTarde->id,
            'specialty_id' => $specElec->id,
            'active' => true,
        ]);

        $this->director = User::factory()->create([
            'name' => 'Director Institucional',
            'username' => 'director_mvp',
            'email' => 'direccion@eet2jujuy.edu.ar',
            'password' => 'secreto2026',
            'role' => Role::Director,
            'active' => true,
        ]);

        $this->operator = User::factory()->create([
            'name' => 'Preceptor 3° 1°',
            'username' => 'preceptor_31',
            'email' => 'preceptor31@eet2jujuy.edu.ar',
            'password' => 'secreto2026',
            'role' => Role::Operador,
            'active' => true,
        ]);

        // Asignar al operador exclusivamente 3° 1° en ámbito Clase (Aula General)
        $this->operator->assignCourse($this->course31, $this->scopeClase, $this->course31->shift_id);

        $this->docente = User::factory()->create([
            'name' => 'Docente Consulta',
            'username' => 'docente_mvp',
            'email' => 'docente@eet2jujuy.edu.ar',
            'password' => 'secreto2026',
            'role' => Role::Docente,
            'active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * CASO DE PRUEBA 1: Autenticación por usuario/email, redirección por rol, bloqueo de cuentas inactivas y bitácora.
     */
    public function test_case_1_authentication_role_redirection_and_audit_trail(): void
    {
        // Director ingresa por email -> redirige a Dashboard Global
        $this->post(route('login.store'), [
            'username' => 'direccion@eet2jujuy.edu.ar',
            'password' => 'secreto2026',
        ])->assertRedirect(route('home'));

        $this->get(route('home'))->assertRedirect(route('director.dashboard'));
        $this->assertDatabaseHas('audit_logs', ['user_id' => $this->director->id, 'action' => 'login']);
        $this->post(route('logout'))->assertRedirect(route('login'));

        // Operador ingresa por username -> redirige a Mis cursos asignados
        $this->post(route('login.store'), [
            'username' => 'preceptor_31',
            'password' => 'secreto2026',
        ])->assertRedirect(route('home'));

        $this->get(route('home'))->assertRedirect(route('operator.home'));
        $this->post(route('logout'));

        // Docente ingresa -> redirige a su panel de solo lectura
        $this->post(route('login.store'), [
            'username' => 'docente_mvp',
            'password' => 'secreto2026',
        ])->assertRedirect(route('home'));

        $this->get(route('home'))->assertRedirect(route('docente.home'));
        $this->post(route('logout'));
    }

    /**
     * CASO DE PRUEBA 2: RBAC estricto y CheckCourseAssignment en toma diaria, grilla mensual y reportes.
     */
    public function test_case_2_rbac_and_course_assignment_protection_across_daily_monthly_and_reports(): void
    {
        $stAssigned = Student::factory()->create(['course_id' => $this->course31->id, 'last_name' => 'Gomez']);
        $stUnassigned = Student::factory()->create(['course_id' => $this->course52->id, 'last_name' => 'Miranda']);

        // Acceso permitido a su curso y ámbito asignado
        $this->actingAs($this->operator)
            ->get(route('attendance.daily', [
                'course_id' => $this->course31->id,
                'scope_id' => $this->scopeClase->id,
            ]))
            ->assertOk()
            ->assertSee('GOMEZ');

        // Bloqueo en Toma Diaria si intenta acceder a un curso no asignado (5° 2°)
        $this->actingAs($this->operator)
            ->get(route('attendance.daily', [
                'course_id' => $this->course52->id,
                'scope_id' => $this->scopeClase->id,
            ]))
            ->assertForbidden()
            ->assertSee(CheckCourseAssignment::DENIED_MESSAGE, false);

        // Bloqueo en Toma Diaria si intenta acceder a su curso pero en otro ámbito no asignado (Taller)
        $this->actingAs($this->operator)
            ->get(route('attendance.daily', [
                'course_id' => $this->course31->id,
                'scope_id' => $this->scopeTaller->id,
            ]))
            ->assertForbidden()
            ->assertSee(CheckCourseAssignment::DENIED_MESSAGE, false);

        // Bloqueo en Planilla Mensual y Ficha Individual sobre curso ajeno
        $this->actingAs($this->operator)
            ->get(route('reports.monthly-sheet', [
                'course_id' => $this->course52->id,
                'scope' => 'clase',
            ]))
            ->assertForbidden()
            ->assertSee(CheckCourseAssignment::DENIED_MESSAGE, false);

        $this->actingAs($this->operator)
            ->get(route('reports.student-history', $stUnassigned))
            ->assertForbidden()
            ->assertSee(CheckCourseAssignment::DENIED_MESSAGE, false);

        // Rol docente tiene prohibida la carga diaria de estudiantes y docentes
        $this->actingAs($this->docente)->get(route('attendance.daily'))->assertForbidden();
        $this->actingAs($this->docente)->get(route('attendance.teachers.index'))->assertForbidden();
    }

    /**
     * CASO DE PRUEBA 3: Baja lógica (ACTIVO / INACTIVO) sin borrado físico preservando historial.
     */
    public function test_case_3_logical_deactivation_preserves_historical_attendance(): void
    {
        $student = Student::factory()->create([
            'course_id' => $this->course31->id,
            'last_name' => 'Arias',
            'first_name' => 'Valentina',
            'dni' => '46001122',
            'status' => StudentStatus::Activo,
            'active' => true,
        ]);

        Attendance::create([
            'student_id' => $student->id,
            'course_id' => $this->course31->id,
            'scope_id' => $this->scopeClase->id,
            'shift_id' => $this->course31->shift_id,
            'user_id' => $this->operator->id,
            'date' => '2026-08-18',
            'activity_type' => 'clase',
            'status' => AttendanceStatus::Ausente,
        ]);

        // Baja lógica del estudiante (seguimos el redirect para consumir el banner flash)
        $this->actingAs($this->director)
            ->followingRedirects()
            ->patch(route('people.students.toggle', $student))
            ->assertOk();

        $this->assertDatabaseHas('students', ['id' => $student->id, 'active' => false]);
        $this->assertDatabaseHas('attendances', ['student_id' => $student->id, 'date' => '2026-08-18']);

        // No aparece para carga nueva en Toma Diaria de hoy
        $this->actingAs($this->operator)
            ->get(route('attendance.daily', ['course_id' => $this->course31->id, 'scope_id' => $this->scopeClase->id]))
            ->assertOk()
            ->assertDontSee('ARIAS, Valentina');

        // Sí conserva intacto su historial individual y su fila en la planilla mensual de agosto
        $this->actingAs($this->operator)
            ->get(route('reports.student-history', $student))
            ->assertOk()
            ->assertSee('ARIAS, Valentina')
            ->assertSee('18/08/2026');

        $this->actingAs($this->operator)
            ->get(route('reports.monthly-sheet', [
                'month' => '2026-08',
                'scope' => 'clase',
                'course_id' => $this->course31->id,
            ]))
            ->assertOk()
            ->assertSee('Arias, Valentina');
    }

    /**
     * CASO DE PRUEBA 4: Importador Masivo Excel/CSV en 9 pasos realiza Upsert por DNI sin perder historial.
     */
    public function test_case_4_nine_step_importer_upserts_by_dni_preserving_attendance_history(): void
    {
        $existing = Student::factory()->create([
            'course_id' => $this->course31->id,
            'dni' => '45999888',
            'last_name' => 'Quispe',
            'first_name' => 'Mateo',
            'file_number' => 'LEG-01',
        ]);

        Attendance::create([
            'student_id' => $existing->id,
            'course_id' => $this->course31->id,
            'scope_id' => $this->scopeClase->id,
            'shift_id' => $this->course31->shift_id,
            'user_id' => $this->director->id,
            'date' => '2026-08-14',
            'activity_type' => 'clase',
            'status' => AttendanceStatus::Presente,
        ]);

        /** @var SpreadsheetWriter $writer */
        $writer = app(SpreadsheetWriter::class);
        $xlsxBinary = $writer->buildXlsxBinary(
            ['dni', 'cuil', 'legajo', 'codigo_sinide', 'apellidos', 'nombres', 'fecha_nacimiento', 'anio', 'division', 'estado'],
            [
                ['45999888', '20459998887', 'LEG-99', 'SIN-888', 'Quispe', 'Mateo Nahuel', '2010-05-12', '3', '1', 'ACTIVO'],
                ['46888777', '27468887774', 'LEG-100', 'SIN-777', 'Cruz', 'Luciana', '2010-09-20', '3', '1', 'ACTIVO'],
            ],
            'Estudiantes'
        );
        $xlsxPath = tempnam(sys_get_temp_dir(), 'test_imp_').'.xlsx';
        file_put_contents($xlsxPath, $xlsxBinary);

        $upload = new UploadedFile(
            $xlsxPath,
            'padron_sinide.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $previewResp = $this->actingAs($this->director)->post(route('people.import.preview'), [
            'entity' => 'students',
            'default_course_id' => $this->course31->id,
            'file' => $upload,
        ]);

        $previewResp->assertOk()->assertSee('Quispe')->assertSee('Cruz');
        $token = session('mass_people_import.token');
        $this->assertNotEmpty($token);

        $this->actingAs($this->director)
            ->post(route('people.import.confirm'), ['token' => $token])
            ->assertRedirect(route('people.import.result'));

        @unlink($xlsxPath);

        $this->assertSame('Mateo Nahuel', $existing->fresh()->first_name);
        $this->assertSame('LEG-99', $existing->fresh()->file_number);
        $this->assertSame(1, Attendance::where('student_id', $existing->id)->count());
        $this->assertDatabaseHas('students', ['dni' => '46888777', 'last_name' => 'Cruz']);
    }

    /**
     * CASO DE PRUEBA 5: Toma de asistencia diaria contextualizada (Estudiantes y Docentes) sincronizada con el servidor.
     */
    public function test_case_5_contextualized_daily_attendance_for_students_and_teachers_with_server_clock_guards(): void
    {
        $s1 = Student::factory()->create(['course_id' => $this->course31->id, 'last_name' => 'Alvarez']);
        $s2 = Student::factory()->create(['course_id' => $this->course31->id, 'last_name' => 'Burgos']);
        $s3 = Student::factory()->create(['course_id' => $this->course31->id, 'last_name' => 'Cardozo']);

        // 1. Guardado exitoso en día hábil actual (Estudiantes)
        $this->actingAs($this->operator)
            ->post(route('attendance.daily.store'), [
                'date' => '2026-08-19',
                'shift_id' => $this->course31->shift_id,
                'scope_id' => $this->scopeClase->id,
                'course_id' => $this->course31->id,
                'activity_id' => $this->activityAula->id,
                'records' => [
                    ['student_id' => $s1->id, 'status' => 'presente', 'observation' => ''],
                    ['student_id' => $s2->id, 'status' => 'ausente', 'observation' => 'Avisó tutor por teléfono'],
                    ['student_id' => $s3->id, 'status' => 'tardanza', 'observation' => 'Ingresó 08:10 hs'],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('attendances', [
            'student_id' => $s2->id,
            'date' => '2026-08-19',
            'activity_type' => 'clase',
            'status' => 'ausente',
            'absence_value' => '1.00',
            'observation' => 'Avisó tutor por teléfono',
            'activity_id' => $this->activityAula->id,
        ]);

        $this->assertDatabaseHas('attendances', [
            'student_id' => $s3->id,
            'date' => '2026-08-19',
            'status' => 'tardanza',
            'absence_value' => '0.25',
        ]);

        // 2. Bloqueo de fechas futuras y feriados / fines de semana
        $this->actingAs($this->operator)
            ->post(route('attendance.daily.store'), [
                'date' => '2026-08-20', // Mañana (futura)
                'shift_id' => $this->course31->shift_id,
                'scope_id' => $this->scopeClase->id,
                'course_id' => $this->course31->id,
                'records' => [['student_id' => $s1->id, 'status' => 'presente']],
            ])
            ->assertSessionHasErrors('date');

        NonWorkingDay::create(['date' => '2026-08-17', 'reason' => 'Paso a la Inmortalidad del Gral. San Martín']);

        $this->actingAs($this->operator)
            ->post(route('attendance.daily.store'), [
                'date' => '2026-08-17', // Feriado cargado en calendario escolar
                'shift_id' => $this->course31->shift_id,
                'scope_id' => $this->scopeClase->id,
                'course_id' => $this->course31->id,
                'records' => [['student_id' => $s1->id, 'status' => 'presente']],
            ])
            ->assertSessionHasErrors('date');

        // 3. Toma diaria de Asistencia de Docentes
        $t1 = Teacher::factory()->create(['last_name' => 'Salazar', 'first_name' => 'Raúl', 'active' => true]);
        $t2 = Teacher::factory()->create(['last_name' => 'Vilte', 'first_name' => 'Claudia', 'active' => true]);

        // Bloquea justificada docente sin observación >= 10 caracteres
        $this->actingAs($this->director)
            ->post(route('attendance.teachers.store'), [
                'date' => '2026-08-19',
                'shift_id' => $this->shiftManana->id,
                'records' => [
                    ['teacher_id' => $t1->id, 'status' => 'justificada', 'observation' => 'corto'],
                ],
            ])
            ->assertSessionHasErrors('records.0.observation');

        // Guarda correctamente asistencia docente válida
        $this->actingAs($this->director)
            ->post(route('attendance.teachers.store'), [
                'date' => '2026-08-19',
                'shift_id' => $this->shiftManana->id,
                'activity_id' => $this->activityAula->id,
                'records' => [
                    ['teacher_id' => $t1->id, 'status' => 'presente', 'observation' => ''],
                    ['teacher_id' => $t2->id, 'status' => 'justificada', 'observation' => 'Licencia médica art. 14 presentada en secretaría'],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(2, TeacherAttendance::where('date', '2026-08-19')->count());
    }

    /**
     * CASO DE PRUEBA 6: Modal obligatorio de Inasistencia Justificada (IJ) en tiempo real y retroactiva + Auditoría del Director.
     */
    public function test_case_6_mandatory_justification_blocks_empty_ij_and_persists_certificate_and_reason_realtime_and_retroactive(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $stRealtime = Student::factory()->create(['course_id' => $this->course31->id, 'last_name' => 'Tolaba']);
        $stRetro = Student::factory()->create(['course_id' => $this->course31->id, 'last_name' => 'Choque']);

        // 1. Backend bloquea guardar 'justificada' vacía en la planilla diaria sin respaldo previo
        $this->actingAs($this->operator)
            ->post(route('attendance.daily.store'), [
                'date' => '2026-08-19',
                'shift_id' => $this->course31->shift_id,
                'scope_id' => $this->scopeClase->id,
                'course_id' => $this->course31->id,
                'records' => [
                    ['student_id' => $stRealtime->id, 'status' => 'justificada'],
                ],
            ])
            ->assertSessionHasErrors('records.0.status');

        // 2. Backend también bloquea 'justificada' directa en API de celda individual y en modal vacío
        $this->actingAs($this->operator)
            ->putJson(route('attendance.store', ['activity' => 'clase', 'course' => $this->course31->id]), [
                'student_id' => $stRealtime->id,
                'date' => '2026-08-19',
                'status' => 'justificada',
            ])
            ->assertStatus(422);

        $this->actingAs($this->operator)
            ->postJson(route('attendance.justify', ['activity' => 'clase', 'course' => $this->course31->id]), [
                'student_id' => $stRealtime->id,
                'date' => '2026-08-19',
                'create_absence_if_missing' => 1,
                'reason_text' => 'corto',
            ])
            ->assertStatus(422);

        // 3. Justificación en TIEMPO REAL desde el modal de toma diaria (con PDF + texto >= 10 caracteres)
        $pdfCert = UploadedFile::fake()->create('certificado_medico.pdf', 180, 'application/pdf');

        $this->actingAs($this->operator)
            ->postJson(route('attendance.justify', ['activity' => 'clase', 'course' => $this->course31->id]), [
                'student_id' => $stRealtime->id,
                'date' => '2026-08-19',
                'create_absence_if_missing' => 1,
                'reason_text' => 'Reposo médico por cuadro gripal (48 hs)',
                'certificate' => $pdfCert,
            ])
            ->assertOk();

        $realtimeAttendance = Attendance::where('student_id', $stRealtime->id)->where('date', '2026-08-19')->firstOrFail();
        $this->assertSame(AttendanceStatus::Justificada, $realtimeAttendance->status);
        $this->assertNotNull($realtimeAttendance->justification);
        Storage::disk('local')->assertExists($realtimeAttendance->justification->certificate_path);
        Storage::disk('public')->assertExists('certificados/2026/08/'.basename($realtimeAttendance->justification->certificate_path));

        // Ahora el guardado masivo diario SÍ permite conservar 'justificada' porque ya posee respaldo
        $this->actingAs($this->operator)
            ->post(route('attendance.daily.store'), [
                'date' => '2026-08-19',
                'shift_id' => $this->course31->shift_id,
                'scope_id' => $this->scopeClase->id,
                'course_id' => $this->course31->id,
                'records' => [
                    ['student_id' => $stRealtime->id, 'status' => 'justificada', 'observation' => 'Certificado archivado'],
                    ['student_id' => $stRetro->id, 'status' => 'ausente', 'observation' => ''],
                ],
            ])
            ->assertRedirect();

        // 4. Justificación RETROACTIVA sobre una falta 'ausente' existente con imagen PNG
        $pngCert = UploadedFile::fake()->create('constancia_hospital.png', 120, 'image/png');

        $this->actingAs($this->operator)
            ->postJson(route('attendance.justify', ['activity' => 'clase', 'course' => $this->course31->id]), [
                'student_id' => $stRetro->id,
                'date' => '2026-08-19',
                'certificate' => $pngCert,
            ])
            ->assertOk();

        $retroJustification = Justification::whereHas('attendance', fn ($q) => $q->where('student_id', $stRetro->id))->firstOrFail();
        $this->assertSame('1.00', (string) $retroJustification->attendance->absence_value);
        $this->assertTrue($retroJustification->certificateExists());

        // 5. Visor de Auditoría y Certificados exclusivo del Director
        $this->actingAs($this->operator)
            ->get(route('director.audit.index'))
            ->assertForbidden();

        $this->actingAs($this->director)
            ->get(route('director.audit.index', ['only_certificates' => 1]))
            ->assertOk()
            ->assertSee('TOLABA')
            ->assertSee('CHOQUE');

        $this->actingAs($this->director)
            ->get(route('director.justifications.certificate', $retroJustification))
            ->assertOk();
    }

    /**
     * CASO DE PRUEBA 7: Consultas, Ranking, Historial Individual, Planilla Mensual (01-31) y Exportaciones (.xlsx, .csv, .pdf).
     */
    public function test_case_7_historical_queries_ranking_student_history_monthly_sheet_and_xlsx_csv_pdf_exports(): void
    {
        $st1 = Student::factory()->create([
            'course_id' => $this->course31->id,
            'last_name' => 'Mamani',
            'first_name' => 'Fabricio',
            'dni' => '45111222',
        ]);
        $st2 = Student::factory()->create([
            'course_id' => $this->course31->id,
            'last_name' => 'Zerpa',
            'first_name' => 'Guadalupe',
            'dni' => '45333444',
        ]);

        // Crear registros variados en agosto 2026
        Attendance::create([
            'student_id' => $st1->id,
            'course_id' => $this->course31->id,
            'scope_id' => $this->scopeClase->id,
            'shift_id' => $this->course31->shift_id,
            'user_id' => $this->operator->id,
            'date' => '2026-08-18',
            'activity_type' => 'clase',
            'status' => AttendanceStatus::Presente,
        ]);

        $justifAtt = Attendance::create([
            'student_id' => $st1->id,
            'course_id' => $this->course31->id,
            'scope_id' => $this->scopeClase->id,
            'shift_id' => $this->course31->shift_id,
            'user_id' => $this->operator->id,
            'date' => '2026-08-19',
            'activity_type' => 'clase',
            'status' => AttendanceStatus::Justificada,
            'absence_value' => 1.00,
        ]);
        Justification::create([
            'attendance_id' => $justifAtt->id,
            'justified_by' => $this->operator->id,
            'original_status' => AttendanceStatus::Ausente,
            'reason_text' => 'Turno odontológico certificado',
            'justified_at' => now(),
        ]);

        Attendance::create([
            'student_id' => $st2->id,
            'course_id' => $this->course31->id,
            'scope_id' => $this->scopeClase->id,
            'shift_id' => $this->course31->shift_id,
            'user_id' => $this->operator->id,
            'date' => '2026-08-18',
            'activity_type' => 'clase',
            'status' => AttendanceStatus::Ausente,
            'observation' => 'Sin aviso',
        ]);
        Attendance::create([
            'student_id' => $st2->id,
            'course_id' => $this->course31->id,
            'scope_id' => $this->scopeClase->id,
            'shift_id' => $this->course31->shift_id,
            'user_id' => $this->operator->id,
            'date' => '2026-08-19',
            'activity_type' => 'clase',
            'status' => AttendanceStatus::Tardanza,
        ]);

        // 1. Vistas Web: Consulta Histórica, Ranking, Búsqueda/Ficha Individual y Planilla Mensual (P,A,T,IJ + SINIDE)
        $this->actingAs($this->director)
            ->get(route('reports.index', ['from' => '2026-08-01', 'to' => '2026-08-19']))
            ->assertOk()
            ->assertSee('MAMANI')
            ->assertSee('ZERPA');

        $this->actingAs($this->director)
            ->get(route('reports.ranking', ['from' => '2026-08-01', 'to' => '2026-08-19', 'course_id' => $this->course31->id]))
            ->assertOk()
            ->assertSeeInOrder(['ZERPA', 'MAMANI']); // Zerpa (1.25) precede a Mamani (1.00)

        $this->actingAs($this->director)
            ->get(route('reports.student-search', ['q' => 'Zerpa']))
            ->assertOk()
            ->assertSee('ZERPA');

        $this->actingAs($this->director)
            ->get(route('reports.student-history', $st1))
            ->assertOk()
            ->assertSee('MAMANI')
            ->assertSee('Turno odontológico certificado');

        // Planilla Mensual en siglas institucionales (P, A, T, IJ)
        $this->actingAs($this->director)
            ->get(route('reports.monthly-sheet', [
                'month' => '2026-08',
                'scope' => 'clase',
                'course_id' => $this->course31->id,
                'notation' => 'patij',
            ]))
            ->assertOk()
            ->assertSee('IJ')
            ->assertSee('31');

        // Planilla Mensual en siglas oficiales SINIDE (P, C, Q, M, J)
        $this->actingAs($this->director)
            ->get(route('reports.monthly-sheet', [
                'month' => '2026-08',
                'scope' => 'clase',
                'course_id' => $this->course31->id,
                'notation' => 'sinide',
            ]))
            ->assertOk()
            ->assertSee('SINIDE Oficial');

        // 2. Verificación de las 12 exportaciones binarias (.xlsx, .csv y .pdf)
        $exportEndpoints = [
            route('reports.index', ['from' => '2026-08-01', 'to' => '2026-08-19']),
            route('reports.ranking', ['from' => '2026-08-01', 'to' => '2026-08-19']),
            route('reports.student-history', ['student' => $st1->id, 'from' => '2026-08-01', 'to' => '2026-08-19']),
            route('reports.monthly-sheet', ['month' => '2026-08', 'scope' => 'clase', 'course_id' => $this->course31->id, 'notation' => 'patij']),
        ];

        foreach ($exportEndpoints as $baseUrl) {
            $sep = str_contains($baseUrl, '?') ? '&' : '?';

            // Excel (.xlsx -> contenedor OpenXML ZIP 'PK')
            $xlsx = $this->actingAs($this->director)->get($baseUrl.$sep.'export=xlsx');
            $xlsx->assertOk();
            $this->assertStringStartsWith('PK', $xlsx->getContent());

            // CSV (.csv -> con BOM UTF-8 para Excel)
            $csv = $this->actingAs($this->director)->get($baseUrl.$sep.'export=csv');
            $csv->assertOk();
            $this->assertStringStartsWith("\xEF\xBB\xBF", $csv->getContent());

            // PDF (.pdf -> encabezado binario '%PDF-')
            $pdf = $this->actingAs($this->director)->get($baseUrl.$sep.'export=pdf');
            $pdf->assertOk();
            $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        }
    }
}
