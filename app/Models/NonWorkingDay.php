<?php

namespace App\Models;

use App\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/** Día hábil del calendario en que no se dicta actividad escolar (feriado, receso, jornada). */
class NonWorkingDay extends Model
{
    use Auditable;

    protected $fillable = ['date', 'reason'];

    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->startOfDay() : null,
            set: fn ($value) => $value ? Carbon::parse($value)->toDateString() : null,
        );
    }

    public function auditLabel(): string
    {
        return "Día sin actividad «{$this->reason}» (".$this->date?->format('d/m/Y').')';
    }
}
