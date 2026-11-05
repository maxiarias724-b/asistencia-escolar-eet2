<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    /**
     * Autenticación por `username` o `email` + contraseña, exigiendo `active == true`.
     * No existe registro público; las cuentas las administra Dirección.
     */
    public function store(Request $request): RedirectResponse
    {
        // Acepta el campo como `username` (estándar del formulario) o `login` / `email`.
        if (! $request->filled('username') && ($request->filled('login') || $request->filled('email'))) {
            $request->merge(['username' => $request->input('login', $request->input('email'))]);
        }

        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ], [
            'username.required' => 'Ingresá tu usuario o correo institucional.',
            'password.required' => 'Ingresá tu contraseña.',
        ]);

        $identifier = trim($credentials['username']);
        $throttleKey = 'login:'.mb_strtolower($identifier).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'username' => "Demasiados intentos. Probá nuevamente en {$seconds} segundos.",
            ]);
        }

        $remember = $request->boolean('remember');
        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $authenticated = Auth::attempt([
            $field => $identifier,
            'password' => $credentials['password'],
            'active' => true,
        ], $remember);

        // Si el usuario tiene formato de correo en username o viceversa, reintenta por el otro campo.
        if (! $authenticated) {
            $fallbackField = $field === 'email' ? 'username' : 'email';
            $authenticated = Auth::attempt([
                $fallbackField => $identifier,
                'password' => $credentials['password'],
                'active' => true,
            ], $remember);
        }

        if (! $authenticated) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'username' => 'Credenciales inválidas o cuenta inactiva.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();
        $this->recordAuthAudit($user, 'login', "Inicio de sesión de «{$user->name}» ({$user->username})", $request);

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        if ($user) {
            $this->recordAuthAudit($user, 'logout', "Cierre de sesión de «{$user->name}» ({$user->username})", $request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function recordAuthAudit(User $user, string $action, string $description, Request $request): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'description' => $description,
            'old_values' => null,
            'new_values' => ['role' => $user->role->value],
            'ip_address' => $request->ip(),
        ]);
    }
}
