<?php

namespace App\Http\Middleware;

use App\Casts\DynamicValue;
use App\Enums\ActivityType;
use App\Models\Course;
use App\Models\Scope;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe el acceso a la asistencia por curso y ámbito configurable (`scopes`).
 *
 * - El Director pasa siempre.
 * - Un Operador solo pasa si el Director le asignó explícitamente ese curso
 *   (y ese ámbito/turno, cuando la petición los indica).
 * - Un Docente no tiene permisos de carga operativa en cursos.
 */
class CheckCourseAssignment
{
    public const DENIED_MESSAGE = 'Acceso Denegado: No tenés permiso para realizar el control de asistencia en este curso';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->active) {
            return $this->deny($request, 401, 'Tu sesión no es válida o tu cuenta está inactiva.');
        }

        if ($user->isDirector()) {
            return $next($request);
        }

        if (! $user->isOperador()) {
            return $this->deny($request, 403);
        }

        $courseId = $this->resolveCourseId($request);
        $scope = $this->resolveScope($request);

        // Ámbito inválido (valor manipulado en la URL o formulario): se bloquea.
        if ($scope === false) {
            return $this->deny($request, 403);
        }

        $shiftId = $request->filled('shift_id') && is_numeric($request->input('shift_id'))
            ? (int) $request->input('shift_id')
            : null;

        if ($courseId !== null) {
            if (! $user->canManageAttendance($courseId, $scope, $shiftId)) {
                return $this->deny($request, 403);
            }
        } elseif ($scope !== null) {
            $slug = $scope instanceof Scope ? $scope->slug : $scope->value;
            $scopeId = $scope instanceof Scope ? $scope->id : Scope::query()->where('slug', $slug)->value('id');

            $hasScope = $user->assignments()->where(function ($q) use ($slug, $scopeId) {
                $q->where('activity_type', $slug);
                if ($scopeId) {
                    $q->orWhere('scope_id', $scopeId);
                }
            })->exists();

            if (! $hasScope) {
                return $this->deny($request, 403);
            }
        }

        return $next($request);
    }

    private function resolveCourseId(Request $request): ?int
    {
        $route = $request->route('course');

        if ($route instanceof Course) {
            return $route->getKey();
        }

        $value = $route ?? $request->input('course_id', $request->input('course'));

        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : 0;
    }

    /** @return ActivityType|Scope|DynamicValue|null|false */
    private function resolveScope(Request $request): ActivityType|Scope|DynamicValue|null|false
    {
        $scopeParam = $request->route('scope') ?? $request->input('scope_id', $request->input('scope'));
        if ($scopeParam !== null && $scopeParam !== '') {
            if ($scopeParam instanceof Scope) {
                return $scopeParam->active ? $scopeParam : false;
            }
            if (is_numeric($scopeParam)) {
                return Scope::active()->find((int) $scopeParam) ?? false;
            }
            if (is_string($scopeParam)) {
                return Scope::active()->where('slug', $scopeParam)->first()
                    ?? ActivityType::tryFrom($scopeParam)
                    ?? false;
            }

            return false;
        }

        $value = $request->route('activity') ?? $request->input('activity_type', $request->input('activity'));

        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            return false;
        }

        if ($enum = ActivityType::tryFrom($value)) {
            return $enum;
        }

        return Scope::active()->where('slug', $value)->first() ?? false;
    }

    private function deny(Request $request, int $status, ?string $message = null): Response
    {
        $message ??= self::DENIED_MESSAGE;

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        return response()->view('errors.access-denied', ['message' => $message], $status);
    }
}
