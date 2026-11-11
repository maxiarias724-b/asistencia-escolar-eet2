<?php

namespace App\Policies;

use App\Models\DisciplinaryRecord;
use App\Models\Student;
use App\Models\User;

/**
 * Matriz de permisos para el Módulo de Amonestaciones (Disciplina):
 * - Director: Acceso total (Ver, Crear, Editar y Baja lógica en toda la escuela).
 * - Preceptor / Operador: Solo lectura de estudiantes de sus cursos asignados (`course_user`).
 * - Docente: Solo lectura de estudiantes de sus cursos asignados (`course_user`).
 */
class DisciplinaryRecordPolicy
{
    public function viewAny(User $user): bool
    {
        if (! $user->active) {
            return false;
        }

        return $user->isDirector() || $user->isOperador() || $user->isDocente();
    }

    public function view(User $user, DisciplinaryRecord $record): bool
    {
        if (! $user->active) {
            return false;
        }

        if ($user->isDirector()) {
            return true;
        }

        if ($user->isOperador() || $user->isDocente()) {
            $courseId = $record->relationLoaded('student')
                ? $record->student?->course_id
                : Student::query()->whereKey($record->student_id)->value('course_id');

            return $user->hasAssignedCourse($courseId);
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->active && $user->isDirector();
    }

    public function update(User $user, DisciplinaryRecord $record): bool
    {
        return $user->active && $user->isDirector();
    }

    public function delete(User $user, DisciplinaryRecord $record): bool
    {
        return $user->active && $user->isDirector();
    }

    public function restore(User $user, DisciplinaryRecord $record): bool
    {
        return $user->active && $user->isDirector();
    }

    public function forceDelete(User $user, DisciplinaryRecord $record): bool
    {
        return $user->active && $user->isDirector();
    }
}
