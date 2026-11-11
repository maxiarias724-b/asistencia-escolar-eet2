<?php

namespace App\Policies;

use App\Models\QrIntervention;
use App\Models\Student;
use App\Models\User;

/**
 * Matriz de permisos para el Módulo Programa QR ("Quiero Regresar"):
 * - Director: Acceso total (Ver, Crear y Editar expedientes de toda la institución).
 * - Preceptor / Operador: Solo lectura de expedientes de estudiantes de sus cursos asignados (`course_user`).
 * - Docente: Acceso denegado (información socioeducativa reservada).
 */
class QrInterventionPolicy
{
    public function viewAny(User $user): bool
    {
        if (! $user->active) {
            return false;
        }

        return $user->isDirector() || $user->isOperador();
    }

    public function view(User $user, QrIntervention $intervention): bool
    {
        if (! $user->active || $user->isDocente()) {
            return false;
        }

        if ($user->isDirector()) {
            return true;
        }

        if ($user->isOperador()) {
            $courseId = $intervention->relationLoaded('student')
                ? $intervention->student?->course_id
                : Student::query()->whereKey($intervention->student_id)->value('course_id');

            return $user->hasAssignedCourse($courseId);
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->active && $user->isDirector();
    }

    public function update(User $user, QrIntervention $intervention): bool
    {
        return $user->active && $user->isDirector();
    }

    public function delete(User $user, QrIntervention $intervention): bool
    {
        return $user->active && $user->isDirector();
    }

    public function restore(User $user, QrIntervention $intervention): bool
    {
        return $user->active && $user->isDirector();
    }

    public function forceDelete(User $user, QrIntervention $intervention): bool
    {
        return $user->active && $user->isDirector();
    }
}
