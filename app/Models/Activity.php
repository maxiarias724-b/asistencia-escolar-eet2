<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Activity extends Model
{
    use Auditable;

    protected $fillable = [
        'scope_id',
        'name',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function scopeRef(): BelongsTo
    {
        return $this->belongsTo(Scope::class, 'scope_id');
    }

    public function studentAttendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'activity_id');
    }

    public function teacherAttendances(): HasMany
    {
        return $this->hasMany(TeacherAttendance::class, 'activity_id');
    }

    public function teacherClassObservations(): HasMany
    {
        return $this->hasMany(TeacherClassObservation::class, 'activity_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('name');
    }

    public function auditLabel(): string
    {
        return "Actividad «{$this->name}»";
    }
}
