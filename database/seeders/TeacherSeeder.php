<?php

namespace Database\Seeders;

use App\Models\Teacher;
use Illuminate\Database\Seeder;

class TeacherSeeder extends Seeder
{
    /** Plantel docente inicial de prueba para la EET N.º 2 "Prof. Jesús Raúl Salazar". */
    public function run(): void
    {
        $teachers = [
            [
                'dni' => '28450112',
                'cuil' => '20-28450112-4',
                'file_number' => 'LEG-D-0101',
                'last_name' => 'Mamani',
                'first_name' => 'Jorge Alberto',
                'email' => 'jmamani@eet2jujuy.edu.ar',
                'phone' => '388-4210112',
                'specialty_area' => 'Construcciones',
            ],
            [
                'dni' => '30118940',
                'cuil' => '27-30118940-6',
                'file_number' => 'LEG-D-0102',
                'last_name' => 'Alancay',
                'first_name' => 'Silvina Noemí',
                'email' => 'salancay@eet2jujuy.edu.ar',
                'phone' => '388-4338940',
                'specialty_area' => 'Matemática — Ciclo Básico',
            ],
            [
                'dni' => '26904521',
                'cuil' => '20-26904521-9',
                'file_number' => 'LEG-D-0103',
                'last_name' => 'Condorí',
                'first_name' => 'Rubén Darío',
                'email' => 'rcondori@eet2jujuy.edu.ar',
                'phone' => '388-4554521',
                'specialty_area' => 'Electrónica',
            ],
            [
                'dni' => '32776310',
                'cuil' => '27-32776310-2',
                'file_number' => 'LEG-D-0104',
                'last_name' => 'Sosa',
                'first_name' => 'Valeria Soledad',
                'email' => 'vsosa@eet2jujuy.edu.ar',
                'phone' => '388-4666310',
                'specialty_area' => 'Lengua y Literatura',
            ],
            [
                'dni' => '29540887',
                'cuil' => '20-29540887-5',
                'file_number' => 'LEG-D-0105',
                'last_name' => 'Gómez',
                'first_name' => 'Carlos Eduardo',
                'email' => 'cgomez@eet2jujuy.edu.ar',
                'phone' => '388-4770887',
                'specialty_area' => 'Educación Física',
            ],
            [
                'dni' => '27883190',
                'cuil' => '27-27883190-1',
                'file_number' => 'LEG-D-0106',
                'last_name' => 'Benítez',
                'first_name' => 'Laura Beatriz',
                'email' => 'lbenitez@eet2jujuy.edu.ar',
                'phone' => '388-4883190',
                'specialty_area' => 'Jefatura de Taller',
            ],
        ];

        foreach ($teachers as $teacher) {
            Teacher::updateOrCreate(
                ['dni' => $teacher['dni']],
                $teacher + ['active' => true]
            );
        }
    }
}
