<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Course;
use App\Models\Scope;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Cuentas institucionales de la EET N.º 2 "Prof. Jesús Raúl Salazar" (contraseña de desarrollo: "password"):
     *  - admin            (admin@eet2jujuy.edu.ar)     -> Rol Director (acceso global)
     *  - preceptor_clases (preceptor@eet2jujuy.edu.ar) -> Rol Operador (Clases: 3° 1°, 4° 1°, 5° 1°)
     *  - profe_edfisica   (edfisica@eet2jujuy.edu.ar)  -> Rol Operador (Educación Física: 1° 1°, 2° 1°, 3° 1°)
     *  - profe_taller     (taller@eet2jujuy.edu.ar)    -> Rol Operador (Taller: 5° 1°, 5° 2°, 6° 1°)
     *  - docente_consulta (docente@eet2jujuy.edu.ar)   -> Rol Docente (acceso de consulta)
     */
    public function run(): void
    {
        User::updateOrCreate(['username' => 'admin'], [
            'first_name' => 'Dirección',
            'last_name' => 'General EET N° 2',
            'name' => 'Dirección General EET N° 2',
            'email' => 'admin@eet2jujuy.edu.ar',
            'password' => 'password',
            'role' => Role::Director,
            'active' => true,
        ]);

        $preceptor = $this->createUser(
            'preceptor_clases',
            'María',
            'Fernández',
            'preceptor@eet2jujuy.edu.ar',
            Role::Operador
        );
        $edFisica = $this->createUser(
            'profe_edfisica',
            'Carlos',
            'Gómez',
            'edfisica@eet2jujuy.edu.ar',
            Role::Operador
        );
        $taller = $this->createUser(
            'profe_taller',
            'Laura',
            'Benítez',
            'taller@eet2jujuy.edu.ar',
            Role::Operador
        );

        $this->createUser(
            'docente_consulta',
            'Jorge',
            'Mamani',
            'docente@eet2jujuy.edu.ar',
            Role::Docente
        );

        $scopeClases = Scope::where('slug', 'clase')->firstOrFail();
        $scopeEdFisica = Scope::where('slug', 'educacion_fisica')->firstOrFail();
        $scopeTaller = Scope::where('slug', 'taller')->firstOrFail();

        $this->assign($preceptor, $scopeClases, [[3, '1'], [4, '1'], [5, '1']]);
        $this->assign($edFisica, $scopeEdFisica, [[1, '1'], [2, '1'], [3, '1']]);
        $this->assign($taller, $scopeTaller, [[5, '1'], [5, '2'], [6, '1']]);
    }

    private function createUser(string $username, string $firstName, string $lastName, string $email, Role $role): User
    {
        return User::updateOrCreate(['username' => $username], [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => "{$firstName} {$lastName}",
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'active' => true,
        ]);
    }

    /** @param  array<int, array{0:int,1:string}>  $courses */
    private function assign(User $user, Scope $scope, array $courses): void
    {
        foreach ($courses as [$year, $division]) {
            $course = Course::where('year', $year)->where('division', $division)->firstOrFail();
            $user->assignCourse($course, $scope, $course->shift_id);
        }
    }
}
