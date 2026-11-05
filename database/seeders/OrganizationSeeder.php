<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Scope;
use App\Models\Shift;
use App\Models\Specialty;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    /**
     * Estructura académica base configurable de la EET N.º 2 "Prof. Jesús Raúl Salazar":
     *  - Turnos: Mañana, Tarde, Vespertino
     *  - Especialidades: Ciclo Básico, Construcciones, Electrónica
     *  - Ámbitos iniciales: Clases, Educación Física, Taller
     *  - Actividades base asociadas a cada ámbito
     */
    public function run(): void
    {
        $shifts = [
            ['slug' => 'manana', 'name' => 'Mañana'],
            ['slug' => 'tarde', 'name' => 'Tarde'],
            ['slug' => 'vespertino', 'name' => 'Vespertino'],
        ];
        foreach ($shifts as $item) {
            Shift::updateOrCreate(['slug' => $item['slug']], ['name' => $item['name'], 'active' => true]);
        }

        $specialties = [
            ['name' => 'Ciclo Básico', 'code' => 'CB'],
            ['name' => 'Construcciones', 'code' => 'CONST'],
            ['name' => 'Electrónica', 'code' => 'ELEC'],
        ];
        foreach ($specialties as $spec) {
            Specialty::updateOrCreate(['name' => $spec['name']], ['code' => $spec['code'], 'active' => true]);
        }

        $scopesWithActivities = [
            [
                'slug' => 'clase',
                'name' => 'Clases',
                'activities' => [
                    'Clase Áulica General',
                    'Matemática',
                    'Lengua y Literatura',
                    'Física Aplicada',
                ],
            ],
            [
                'slug' => 'educacion_fisica',
                'name' => 'Educación Física',
                'activities' => [
                    'Educación Física General',
                    'Prácticas Deportivas',
                ],
            ],
            [
                'slug' => 'taller',
                'name' => 'Taller',
                'activities' => [
                    'Taller de Ciclo Básico',
                    'Taller de Construcciones',
                    'Taller de Electrónica',
                ],
            ],
        ];

        foreach ($scopesWithActivities as $scopeData) {
            $scope = Scope::updateOrCreate(
                ['slug' => $scopeData['slug']],
                ['name' => $scopeData['name'], 'active' => true]
            );

            foreach ($scopeData['activities'] as $activityName) {
                Activity::updateOrCreate(
                    ['scope_id' => $scope->id, 'name' => $activityName],
                    ['active' => true]
                );
            }
        }
    }
}
