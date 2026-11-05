@extends('layouts.app')
@section('title', 'Personas — Padrón de Docentes')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Padrón Institucional de Docentes</h1>
            <p class="text-sm text-slate-500">Registro de profesores, preceptores y maestros de enseñanza práctica de la EET N° 2 (con baja lógica estricta).</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('people.import.index', ['entity' => 'teachers']) }}"
               class="rounded border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-sm font-medium text-emerald-800 hover:bg-emerald-100">
                ↑ Importar Docentes (Excel / CSV)
            </a>
            <a href="{{ route('people.teachers.create') }}"
               class="rounded bg-indigo-600 px-3.5 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                + Nuevo docente
            </a>
        </div>
    </div>

    @include('people._tabs')

    {{-- Buscador multicriterio --}}
    <form method="GET" action="{{ route('people.teachers.index') }}" class="mb-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid items-end gap-3 sm:grid-cols-4">
            <label class="block text-xs font-medium text-slate-600 sm:col-span-2">Buscar (Apellido, Nombre, DNI, CUIL, Legajo, Email o Área)
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Ej.: Quispe, 28456789 o Informática…"
                       class="mt-1 block w-full rounded border border-slate-300 px-3 py-1.5 text-sm">
            </label>
            <label class="block text-xs font-medium text-slate-600">Estado institucional
                <select name="active" class="mt-1 block w-full rounded border border-slate-300 bg-white px-2.5 py-1.5 text-sm">
                    <option value="" @selected($filters['active'] === '')>Todos</option>
                    <option value="1" @selected($filters['active'] === '1')>ACTIVO</option>
                    <option value="0" @selected($filters['active'] === '0')>INACTIVO</option>
                </select>
            </label>
            <div class="flex items-center gap-2">
                <button class="w-full rounded bg-slate-800 px-4 py-1.5 text-sm font-medium text-white hover:bg-slate-900">Buscar</button>
                @if ($filters['q'] !== '' || $filters['active'] !== '')
                    <a href="{{ route('people.teachers.index') }}" class="text-xs text-slate-500 hover:underline">Limpiar</a>
                @endif
            </div>
        </div>
    </form>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2.5">Apellido y Nombre</th>
                    <th class="px-3 py-2.5">DNI / CUIL</th>
                    <th class="px-3 py-2.5">Legajo</th>
                    <th class="px-3 py-2.5">Área / Especialidad</th>
                    <th class="px-3 py-2.5">Contacto</th>
                    <th class="px-3 py-2.5">Estado</th>
                    <th class="px-3 py-2.5 text-center">Reg. Asist.</th>
                    <th class="px-3 py-2.5 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($teachers as $t)
                    <tr class="{{ $t->active ? '' : 'bg-slate-50/70 text-slate-500' }}">
                        <td class="px-3 py-2.5 font-semibold {{ $t->active ? 'text-slate-900' : 'text-slate-500' }}">
                            {{ $t->full_name }}
                        </td>
                        <td class="px-3 py-2.5 font-mono text-xs">
                            <div>{{ $t->dni }}</div>
                            @if ($t->cuil)
                                <div class="text-slate-400">{{ $t->cuil }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 font-mono text-xs">{{ $t->file_number ?: '—' }}</td>
                        <td class="px-3 py-2.5 text-slate-700">{{ $t->specialty_area ?: '—' }}</td>
                        <td class="px-3 py-2.5 text-xs text-slate-600">
                            @if ($t->email)<div>{{ $t->email }}</div>@endif
                            @if ($t->phone)<div class="text-slate-400">{{ $t->phone }}</div>@endif
                            @if (! $t->email && ! $t->phone)—@endif
                        </td>
                        <td class="px-3 py-2.5">
                            <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $t->active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">
                                {{ $t->active ? 'ACTIVO' : 'INACTIVO' }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5 text-center font-mono text-xs">{{ $t->attendances_count }}</td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-right text-xs">
                            <a href="{{ route('people.teachers.edit', $t) }}" class="font-medium text-indigo-600 hover:underline">Editar</a>
                            <form method="POST" action="{{ route('people.teachers.toggle', $t) }}" class="inline"
                                  onsubmit="return confirm('¿Cambiar estado lógico de {{ e($t->full_name) }}?')">
                                @csrf @method('PATCH')
                                <button class="ml-3 font-medium {{ $t->active ? 'text-amber-700' : 'text-emerald-700' }} hover:underline">
                                    {{ $t->active ? 'Dar de baja' : 'Reactivar' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-slate-500">
                            No se encontraron docentes con los filtros aplicados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $teachers->links() }}</div>
@endsection
