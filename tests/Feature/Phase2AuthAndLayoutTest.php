<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckCourseAssignment;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Scope;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase2AuthAndLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_accepts_email_or_username_and_logs_audit_trail(): void
    {
        $director = User::factory()->director()->create([
            'username' => 'admin',
            'email' => 'admin@eet2jujuy.edu.ar',
            'password' => 'clave2026',
        ]);

        // Ingreso por email institucional
        $this->post(route('login.store'), [
            'username' => 'admin@eet2jujuy.edu.ar',
            'password' => 'clave2026',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($director);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $director->id,
            'action' => 'login',
        ]);

        // Cierre de sesión auditado
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $director->id,
            'action' => 'logout',
        ]);
    }

    public function test_active_user_middleware_logs_out_deactivated_account_on_home(): void
    {
        $user = User::factory()->create(['active' => false]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_docente_role_has_read_only_home_and_is_denied_course_attendance_control(): void
    {
        $course = Course::create(['year' => 3, 'division' => '1', 'shift' => 'manana']);
        $docente = User::factory()->docente()->create();

        $this->actingAs($docente)->get(route('home'))->assertRedirect(route('docente.home'));

        $this->actingAs($docente)->get(route('docente.home'))
            ->assertOk()
            ->assertSee('Panel de consulta docente')
            ->assertSee('EET N° 2 "Prof. Jesús Raúl Salazar" — Sistema de Asistencia', false);

        $this->actingAs($docente)->get(route('attendance.show', ['activity' => 'clase', 'course' => $course->id]))
            ->assertForbidden()
            ->assertSee(CheckCourseAssignment::DENIED_MESSAGE, false);

        $this->actingAs($docente)->get(route('director.dashboard'))->assertForbidden();
    }

    public function test_dynamic_custom_scope_works_through_middleware_grid_and_dashboard(): void
    {
        \Carbon\Carbon::setTestNow('2026-08-20 10:00:00');

        $scope = Scope::create(['name' => 'Salidas Educativas', 'slug' => 'salidas_educativas', 'active' => true]);
        $course = Course::create(['year' => 4, 'division' => '1', 'shift' => 'manana']);
        $student = Student::factory()->create(['course_id' => $course->id]);

        $operador = User::factory()->create();
        $operador->assignCourse($course, $scope);

        $this->actingAs($operador)
            ->get(route('attendance.show', ['activity' => 'salidas_educativas', 'course' => $course->id]))
            ->assertOk()
            ->assertSee('Salidas Educativas')
            ->assertSee('EET N° 2');

        $this->actingAs($operador)
            ->putJson(route('attendance.store', ['activity' => 'salidas_educativas', 'course' => $course->id]), [
                'student_id' => $student->id,
                'date' => '2026-08-19',
                'status' => 'presente',
            ])->assertOk();

        Teacher::factory()->count(2)->create();
        $director = User::factory()->director()->create();

        $this->actingAs($director)->get(route('director.dashboard'))
            ->assertOk()
            ->assertSee('Salidas Educativas')
            ->assertSee('Padrón institucional activo')
            ->assertSee('Asistencia registrada hoy');

        $this->actingAs($director)->get(route('director.audit-logs.index'))
            ->assertOk()
            ->assertSee('Bitácora de operaciones');
    }
}
