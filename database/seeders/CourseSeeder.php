<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Shift;
use App\Models\Specialty;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    /**
     * Cursos de 1° a 6° año de la EET N.º 2 "Prof. Jesús Raúl Salazar":
     *  - 1° y 2° año: Ciclo Básico (divisiones 1° Mañana y 2° Tarde).
     *  - 3° a 6° año: Ciclo Superior Técnico:
     *      - División 1° (Mañana) -> Construcciones
     *      - División 2° (Tarde)  -> Electrónica
     *      - División 3° (Vespertino, en 5° y 6°) -> Construcciones
     */
    public function run(): void
    {
        $manana = Shift::where('slug', 'manana')->firstOrFail();
        $tarde = Shift::where('slug', 'tarde')->firstOrFail();
        $vespertino = Shift::where('slug', 'vespertino')->firstOrFail();

        $basico = Specialty::where('name', 'Ciclo Básico')->firstOrFail();
        $construcciones = Specialty::where('name', 'Construcciones')->firstOrFail();
        $electronica = Specialty::where('name', 'Electrónica')->firstOrFail();

        for ($year = 1; $year <= 6; $year++) {
            $definitions = $year <= 2
                ? [
                    ['division' => '1', 'shift' => $manana, 'specialty' => $basico],
                    ['division' => '2', 'shift' => $tarde, 'specialty' => $basico],
                ]
                : array_filter([
                    ['division' => '1', 'shift' => $manana, 'specialty' => $construcciones],
                    ['division' => '2', 'shift' => $tarde, 'specialty' => $electronica],
                    $year >= 5 ? ['division' => '3', 'shift' => $vespertino, 'specialty' => $construcciones] : null,
                ]);

            foreach ($definitions as $def) {
                Course::updateOrCreate(
                    [
                        'year' => $year,
                        'division' => $def['division'],
                        'shift' => $def['shift']->slug,
                    ],
                    [
                        'specialty_id' => $def['specialty']->id,
                        'shift_id' => $def['shift']->id,
                        'active' => true,
                    ]
                );
            }
        }
    }
}
