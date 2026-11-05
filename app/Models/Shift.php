<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Shift extends Model
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
        static::saving(function (Shift $shift): void {
            if (blank($shift->slug) && filled($shift->name)) {
                $shift->slug = Str::slug($shift->name, '_');
            }
        });
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'shift_id');
    }

    public function courseAssignments(): HasMany
    {
        return $this->hasMany(CourseAssignment::class, 'shift_id');
    }

    public function studentAttendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'shift_id');
    }

    public function teacherAttendances(): HasMany
    {
        return $this->hasMany(TeacherAttendance::class, 'shift_id');
    }

    public function label(): string
    {
        return $this->name;
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
        return "Turno «{$this->name}»";
    }
}
