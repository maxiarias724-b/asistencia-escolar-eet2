<?php

namespace App\Enums;

enum Cycle: string
{
    case PrimerCiclo = 'primer_ciclo';   // Ciclo Básico: 1° y 2° año
    case SegundoCiclo = 'segundo_ciclo'; // Ciclo Superior Técnico: 3° a 6°/7° año

    public function label(): string
    {
        return match ($this) {
            self::PrimerCiclo => 'Primer Ciclo (Ciclo Básico)',
            self::SegundoCiclo => 'Segundo Ciclo (Ciclo Superior Técnico)',
        };
    }

    /** Devuelve el ciclo que corresponde a un año (1° a 7°). */
    public static function fromYear(int $year): self
    {
        return $year <= 2 ? self::PrimerCiclo : self::SegundoCiclo;
    }
}
