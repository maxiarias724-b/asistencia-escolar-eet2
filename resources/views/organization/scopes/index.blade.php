@extends('layouts.app')
@section('title', 'Organización — Ámbitos de Asistencia')

@section('content')
    <div class="mb-4">
        <h1 class="text-2xl font-bold text-slate-900">Organización Escolar — Ámbitos de Asistencia</h1>
        <p class="text-sm text-slate-500">Define en qué ámbitos independientes se toma asistencia (por defecto: Clases Áulicas, Educación Física y Taller, extensible a Laboratorio, Pasantías, etc.).</p>
    </div>

    @include('organization._tabs')

    @if ($errors->any())
        <div class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">
            <ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="mb-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-600">Nuevo ámbito</h2>
        <form method="POST" action="{{ route('organization.scopes.store') }}" class="grid items-end gap-3 sm:grid-cols-4">
            @csrf
            <label class="block text-xs font-medium text-slate-700 sm:col-span-2">Nombre visible del ámbito *
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="Ej.: Prácticas Profesionalizantes"
                       class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm">
            </label>
            <label class="block text-xs font-medium text-slate-700">Identificador URL (slug, opcional)
                <input type="text" name="slug" value="{{ old('slug') }}" placeholder="practicas_prof"
                       class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 font-mono text-sm">
            </label>
            <div>
                <input type="hidden" name="active" value="1">
                <button class="w-full rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">+ Crear ámbito</button>
            </div>
        </form>
    </section>

    <div x-data="{ editing: null }" class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-2.5">Ámbito</th>
                    <th class="px-4 py-2.5">Slug</th>
                    <th class="px-4 py-2.5 text-center">Materias / Actividades</th>
                    <th class="px-4 py-2.5 text-center">Asignaciones activas</th>
                    <th class="px-4 py-2.5">Estado</th>
                    <th class="px-4 py-2.5 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($scopes as $sc)
                    <tr>
                        <td class="px-4 py-2.5 font-semibold text-slate-900">{{ $sc->name }}</td>
                        <td class="px-4 py-2.5 font-mono text-xs text-slate-600">{{ $sc->slug }}</td>
                        <td class="px-4 py-2.5 text-center font-mono">
                            <a href="{{ route('organization.activities.index', ['scope_id' => $sc->id]) }}" class="text-indigo-600 hover:underline">{{ $sc->activities_count }}</a>
                        </td>
                        <td class="px-4 py-2.5 text-center font-mono">{{ $sc->course_assignments_count }}</td>
                        <td class="px-4 py-2.5">
                            <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $sc->active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">
                                {{ $sc->active ? 'ACTIVO' : 'INACTIVO' }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-2.5 text-right">
                            <button type="button"
                                    @click="editing = @js([
                                        'action' => route('organization.scopes.update', $sc),
                                        'name' => $sc->name,
                                        'slug' => $sc->slug,
                                        'active' => $sc->active,
                                    ])"
                                    class="text-indigo-600 hover:underline">Editar</button>
                            <form method="POST" action="{{ route('organization.scopes.toggle', $sc) }}" class="inline">
                                @csrf @method('PATCH')
                                <button class="ml-3 text-amber-700 hover:underline">{{ $sc->active ? 'Desactivar' : 'Activar' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No hay ámbitos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div x-show="editing" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div @click.outside="editing = null" class="w-full max-w-md rounded-lg bg-white p-5 shadow-xl">
                <h3 class="mb-4 text-lg font-bold text-slate-900">Editar ámbito</h3>
                <template x-if="editing">
                    <form method="POST" :action="editing.action" class="space-y-4">
                        @csrf @method('PUT')
                        <label class="block text-sm">Nombre *
                            <input type="text" name="name" x-model="editing.name" required class="mt-1 block w-full rounded border border-slate-300 px-3 py-2">
                        </label>
                        <label class="block text-sm">Identificador (slug) *
                            <input type="text" name="slug" x-model="editing.slug" required class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 font-mono">
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="hidden" name="active" value="0">
                            <input type="checkbox" name="active" value="1" :checked="editing.active"> Ámbito activo
                        </label>
                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="editing = null" class="rounded border border-slate-300 px-3 py-1.5 text-sm">Cancelar</button>
                            <button class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">Guardar cambios</button>
                        </div>
                    </form>
                </template>
            </div>
        </div>
    </div>
@endsection
