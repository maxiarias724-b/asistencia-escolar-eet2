<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Scope extends Model
{
    use Auditable;

    protected $fillable = [
        'name',
        'slug',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Scope $scope): void {
            if (blank($scope->slug) && filled($scope->name)) {
                $scope->slug = Str::slug($scope->name, '_');
            }
        });
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'scope_id');
    }

    public function courseAssignments(): HasMany
    {
        return $this->hasMany(CourseAssignment::class, 'scope_id');
    }

    public function studentAttendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'scope_id');
    }

    public function label(): string
    {
        return $this->name;
    }

    /** Resuelve un slug de ámbito contra la tabla `scopes` o el enum inicial `ActivityType`. */
    public static function resolveSlug(string $slug): \App\Enums\ActivityType|\App\Casts\DynamicValue|null
    {
        $record = static::active()->where('slug', $slug)->first();
        if ($record) {
            if ($enum = \App\Enums\ActivityType::tryFrom($record->slug)) {
                return $enum;
            }

            return new \App\Casts\DynamicValue($record->slug, $record->name);
        }

        return \App\Enums\ActivityType::tryFrom($slug);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('id');
    }

    public function auditLabel(): string
    {
        return "Ámbito «{$this->name}»";
    }
}
