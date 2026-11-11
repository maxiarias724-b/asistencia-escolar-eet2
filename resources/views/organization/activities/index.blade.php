@extends('layouts.app')
@section('title', 'Organización — Materias y Actividades')

@section('content')
    <div class="mb-4">
        <h1 class="text-2xl font-bold text-slate-900">Organización Escolar — Materias y Actividades</h1>
        <p class="text-sm text-slate-500">Catálogo de materias áulicas, secciones de Taller y Educación Física agrupadas por Ámbito.</p>
    </div>

    @include('organization._tabs')

    @if ($errors->any())
        <div class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">
            <ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="mb-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-600">Nueva materia / actividad</h2>
        <form method="POST" action="{{ route('organization.activities.store') }}" class="grid items-end gap-3 sm:grid-cols-4">
            @csrf
            <label class="block text-xs font-medium text-slate-700">Ámbito *
                <select name="scope_id" required class="mt-1 block w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm">
                    <option value="">Seleccionar ámbito…</option>
                    @foreach ($scopes as $sc)
                        <option value="{{ $sc->id }}" @selected((int) old('scope_id', $scopeId) === $sc->id)>{{ $sc->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-xs font-medium text-slate-700 sm:col-span-2">Nombre de la materia o actividad *
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="Ej.: Taller de Redes e Infraestructura"
                       class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm">
            </label>
            <div>
                <input type="hidden" name="active" value="1">
                <button class="w-full rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">+ Crear actividad</button>
            </div>
        </form>
    </section>

    <div x-data="{ editing: null }" class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-4">
            <h2 class="font-semibold text-slate-800">Actividades registradas ({{ $activities->count() }})</h2>
            <form method="GET" class="flex items-center gap-2 text-sm">
                <select name="scope_id" class="rounded border border-slate-300 bg-white px-2.5 py-1.5">
                    <option value="">Todos los ámbitos</option>
                    @foreach ($scopes as $sc)
                        <option value="{{ $sc->id }}" @selected($scopeId === $sc->id)>{{ $sc->name }}</option>
                    @endforeach
                </select>
                <button class="rounded border border-slate-300 bg-slate-50 px-3 py-1.5 font-medium hover:bg-slate-100">Filtrar</button>
                @if ($scopeId)
                    <a href="{{ route('organization.activities.index') }}" class="text-xs text-slate-500 hover:underline">Limpiar</a>
                @endif
            </form>
        </div>

        <div class="overflow-x-auto"><table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-2.5">Ámbito</th>
                    <th class="px-4 py-2.5">Materia / Actividad</th>
                    <th class="px-4 py-2.5">Estado</th>
                    <th class="px-4 py-2.5 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($activities as $act)
                    <tr>
                        <td class="px-4 py-2.5">
                            <span class="rounded bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-800">
                                {{ $act->scopeRef?->name ?? '—' }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 font-medium text-slate-900">{{ $act->name }}</td>
                        <td class="px-4 py-2.5">
                            <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $act->active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">
                                {{ $act->active ? 'ACTIVO' : 'INACTIVO' }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-2.5 text-right">
                            <button type="button"
                                    @click="editing = @js([
                                        'action' => route('organization.activities.update', $act),
                                        'scope_id' => $act->scope_id,
                                        'name' => $act->name,
                                        'active' => $act->active,
                                    ])"
                                    class="text-indigo-600 hover:underline">Editar</button>
                            <form method="POST" action="{{ route('organization.activities.toggle', $act) }}" class="inline">
                                @csrf @method('PATCH')
                                <button class="ml-3 text-amber-700 hover:underline">{{ $act->active ? 'Desactivar' : 'Activar' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No hay actividades registradas.</td></tr>
                @endforelse
            </tbody>
        </table></div>

        <div x-show="editing" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div @click.outside="editing = null" class="w-full max-w-md rounded-lg bg-white p-5 shadow-xl">
                <h3 class="mb-4 text-lg font-bold text-slate-900">Editar materia / actividad</h3>
                <template x-if="editing">
                    <form method="POST" :action="editing.action" class="space-y-4">
                        @csrf @method('PUT')
                        <label class="block text-sm">Ámbito *
                            <select name="scope_id" x-model="editing.scope_id" required class="mt-1 block w-full rounded border border-slate-300 bg-white px-3 py-2">
                                @foreach ($scopes as $sc)
                                    <option value="{{ $sc->id }}">{{ $sc->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-sm">Nombre *
                            <input type="text" name="name" x-model="editing.name" required class="mt-1 block w-full rounded border border-slate-300 px-3 py-2">
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="hidden" name="active" value="0">
                            <input type="checkbox" name="active" value="1" :checked="editing.active"> Actividad activa
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
