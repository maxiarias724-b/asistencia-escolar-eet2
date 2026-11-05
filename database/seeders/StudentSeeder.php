<?php

namespace Database\Seeders;

use App\Enums\StudentStatus;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    private const STUDENTS_PER_COURSE = 10;

    /** Nómina real de ejemplo de 3° 1° según planilla SINIDE de la EET N.º 2 "Prof. Jesús Raúl Salazar". */
    private const SINIDE_3RO_1RA = [
        ['Aranibar', 'Ian Emanuel', false],
        ['Baina', 'Agustín Jonatan', false],
        ['Behr Huanco', 'Maia Yuliana', true],
        ['Caceres', 'Kiara Isabella', true],
        ['Cala', 'Facundo Ariel Armando', false],
        ['Calisaya', 'Celeste Guadalupe', true],
        ['Camacho Bautista', 'Sofía Yazmín', true],
        ['Cespedes', 'Tiago Daniel Omar', false],
        ['Chacon', 'Faustino Bautista', false],
        ['Coraite', 'Luana Azul', true],
    ];

    private const LAST_NAMES = [
        'Apaza', 'Aramayo', 'Benítez', 'Cabrera', 'Cardozo', 'Choque', 'Condorí', 'Cruz', 'Díaz', 'Escalada',
        'Flores', 'Gallardo', 'Gaspar', 'Geronimo', 'Gutiérrez', 'López', 'Mamani', 'Marquez', 'Mogro', 'Ocampo',
        'Orquera', 'Palacios', 'Pérez', 'Quispe', 'Reinaga', 'Romero', 'Salas', 'Silisque', 'Terán', 'Tolaba',
        'Ugarte', 'Vilte', 'Zambrano', 'Zerpa',
    ];

    private const MALE_NAMES = [
        'Santiago Martín', 'Mateo Ezequiel', 'Benjamín', 'Thiago Sebastián', 'Lautaro',
        'Joaquín Augusto', 'Facundo Daniel', 'Víctor Samuel', 'Máximo Agustín', 'Fabricio David',
    ];

    private const FEMALE_NAMES = [
        'Valentina', 'Sofía', 'Bianca Luján', 'Brisa Aimee', 'Paula Antonella',
        'Rosa Guadalupe', 'Florencia Luisina', 'Josefina Nayra', 'Guadalupe Mariel', 'Maité Yamile',
    ];

    public function run(): void
    {
        $counter = 0;

        foreach (Course::ordered()->get() as $course) {
            $is3ro1ra = $course->year === 3 && $course->division === '1';

            for ($i = 0; $i < self::STUDENTS_PER_COURSE; $i++) {
                $counter++;
                $dni = (string) (46500000 - ($counter * 137));
                $birthYear = now()->year - (11 + $course->year) - ($i % 2);

                if ($is3ro1ra && isset(self::SINIDE_3RO_1RA[$i])) {
                    [$lastName, $firstName, $female] = self::SINIDE_3RO_1RA[$i];
                    $status = StudentStatus::Activo;
                } else {
                    $female = $i % 2 === 1;
                    $lastName = self::LAST_NAMES[($counter + $i) % count(self::LAST_NAMES)];
                    $firstName = ($female ? self::FEMALE_NAMES : self::MALE_NAMES)[($counter + $i) % 10];
                    $status = $i === self::STUDENTS_PER_COURSE - 1
                        ? StudentStatus::Pase
                        : StudentStatus::Activo;
                }

                Student::updateOrCreate(['dni' => $dni], [
                    'cuil' => $this->cuil($dni, $female),
                    'file_number' => sprintf('LEG-A-%04d', $counter),
                    'last_name' => $lastName,
                    'first_name' => $firstName,
                    'birth_date' => sprintf('%d-%02d-%02d', $birthYear, (($counter + $i) % 12) + 1, (($counter * 3 + $i) % 27) + 1),
                    'course_id' => $course->id,
                    'sinide_code' => sprintf('SIN-%d%s-%04d', $course->year, $course->division, $counter),
                    'status' => $status->value,
                    'active' => $status === StudentStatus::Activo,
                ]);
            }
        }
    }

    private function cuil(string $dni, bool $female): string
    {
        $prefix = $female ? '27' : '20';
        $base = $prefix.str_pad($dni, 8, '0', STR_PAD_LEFT);
        $weights = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];

        $sum = 0;
        foreach (str_split($base) as $i => $digit) {
            $sum += (int) $digit * $weights[$i];
        }

        $check = 11 - ($sum % 11);

        if ($check === 11) {
            $check = 0;
        } elseif ($check === 10) {
            $base = '23'.str_pad($dni, 8, '0', STR_PAD_LEFT);
            $check = $female ? 4 : 9;
        }

        return substr($base, 0, 2).'-'.substr($base, 2, 8).'-'.$check;
    }
}
