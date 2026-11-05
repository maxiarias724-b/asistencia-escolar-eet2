<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Scope;
use App\Models\Shift;
use App\Models\Specialty;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase1InstitutionalSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_auditable_trait_records_created_updated_and_deleted_while_hiding_passwords(): void
    {
        $director = User::factory()->director()->create([
            'first_name' => 'Raúl',
            'last_name' => 'Salazar',
            'username' => 'director.eet2',
            'password' => 'secreto123',
        ]);

        $this->actingAs($director);

        $teacher = Teacher::create([
            'dni' => '29111222',
            'file_number' => 'LEG-D-900',
            'last_name' => 'Condorí',
            'first_name' => 'Héctor',
            'specialty_area' => 'Construcciones',
            'active' => true,
        ]);

        $teacher->update(['specialty_area' => 'Electrónica']);
        $teacher->delete();

        // Verifica que nunca se guardó el hash de password en audit_logs
        $userLog = AuditLog::where('auditable_type', User::class)
            ->where('auditable_id', $director->id)
            ->where('action', 'created')
            ->firstOrFail();

        $this->assertArrayNotHasKey('password', $userLog->new_values);
        $this->assertArrayNotHasKey('remember_token', $userLog->new_values);

        // Verifica ciclo completo created / updated / deleted con user_id autenticado
        $teacherLogs = AuditLog::where('auditable_type', Teacher::class)
            ->where('auditable_id', $teacher->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $teacherLogs);
        $this->assertSame(['created', 'updated', 'deleted'], $teacherLogs->pluck('action')->all());
        $this->assertSame($director->id, $teacherLogs[1]->user_id);
        $this->assertSame('Construcciones', $teacherLogs[1]->old_values['specialty_area']);
        $this->assertSame('Electrónica', $teacherLogs[1]->new_values['specialty_area']);
    }

    public function test_configurable_academic_entities_and_dynamic_scopes_work_without_hardcoding(): void
    {
        $specialty = Specialty::create(['name' => 'Electrónica', 'code' => 'ELEC', 'active' => true]);
        $shift = Shift::create(['name' => 'Noche', 'slug' => 'noche', 'active' => true]);
        $customScope = Scope::create(['name' => 'Salidas Educativas', 'slug' => 'salidas_educativas', 'active' => true]);
        $activity = Activity::create(['scope_id' => $customScope->id, 'name' => 'Visita Técnica a Planta', 'active' => true]);

        $course = Course::create([
            'year' => 6,
            'division' => '2',
            'specialty_id' => $specialty->id,
            'shift_id' => $shift->id,
        ]);

        $this->assertSame('segundo_ciclo', $course->cycle->value);
        $this->assertSame('noche', $course->shift->value);
        $this->assertSame('Noche', $course->shift->label());
        $this->assertSame('6° 2° - Noche', $course->full_name);

        $operador = User::factory()->create();
        $operador->assignCourse($course, $customScope, $shift);

        $this->assertTrue($operador->canManageAttendance($course, 'salidas_educativas'));
        $this->assertTrue($operador->canManageAttendance($course, $customScope));
        $this->assertFalse($operador->canManageAttendance($course, 'clase'));

        // Un usuario con rol Docente no puede cargar asistencia de cursos aunque esté activo
        $docente = User::factory()->docente()->create();
        $this->assertSame(Role::Docente, $docente->role);
        $this->assertFalse($docente->canManageAttendance($course, $customScope));

        $student = Student::factory()->create(['course_id' => $course->id]);
        $att = StudentAttendance::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'scope_id' => $customScope->id,
            'activity_id' => $activity->id,
            'recorded_by' => $operador->id,
            'date' => '2026-08-18',
            'status' => AttendanceStatus::Tardanza->value,
        ]);

        $this->assertSame('salidas_educativas', $att->activity_type->value);
        $this->assertSame('Salidas Educativas', $att->activity_type->label());
        $this->assertSame($shift->id, $att->shift_id);
        $this->assertSame($operador->id, $att->recorded_by);
        $this->assertEquals(0.25, $att->absence_value);
    }

    public function test_teacher_attendance_model_and_relations(): void
    {
        $user = User::factory()->director()->create();
        $shift = Shift::create(['name' => 'Mañana', 'slug' => 'manana']);
        $scope = Scope::create(['name' => 'Taller', 'slug' => 'taller']);
        $activity = Activity::create(['scope_id' => $scope->id, 'name' => 'Taller de Construcciones']);
        $teacher = Teacher::factory()->create();

        $ta = TeacherAttendance::create([
            'teacher_id' => $teacher->id,
            'shift_id' => $shift->id,
            'activity_id' => $activity->id,
            'recorded_by' => $user->id,
            'date' => '2026-08-19',
            'status' => AttendanceStatus::Ausente->value,
            'observation' => 'Licencia médica artículo 14',
        ]);

        $this->assertEquals(1.00, $ta->absence_value);
        $this->assertSame($teacher->id, $ta->teacher->id);
        $this->assertSame('Mañana', $ta->shift->name);
        $this->assertSame($user->id, $ta->recorder->id);
    }

    public function test_database_seeder_populates_eet2_institutional_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', ['username' => 'admin', 'email' => 'admin@eet2jujuy.edu.ar', 'role' => 'director']);
        $this->assertDatabaseHas('users', ['username' => 'preceptor_clases', 'role' => 'operador']);
        $this->assertDatabaseHas('users', ['username' => 'profe_edfisica', 'role' => 'operador']);
        $this->assertDatabaseHas('users', ['username' => 'profe_taller', 'role' => 'operador']);
        $this->assertDatabaseHas('users', ['username' => 'docente_consulta', 'role' => 'docente']);

        $this->assertSame(3, Specialty::count());
        $this->assertSame(3, Shift::count());
        $this->assertSame(3, Scope::count());
        $this->assertGreaterThanOrEqual(8, Activity::count());
        $this->assertSame(14, Course::count());
        $this->assertGreaterThanOrEqual(30, Student::count());
        $this->assertGreaterThanOrEqual(5, Teacher::count());
        $this->assertGreaterThan(0, AuditLog::count());
    }
}
