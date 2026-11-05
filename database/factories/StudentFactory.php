<?php

namespace Database\Factories;

use App\Enums\StudentStatus;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Student>
 */
class StudentFactory extends Factory
{
    public function definition(): array
    {
        $dni = (string) fake()->unique()->numberBetween(40000000, 49999999);

        return [
            'dni' => $dni,
            'cuil' => '20-'.$dni.'-'.fake()->numberBetween(0, 9),
            'file_number' => 'LEG-A-'.fake()->unique()->numerify('#####'),
            'last_name' => fake()->lastName(),
            'first_name' => fake()->firstName(),
            'birth_date' => fake()->dateTimeBetween('-18 years', '-12 years')->format('Y-m-d'),
            'course_id' => Course::query()->inRandomOrder()->value('id'),
            'sinide_code' => 'SIN-'.fake()->unique()->numerify('########'),
            'status' => StudentStatus::Activo->value,
            'active' => true,
        ];
    }
}
