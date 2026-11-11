<?php

namespace App\Enums;

/**
 * Estados del expediente socioeducativo del Programa QR ("Quiero Regresar").
 */
enum QrInterventionStatus: string
{
    case Evaluacion = 'evaluacion';
    case IntervencionActiva = 'intervencion_activa';
    case Revinculado = 'revinculado';
    case Abandono = 'abandono';

    public function label(): string
    {
        return match ($this) {
            self::Evaluacion => 'En evaluación',
            self::IntervencionActiva => 'Intervención activa',
            self::Revinculado => 'Revinculado',
            self::Abandono => 'Abandono',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Evaluacion => 'bg-amber-100 text-amber-800 border border-amber-300',
            self::IntervencionActiva => 'bg-sky-100 text-sky-800 border border-sky-300',
            self::Revinculado => 'bg-emerald-100 text-emerald-800 border border-emerald-300',
            self::Abandono => 'bg-red-100 text-red-800 border border-red-300',
        };
    }
}
