<?php

namespace App\Enums;

/**
 * Estados del registro mensual SINIDE.
 * Cada estado descuenta una fracción de "inasistencia": C = 1, M = ½, Q = ¼.
 */
enum AttendanceStatus: string
{
    case Presente = 'presente';
    case Ausente = 'ausente';
    case Tardanza = 'tardanza';
    case MediaFalta = 'media_falta';
    case Justificada = 'justificada';

    public function label(): string
    {
        return match ($this) {
            self::Presente => 'Presente',
            self::Ausente => 'Ausente',
            self::Tardanza => 'Tardanza',
            self::MediaFalta => 'Media falta',
            self::Justificada => 'Inasistencia Justificada',
        };
    }

    /** Letra usada en la grilla (como el registro de SINIDE). */
    public function code(): string
    {
        return match ($this) {
            self::Presente => 'P',
            self::Ausente => 'C',
            self::Tardanza => 'Q',
            self::MediaFalta => 'M',
            self::Justificada => 'J',
        };
    }

    /** Valor de inasistencia que suma el estado (la justificada conserva el de la falta original). */
    public function weight(): float
    {
        return match ($this) {
            self::Presente => 0.0,
            self::Ausente => 1.0,
            self::Tardanza => 0.25,
            self::MediaFalta => 0.5,
            self::Justificada => 1.0,
        };
    }

    /** Estados sobre los que tiene sentido presentar una justificación. */
    public function canBeJustified(): bool
    {
        return $this !== self::Presente;
    }

    /** Estados que el operador puede elegir directamente (la justificada pasa por el modal). */
    public static function selectable(): array
    {
        return [self::Presente, self::Ausente, self::Tardanza, self::MediaFalta];
    }

    /** Cuenta como falta injustificada (la justificada deja de computar como tal). */
    public function countsAsUnjustifiedAbsence(): bool
    {
        return $this !== self::Presente && $this !== self::Justificada;
    }
}
