<?php

namespace App\Enums;

enum ActivityType: string
{
    case Clase = 'clase';
    case EducacionFisica = 'educacion_fisica';
    case Taller = 'taller';

    public function label(): string
    {
        return match ($this) {
            self::Clase => 'Clase',
            self::EducacionFisica => 'Educación Física',
            self::Taller => 'Taller',
        };
    }
}
