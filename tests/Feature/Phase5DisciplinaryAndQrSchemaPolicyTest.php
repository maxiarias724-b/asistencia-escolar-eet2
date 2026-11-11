<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\QrInterventionStatus;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\Course;
use App\Models\DisciplinaryRecord;
use App\Models\QrIntervention;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class Phase5DisciplinaryAndQrSchemaPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_disciplinary_records_and_qr_interventions_relations_and_audit_logs(): void
    {
        $director = User::factory()->create([
            'username' => 'director_test',
            'role' => Role::Director,
            'active' => true,
        ]);

        $course = Course::create([
            'year' => 3,
            'division' => '1',
            'shift' => 'manana',
            'active' => true,
        ]);

        $student = Student::create([
            'dni' => '45111222',
            'last_name' => 'GOMEZ',
            'first_name' => 'Lautaro',
            'course_id' => $course->id,
            'status' => StudentStatus::Activo,
            'active' => true,
        ]);

        $this->actingAs($director);

        $record = DisciplinaryRecord::create([
            'student_id' => $student->id,
            'recorded_by' => $director->id,
            'date' => '2026-05-12',
            'amount' => 3,
            'reason' => 'Falta de respeto a las normas de convivencia en taller.',
            'active' => true,
        ]);

        $intervention = QrIntervention::create([
            'student_id' => $student->id,
            'initiated_by' => $director->id,
            'detection_date' => '2026-05-15',
            'consecutive_absences_flag' => 5,
            'territorial_team_contact' => 'Lic. María Fernanda Quispe (Equipo Territorial Región I)',
            'home_visit_date' => '2026-05-18',
            'pedagogical_support_details' => 'Tutoría PECOS en contraturno y acuerdo con la familia.',
            'status' => QrInterventionStatus::IntervencionActiva,
        ]);

        $this->assertSame($student->id, $record->student->id);
        $this->assertSame($director->id, $record->recorder->id);
        $this->assertSame(1, $student->disciplinaryRecords()->count());
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => DisciplinaryRecord::class,
            'auditable_id' => $record->id,
            'action' => 'created',
        ]);

        $this->assertSame($student->id, $intervention->student->id);
        $this->assertSame($director->id, $intervention->initiator->id);
        $this->assertSame(QrInterventionStatus::IntervencionActiva, $intervention->status);
        $this->assertSame('Intervención activa', $intervention->status_label);
        $this->assertSame(1, $student->qrInterventions()->count());
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => QrIntervention::class,
            'auditable_id' => $intervention->id,
            'action' => 'created',
        ]);
    }

    public function test_disciplinary_record_policy_and_visibility_matrix(): void
    {
        $director = User::factory()->create(['username' => 'dir1', 'role' => Role::Director, 'active' => true]);
        $preceptor = User::factory()->create(['username' => 'prec1', 'role' => Role::Operador, 'active' => true]);
        $docente = User::factory()->create(['username' => 'doc1', 'role' => Role::Docente, 'active' => true]);

        $assignedCourse = Course::create(['year' => 2, 'division' => '1', 'shift' => 'manana', 'active' => true]);
        $otherCourse = Course::create(['year' => 5, 'division' => '2', 'shift' => 'tarde', 'active' => true]);

        $preceptor->assignCourse($assignedCourse, ActivityType::Clase);
        $docente->assignCourse($assignedCourse, ActivityType::Clase);

        $studentAssigned = Student::create([
            'dni' => '46000001',
            'last_name' => 'PEREZ',
            'first_name' => 'Ana',
            'course_id' => $assignedCourse->id,
            'status' => StudentStatus::Activo,
        ]);

        $studentOther = Student::create([
            'dni' => '46000002',
            'last_name' => 'LOPEZ',
            'first_name' => 'Juan',
            'course_id' => $otherCourse->id,
            'status' => StudentStatus::Activo,
        ]);

        $recordAssigned = DisciplinaryRecord::create([
            'student_id' => $studentAssigned->id,
            'recorded_by' => $director->id,
            'date' => '2026-06-01',
            'amount' => 2,
            'reason' => 'Retiro sin autorización del aula.',
            'active' => true,
        ]);

        $recordOther = DisciplinaryRecord::create([
            'student_id' => $studentOther->id,
            'recorded_by' => $director->id,
            'date' => '2026-06-02',
            'amount' => 5,
            'reason' => 'Daño intencional de mobiliario.',
            'active' => true,
        ]);

        // Director: acceso total
        $this->assertTrue(Gate::forUser($director)->allows('viewAny', DisciplinaryRecord::class));
        $this->assertTrue(Gate::forUser($director)->allows('view', $recordAssigned));
        $this->assertTrue(Gate::forUser($director)->allows('view', $recordOther));
        $this->assertTrue(Gate::forUser($director)->allows('create', DisciplinaryRecord::class));
        $this->assertTrue(Gate::forUser($director)->allows('update', $recordAssigned));
        $this->assertTrue(Gate::forUser($director)->allows('delete', $recordAssigned));
        $this->assertCount(2, DisciplinaryRecord::visibleTo($director)->get());

        // Preceptor (Operador): solo lectura de sus cursos asignados
        $this->assertTrue(Gate::forUser($preceptor)->allows('viewAny', DisciplinaryRecord::class));
        $this->assertTrue(Gate::forUser($preceptor)->allows('view', $recordAssigned));
        $this->assertFalse(Gate::forUser($preceptor)->allows('view', $recordOther));
        $this->assertFalse(Gate::forUser($preceptor)->allows('create', DisciplinaryRecord::class));
        $this->assertFalse(Gate::forUser($preceptor)->allows('update', $recordAssigned));
        $this->assertFalse(Gate::forUser($preceptor)->allows('delete', $recordAssigned));
        $this->assertSame([$recordAssigned->id], DisciplinaryRecord::visibleTo($preceptor)->pluck('id')->all());

        // Docente: solo lectura de sus cursos asignados
        $this->assertTrue(Gate::forUser($docente)->allows('viewAny', DisciplinaryRecord::class));
        $this->assertTrue(Gate::forUser($docente)->allows('view', $recordAssigned));
        $this->assertFalse(Gate::forUser($docente)->allows('view', $recordOther));
        $this->assertFalse(Gate::forUser($docente)->allows('create', DisciplinaryRecord::class));
        $this->assertFalse(Gate::forUser($docente)->allows('update', $recordAssigned));
        $this->assertFalse(Gate::forUser($docente)->allows('delete', $recordAssigned));
        $this->assertSame([$recordAssigned->id], DisciplinaryRecord::visibleTo($docente)->pluck('id')->all());
    }

    public function test_qr_intervention_policy_and_visibility_matrix(): void
    {
        $director = User::factory()->create(['username' => 'dir2', 'role' => Role::Director, 'active' => true]);
        $preceptor = User::factory()->create(['username' => 'prec2', 'role' => Role::Operador, 'active' => true]);
        $docente = User::factory()->create(['username' => 'doc2', 'role' => Role::Docente, 'active' => true]);

        $assignedCourse = Course::create(['year' => 1, 'division' => '2', 'shift' => 'tarde', 'active' => true]);
        $otherCourse = Course::create(['year' => 4, 'division' => '1', 'shift' => 'manana', 'active' => true]);

        $preceptor->assignCourse($assignedCourse, ActivityType::Clase);
        $docente->assignCourse($assignedCourse, ActivityType::Clase);

        $studentAssigned = Student::create([
            'dni' => '47000001',
            'last_name' => 'MAMANI',
            'first_name' => 'Sofía',
            'course_id' => $assignedCourse->id,
            'status' => StudentStatus::Activo,
        ]);

        $studentOther = Student::create([
            'dni' => '47000002',
            'last_name' => 'FLORES',
            'first_name' => 'Mateo',
            'course_id' => $otherCourse->id,
            'status' => StudentStatus::Activo,
        ]);

        $qrAssigned = QrIntervention::create([
            'student_id' => $studentAssigned->id,
            'initiated_by' => $director->id,
            'detection_date' => '2026-06-10',
            'consecutive_absences_flag' => 4,
            'status' => QrInterventionStatus::Evaluacion,
        ]);

        $qrOther = QrIntervention::create([
            'student_id' => $studentOther->id,
            'initiated_by' => $director->id,
            'detection_date' => '2026-06-11',
            'consecutive_absences_flag' => 7,
            'status' => QrInterventionStatus::IntervencionActiva,
        ]);

        // Director: acceso total
        $this->assertTrue(Gate::forUser($director)->allows('viewAny', QrIntervention::class));
        $this->assertTrue(Gate::forUser($director)->allows('view', $qrAssigned));
        $this->assertTrue(Gate::forUser($director)->allows('view', $qrOther));
        $this->assertTrue(Gate::forUser($director)->allows('create', QrIntervention::class));
        $this->assertTrue(Gate::forUser($director)->allows('update', $qrAssigned));
        $this->assertCount(2, QrIntervention::visibleTo($director)->get());

        // Preceptor (Operador): solo lectura de sus cursos asignados
        $this->assertTrue(Gate::forUser($preceptor)->allows('viewAny', QrIntervention::class));
        $this->assertTrue(Gate::forUser($preceptor)->allows('view', $qrAssigned));
        $this->assertFalse(Gate::forUser($preceptor)->allows('view', $qrOther));
        $this->assertFalse(Gate::forUser($preceptor)->allows('create', QrIntervention::class));
        $this->assertFalse(Gate::forUser($preceptor)->allows('update', $qrAssigned));
        $this->assertSame([$qrAssigned->id], QrIntervention::visibleTo($preceptor)->pluck('id')->all());

        // Docente: acceso completamente denegado
        $this->assertFalse(Gate::forUser($docente)->allows('viewAny', QrIntervention::class));
        $this->assertFalse(Gate::forUser($docente)->allows('view', $qrAssigned));
        $this->assertFalse(Gate::forUser($docente)->allows('view', $qrOther));
        $this->assertFalse(Gate::forUser($docente)->allows('create', QrIntervention::class));
        $this->assertFalse(Gate::forUser($docente)->allows('update', $qrAssigned));
        $this->assertCount(0, QrIntervention::visibleTo($docente)->get());
    }

    public function test_disciplinary_module_http_endpoints_and_course_scoping(): void
    {
        $director = User::factory()->create(['username' => 'dir_http', 'role' => Role::Director, 'active' => true]);
        $preceptor = User::factory()->create(['username' => 'prec_http', 'role' => Role::Operador, 'active' => true]);
        $docente = User::factory()->create(['username' => 'doc_http', 'role' => Role::Docente, 'active' => true]);

        $courseA = Course::create(['year' => 2, 'division' => '1', 'shift' => 'manana', 'active' => true]);
        $courseB = Course::create(['year' => 6, 'division' => '1', 'shift' => 'tarde', 'active' => true]);

        $preceptor->assignCourse($courseA, ActivityType::Clase);
        $docente->assignCourse($courseA, ActivityType::Clase);

        $studentA = Student::create([
            'dni' => '48000001',
            'last_name' => 'QUISPE',
            'first_name' => 'Valentina',
            'course_id' => $courseA->id,
            'status' => StudentStatus::Activo,
        ]);

        $studentB = Student::create([
            'dni' => '48000002',
            'last_name' => 'VILTE',
            'first_name' => 'Nicolás',
            'course_id' => $courseB->id,
            'status' => StudentStatus::Activo,
        ]);

        // Director crea amonestaciones en ambos cursos
        $this->actingAs($director)
            ->get(route('disciplinary.create'))
            ->assertOk()
            ->assertSee('Registrar nueva amonestación');

        $this->actingAs($director)
            ->post(route('disciplinary.store'), [
                'student_id' => $studentA->id,
                'date' => now()->toDateString(),
                'amount' => 3,
                'reason' => 'Sanción curso A para prueba HTTP.',
            ])
            ->assertRedirect(route('disciplinary.index'));

        $this->actingAs($director)
            ->post(route('disciplinary.store'), [
                'student_id' => $studentB->id,
                'date' => now()->toDateString(),
                'amount' => 5,
                'reason' => 'Sanción curso B exclusiva para director.',
            ])
            ->assertRedirect(route('disciplinary.index'));

        $recordA = DisciplinaryRecord::where('student_id', $studentA->id)->firstOrFail();

        // Director ve ambos registros y el botón de creación
        $this->actingAs($director)
            ->get(route('disciplinary.index'))
            ->assertOk()
            ->assertSee('+ Registrar amonestación')
            ->assertSee('QUISPE, Valentina')
            ->assertSee('VILTE, Nicolás');

        // Preceptor ve solo el curso A y no puede crear ni editar
        $this->actingAs($preceptor)
            ->get(route('disciplinary.index'))
            ->assertOk()
            ->assertDontSee('+ Registrar amonestación')
            ->assertSee('QUISPE, Valentina')
            ->assertDontSee('VILTE, Nicolás');

        $this->actingAs($preceptor)->get(route('disciplinary.create'))->assertForbidden();
        $this->actingAs($preceptor)->post(route('disciplinary.store'), [
            'student_id' => $studentA->id,
            'date' => now()->toDateString(),
            'amount' => 1,
            'reason' => 'Intento no autorizado.',
        ])->assertForbidden();
        $this->actingAs($preceptor)->get(route('disciplinary.edit', $recordA))->assertForbidden();
        $this->actingAs($preceptor)->patch(route('disciplinary.toggle', $recordA))->assertForbidden();

        // Docente ve solo el curso A y no puede crear ni editar
        $this->actingAs($docente)
            ->get(route('disciplinary.index'))
            ->assertOk()
            ->assertDontSee('+ Registrar amonestación')
            ->assertSee('QUISPE, Valentina')
            ->assertDontSee('VILTE, Nicolás');

        $this->actingAs($docente)->get(route('disciplinary.create'))->assertForbidden();
        $this->actingAs($docente)->put(route('disciplinary.update', $recordA), [
            'student_id' => $studentA->id,
            'date' => now()->toDateString(),
            'amount' => 2,
            'reason' => 'Intento docente no autorizado.',
        ])->assertForbidden();

        // Director edita y da de baja lógica
        $this->actingAs($director)
            ->put(route('disciplinary.update', $recordA), [
                'student_id' => $studentA->id,
                'date' => now()->toDateString(),
                'amount' => 4,
                'reason' => 'Sanción curso A actualizada por dirección.',
                'active' => 1,
            ])
            ->assertRedirect(route('disciplinary.index'));

        $this->assertSame(4, $recordA->fresh()->amount);

        $this->actingAs($director)
            ->patch(route('disciplinary.toggle', $recordA))
            ->assertRedirect();

        $this->assertFalse($recordA->fresh()->active);
    }

    public function test_qr_intervention_module_http_endpoints_and_role_permissions(): void
    {
        $director = User::factory()->create(['username' => 'dir_qr', 'role' => Role::Director, 'active' => true]);
        $preceptor = User::factory()->create(['username' => 'prec_qr', 'role' => Role::Operador, 'active' => true]);
        $docente = User::factory()->create(['username' => 'doc_qr', 'role' => Role::Docente, 'active' => true]);

        $courseA = Course::create(['year' => 1, 'division' => '1', 'shift' => 'manana', 'active' => true]);
        $courseB = Course::create(['year' => 4, 'division' => '2', 'shift' => 'tarde', 'active' => true]);

        $preceptor->assignCourse($courseA, ActivityType::Clase);
        $docente->assignCourse($courseA, ActivityType::Clase);

        $studentA = Student::create([
            'dni' => '49000001',
            'last_name' => 'ARAMAYO',
            'first_name' => 'Lucía',
            'course_id' => $courseA->id,
            'status' => StudentStatus::Activo,
        ]);

        $studentB = Student::create([
            'dni' => '49000002',
            'last_name' => 'CHOCOBAR',
            'first_name' => 'Tomás',
            'course_id' => $courseB->id,
            'status' => StudentStatus::Activo,
        ]);

        // Director abre expedientes QR en ambos cursos
        $this->actingAs($director)
            ->get(route('qr.create'))
            ->assertOk()
            ->assertSee('Abrir expediente — Programa QR');

        $this->actingAs($director)
            ->post(route('qr.store'), [
                'student_id' => $studentA->id,
                'detection_date' => now()->toDateString(),
                'consecutive_absences_flag' => 4,
                'territorial_team_contact' => 'Lic. Rosa Gutiérrez',
                'home_visit_date' => now()->toDateString(),
                'pedagogical_support_details' => 'Entrevista con tutor y apoyo PECOS.',
                'status' => QrInterventionStatus::IntervencionActiva->value,
            ])
            ->assertRedirect();

        $this->actingAs($director)
            ->post(route('qr.store'), [
                'student_id' => $studentB->id,
                'detection_date' => now()->toDateString(),
                'consecutive_absences_flag' => 8,
                'status' => QrInterventionStatus::Evaluacion->value,
            ])
            ->assertRedirect();

        $qrA = QrIntervention::where('student_id', $studentA->id)->firstOrFail();
        $qrB = QrIntervention::where('student_id', $studentB->id)->firstOrFail();

        // Director ve ambos expedientes y puede actualizarlos
        $this->actingAs($director)
            ->get(route('qr.index'))
            ->assertOk()
            ->assertSee('+ Abrir expediente QR')
            ->assertSee('ARAMAYO, Lucía')
            ->assertSee('CHOCOBAR, Tomás');

        $this->actingAs($director)
            ->put(route('qr.update', $qrA), [
                'student_id' => $studentA->id,
                'detection_date' => now()->toDateString(),
                'consecutive_absences_flag' => 4,
                'territorial_team_contact' => 'Lic. Rosa Gutiérrez',
                'home_visit_date' => now()->toDateString(),
                'pedagogical_support_details' => 'Estudiante reincorporada satisfactoriamente.',
                'status' => QrInterventionStatus::Revinculado->value,
            ])
            ->assertRedirect(route('qr.show', $qrA));

        $this->assertSame(QrInterventionStatus::Revinculado, $qrA->fresh()->status);

        // Preceptor ve solo su curso asignado en index y en show (solo lectura), bloqueado en otros cursos y en edición
        $this->actingAs($preceptor)
            ->get(route('qr.index'))
            ->assertOk()
            ->assertDontSee('+ Abrir expediente QR')
            ->assertSee('ARAMAYO, Lucía')
            ->assertDontSee('CHOCOBAR, Tomás');

        $this->actingAs($preceptor)
            ->get(route('qr.show', $qrA))
            ->assertOk()
            ->assertSee('ARAMAYO, Lucía')
            ->assertSee('Estudiante reincorporada satisfactoriamente.')
            ->assertDontSee('Editar seguimiento');

        $this->actingAs($preceptor)->get(route('qr.show', $qrB))->assertForbidden();
        $this->actingAs($preceptor)->get(route('qr.create'))->assertForbidden();
        $this->actingAs($preceptor)->get(route('qr.edit', $qrA))->assertForbidden();
        $this->actingAs($preceptor)->put(route('qr.update', $qrA), [
            'student_id' => $studentA->id,
            'detection_date' => now()->toDateString(),
            'consecutive_absences_flag' => 4,
            'status' => QrInterventionStatus::Abandono->value,
        ])->assertForbidden();

        // Docente tiene acceso denegado a todo el módulo QR y no ve el enlace en el menú
        $this->actingAs($docente)
            ->get(route('disciplinary.index'))
            ->assertOk()
            ->assertDontSee('Programa QR');

        $this->actingAs($docente)->get(route('qr.index'))->assertForbidden();
        $this->actingAs($docente)->get(route('qr.show', $qrA))->assertForbidden();
        $this->actingAs($docente)->get(route('qr.create'))->assertForbidden();
        $this->actingAs($docente)->post(route('qr.store'), [
            'student_id' => $studentA->id,
            'detection_date' => now()->toDateString(),
            'consecutive_absences_flag' => 3,
            'status' => QrInterventionStatus::Evaluacion->value,
        ])->assertForbidden();
    }
}
