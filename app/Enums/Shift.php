<?php

namespace App\Enums;

enum Shift: string
{
    // Valores ASCII en BD (sin "ñ") para evitar problemas de collation/enum; la etiqueta conserva el acento.
    case Manana = 'manana';
    case Tarde = 'tarde';
    case Vespertino = 'vespertino';

    public function label(): string
    {
        return match ($this) {
            self::Manana => 'Mañana',
            self::Tarde => 'Tarde',
            self::Vespertino => 'Vespertino',
        };
    }
}
