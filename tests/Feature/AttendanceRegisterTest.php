<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Justification;
use App\Models\NonWorkingDay;
use App\Models\Student;
use App\Models\User;
use App\Services\MonthlyRegister;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttendanceRegisterTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;
    private Course $other;
    private User $operador;
    private Student $ana;
    private Student $beto;
    private Student $pase;

    protected function setUp(): void
    {
        parent::setUp();

        // Jueves 20 de agosto de 2026 (hay días hábiles pasados y futuros en el mes).
        Carbon::setTestNow('2026-08-20 10:00:00');
        Storage::fake('local');

        $this->course = Course::create(['year' => 3, 'division' => '1', 'shift' => 'manana']);
        $this->other = Course::create(['year' => 5, 'division' => '2', 'shift' => 'tarde']);
        $this->operador = User::factory()->create();
        $this->operador->assignCourse($this->course, ActivityType::Clase);

        $this->ana = Student::factory()->create(['course_id' => $this->course->id, 'last_name' => 'Aranibar', 'first_name' => 'Ian']);
        $this->beto = Student::factory()->create(['course_id' => $this->course->id, 'last_name' => 'Baina', 'first_name' => 'Agustin']);
        $this->pase = Student::factory()->create(['course_id' => $this->course->id, 'last_name' => 'Zeta', 'first_name' => 'Pase', 'status' => 'pase']);
    }

    private function url(string $suffix = '', ?Course $course = null, string $activity = 'clase'): string
    {
        return "/asistencia/{$activity}/".($course ?? $this->course)->id.$suffix;
    }

    private function mark(Student $s, string $date, string $status, ?string $obs = null): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->operador)->putJson($this->url('/registro'), [
            'student_id' => $s->id, 'date' => $date, 'status' => $status, 'observation' => $obs,
        ]);
    }

    private function attendance(Student $s, string $date): ?Attendance
    {
        return Attendance::where('student_id', $s->id)->where('date', $date)->first();
    }

    /* ------------------------------ Grilla ------------------------------ */

    public function test_grid_renders_for_assigned_operator_using_server_month(): void
    {
        $this->actingAs($this->operador)->get($this->url())
            ->assertOk()
            ->assertSee('Registro de asistencia mensual')
            ->assertSee('2026-08', false)
            ->assertSee('Aranibar')
            ->assertSee('Agosto de 2026', false);
    }

    public function test_month_navigation_param(): void
    {
        $this->actingAs($this->operador)->get($this->url().'?month=2026-02')
            ->assertOk()->assertSee('2026-02-28', false);

        $this->actingAs($this->operador)->get($this->url().'?month=banana')->assertSessionHasErrors('month');
    }

    public function test_grid_lists_only_students_and_hides_other_courses(): void
    {
        $outsider = Student::factory()->create(['course_id' => $this->other->id, 'last_name' => 'Ajeno']);

        $this->actingAs($this->operador)->get($this->url())->assertOk()->assertDontSee('Ajeno');
        $this->actingAs($this->operador)->get($this->url('', $this->other))->assertForbidden();
    }

    /* ------------------------- Estados y fracciones ------------------------- */

    public function test_status_values_follow_sinide_fractions(): void
    {
        $this->mark($this->ana, '2026-08-03', 'ausente')->assertOk();
        $this->mark($this->ana, '2026-08-04', 'media_falta')->assertOk();
        $this->mark($this->ana, '2026-08-05', 'tardanza')->assertOk();
        $this->mark($this->ana, '2026-08-06', 'presente')->assertOk();

        $this->assertEquals(1.0, $this->attendance($this->ana, '2026-08-03')->absence_value);
        $this->assertEquals(0.5, $this->attendance($this->ana, '2026-08-04')->absence_value);
        $this->assertEquals(0.25, $this->attendance($this->ana, '2026-08-05')->absence_value);
        $this->assertEquals(0.0, $this->attendance($this->ana, '2026-08-06')->absence_value);
    }

    public function test_update_is_idempotent_and_modifies_retroactively(): void
    {
        $this->mark($this->ana, '2026-08-03', 'ausente', 'Tuvo un accidente')->assertOk();
        $this->mark($this->ana, '2026-08-03', 'presente', 'Se retiró con dolor de cabeza')->assertOk();

        $this->assertSame(1, Attendance::where('student_id', $this->ana->id)->count());
        $a = $this->attendance($this->ana, '2026-08-03');
        $this->assertSame('presente', $a->status->value);
        $this->assertSame('Se retiró con dolor de cabeza', $a->observation);
        $this->assertSame($this->operador->id, $a->user_id);
    }

    public function test_observation_is_optional_in_any_status(): void
    {
        foreach (['presente', 'ausente', 'tardanza', 'media_falta'] as $i => $status) {
            $day = Carbon::parse('2026-08-03')->addDays($i)->toDateString();
            $this->mark($this->ana, $day, $status)->assertOk();
            $this->assertNull($this->attendance($this->ana, $day)->observation);
        }
    }

    public function test_cannot_take_future_weekend_or_non_working_days(): void
    {
        $this->mark($this->ana, '2026-08-21', 'presente')->assertStatus(422)->assertJsonValidationErrors('date'); // futuro
        $this->mark($this->ana, '2026-08-15', 'presente')->assertStatus(422)->assertJsonValidationErrors('date'); // sábado

        NonWorkingDay::create(['date' => '2026-08-17', 'reason' => 'Paso a la Inmortalidad del Gral. San Martín']);
        $this->mark($this->ana, '2026-08-17', 'presente')->assertStatus(422)->assertJsonValidationErrors('date');
        $this->assertSame(0, Attendance::count());
    }

    public function test_cannot_mark_inactive_or_foreign_students(): void
    {
        $this->mark($this->pase, '2026-08-03', 'presente')->assertStatus(422)->assertJsonValidationErrors('student_id');

        $foreign = Student::factory()->create(['course_id' => $this->other->id]);
        $this->mark($foreign, '2026-08-03', 'presente')->assertStatus(422)->assertJsonValidationErrors('student_id');
        $this->assertSame(0, Attendance::count());
    }

    public function test_invalid_status_is_rejected(): void
    {
        $this->mark($this->ana, '2026-08-03', 'volando')->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_justified_status_cannot_be_set_without_the_justification_modal(): void
    {
        $this->mark($this->ana, '2026-08-03', 'ausente')->assertOk();
        $this->mark($this->ana, '2026-08-03', 'justificada')->assertStatus(422)->assertJsonValidationErrors('status');
        $this->assertSame('ausente', $this->attendance($this->ana, '2026-08-03')->status->value);
    }

    public function test_operator_without_assignment_cannot_write(): void
    {
        $this->actingAs($this->operador)->putJson($this->url('/registro', $this->other), [
            'student_id' => 1, 'date' => '2026-08-03', 'status' => 'presente',
        ])->assertForbidden()->assertJson(['message' => \App\Http\Middleware\CheckCourseAssignment::DENIED_MESSAGE]);

        $this->actingAs($this->operador)->putJson($this->url('/registro', null, 'taller'), [
            'student_id' => $this->ana->id, 'date' => '2026-08-03', 'status' => 'presente',
        ])->assertForbidden();
    }

    public function test_remove_record(): void
    {
        $this->mark($this->ana, '2026-08-03', 'ausente')->assertOk();

        $this->actingAs($this->operador)->deleteJson($this->url('/registro'), ['student_id' => $this->ana->id, 'date' => '2026-08-03'])->assertOk();
        $this->assertNull($this->attendance($this->ana, '2026-08-03'));
    }

    public function test_fill_day_marks_present_only_active_students_without_record(): void
    {
        $this->mark($this->ana, '2026-08-03', 'ausente')->assertOk();

        $this->actingAs($this->operador)->postJson($this->url('/registro/dia'), ['date' => '2026-08-03'])->assertOk();

        $this->assertSame('ausente', $this->attendance($this->ana, '2026-08-03')->status->value);   // no se pisa
        $this->assertSame('presente', $this->attendance($this->beto, '2026-08-03')->status->value);
        $this->assertNull($this->attendance($this->pase, '2026-08-03'));                              // no activo

        $this->actingAs($this->operador)->postJson($this->url('/registro/dia'), ['date' => '2026-08-21'])->assertStatus(422);
    }

    /* ------------------------ Cálculos del registro ------------------------ */

    public function test_monthly_totals_match_the_sinide_report(): void
    {
        // 21 días de semana en agosto de 2026; 4 sin actividad => 17 días hábiles (como el ejemplo de SINIDE).
        NonWorkingDay::create(['date' => '2026-08-17', 'reason' => 'Feriado']);
        NonWorkingDay::create(['date' => '2026-08-18', 'reason' => 'Receso']);
        NonWorkingDay::create(['date' => '2026-08-19', 'reason' => 'Receso']);
        NonWorkingDay::create(['date' => '2026-08-20', 'reason' => 'Receso']);
        Carbon::setTestNow('2026-08-31 18:00:00');

        // Fila "Baina": 4 M (2.00) + 1 Q (0.25) + 1 C (1.00) = 3.25  =>  ASIST. 13.75
        foreach (['2026-08-03', '2026-08-04', '2026-08-05', '2026-08-06'] as $d) {
            $this->mark($this->beto, $d, 'media_falta')->assertOk();
        }
        $this->mark($this->beto, '2026-08-07', 'tardanza')->assertOk();
        $this->mark($this->beto, '2026-08-10', 'ausente', 'Faltó por un imprevisto')->assertOk();
        $this->mark($this->ana, '2026-08-03', 'media_falta')->assertOk();   // fila "Aranibar": 0.50

        $register = app(MonthlyRegister::class)->build($this->course, ActivityType::Clase, Carbon::parse('2026-08-01'));
        $rows = collect($register['rows'])->keyBy('name');

        $this->assertSame(17, $register['working_days']);
        $this->assertSame(17, $register['base_days']);

        $baina = $rows['Baina, Agustin'];
        $this->assertEquals(3.25, $baina['inj']);
        $this->assertEquals(0.0, $baina['j']);
        $this->assertEquals(3.25, $baina['total']);
        $this->assertEquals(13.75, $baina['asist']);
        $this->assertStringContainsString('10: Faltó por un imprevisto', $baina['observations']);

        $this->assertEquals(16.5, $rows['Aranibar, Ian']['asist']);

        // Pie: total asistencias, inasistencias, media y % (2 alumnos activos).
        $this->assertEquals(30.25, $register['footer']['total_asist']);
        $this->assertEquals(3.75, $register['footer']['total_inasist']);
        $this->assertEquals(round(30.25 / 17, 2), $register['footer']['media']);
        $this->assertEquals(round(30.25 / (2 * 17) * 100, 2), $register['footer']['percent']);
        $this->assertSame(2, $register['footer']['enrolled']);
    }

    public function test_previous_months_accumulate_from_march(): void
    {
        $this->mark($this->ana, '2026-07-01', 'ausente')->assertOk();      // 1.00
        Carbon::setTestNow('2026-08-31 18:00:00');
        Attendance::create(['student_id' => $this->ana->id, 'course_id' => $this->course->id, 'user_id' => $this->operador->id,
            'date' => '2026-05-04', 'activity_type' => 'clase', 'status' => 'media_falta']);   // 0.50
        Attendance::create(['student_id' => $this->ana->id, 'course_id' => $this->course->id, 'user_id' => $this->operador->id,
            'date' => '2026-02-02', 'activity_type' => 'clase', 'status' => 'ausente']);        // ciclo anterior: no suma

        $register = app(MonthlyRegister::class)->build($this->course, ActivityType::Clase, Carbon::parse('2026-08-01'));

        $this->assertEquals(1.5, collect($register['rows'])->firstWhere('name', 'Aranibar, Ian')['anterior']);
    }

    public function test_current_month_only_counts_elapsed_days(): void
    {
        // Hoy es jueves 20/08: transcurridos 1/8 a 20/8 => 14 hábiles de 21.
        $register = app(MonthlyRegister::class)->build($this->course, ActivityType::Clase, now());

        $this->assertSame(21, $register['working_days']);
        $this->assertSame(14, $register['base_days']);
    }

    public function test_activities_are_independent(): void
    {
        $this->operador->assignCourse($this->course, ActivityType::EducacionFisica);
        $this->mark($this->ana, '2026-08-03', 'ausente')->assertOk();

        $this->actingAs($this->operador)->putJson($this->url('/registro', null, 'educacion_fisica'), [
            'student_id' => $this->ana->id, 'date' => '2026-08-03', 'status' => 'presente',
        ])->assertOk();

        $this->assertSame(2, Attendance::where('student_id', $this->ana->id)->count());
        $this->assertSame('ausente', Attendance::where('activity_type', 'clase')->first()->status->value);
    }

    /* ------------------------ Inasistencia justificada ------------------------ */

    private function justify(Student $s, string $date, array $data = [], string $activity = 'clase'): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->operador)->postJson(
            $this->url('/justificar', null, $activity),
            ['student_id' => $s->id, 'date' => $date] + $data
        );
    }

    /** Genera PNG o JPEG reales en memoria sin depender de ext-gd. */
    private function fakeImage(string $name): UploadedFile
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $bytes = $ext === 'png'
            ? base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')
            : "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x01\x00\x48\x00\x48\x00\x00\xFF\xDB\x00\x43\x00"
              .str_repeat("\x08", 64)
              ."\xFF\xC0\x00\x0B\x08\x00\x01\x00\x01\x01\x01\x11\x00\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00\x7F\xFF\xD9";

        return UploadedFile::fake()->createWithContent($name, $bytes);
    }

    public function test_cannot_justify_with_nothing(): void
    {
        $this->mark($this->ana, '2026-08-03', 'ausente')->assertOk();

        $this->justify($this->ana, '2026-08-03')->assertStatus(422)->assertJsonValidationErrors('reason_text');
        $this->justify($this->ana, '2026-08-03', ['reason_text' => '   '])->assertStatus(422)->assertJsonValidationErrors('reason_text');

        $this->assertSame('ausente', $this->attendance($this->ana, '2026-08-03')->status->value);
        $this->assertSame(0, Justification::count());
    }

    public function test_short_text_is_not_a_valid_justification(): void
    {
        $this->mark($this->ana, '2026-08-03', 'ausente')->assertOk();

        $this->justify($this->ana, '2026-08-03', ['reason_text' => 'ok'])->assertStatus(422)->assertJsonValidationErrors('reason_text');
        $this->assertSame(0, Justification::count());
    }

    public function test_justify_with_text_only(): void
    {
        $this->mark($this->ana, '2026-08-03', 'ausente')->assertOk();

        $this->justify($this->ana, '2026-08-03', ['reason_text' => 'El alumno tuvo un viaje imprevisto por cuestiones familiares.'])
            ->assertOk()
            ->assertJsonPath('message', 'Inasistencia justificada.');

        $a = $this->attendance($this->ana, '2026-08-03');
        $this->assertSame('justificada', $a->status->value);
        $this->assertEquals(1.0, $a->absence_value);   // conserva el valor de la falta original
        $this->assertNull($a->justification->certificate_path);
        $this->assertSame($this->operador->id, $a->justification->justified_by);
    }

    public function test_justify_with_certificate_only_png_jpg_jpeg_pdf(): void
    {
        $this->mark($this->ana, '2026-08-03', 'ausente')->assertOk();

        foreach (['certificado.gif' => 'image/gif', 'virus.exe' => 'application/octet-stream', 'nota.txt' => 'text/plain', 'doc.docx' => 'application/msword'] as $name => $mime) {
            $this->justify($this->ana, '2026-08-03', ['certificate' => UploadedFile::fake()->create($name, 20, $mime)])
                ->assertStatus(422)->assertJsonValidationErrors('certificate');
        }
        $this->assertSame(0, Justification::count());

        $this->justify($this->ana, '2026-08-03', ['certificate' => $this->fakeImage('c.png')])->assertOk();

        $path = $this->attendance($this->ana, '2026-08-03')->justification->certificate_path;
        $this->assertNotNull($path);
        $this->assertStringStartsWith('certificados/2026/08/', $path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_accepts_jpg_jpeg_and_pdf_and_rejects_oversized(): void
    {
        foreach (['a.jpg', 'a.jpeg'] as $i => $name) {
            $day = Carbon::parse('2026-08-03')->addDays($i)->toDateString();
            $this->mark($this->ana, $day, 'ausente')->assertOk();
            $this->justify($this->ana, $day, ['certificate' => $this->fakeImage($name)])->assertOk();
        }

        $this->mark($this->ana, '2026-08-05', 'ausente')->assertOk();
        $this->justify($this->ana, '2026-08-05', ['certificate' => UploadedFile::fake()->create('c.pdf', 100, 'application/pdf')])->assertOk();

        $this->mark($this->ana, '2026-08-06', 'ausente')->assertOk();
        $this->justify($this->ana, '2026-08-06', ['certificate' => UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf')])
            ->assertStatus(422)->assertJsonValidationErrors('certificate');
    }

    public function test_retroactive_justification_days_later(): void
    {
        $this->mark($this->ana, '2026-08-10', 'ausente')->assertOk();

        Carbon::setTestNow('2026-08-15 09:00:00'); // 5 días después, cuando presenta el certificado
        $this->justify($this->ana, '2026-08-10', ['reason_text' => 'Presentó certificado médico por gastroenteritis.', 'certificate' => $this->fakeImage('c.jpg')])->assertOk();

        $a = $this->attendance($this->ana, '2026-08-10');
        $this->assertSame('justificada', $a->status->value);
        $this->assertSame('2026-08-15', $a->justification->justified_at->toDateString());
        $this->assertSame('2026-08-10', $a->date->toDateString());
    }

    public function test_justified_stops_counting_as_unjustified_but_keeps_its_value(): void
    {
        $this->mark($this->ana, '2026-08-03', 'ausente')->assertOk();
        $this->mark($this->ana, '2026-08-04', 'media_falta')->assertOk();
        $this->justify($this->ana, '2026-08-04', ['reason_text' => 'Control médico programado con turno.'])->assertOk();

        $row = collect(app(MonthlyRegister::class)->build($this->course, ActivityType::Clase, now())['rows'])->firstWhere('name', 'Aranibar, Ian');

        $this->assertEquals(1.0, $row['inj']);   // solo la ausencia
        $this->assertEquals(0.5, $row['j']);     // la media falta justificada conserva su ½
        $this->assertEquals(1.5, $row['total']);
        $this->assertSame(1, Attendance::unjustifiedAbsences()->count());
    }

    public function test_cannot_justify_presente_or_unregistered_day(): void
    {
        $this->mark($this->ana, '2026-08-03', 'presente')->assertOk();

        $this->justify($this->ana, '2026-08-03', ['reason_text' => 'Texto suficientemente largo.'])->assertStatus(422)->assertJsonValidationErrors('date');
        $this->justify($this->ana, '2026-08-04', ['reason_text' => 'Texto suficientemente largo.'])->assertStatus(422)->assertJsonValidationErrors('date');
        $this->assertSame(0, Justification::count());
    }

    public function test_edit_justification_must_keep_at_least_one_support(): void
    {
        $this->mark($this->ana, '2026-08-03', 'ausente')->assertOk();
        $this->justify($this->ana, '2026-08-03', ['reason_text' => 'Texto justificativo inicial.'])->assertOk();

        // Se intenta dejar la justificación sin texto y sin archivo.
        $this->justify($this->ana, '2026-08-03', ['reason_text' => ''])->assertStatus(422)->assertJsonValidationErrors('reason_text');
        $this->assertSame('Texto justificativo inicial.', $this->attendance($this->ana, '2026-08-03')->justification->reason_text);

        // Agregar certificado, y luego quitar el texto: queda solo el certificado (válido).
        $this->justify($this->ana, '2026-08-03', ['reason_text' => 'Texto justificativo inicial.', 'certificate' => $this->fakeImage('c.png')])->assertOk();
        $path = $this->attendance($this->ana, '2026-08-03')->justification->certificate_path;
        $this->justify($this->ana, '2026-08-03', ['reason_text' => ''])->assertOk();
        $j = $this->attendance($this->ana, '2026-08-03')->justification;
        $this->assertNull($j->reason_text);
        $this->assertSame($path, $j->certificate_path);

        // Quitar el certificado sin texto: no se puede.
        $this->justify($this->ana, '2026-08-03', ['reason_text' => '', 'remove_certificate' => 1])->assertStatus(422);
        Storage::disk('local')->assertExists($path);
    }

    public function test_replacing_certificate_deletes_the_old_file(): void
    {
        $this->mark($this->ana, '2026-08-03', 'ausente')->assertOk();
        $this->justify($this->ana, '2026-08-03', ['certificate' => $this->fakeImage('uno.png')])->assertOk();
        $old = $this->attendance($this->ana, '2026-08-03')->justification->certificate_path;

        $this->justify($this->ana, '2026-08-03', ['certificate' => $this->fakeImage('dos.jpg')])->assertOk();
        $new = $this->attendance($this->ana, '2026-08-03')->justification->certificate_path;

        $this->assertNotSame($old, $new);
        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($new);
    }

    public function test_changing_a_justified_status_requires_confirmation_and_removes_justification(): void
    {
        $this->mark($this->ana, '2026-08-03', 'ausente')->assertOk();
        $this->justify($this->ana, '2026-08-03', ['certificate' => $this->fakeImage('c.png')])->assertOk();
        $path = $this->attendance($this->ana, '2026-08-03')->justification->certificate_path;

        $this->mark($this->ana, '2026-08-03', 'ausente')->assertStatus(409)->assertJson(['requires_confirmation' => true]);
        $this->assertSame('justificada', $this->attendance($this->ana, '2026-08-03')->status->value);

        $this->actingAs($this->operador)->putJson($this->url('/registro'), [
            'student_id' => $this->ana->id, 'date' => '2026-08-03', 'status' => 'ausente', 'confirm_unjustify' => true,
        ])->assertOk();

        $this->assertSame('ausente', $this->attendance($this->ana, '2026-08-03')->status->value);
        $this->assertSame(0, Justification::count());
        Storage::disk('local')->assertMissing($path);
    }

    public function test_observation_can_be_edited_on_a_justified_record(): void
    {
        $this->mark($this->ana, '2026-08-03', 'ausente', 'Tuvo un accidente')->assertOk();
        $this->justify($this->ana, '2026-08-03', ['reason_text' => 'Accidente con parte médico presentado.'])->assertOk();

        $this->mark($this->ana, '2026-08-03', 'justificada', 'Tuvo un accidente en el recreo')->assertOk();

        $a = $this->attendance($this->ana, '2026-08-03');
        $this->assertSame('justificada', $a->status->value);
        $this->assertSame('Tuvo un accidente en el recreo', $a->observation);
        $this->assertNotNull($a->justification);
    }

    public function test_removing_a_justified_record_deletes_its_certificate(): void
    {
        $this->mark($this->ana, '2026-08-03', 'ausente')->assertOk();
        $this->justify($this->ana, '2026-08-03', ['certificate' => $this->fakeImage('c.png')])->assertOk();
        $path = $this->attendance($this->ana, '2026-08-03')->justification->certificate_path;

        $this->actingAs($this->operador)->deleteJson($this->url('/registro'), ['student_id' => $this->ana->id, 'date' => '2026-08-03'])->assertOk();

        $this->assertSame(0, Justification::count());
        Storage::disk('local')->assertMissing($path);
    }

    public function test_justification_is_scoped_to_assigned_course_and_activity(): void
    {
        $foreign = Student::factory()->create(['course_id' => $this->other->id]);

        $this->actingAs($this->operador)->postJson($this->url('/justificar', $this->other), [
            'student_id' => $foreign->id, 'date' => '2026-08-03', 'reason_text' => 'Texto suficientemente largo.',
        ])->assertForbidden();

        $this->actingAs($this->operador)->postJson($this->url('/justificar', null, 'taller'), [
            'student_id' => $this->ana->id, 'date' => '2026-08-03', 'reason_text' => 'Texto suficientemente largo.',
        ])->assertForbidden();
    }

    public function test_certificate_download_is_limited_to_assigned_operators_and_director(): void
    {
        $this->mark($this->ana, '2026-08-03', 'ausente')->assertOk();
        $this->justify($this->ana, '2026-08-03', ['certificate' => $this->fakeImage('c.png')])->assertOk();
        $a = $this->attendance($this->ana, '2026-08-03');

        $this->actingAs($this->operador)->get($this->url("/justificaciones/{$a->id}/certificado"))->assertOk();

        $stranger = User::factory()->create();
        $stranger->assignCourse($this->other, ActivityType::Clase);
        $this->actingAs($stranger)->get($this->url("/justificaciones/{$a->id}/certificado"))->assertForbidden();

        $this->actingAs(User::factory()->director()->create())
            ->get(route('director.justifications.certificate', $a->justification))->assertOk();
    }
}
