<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\Cycle;
use App\Enums\Role;
use App\Enums\Shift as ShiftEnum;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Scope;
use App\Models\Shift;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\User;
use App\Services\InstitutionalStateSyncService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CrossComputerSyncAndRecorderPreservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 11, 11, 9, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_unchanged_rows_preserve_original_recorder_for_students_and_teachers(): void
    {
        $shift = Shift::create(['name' => 'Mañana', 'slug' => 'manana', 'active' => true]);
        $scope = Scope::create(['name' => 'Clases', 'slug' => 'clase', 'active' => true]);
        $activity = Activity::create(['scope_id' => $scope->id, 'name' => 'Clase Áulica General', 'active' => true]);

        $course = Course::create([
            'year' => 3,
            'division' => '1',
            'cycle' => Cycle::SegundoCiclo,
            'shift' => ShiftEnum::Manana,
            'shift_id' => $shift->id,
            'active' => true,
        ]);

        $director = User::factory()->create([
            'name' => 'Dirección General EET N° 2',
            'username' => 'admin',
            'role' => Role::Director,
            'active' => true,
        ]);

        $maximo = User::factory()->create([
            'name' => 'Maximo',
            'first_name' => 'Maximo Alejandro',
            'last_name' => 'Arias',
            'username' => 'usuario',
            'role' => Role::Operador,
            'active' => true,
        ]);
        $maximo->assignCourse($course, $scope, $shift->id);

        $student1 = Student::create([
            'dni' => '45100001',
            'last_name' => 'Acosta',
            'first_name' => 'Juan',
            'course_id' => $course->id,
            'status' => 'activo',
            'active' => true,
        ]);

        $student2 = Student::create([
            'dni' => '45100002',
            'last_name' => 'Benítez',
            'first_name' => 'Lucía',
            'course_id' => $course->id,
            'status' => 'activo',
            'active' => true,
        ]);

        // 1. Maximo registra la asistencia diaria de los estudiantes
        $this->actingAs($maximo)->post(route('attendance.daily.store'), [
            'date' => '2026-11-11',
            'shift_id' => $shift->id,
            'scope_id' => $scope->id,
            'course_id' => $course->id,
            'activity_id' => $activity->id,
            'records' => [
                ['student_id' => $student1->id, 'status' => AttendanceStatus::Presente->value, 'observation' => null],
                ['student_id' => $student2->id, 'status' => AttendanceStatus::Presente->value, 'observation' => 'Puntual'],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('attendances', [
            'student_id' => $student1->id,
            'user_id' => $maximo->id,
            'status' => AttendanceStatus::Presente->value,
        ]);
        $this->assertDatabaseHas('attendances', [
            'student_id' => $student2->id,
            'user_id' => $maximo->id,
            'status' => AttendanceStatus::Presente->value,
        ]);

        // 2. El Director abre la planilla y modifica SOLO a student1 (a Tardanza), dejando intacto a student2
        $this->actingAs($director)->post(route('attendance.daily.store'), [
            'date' => '2026-11-11',
            'shift_id' => $shift->id,
            'scope_id' => $scope->id,
            'course_id' => $course->id,
            'activity_id' => $activity->id,
            'records' => [
                ['student_id' => $student1->id, 'status' => AttendanceStatus::Tardanza->value, 'observation' => null],
                ['student_id' => $student2->id, 'status' => AttendanceStatus::Presente->value, 'observation' => 'Puntual'],
            ],
        ])->assertRedirect();

        // student1 cambió -> su registrador pasa al Director; student2 NO cambió -> conserva a Maximo
        $this->assertDatabaseHas('attendances', [
            'student_id' => $student1->id,
            'user_id' => $director->id,
            'status' => AttendanceStatus::Tardanza->value,
        ]);
        $this->assertDatabaseHas('attendances', [
            'student_id' => $student2->id,
            'user_id' => $maximo->id,
            'status' => AttendanceStatus::Presente->value,
        ]);

        // 3. Mismo flujo para Asistencia Docente: Maximo toma asistencia a 2 docentes
        $teacher1 = Teacher::create([
            'dni' => '25000001',
            'last_name' => 'Alancay',
            'first_name' => 'Silvina',
            'active' => true,
        ]);
        $teacher2 = Teacher::create([
            'dni' => '25000002',
            'last_name' => 'Arias',
            'first_name' => 'Maximo Alejandro',
            'active' => true,
        ]);

        $this->actingAs($maximo)->post(route('attendance.teachers.store'), [
            'date' => '2026-11-11',
            'shift_id' => $shift->id,
            'records' => [
                ['teacher_id' => $teacher1->id, 'status' => AttendanceStatus::Presente->value, 'observation' => null],
                ['teacher_id' => $teacher2->id, 'status' => AttendanceStatus::Presente->value, 'observation' => 'muy crack'],
            ],
        ])->assertRedirect();

        // El Director modifica solo a teacher1 y deja intacto a teacher2
        $this->actingAs($director)->post(route('attendance.teachers.store'), [
            'date' => '2026-11-11',
            'shift_id' => $shift->id,
            'records' => [
                ['teacher_id' => $teacher1->id, 'status' => AttendanceStatus::Tardanza->value, 'observation' => null],
                ['teacher_id' => $teacher2->id, 'status' => AttendanceStatus::Presente->value, 'observation' => 'muy crack'],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('teacher_attendances', [
            'teacher_id' => $teacher1->id,
            'recorded_by' => $director->id,
            'status' => AttendanceStatus::Tardanza->value,
        ]);
        $this->assertDatabaseHas('teacher_attendances', [
            'teacher_id' => $teacher2->id,
            'recorded_by' => $maximo->id,
            'status' => AttendanceStatus::Presente->value,
            'observation' => 'muy crack',
        ]);
    }

    public function test_institutional_state_sync_service_exports_and_imports_users_assignments_and_attendances(): void
    {
        $shift = Shift::create(['name' => 'Mañana', 'slug' => 'manana', 'active' => true]);
        $scope = Scope::create(['name' => 'Clases', 'slug' => 'clase', 'active' => true]);
        $course = Course::create([
            'year' => 3,
            'division' => '1',
            'cycle' => Cycle::SegundoCiclo,
            'shift' => ShiftEnum::Manana,
            'shift_id' => $shift->id,
            'active' => true,
        ]);

        $maximo = User::create([
            'name' => 'Maximo',
            'first_name' => 'Maximo Alejandro',
            'last_name' => 'Arias',
            'username' => 'usuario',
            'password' => 'secreto123',
            'role' => Role::Operador,
            'active' => true,
        ]);
        $maximo->assignCourse($course, $scope, $shift->id);

        $student = Student::create([
            'dni' => '45999888',
            'last_name' => 'Quispe',
            'first_name' => 'Mateo',
            'course_id' => $course->id,
            'status' => 'activo',
            'active' => true,
        ]);

        Attendance::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'scope_id' => $scope->id,
            'shift_id' => $shift->id,
            'user_id' => $maximo->id,
            'date' => '2026-11-12',
            'activity_type' => $scope->slug,
            'status' => AttendanceStatus::Presente,
        ]);

        $syncService = app(InstitutionalStateSyncService::class);
        $tempPath = storage_path('framework/testing/test_institutional_state.json');

        try {
            $this->assertTrue($syncService->exportSnapshot($tempPath));
            $this->assertFileExists($tempPath);

            // Simulamos otra computadora donde Maximo no existe y la asistencia fue tomada por otro usuario
            Attendance::query()->delete();
            $maximo->assignments()->delete();
            $maximo->delete();

            $this->assertDatabaseMissing('users', ['username' => 'usuario']);

            // Al importar el snapshot institucional, Maximo, sus cursos asignados y su asistencia quedan restaurados
            $this->assertTrue($syncService->importSnapshot(true, $tempPath));

            $restoredMaximo = User::where('username', 'usuario')->first();
            $this->assertNotNull($restoredMaximo);
            $this->assertSame('Maximo', $restoredMaximo->name);
            $this->assertTrue(Hash::check('secreto123', $restoredMaximo->password));
            $this->assertTrue($restoredMaximo->canManageAttendance($course, $scope));

            $restoredAttendance = Attendance::where('student_id', $student->id)->first();
            $this->assertNotNull($restoredAttendance);
            $this->assertSame($restoredMaximo->id, $restoredAttendance->user_id);
        } finally {
            if (File::exists($tempPath)) {
                File::delete($tempPath);
            }
        }
    }
}
