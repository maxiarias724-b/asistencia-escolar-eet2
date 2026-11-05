<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Teacher>
 */
class TeacherFactory extends Factory
{
    public function definition(): array
    {
        $dni = (string) fake()->unique()->numberBetween(24000000, 38999999);

        return [
            'dni' => $dni,
            'cuil' => '20-'.$dni.'-'.fake()->numberBetween(0, 9),
            'file_number' => 'LEG-D-'.fake()->unique()->numerify('####'),
            'last_name' => fake()->lastName(),
            'first_name' => fake()->firstName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '388-'.fake()->numerify('4######'),
            'specialty_area' => fake()->randomElement(['Ciclo Básico', 'Construcciones', 'Electrónica', 'Educación Física']),
            'active' => true,
        ];
    }
}
