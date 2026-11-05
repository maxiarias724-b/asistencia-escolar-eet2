<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Modelo explícito de Asistencia de Estudiantes sobre la tabla `attendances`,
 * compatible con `recorded_by` / `user_id` y con todos los ámbitos configurables.
 */
class StudentAttendance extends Attendance
{
    protected $table = 'attendances';

    protected $fillable = [
        'student_id',
        'course_id',
        'scope_id',
        'activity_id',
        'shift_id',
        'user_id',
        'recorded_by',
        'date',
        'activity_type',
        'status',
        'absence_value',
        'observation',
    ];

    protected function recordedBy(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attributes['user_id'] ?? null,
            set: fn ($value) => ['user_id' => $value],
        );
    }
}
