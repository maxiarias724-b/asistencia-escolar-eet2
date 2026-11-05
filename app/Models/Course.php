<?php

namespace App\Models;

use App\Casts\ShiftCast;
use App\Enums\Cycle;
use App\Enums\Shift as ShiftEnum;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use Auditable;

    protected $fillable = [
        'year',
        'division',
        'cycle',
        'specialty_id',
        'shift',
        'shift_id',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'cycle' => Cycle::class,
            'shift' => ShiftCast::class,
            'active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Course $course): void {
            // 1°-2° Ciclo Básico, 3°-7° Ciclo Superior Técnico.
            $course->cycle = Cycle::fromYear((int) $course->year);

            // Sincroniza shift (slug) <-> shift_id (tabla configurable `shifts`).
            $rawShift = $course->getAttributes()['shift'] ?? null;

            if ($course->shift_id && blank($rawShift)) {
                $shiftModel = Shift::find($course->shift_id);
                if ($shiftModel) {
                    $course->setAttribute('shift', $shiftModel->slug);
                }
            } elseif (filled($rawShift) && ! $course->shift_id) {
                $slug = (string) $rawShift;
                $defaultName = ShiftEnum::tryFrom($slug)?->label() ?? ucfirst(str_replace(['_', '-'], ' ', $slug));
                $shiftModel = self::withoutAuditing(fn () => Shift::firstOrCreate(
                    ['slug' => $slug],
                    ['name' => $defaultName, 'active' => true]
                ));
                $course->shift_id = $shiftModel->id;
            }

            if (! $course->specialty_id && (int) $course->year <= 2) {
                $basicId = Specialty::query()->where('name', 'Ciclo Básico')->value('id');
                if ($basicId) {
                    $course->specialty_id = $basicId;
                }
            }
        });
    }

    /* ----------------------------- Relaciones ----------------------------- */

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function shiftRef(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CourseAssignment::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_user')
            ->using(CourseAssignment::class)
            ->withPivot(['id', 'activity_type', 'scope_id', 'shift_id'])
            ->withTimestamps();
    }

    /* ------------------------------ Accessors ----------------------------- */

    /** Nombre corto: "5° 1°". */
    protected function name(): Attribute
    {
        return Attribute::get(fn () => "{$this->year}° {$this->division}°");
    }

    /** Nombre completo: "5° 1° - Mañana". */
    protected function fullName(): Attribute
    {
        return Attribute::get(function () {
            $shiftLabel = $this->relationLoaded('shiftRef') && $this->shiftRef
                ? $this->shiftRef->name
                : ($this->shift?->label() ?? 'Sin turno');

            return "{$this->name} - {$shiftLabel}";
        });
    }

    /* -------------------------------- Scopes ------------------------------ */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('year')->orderBy('division')->orderBy('shift');
    }

    public function auditLabel(): string
    {
        return "Curso «{$this->name}»";
    }
}
