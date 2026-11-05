<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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
     */
    public function run(): void
    {
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
