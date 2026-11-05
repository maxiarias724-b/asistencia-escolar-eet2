@extends('layouts.app')
@php
    $editing = $user->exists;
    $currentRole = old('account_role', $user->role?->value ?? 'operador');
@endphp
@section('title', $editing ? 'Editar usuario' : 'Nuevo usuario')

@section('content')
    <a href="{{ route('director.users.index') }}" class="text-sm text-indigo-600 hover:underline">← Volver al listado</a>
    <h1 class="mb-4 mt-2 text-2xl font-bold">{{ $editing ? 'Editar «'.$user->name.'»' : 'Nuevo usuario del sistema' }}</h1>

    <form method="POST" action="{{ $editing ? route('director.users.update', $user) : route('director.users.store') }}"
          x-data="{ accountRole: @js($currentRole) }" class="space-y-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        @if ($errors->any())
            <div class="rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">
                <ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        {{-- Datos y credenciales --}}
        <section class="rounded-lg bg-white p-4 shadow-sm" x-data="{ pwd: '', show: false,
            generate() { const c = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789'; const a = new Uint32Array(10); crypto.getRandomValues(a); this.pwd = Array.from(a, n => c[n % c.length]).join(''); this.show = true; } }">
            <h2 class="mb-3 font-semibold">Datos personales, rol y credenciales</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <label class="block text-sm">Nombre completo (visible) *
                    <input name="name" value="{{ old('name', $user->name) }}" required class="mt-1 block w-full rounded border border-slate-300 px-3 py-2" placeholder="Ej.: Carla Méndez">
                </label>
                <label class="block text-sm">Nombre(s) (opcional)
                    <input name="first_name" value="{{ old('first_name', $user->first_name) }}" class="mt-1 block w-full rounded border border-slate-300 px-3 py-2" placeholder="Ej.: Carla">
                </label>
                <label class="block text-sm">Apellido(s) (opcional)
                    <input name="last_name" value="{{ old('last_name', $user->last_name) }}" class="mt-1 block w-full rounded border border-slate-300 px-3 py-2" placeholder="Ej.: Méndez">
                </label>
                <label class="block text-sm">Usuario (para ingresar) *
                    <input name="username" value="{{ old('username', $user->username) }}" required autocomplete="off" class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 font-mono" placeholder="Ej.: preceptor_5to">
                </label>
                <label class="block text-sm">Email (opcional, también permite login)
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" class="mt-1 block w-full rounded border border-slate-300 px-3 py-2" placeholder="usuario@eet2jujuy.edu.ar">
                </label>
                <label class="block text-sm">Rol en la plataforma *
                    <select name="account_role" x-model="accountRole" class="mt-1 block w-full rounded border border-slate-300 bg-white px-3 py-2">
                        <option value="operador">Preceptor / Operador de Asistencia</option>
                        <option value="docente">Docente (consulta institucional)</option>
                    </select>
                </label>
                <label class="block text-sm sm:col-span-2">Contraseña {{ $editing ? '(dejar vacía para no cambiarla)' : '*' }}
                    <div class="mt-1 flex gap-2">
                        <input :type="show ? 'text' : 'password'" name="password" x-model="pwd" autocomplete="new-password" {{ $editing ? '' : 'required' }}
                               class="block w-full rounded border border-slate-300 px-3 py-2 font-mono">
                        <button type="button" @click="show = !show" class="rounded border border-slate-300 px-2 text-xs">Ver</button>
                        <button type="button" @click="generate()" class="whitespace-nowrap rounded bg-slate-700 px-2 text-xs text-white">Generar</button>
                    </div>
                </label>
            </div>
            <label class="mt-4 flex items-center gap-2 text-sm">
                <input type="hidden" name="active" value="0">
                <input type="checkbox" name="active" value="1" @checked((bool) old('active', $user->active))> Cuenta activa (habilitada para iniciar sesión)
            </label>
        </section>

        {{-- Delegación: curso + ámbito --}}
        <section x-show="accountRole === 'operador'" x-cloak class="rounded-lg bg-white p-4 shadow-sm">
            <h2 class="mb-1 font-semibold">Cursos y ámbitos que puede gestionar</h2>
            <p class="mb-4 text-sm text-slate-500">Marcá exactamente qué cursos y en qué ámbito (`Clases Áulicas`, `Educación Física`, `Taller` u otros configurados) podrá operar este usuario.</p>

            <div class="grid gap-6 lg:grid-cols-3">
                @foreach ($activities as $activity)
                    @php $ids = array_map('intval', $selected[$activity->value] ?? []); @endphp
                    <fieldset x-data="{ all(v) { this.$root.querySelectorAll('input[type=checkbox]').forEach(c => c.checked = v) } }" class="rounded border border-slate-200 p-3">
                        <legend class="px-1 text-sm font-semibold text-indigo-700">{{ $activity->label() }}</legend>
                        <div class="mb-2 flex gap-3 text-xs">
                            <button type="button" @click="all(true)" class="text-indigo-600 hover:underline">Todos</button>
                            <button type="button" @click="all(false)" class="text-slate-500 hover:underline">Ninguno</button>
                        </div>
                        @foreach ($courses->groupBy('year') as $year => $yearCourses)
                            <div class="mb-2">
                                <div class="text-xs font-semibold uppercase text-slate-400">{{ $year }}° año · {{ $yearCourses->first()->cycle->label() }}</div>
                                @foreach ($yearCourses as $course)
                                    <label class="flex items-center gap-2 py-0.5 text-sm">
                                        <input type="checkbox" name="assignments[{{ $activity->value }}][]" value="{{ $course->id }}" @checked(in_array($course->id, $ids, true))>
                                        <span>{{ $course->name }}</span>
                                        <span class="text-xs text-slate-500">
                                            ({{ $course->shiftRef?->name ?? $course->shift->label() }}{{ $course->specialty ? ' · '.$course->specialty->code : '' }})
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @endforeach
                    </fieldset>
                @endforeach
            </div>
        </section>

        <section x-show="accountRole === 'docente'" x-cloak class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <strong>Rol Docente (solo consulta):</strong> esta cuenta accederá al portal institucional docente (calendario escolar y resumen de cursos), sin permisos de escritura sobre planillas de asistencia.
        </section>

        <div class="flex gap-3">
            <button class="rounded bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700">{{ $editing ? 'Guardar cambios' : 'Crear usuario' }}</button>
            <a href="{{ route('director.users.index') }}" class="px-4 py-2 text-slate-600 hover:underline">Cancelar</a>
        </div>
    </form>
@endsection
