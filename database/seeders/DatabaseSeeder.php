<?php

namespace Database\Seeders;

use App\Services\InstitutionalStateSyncService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DatabaseSeeder extends Seeder
{
    /**
     * Orden de dependencia referencial para la EET N.º 2 "Prof. Jesús Raúl Salazar":
     *  1. Calendario escolar (feriados/recesos)
     *  2. Organización configurable (Turnos, Especialidades, Ámbitos y Actividades)
     *  3. Cursos y divisiones (1° a 6° año)
     *  4. Usuarios (Director, Preceptores/Operadores, Docente) y asignaciones course_user
     *  5. Nómina SINIDE de Estudiantes y Plantel Docente
     *  6. Asistencias demo (estudiantes + docentes) y justificaciones auditables
     *  7. Estado institucional sincronizado (`institutional_state.json`) si está presente
     */
    public function run(): void
    {
        $syncService = app(InstitutionalStateSyncService::class);

        if (! app()->runningUnitTests() && File::exists($syncService->snapshotPath())) {
            $syncService->importSnapshot(true);

            return;
        }

        DB::transaction(function (): void {
            $this->call([
                NonWorkingDaySeeder::class,
                OrganizationSeeder::class,
                CourseSeeder::class,
                UserSeeder::class,
                StudentSeeder::class,
                TeacherSeeder::class,
                AttendanceDemoSeeder::class,
            ]);
        });
    }
}
