<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherAttendance extends Model
{
    use Auditable;

    protected $fillable = [
        'teacher_id',
        'shift_id',
        'activity_id',
        'recorded_by',
        'date',
        'status',
        'absence_value',
        'observation',
    ];

    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'absence_value' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (TeacherAttendance $attendance): void {
            $status = $attendance->status;

            if ($status !== AttendanceStatus::Justificada) {
                $attendance->absence_value = $status->weight();
            } elseif ((float) $attendance->absence_value <= 0) {
                $attendance->absence_value = 1;
            }
        });
    }

    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->startOfDay() : null,
            set: fn ($value) => $value ? Carbon::parse($value)->toDateString() : null,
        );
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function auditLabel(): string
    {
        return 'Asistencia Docente #'.$this->getKey().' ('.$this->date?->format('d/m/Y').')';
    }
}
