<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Justification;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DirectorPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $director;
    private Course $c51;
    private Course $c52;

    protected function setUp(): void
    {
        parent::setUp();
        $this->director = User::factory()->director()->create();
        $this->c51 = Course::create(['year' => 5, 'division' => '1', 'shift' => 'manana']);
        $this->c52 = Course::create(['year' => 5, 'division' => '2', 'shift' => 'tarde']);
    }

    public function test_dashboard_renders_with_stats(): void
    {
        $op = User::factory()->create();
        $student = Student::factory()->create(['course_id' => $this->c51->id]);
        foreach (['presente', 'ausente', 'tardanza'] as $i => $status) {
            Attendance::create([
                'student_id' => $student->id, 'course_id' => $this->c51->id, 'user_id' => $op->id,
                'date' => now()->startOfMonth()->addDays($i), 'activity_type' => 'clase', 'status' => $status,
                'observation' => $status === 'presente' ? 'Se retiró con dolor de cabeza' : null,
            ]);
        }

        $this->actingAs($this->director)->get(route('director.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard global')
            ->assertSee('58.3%')   // Ponderado SINIDE: (3 - (1.00 ausente + 0.25 tardanza)) / 3 = 58.3%
            ->assertSee('Se retiró con dolor de cabeza');
    }

    public function test_director_creates_operator_with_assignments(): void
    {
        $this->actingAs($this->director)->post(route('director.users.store'), [
            'name' => 'Ana Profe', 'username' => 'ana.profe', 'password' => 'clave12345',
            'assignments' => ['educacion_fisica' => [$this->c51->id, $this->c52->id], 'clase' => [$this->c51->id]],
        ])->assertRedirect(route('director.users.index'))->assertSessionHas('credentials');

        $user = User::where('username', 'ana.profe')->firstOrFail();
        $this->assertSame('operador', $user->role->value);   // nunca se crean directores desde el panel
        $this->assertNotSame('clave12345', $user->password);  // hasheada
        $this->assertSame(3, $user->assignments()->count());
        $this->assertTrue($user->canManageAttendance($this->c52, ActivityType::EducacionFisica));
        $this->assertFalse($user->canManageAttendance($this->c52, ActivityType::Clase));
    }

    public function test_role_cannot_be_forced_through_request(): void
    {
        $this->actingAs($this->director)->post(route('director.users.store'), [
            'name' => 'Intruso', 'username' => 'intruso', 'password' => 'clave12345', 'role' => 'director',
        ]);

        $this->assertSame('operador', User::where('username', 'intruso')->firstOrFail()->role->value);
    }

    public function test_update_revokes_removed_assignments_and_keeps_password_when_empty(): void
    {
        $user = User::factory()->create(['username' => 'taller1', 'password' => 'original123']);
        $user->assignCourse($this->c51, ActivityType::Taller);
        $user->assignCourse($this->c52, ActivityType::Taller);
        $oldHash = $user->password;

        $this->actingAs($this->director)->put(route('director.users.update', $user), [
            'name' => $user->name, 'username' => 'taller1', 'password' => '', 'active' => 1,
            'assignments' => ['taller' => [$this->c51->id]],
        ])->assertRedirect(route('director.users.index'));

        $user->refresh();
        $this->assertSame($oldHash, $user->password);
        $this->assertTrue($user->canManageAttendance($this->c51, ActivityType::Taller));
        $this->assertFalse($user->canManageAttendance($this->c52, ActivityType::Taller));
    }

    public function test_validation_rejects_bad_assignment_data(): void
    {
        $this->actingAs($this->director)->post(route('director.users.store'), [
            'name' => 'X', 'username' => 'x', 'password' => 'corta',
            'assignments' => ['inventada' => [$this->c51->id], 'clase' => [9999]],
        ])->assertSessionHasErrors(['username', 'password', 'assignments', 'assignments.clase.0']);
    }

    public function test_user_with_attendances_cannot_be_deleted_only_deactivated(): void
    {
        $user = User::factory()->create();
        $student = Student::factory()->create(['course_id' => $this->c51->id]);
        Attendance::create(['student_id' => $student->id, 'course_id' => $this->c51->id, 'user_id' => $user->id,
            'date' => now(), 'activity_type' => 'clase', 'status' => 'presente']);

        $this->actingAs($this->director)->delete(route('director.users.destroy', $user))->assertSessionHasErrors('delete');
        $this->assertModelExists($user);

        $this->actingAs($this->director)->patch(route('director.users.toggle', $user));
        $this->assertFalse($user->fresh()->active);
    }

    public function test_director_account_is_not_manageable_from_user_crud(): void
    {
        $this->actingAs($this->director)->get(route('director.users.edit', $this->director))->assertNotFound();
        $this->actingAs($this->director)->delete(route('director.users.destroy', $this->director))->assertNotFound();
    }

    public function test_audit_shows_justification_data_and_certificate_is_private_to_director(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('certificados/c.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

        $op = User::factory()->create();
        $student = Student::factory()->create(['course_id' => $this->c51->id, 'last_name' => 'Zapata']);
        $att = Attendance::create(['student_id' => $student->id, 'course_id' => $this->c51->id, 'user_id' => $op->id,
            'date' => now(), 'activity_type' => 'clase', 'status' => AttendanceStatus::Justificada->value]);
        $just = Justification::create(['attendance_id' => $att->id, 'justified_by' => $op->id,
            'reason_text' => 'Viaje imprevisto por cuestiones familiares', 'certificate_path' => 'certificados/c.png', 'justified_at' => now()]);

        $this->actingAs($this->director)->get(route('director.audit.index'))
            ->assertOk()->assertSee('ZAPATA')->assertSee('Viaje imprevisto por cuestiones familiares', false);

        $this->actingAs($this->director)->get(route('director.justifications.certificate', $just))
            ->assertOk()->assertHeader('Content-Type', 'image/png');

        $this->actingAs($op)->get(route('director.justifications.certificate', $just))->assertForbidden();
        auth()->logout();
        $this->get(route('director.justifications.certificate', $just))->assertRedirect(route('login'));
    }

    public function test_audit_filters_by_status_and_student_search(): void
    {
        $op = User::factory()->create();
        $a = Student::factory()->create(['course_id' => $this->c51->id, 'last_name' => 'Alvarez']);
        $b = Student::factory()->create(['course_id' => $this->c51->id, 'last_name' => 'Benitez']);
        foreach ([[$a, 'ausente'], [$b, 'presente']] as [$s, $st]) {
            Attendance::create(['student_id' => $s->id, 'course_id' => $this->c51->id, 'user_id' => $op->id,
                'date' => now(), 'activity_type' => 'clase', 'status' => $st]);
        }

        $this->actingAs($this->director)->get(route('director.audit.index', ['status' => 'ausente']))
            ->assertSee('ALVAREZ')->assertDontSee('BENITEZ');

        $this->actingAs($this->director)->get(route('director.audit.index', ['q' => 'Benitez']))
            ->assertSee('BENITEZ')->assertDontSee('ALVAREZ');
    }
}
