<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /** Punto de entrada único (`/`): redirige al panel correspondiente según el rol del usuario. */
    public function __invoke(Request $request): RedirectResponse
    {
        return match ($request->user()->role) {
            Role::Director => redirect()->route('director.dashboard'),
            Role::Docente => redirect()->route('docente.home'),
            default => redirect()->route('operator.home'),
        };
    }
}
