<?php

namespace App\Casts;

use App\Enums\ActivityType;
use App\Models\Scope;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Permite trabajar con ámbitos configurables en la tabla `scopes` (p. ej. "examenes", "salidas_educativas")
 * manteniendo compatibilidad con `App\Enums\ActivityType` para los ámbitos iniciales.
 */
class ScopeTypeCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ActivityType|DynamicValue|null
    {
        if ($value === null || $value === '') {
            if (! empty($attributes['scope_id'])) {
                $scope = Scope::find($attributes['scope_id']);
                if ($scope) {
                    return ActivityType::tryFrom($scope->slug) ?? new DynamicValue($scope->slug, $scope->name);
                }
            }

            return null;
        }

        $slug = (string) $value;
        if ($enum = ActivityType::tryFrom($slug)) {
            return $enum;
        }

        $name = Scope::query()->where('slug', $slug)->value('name')
            ?? ucfirst(str_replace(['_', '-'], ' ', $slug));

        return new DynamicValue($slug, $name);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof ActivityType || $value instanceof DynamicValue) {
            return $value->value;
        }

        if ($value instanceof Scope) {
            return $value->slug;
        }

        return (string) $value;
    }
}
