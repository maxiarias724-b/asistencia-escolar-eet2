<?php

namespace Database\Seeders;

use App\Models\NonWorkingDay;
use Illuminate\Database\Seeder;

class NonWorkingDaySeeder extends Seeder
{
    /**
     * Feriados nacionales de Argentina 2026 (días de semana). Referencial: el Director debe
     * completar/ajustar con el calendario escolar oficial de su jurisdicción (receso invernal, días institucionales, etc.).
     */
    private const HOLIDAYS_2026 = [
        '2026-01-01' => 'Año Nuevo',
        '2026-02-16' => 'Carnaval',
        '2026-02-17' => 'Carnaval',
        '2026-03-23' => 'Día no laborable con fines turísticos',
        '2026-03-24' => 'Día Nacional de la Memoria',
        '2026-04-02' => 'Día del Veterano y de los Caídos en la Guerra de Malvinas',
        '2026-04-03' => 'Viernes Santo',
        '2026-05-01' => 'Día del Trabajador',
        '2026-05-25' => 'Día de la Revolución de Mayo',
        '2026-06-15' => 'Paso a la Inmortalidad del Gral. Güemes (trasladado)',
        '2026-07-09' => 'Día de la Independencia',
        '2026-07-10' => 'Día no laborable con fines turísticos',
        '2026-08-17' => 'Paso a la Inmortalidad del Gral. San Martín',
        '2026-10-12' => 'Día del Respeto a la Diversidad Cultural',
        '2026-11-23' => 'Día de la Soberanía Nacional (trasladado)',
        '2026-12-07' => 'Día no laborable con fines turísticos',
        '2026-12-08' => 'Inmaculada Concepción de María',
        '2026-12-25' => 'Navidad',
    ];

    public function run(): void
    {
        foreach (self::HOLIDAYS_2026 as $date => $reason) {
            NonWorkingDay::updateOrCreate(['date' => $date], ['reason' => $reason]);
        }
    }
}
