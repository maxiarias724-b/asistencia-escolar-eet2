<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea el acceso si una cuenta autenticada fue desactivada (`active = false`) durante su sesión.
 */
class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->active) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Tu cuenta se encuentra inactiva.'], 401);
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['username' => 'Tu cuenta fue desactivada. Contactá a Dirección.']);
        }

        return $next($request);
    }
}
