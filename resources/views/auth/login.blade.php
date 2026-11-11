<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar · EET N° 2</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
    @include('partials.theme-head')
</head>
<body class="flex min-h-screen flex-col justify-between p-4 text-slate-800 antialiased">
    <div class="text-right">
        <button type="button" data-theme-cycle class="eet-cycle" title="Cambiar tema (claro / oscuro / automático)">
            <span data-theme-label>☀ Claro</span>
        </button>
    </div>

    <div class="mx-auto w-full max-w-md overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
        <div class="bg-slate-900 px-6 py-5 text-center text-white">
            <span class="inline-block rounded bg-amber-500 px-2.5 py-0.5 text-xs font-extrabold uppercase tracking-wide text-slate-950">EET N° 2</span>
            <h1 class="mt-2 text-lg font-bold">Prof. Jesús Raúl Salazar</h1>
            <p class="text-xs text-slate-400">San Salvador de Jujuy · Nivel Secundario Técnico</p>
        </div>

        <div class="p-6 sm:p-8">
            <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="username" class="mb-1 block text-sm font-medium text-slate-700">Usuario o correo institucional</label>
                    <input id="username" name="username" value="{{ old('username') }}" required autofocus autocomplete="username"
                           placeholder="Ej.: admin o admin@eet2jujuy.edu.ar"
                           class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none">
                    @error('username') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password" class="mb-1 block text-sm font-medium text-slate-700">Contraseña</label>
                    <input id="password" type="password" name="password" required autocomplete="current-password"
                           class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-900 focus:outline-none">
                    @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <label class="flex items-center gap-2 text-xs text-slate-600">
                    <input type="checkbox" name="remember" value="1" class="rounded border-slate-300">
                    Mantener sesión iniciada en este equipo
                </label>

                <button class="w-full rounded-md bg-slate-900 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">
                    Ingresar al sistema
                </button>
            </form>

            <p class="mt-5 text-center text-xs text-slate-400">
                Acceso exclusivo con credenciales otorgadas por Dirección.
            </p>
        </div>
    </div>

    <footer class="py-3 text-center text-xs text-slate-500">
        EET N° 2 "Prof. Jesús Raúl Salazar" — Sistema de Asistencia
    </footer>
</body>
</html>
