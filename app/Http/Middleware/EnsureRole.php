<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autorización por rol institucional.
 * Soporta un rol (`role:director`) o varios (`role:director,operador`).
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $allowed = array_map(fn (string $r) => Role::from(trim($r)), $roles);

        if (! $user || ! $user->active || ! in_array($user->role, $allowed, true)) {
            $labels = implode(' o ', array_map(fn (Role $r) => $r->label(), $allowed));
            $message = "Acceso Denegado: esta sección es exclusiva de {$labels}.";

            return $request->expectsJson()
                ? response()->json(['message' => $message], 403)
                : response()->view('errors.access-denied', ['message' => $message], 403);
        }

        return $next($request);
    }
}
