<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\Role;
use App\Http\Middleware\CheckCourseAssignment;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseAssignmentMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private Course $c51;
    private Course $c52;
    private User $operador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->c51 = Course::create(['year' => 5, 'division' => '1', 'shift' => 'manana']);
        $this->c52 = Course::create(['year' => 5, 'division' => '2', 'shift' => 'tarde']);

        $this->operador = User::factory()->create(['username' => 'preceptor1']);
        $this->operador->assignCourse($this->c51, ActivityType::Clase);
    }

    private function denied($response): void
    {
        $response->assertForbidden();
        $response->assertSee(CheckCourseAssignment::DENIED_MESSAGE, false);
    }

    public function test_operator_can_access_assigned_course_and_activity(): void
    {
        $this->actingAs($this->operador)
            ->get(route('attendance.show', ['activity' => 'clase', 'course' => $this->c51->id]))
            ->assertOk();
    }

    public function test_operator_is_denied_on_unassigned_course_by_url(): void
    {
        $this->denied($this->actingAs($this->operador)
            ->get(route('attendance.show', ['activity' => 'clase', 'course' => $this->c52->id])));
    }

    public function test_operator_is_denied_on_assigned_course_but_other_activity(): void
    {
        $this->denied($this->actingAs($this->operador)
            ->get(route('attendance.show', ['activity' => 'taller', 'course' => $this->c51->id])));
    }

    public function test_operator_is_denied_when_filtering_by_query_string(): void
    {
        $this->denied($this->actingAs($this->operador)
            ->get(route('attendance.index', ['activity' => 'clase', 'course_id' => $this->c52->id])));
    }

    public function test_query_filter_with_assigned_course_redirects_to_grid(): void
    {
        $this->actingAs($this->operador)
            ->get(route('attendance.index', ['activity' => 'clase', 'course_id' => $this->c51->id]))
            ->assertRedirect(route('attendance.show', ['activity' => 'clase', 'course' => $this->c51->id]));
    }

    public function test_nonexistent_course_is_denied_not_404_for_operator(): void
    {
        // No se revela qué cursos existen.
        $this->denied($this->actingAs($this->operador)
            ->get(route('attendance.show', ['activity' => 'clase', 'course' => 99999])));
    }

    public function test_invalid_activity_is_denied(): void
    {
        $this->denied($this->actingAs($this->operador)
            ->get('/asistencia/hackeo/'.$this->c51->id));
    }

    public function test_array_parameter_tampering_is_denied(): void
    {
        $this->denied($this->actingAs($this->operador)
            ->get('/asistencia?course_id[]='.$this->c51->id.'&course_id[]='.$this->c52->id));
    }

    public function test_json_requests_get_403_json_with_message(): void
    {
        $this->actingAs($this->operador)
            ->getJson(route('attendance.show', ['activity' => 'clase', 'course' => $this->c52->id]))
            ->assertForbidden()
            ->assertJson(['message' => CheckCourseAssignment::DENIED_MESSAGE]);
    }

    public function test_director_can_access_any_course(): void
    {
        $director = User::factory()->director()->create();

        $this->actingAs($director)
            ->get(route('attendance.show', ['activity' => 'taller', 'course' => $this->c52->id]))
            ->assertOk();
    }

    public function test_inactive_operator_is_blocked(): void
    {
        $this->operador->update(['active' => false]);

        $this->actingAs($this->operador)
            ->get(route('attendance.show', ['activity' => 'clase', 'course' => $this->c51->id]))
            ->assertStatus(401);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('attendance.show', ['activity' => 'clase', 'course' => $this->c51->id]))
            ->assertRedirect(route('login'));
    }

    public function test_operator_cannot_enter_director_panel(): void
    {
        $this->actingAs($this->operador)->get(route('director.dashboard'))->assertForbidden();
        $this->actingAs($this->operador)->get(route('director.users.index'))->assertForbidden();
        $this->actingAs($this->operador)->post(route('director.users.store'), ['name' => 'x'])->assertForbidden();
    }

    public function test_operator_home_lists_only_assigned_courses(): void
    {
        $this->actingAs($this->operador)
            ->get(route('operator.home'))
            ->assertOk()
            ->assertSee('5° 1°')
            ->assertDontSee('5° 2°');
    }

    public function test_login_by_username_and_inactive_cannot_login(): void
    {
        $this->operador->update(['password' => 'secreto123']);

        $this->post(route('login.store'), ['username' => 'preceptor1', 'password' => 'secreto123'])
            ->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($this->operador);

        auth()->logout();
        $this->operador->update(['active' => false]);

        $this->post(route('login.store'), ['username' => 'preceptor1', 'password' => 'secreto123'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_role_enum_is_cast(): void
    {
        $this->assertSame(Role::Operador, $this->operador->role);
    }
}
