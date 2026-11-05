<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Http\Middleware\CheckCourseAssignment;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\NonWorkingDay;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

class RosterAndCalendarTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;
    private Course $other;
    private User $operador;
    private User $director;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-20 10:00:00');

        $this->course = Course::create(['year' => 3, 'division' => '1', 'shift' => 'manana']);
        $this->other = Course::create(['year' => 5, 'division' => '2', 'shift' => 'tarde']);

        $this->operador = User::factory()->create();
        $this->operador->assignCourse($this->course, ActivityType::Clase);

        $this->director = User::factory()->director()->create();
    }

    private function rosterUrl(string $suffix = '', ?Course $course = null, string $activity = 'clase'): string
    {
        return "/asistencia/{$activity}/".($course ?? $this->course)->id.'/nomina'.$suffix;
    }

    /* ------------------------- Nómina manual ------------------------- */

    public function test_operator_can_view_and_create_students_with_or_without_dni(): void
    {
        $this->actingAs($this->operador)->get($this->rosterUrl())
            ->assertOk()
            ->assertSee('Nómina');

        // Con DNI y CUIL tipeados con puntos/guiones
        $this->actingAs($this->operador)->post($this->rosterUrl(), [
            'last_name' => 'Aranibar',
            'first_name' => 'Ian Emanuel',
            'dni' => '45.123.456',
            'cuil' => '20-45123456-3',
            'birth_date' => '2010-05-14',
            'status' => 'activo',
            'sinide_code' => 'SIN-1001',
        ])->assertRedirect();

        // Sin DNI ni CUIL (planilla SINIDE solo con Apellido, Nombre)
        $this->actingAs($this->operador)->post($this->rosterUrl(), [
            'last_name' => 'Baina',
            'first_name' => 'Agustín Jonatan',
            'dni' => '',
            'cuil' => '',
            'status' => 'activo',
        ])->assertRedirect();

        $this->assertSame(2, Student::where('course_id', $this->course->id)->count());
        $ian = Student::where('last_name', 'Aranibar')->firstOrFail();
        $this->assertSame('45123456', $ian->dni);
        $this->assertSame('20-45123456-3', $ian->cuil);

        $agustin = Student::where('last_name', 'Baina')->firstOrFail();
        $this->assertNull($agustin->dni);
        $this->assertNull($agustin->cuil);
    }

    public function test_prevents_duplicates_by_dni_cuil_or_name_without_dni(): void
    {
        Student::create([
            'course_id' => $this->course->id,
            'last_name' => 'Caceres',
            'first_name' => 'Kiara Isabella',
            'dni' => '46000111',
            'cuil' => '27-46000111-4',
            'status' => 'activo',
        ]);

        // DNI duplicado
        $this->actingAs($this->operador)->post($this->rosterUrl(), [
            'last_name' => 'Otra', 'first_name' => 'Alumna', 'dni' => '46000111', 'status' => 'activo',
        ])->assertSessionHasErrors('dni');

        // CUIL duplicado
        $this->actingAs($this->operador)->post($this->rosterUrl(), [
            'last_name' => 'Otra', 'first_name' => 'Alumna', 'dni' => '46000222', 'cuil' => '27460001114', 'status' => 'activo',
        ])->assertSessionHasErrors('cuil');

        // Mismo apellido y nombre en el curso sin DNI -> bloqueado
        $this->actingAs($this->operador)->post($this->rosterUrl(), [
            'last_name' => 'CACERES', 'first_name' => 'kiara isabella', 'status' => 'activo',
        ])->assertSessionHasErrors('last_name');

        // Homónimo CON otro DNI distinto -> permitido
        $this->actingAs($this->operador)->post($this->rosterUrl(), [
            'last_name' => 'Caceres', 'first_name' => 'Kiara Isabella', 'dni' => '46999888', 'status' => 'activo',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, Student::where('course_id', $this->course->id)->count());
    }

    public function test_update_and_delete_protects_students_with_attendance_history(): void
    {
        $s1 = Student::factory()->create(['course_id' => $this->course->id, 'last_name' => 'Gomez', 'first_name' => 'Ana']);
        $s2 = Student::factory()->create(['course_id' => $this->course->id, 'last_name' => 'Lopez', 'first_name' => 'Juan']);

        Attendance::create([
            'student_id' => $s1->id, 'course_id' => $this->course->id, 'user_id' => $this->operador->id,
            'date' => '2026-08-03', 'activity_type' => 'clase', 'status' => 'presente',
        ]);

        // No se puede eliminar si tiene asistencias
        $this->actingAs($this->operador)->delete($this->rosterUrl("/{$s1->id}"))
            ->assertSessionHasErrors('roster');
        $this->assertModelExists($s1);

        // Sí se puede pasar a Pase
        $this->actingAs($this->operador)->put($this->rosterUrl("/{$s1->id}"), [
            'last_name' => 'Gomez', 'first_name' => 'Ana', 'dni' => $s1->dni, 'status' => 'pase',
        ])->assertSessionHasNoErrors();
        $this->assertSame('pase', $s1->fresh()->status->value);

        // Alumno sin asistencias sí puede eliminarse
        $this->actingAs($this->operador)->delete($this->rosterUrl("/{$s2->id}"))
            ->assertSessionHasNoErrors();
        $this->assertModelMissing($s2);
    }

    public function test_roster_enforces_course_and_activity_assignment_middleware(): void
    {
        $this->actingAs($this->operador)->get($this->rosterUrl('', $this->other))
            ->assertForbidden()
            ->assertSee(CheckCourseAssignment::DENIED_MESSAGE);

        $this->actingAs($this->operador)->get($this->rosterUrl('', $this->course, 'taller'))
            ->assertForbidden()
            ->assertSee(CheckCourseAssignment::DENIED_MESSAGE);

        $this->actingAs($this->director)->get($this->rosterUrl('', $this->other))
            ->assertOk();
    }

    /* ------------------------- Importador SINIDE (CSV y XLSX) ------------------------- */

    public function test_csv_template_download(): void
    {
        $this->actingAs($this->operador)->get($this->rosterUrl('/plantilla'))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="plantilla-nomina-sinide.csv"')
            ->assertSee('DNI;CUIL;Apellido;Nombre', false);
    }

    public function test_import_sinide_combined_full_name_csv_with_title_rows(): void
    {
        // Simula exportación directa de planilla SINIDE con filas de título arriba y columna combinada "Apellido y Nombre"
        $csv = implode("\r\n", [
            'ESCUELA DE EDUCACION TECNICA N 2;',
            'REGISTRO DE ASISTENCIA MENSUAL correspondiente al 3RO 1RA;',
            'Apellido y Nombre;Estado de matrícula',
            '"ARANIBAR, IAN EMANUEL";Activo',
            '"BAINA, AGUSTIN JONATAN";Regular',
            '"FILA SIN COMA INVALIDA";Activo',
            ';',
        ]);

        $file = UploadedFile::fake()->createWithContent('sinide_3ro_1ra.csv', $csv);

        $res = $this->actingAs($this->operador)->post($this->rosterUrl('/importar'), ['file' => $file]);
        $res->assertOk()
            ->assertSee('Aranibar')
            ->assertSee('Ian Emanuel')
            ->assertSee('El nombre debe venir como');

        $token = session("roster_import.{$this->course->id}.token");
        $this->assertNotEmpty($token);

        // Token inválido -> redirige con error y no importa nada
        $this->actingAs($this->operador)->post($this->rosterUrl('/importar/confirmar'), ['token' => 'invalido'])
            ->assertSessionHasErrors('roster');
        $this->assertSame(0, Student::where('course_id', $this->course->id)->count());

        // Token válido -> crea los 2 válidos y omite el erróneo
        $res = $this->actingAs($this->operador)->post($this->rosterUrl('/importar'), [
            'file' => UploadedFile::fake()->createWithContent('sinide_3ro_1ra.csv', $csv),
        ])->assertOk();
        $token = session("roster_import.{$this->course->id}.token");

        $this->actingAs($this->operador)->post($this->rosterUrl('/importar/confirmar'), ['token' => $token])
            ->assertRedirect($this->rosterUrl())
            ->assertSessionHas('status');

        $this->assertSame(2, Student::where('course_id', $this->course->id)->count());
        $this->assertDatabaseHas('students', [
            'course_id' => $this->course->id,
            'last_name' => 'Aranibar',
            'first_name' => 'Ian Emanuel',
            'status' => 'activo',
        ]);
    }

    public function test_import_full_columns_csv_creates_updates_and_detects_unchanged(): void
    {
        $existing = Student::create([
            'course_id' => $this->course->id,
            'dni' => '45111222',
            'last_name' => 'Perez',
            'first_name' => 'Juan',
            'status' => 'activo',
        ]);
        Student::create([
            'course_id' => $this->course->id,
            'dni' => '45333444',
            'last_name' => 'Gomez',
            'first_name' => 'Maria',
            'status' => 'activo',
        ]);

        $csv = implode("\r\n", [
            'DNI;CUIL;Apellido;Nombre;Fecha de nacimiento;Estado de matrícula;Código SINIDE',
            '45111222;20-45111222-3;Perez;Juan Carlos;15/03/2010;Activo;SIN-01', // actualiza
            '45333444;;Gomez;Maria;;Activo;',                                      // sin cambios
            '45555666;27-45555666-8;Diaz;Lucia;20/07/2010;Activo;SIN-03',          // nuevo
        ]);

        $this->actingAs($this->operador)->post($this->rosterUrl('/importar'), [
            'file' => UploadedFile::fake()->createWithContent('nomina.csv', $csv),
        ])->assertOk();

        $token = session("roster_import.{$this->course->id}.token");
        $this->actingAs($this->operador)->post($this->rosterUrl('/importar/confirmar'), ['token' => $token])
            ->assertRedirect($this->rosterUrl());

        $this->assertSame(3, Student::where('course_id', $this->course->id)->count());
        $this->assertSame('Juan Carlos', $existing->fresh()->first_name);
        $this->assertSame('SIN-01', $existing->fresh()->sinide_code);
        $this->assertSame('2010-03-15', $existing->fresh()->birth_date->toDateString());
    }

    public function test_import_xlsx_with_shared_strings_and_excel_serial_date(): void
    {
        $xlsxPath = $this->makeTempXlsx([
            ['DNI', 'Apellido', 'Nombre', 'Fecha de nacimiento', 'Estado de matrícula'],
            ['46100200', 'Teran', 'Victor Samuel', '40252', 'Activo'], // 40252 = 2010-03-15 en serie Excel
            ['', 'Zambrano', 'Ian Jano', '10/08/2010', 'Regular'],
        ]);

        try {
            $file = new UploadedFile($xlsxPath, 'nomina_sinide.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

            $this->actingAs($this->operador)->post($this->rosterUrl('/importar'), ['file' => $file])
                ->assertOk()
                ->assertSee('Victor Samuel')
                ->assertSee('Ian Jano');

            $token = session("roster_import.{$this->course->id}.token");
            $this->actingAs($this->operador)->post($this->rosterUrl('/importar/confirmar'), ['token' => $token])
                ->assertRedirect($this->rosterUrl());

            $victor = Student::where('dni', '46100200')->firstOrFail();
            $this->assertSame('Teran', $victor->last_name);
            $this->assertSame('2010-03-15', $victor->birth_date->toDateString());
            $this->assertDatabaseHas('students', ['course_id' => $this->course->id, 'last_name' => 'Zambrano', 'dni' => null]);
        } finally {
            @unlink($xlsxPath);
        }
    }

    /* ------------------------- Calendario Escolar (Director) ------------------------- */

    public function test_director_manages_non_working_days_and_ranges_while_operator_is_forbidden(): void
    {
        $this->actingAs($this->operador)->get(route('director.calendar.index'))->assertForbidden();
        $this->actingAs($this->operador)->post(route('director.calendar.store'), [
            'date' => '2026-08-17', 'reason' => 'Feriado',
        ])->assertForbidden();

        // Día único
        $this->actingAs($this->director)->post(route('director.calendar.store'), [
            'date' => '2026-08-17',
            'reason' => 'Paso a la Inmortalidad del Gral. San Martín',
        ])->assertRedirect(route('director.calendar.index', ['year' => 2026]));

        $this->assertSame(1, NonWorkingDay::count());

        // Rango de viernes 10/07/2026 a martes 14/07/2026 -> viernes, lunes y martes (omite sábado 11 y domingo 12)
        $this->actingAs($this->director)->post(route('director.calendar.store'), [
            'date' => '2026-07-10',
            'date_to' => '2026-07-14',
            'reason' => 'Receso invernal',
        ])->assertRedirect(route('director.calendar.index', ['year' => 2026]));

        $this->assertSame(4, NonWorkingDay::count());
        $this->assertFalse(NonWorkingDay::where('date', '2026-07-11')->exists());

        // Eliminar día
        $day = NonWorkingDay::where('date', '2026-08-17')->firstOrFail();
        $this->actingAs($this->director)->delete(route('director.calendar.destroy', $day))
            ->assertRedirect(route('director.calendar.index', ['year' => 2026]));
        $this->assertModelMissing($day);
    }

    /** Construye un archivo .xlsx mínimo válido en disco usando ZipArchive. */
    private function makeTempXlsx(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sinide_xlsx_').'.xlsx';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $shared = [];
        $sheetRowsXml = '';

        foreach (array_values($rows) as $rIdx => $row) {
            $rowNum = $rIdx + 1;
            $cellsXml = '';
            foreach (array_values($row) as $cIdx => $val) {
                $colLetter = chr(65 + $cIdx);
                $ref = $colLetter.$rowNum;
                if ($val === '') {
                    continue;
                }
                if (ctype_digit((string) $val)) {
                    $cellsXml .= "<c r=\"{$ref}\"><v>{$val}</v></c>";
                } else {
                    $sIdx = count($shared);
                    $shared[] = htmlspecialchars((string) $val, ENT_XML1);
                    $cellsXml .= "<c r=\"{$ref}\" t=\"s\"><v>{$sIdx}</v></c>";
                }
            }
            $sheetRowsXml .= "<row r=\"{$rowNum}\">{$cellsXml}</row>";
        }

        $sharedXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .implode('', array_map(fn ($s) => "<si><t>{$s}</t></si>", $shared))
            .'</sst>';

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .$sheetRowsXml
            .'</sheetData></worksheet>';

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/></Types>');
        $zip->addFromString('xl/sharedStrings.xml', $sharedXml);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        return $path;
    }
}
