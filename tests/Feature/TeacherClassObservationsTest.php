<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Scope;
use App\Models\Student;
use App\Models\TeacherClassObservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherClassObservationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_docente_can_record_observation_via_ajax_only_for_assigned_courses_and_cannot_modify_official_attendance(): void
    {
        Carbon::setTestNow('2026-11-11 10:30:00');

        $scope = Scope::firstOrCreate(['slug' => 'clase'], ['name' => 'Clases', 'active' => true]);
        $activity = Activity::create(['scope_id' => $scope->id, 'name' => 'Matemática Aplicada', 'active' => true]);

        $assignedCourse = Course::create(['year' => 3, 'division' => '1', 'shift' => 'manana', 'active' => true]);
        $unassignedCourse = Course::create(['year' => 5, 'division' => '2', 'shift' => 'tarde', 'active' => true]);

        $studentInCourse = Student::factory()->create([
            'course_id' => $assignedCourse->id,
            'last_name' => 'Gómez',
            'first_name' => 'Lucía',
        ]);
        $studentOutside = Student::factory()->create([
            'course_id' => $unassignedCourse->id,
            'last_name' => 'Ruiz',
            'first_name' => 'Mateo',
        ]);

        $docente = User::factory()->docente()->create(['name' => 'Prof. Ricardo Sosa']);
        $docente->assignCourse($assignedCourse, $scope);

        // 1. El docente puede acceder a su nómina y ver al alumno de su curso asignado
        $this->actingAs($docente)
            ->get(route('teacher-observations.index', ['course_id' => $assignedCourse->id, 'date' => '2026-11-11']))
            ->assertOk()
            ->assertSee('GÓMEZ, Lucía')
            ->assertSee('Observaciones');

        // 2. El docente guarda una observación vía Fetch/AJAX (JSON)
        $response = $this->actingAs($docente)
            ->postJson(route('teacher-observations.store'), [
                'student_id' => $studentInCourse->id,
                'activity_id' => $activity->id,
                'date' => '2026-11-11',
                'observation_text' => 'Ausente en la hora de Matemática Aplicada (figuraba Presente en el parte).',
            ]);

        $response->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('observation.student_id', $studentInCourse->id)
            ->assertJsonPath('observation.activity_name', 'Matemática Aplicada')
            ->assertJsonPath('observation.teacher_name', 'Prof. Ricardo Sosa');

        $this->assertDatabaseHas('teacher_class_observations', [
            'student_id' => $studentInCourse->id,
            'teacher_id' => $docente->id,
            'activity_id' => $activity->id,
            'date' => '2026-11-11',
            'observation_text' => 'Ausente en la hora de Matemática Aplicada (figuraba Presente en el parte).',
        ]);

        // 3. El docente NO puede cargar observaciones sobre alumnos de cursos no asignados
        $this->actingAs($docente)
            ->postJson(route('teacher-observations.store'), [
                'student_id' => $studentOutside->id,
                'activity_id' => $activity->id,
                'date' => '2026-11-11',
                'observation_text' => 'Intento en curso ajeno.',
            ])
            ->assertForbidden();

        // 4. El docente NO puede modificar la asistencia oficial en `student_attendances`
        $this->actingAs($docente)
            ->post(route('attendance.daily.store'), [
                'date' => '2026-11-11',
                'scope_id' => $scope->id,
                'course_id' => $assignedCourse->id,
                'records' => [
                    ['student_id' => $studentInCourse->id, 'status' => 'ausente'],
                ],
            ])
            ->assertForbidden();
    }

    public function test_preceptor_sees_visual_alert_in_daily_attendance_and_exports_include_teacher_observations_annex(): void
    {
        Carbon::setTestNow('2026-11-11 11:00:00');

        $scope = Scope::firstOrCreate(['slug' => 'clase'], ['name' => 'Clases', 'active' => true]);
        $activity = Activity::create(['scope_id' => $scope->id, 'name' => 'Electrónica Industrial', 'active' => true]);
        $course = Course::create(['year' => 4, 'division' => '1', 'shift' => 'manana', 'active' => true]);

        $student = Student::factory()->create([
            'course_id' => $course->id,
            'last_name' => 'Fernández',
            'first_name' => 'Martín',
            'dni' => '46999888',
        ]);

        $preceptor = User::factory()->create(['name' => 'Preceptor Juan']);
        $preceptor->assignCourse($course, $scope);

        $docente = User::factory()->docente()->create(['name' => 'Prof. Elena Paz']);
        $docente->assignCourse($course, $scope);

        // El preceptor tomó asistencia a primera hora como Presente
        Attendance::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'scope_id' => $scope->id,
            'user_id' => $preceptor->id,
            'date' => '2026-11-11',
            'activity_type' => $scope->slug,
            'status' => AttendanceStatus::Presente->value,
        ]);

        // Luego el docente reporta que se retiró y se ausentó en su materia
        TeacherClassObservation::create([
            'student_id' => $student->id,
            'teacher_id' => $docente->id,
            'activity_id' => $activity->id,
            'date' => '2026-11-11',
            'observation_text' => 'Se retiró al baño y no volvió a Electrónica Industrial.',
        ]);

        // 1. En la toma diaria del Preceptor aparece la alerta visual "⚠️ Observación Docente" y el detalle del reporte
        $this->actingAs($preceptor)
            ->get(route('attendance.daily', [
                'date' => '2026-11-11',
                'scope_id' => $scope->id,
                'course_id' => $course->id,
            ]))
            ->assertOk()
            ->assertSee('⚠️ Observación Docente')
            ->assertSee('Electrónica Industrial')
            ->assertSee('Prof. Elena Paz')
            ->assertSee('Se retiró al baño y no volvió a Electrónica Industrial.');

        // 2. En la vista de Planilla Mensual (01 al 31) aparece el Anexo Institucional con Fecha | Alumno | Materia | Docente | Observación
        $this->actingAs($preceptor)
            ->get(route('reports.monthly-sheet', [
                'month' => '2026-11',
                'scope' => $scope->slug,
                'course_id' => $course->id,
            ]))
            ->assertOk()
            ->assertSee('Anexo Institucional: Observaciones Docentes de Clase')
            ->assertSee('11/11/2026')
            ->assertSee('Fernández, Martín')
            ->assertSee('Electrónica Industrial')
            ->assertSee('Prof. Elena Paz')
            ->assertSee('Se retiró al baño y no volvió a Electrónica Industrial.');

        // 3. Exportación CSV incluye el Anexo de Observaciones Docentes
        $csvResponse = $this->actingAs($preceptor)
            ->get(route('reports.monthly-sheet', [
                'month' => '2026-11',
                'scope' => $scope->slug,
                'course_id' => $course->id,
                'export' => 'csv',
            ]));
        $csvResponse->assertOk();
        $csvContent = (string) $csvResponse->getContent();
        $this->assertStringContainsString('ANEXO INSTITUCIONAL: OBSERVACIONES DOCENTES DE CLASE', $csvContent);
        $this->assertStringContainsString('Electrónica Industrial', $csvContent);
        $this->assertStringContainsString('Prof. Elena Paz', $csvContent);
        $this->assertStringContainsString('Se retiró al baño y no volvió a Electrónica Industrial.', $csvContent);

        // 4. Exportación PDF (DomPDF) y XLSX responden 200 OK
        $this->actingAs($preceptor)
            ->get(route('reports.monthly-sheet', [
                'month' => '2026-11',
                'scope' => $scope->slug,
                'course_id' => $course->id,
                'export' => 'pdf',
            ]))
            ->assertOk();

        $this->actingAs($preceptor)
            ->get(route('reports.monthly-sheet', [
                'month' => '2026-11',
                'scope' => $scope->slug,
                'course_id' => $course->id,
                'export' => 'xlsx',
            ]))
            ->assertOk();
    }
}
