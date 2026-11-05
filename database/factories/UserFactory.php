<?php

namespace Database\Factories;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        $first = fake()->firstName();
        $last = fake()->lastName();

        return [
            'first_name' => $first,
            'last_name' => $last,
            'name' => "{$first} {$last}",
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => Role::Operador->value,
            'active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function director(): static
    {
        return $this->state(fn () => ['role' => Role::Director->value]);
    }

    public function docente(): static
    {
        return $this->state(fn () => ['role' => Role::Docente->value]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
