<?php

namespace App\Enums;

/** Estado de matrícula (SINIDE). */
enum StudentStatus: string
{
    case Activo = 'activo';
    case Libre = 'libre';
    case Pase = 'pase';
    case Baja = 'baja';

    public function label(): string
    {
        return match ($this) {
            self::Activo => 'Activo',
            self::Libre => 'Libre',
            self::Pase => 'Pase',
            self::Baja => 'Baja',
        };
    }
}
