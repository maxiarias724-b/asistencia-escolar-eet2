<?php

namespace App\Models;

use App\Casts\ScopeTypeCast;
use App\Enums\ActivityType;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Permiso delegado por el Director a un operador: Curso + Ámbito (+ Turno opcional).
 * Tabla pivot `course_user` con clave primaria propia (`id`).
 */
class CourseAssignment extends Pivot
{
    use Auditable;

    protected $table = 'course_user';

    public $incrementing = true;

    protected $fillable = [
        'user_id',
        'course_id',
        'scope_id',
        'shift_id',
        'activity_type',
    ];

    protected function casts(): array
    {
        return [
            'activity_type' => ScopeTypeCast::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (CourseAssignment $assignment): void {
            $rawActivity = $assignment->getAttributes()['activity_type'] ?? null;

            if ($assignment->scope_id && blank($rawActivity)) {
                $scope = Scope::find($assignment->scope_id);
                if ($scope) {
                    $assignment->setAttribute('activity_type', $scope->slug);
                }
            } elseif (filled($rawActivity) && ! $assignment->scope_id) {
                $slug = (string) $rawActivity;
                $defaultName = ActivityType::tryFrom($slug)?->label() ?? ucfirst(str_replace(['_', '-'], ' ', $slug));
                $scope = self::withoutAuditing(fn () => Scope::firstOrCreate(
                    ['slug' => $slug],
                    ['name' => $defaultName, 'active' => true]
                ));
                $assignment->scope_id = $scope->id;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function scopeRef(): BelongsTo
    {
        return $this->belongsTo(Scope::class, 'scope_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function auditLabel(): string
    {
        return 'Asignación #'.$this->getKey().' (Usuario #'.$this->user_id.' · Curso #'.$this->course_id.')';
    }
}
