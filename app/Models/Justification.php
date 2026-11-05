<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Justification extends Model
{
    use Auditable;

    protected $fillable = [
        'attendance_id',
        'student_attendance_id',
        'justified_by',
        'reason_text',
        'certificate_path',
        'justified_at',
    ];

    protected function casts(): array
    {
        return [
            'justified_at' => 'datetime',
        ];
    }

    protected function studentAttendanceId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attributes['attendance_id'] ?? null,
            set: fn ($value) => ['attendance_id' => $value],
        );
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function studentAttendance(): BelongsTo
    {
        return $this->belongsTo(StudentAttendance::class, 'attendance_id');
    }

    public function justifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'justified_by');
    }

    public function hasCertificate(): bool
    {
        return filled($this->certificate_path);
    }

    protected static function booted(): void
    {
        static::deleted(fn (Justification $j) => $j->deleteCertificateFile());
    }

    public function deleteCertificateFile(?string $path = null): void
    {
        $path ??= $this->certificate_path;

        if (filled($path)) {
            Storage::disk('local')->delete($path);
            Storage::disk('public')->delete($path);
        }
    }

    /** Verifica si el certificado existe en disco privado (`local`) o `public`. */
    public function certificateExists(): bool
    {
        if (! $this->hasCertificate()) {
            return false;
        }

        return Storage::disk('local')->exists($this->certificate_path)
            || Storage::disk('public')->exists($this->certificate_path);
    }

    public function auditLabel(): string
    {
        return 'Justificación #'.$this->getKey().' (Asistencia #'.$this->attendance_id.')';
    }
}
