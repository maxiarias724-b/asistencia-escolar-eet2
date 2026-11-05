<?php

namespace App\Enums;

enum Role: string
{
    case Director = 'director';
    case Operador = 'operador';
    case Docente = 'docente';

    public function label(): string
    {
        return match ($this) {
            self::Director => 'Director',
            self::Operador => 'Preceptor / Operador',
            self::Docente => 'Docente',
        };
    }
}
