<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Teacher extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'dni',
        'cuil',
        'file_number',
        'last_name',
        'first_name',
        'email',
        'phone',
        'specialty_area',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(TeacherAttendance::class);
    }

    /** "APELLIDO, Nombre" en formato institucional SINIDE. */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => mb_strtoupper($this->last_name).', '.$this->first_name);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeAlphabetical(Builder $query): Builder
    {
        return $query->orderBy('last_name')->orderBy('first_name');
    }

    public function auditLabel(): string
    {
        return "Docente «{$this->last_name}, {$this->first_name}» (DNI {$this->dni})";
    }
}
