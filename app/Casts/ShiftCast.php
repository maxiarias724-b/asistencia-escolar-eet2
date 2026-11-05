<?php

namespace App\Casts;

use App\Enums\Shift as ShiftEnum;
use App\Models\Shift as ShiftModel;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Permite trabajar con turnos configurables en la tabla `shifts` (p. ej. "noche")
 * manteniendo compatibilidad con el enum base `App\Enums\Shift` (`->value`, `->label()`).
 */
class ShiftCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ShiftEnum|DynamicValue|null
    {
        if ($value === null || $value === '') {
            if (! empty($attributes['shift_id'])) {
                $record = ShiftModel::find($attributes['shift_id']);
                if ($record) {
                    return ShiftEnum::tryFrom($record->slug) ?? new DynamicValue($record->slug, $record->name);
                }
            }

            return null;
        }

        $slug = (string) $value;
        if ($enum = ShiftEnum::tryFrom($slug)) {
            return $enum;
        }

        $name = ShiftModel::query()->where('slug', $slug)->value('name')
            ?? ucfirst(str_replace(['_', '-'], ' ', $slug));

        return new DynamicValue($slug, $name);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof ShiftEnum || $value instanceof DynamicValue) {
            return $value->value;
        }

        if ($value instanceof ShiftModel) {
            return $value->slug;
        }

        return (string) $value;
    }
}
