<?php

namespace App\Models;

use App\Enums\StudentStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'dni',
        'cuil',
        'file_number',
        'last_name',
        'first_name',
        'birth_date',
        'course_id',
        'sinide_code',
        'status',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'status' => StudentStatus::class,
            'active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Student $student): void {
            $status = $student->status ?? StudentStatus::Activo;
            if ($student->isDirty('status')) {
                $student->active = $status === StudentStatus::Activo;
            } elseif ($student->isDirty('active') && ! $student->active && $status === StudentStatus::Activo) {
                $student->status = StudentStatus::Baja;
            } elseif ($student->getAttribute('active') === null) {
                $student->active = $status === StudentStatus::Activo;
            }
        });
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** "APELLIDO, Nombre" como se muestra en las nóminas de SINIDE. */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => mb_strtoupper($this->last_name).', '.$this->first_name);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true)->where('status', StudentStatus::Activo->value);
    }

    public function scopeAlphabetical(Builder $query): Builder
    {
        return $query->orderBy('last_name')->orderBy('first_name');
    }

    public function auditLabel(): string
    {
        return "Estudiante «{$this->last_name}, {$this->first_name}»".($this->dni ? " (DNI {$this->dni})" : '');
    }
}
