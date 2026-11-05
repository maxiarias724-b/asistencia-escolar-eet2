@extends('layouts.app')
@section('title', 'Personas — Estudiantes SINIDE')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Padrón de Estudiantes — Estructura SINIDE</h1>
            <p class="text-sm text-slate-500">Gestión integral con buscador multicriterio y preservación histórica mediante baja lógica (`ACTIVO` / `INACTIVO`).</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('people.import.index', ['entity' => 'students']) }}"
               class="rounded border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-sm font-medium text-emerald-800 hover:bg-emerald-100">
                ↑ Importar Excel / CSV
            </a>
            <a href="{{ route('people.students.create', array_filter(['course_id' => $filters['course_id']])) }}"
               class="rounded bg-indigo-600 px-3.5 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                + Nuevo estudiante
            </a>
        </div>
    </div>

    @include('people._tabs')

    {{-- Buscador multicriterio --}}
    <form method="GET" action="{{ route('people.students.index') }}" class="mb-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <label class="block text-xs font-medium text-slate-600 lg:col-span-2">Buscar (Apellido, Nombre, DNI, CUIL, Legajo o SINIDE)
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Ej.: Mamani o 46123456…"
                       class="mt-1 block w-full rounded border border-slate-300 px-3 py-1.5 text-sm">
            </label>
            <label class="block text-xs font-medium text-slate-600">Curso / División
                <select name="course_id" class="mt-1 block w-full rounded border border-slate-300 bg-white px-2.5 py-1.5 text-sm">
                    <option value="">Todos los habilitados</option>
                    @foreach ($courses as $c)
                        <option value="{{ $c->id }}" @selected($filters['course_id'] === $c->id)>
                            {{ $c->name }} ({{ $c->shiftRef?->name ?? $c->shift->label() }})
                        </option>
                    @endforeach
                </select>
            </label>
            @if (auth()->user()->isDirector())
                <label class="block text-xs font-medium text-slate-600">Especialidad
                    <select name="specialty_id" class="mt-1 block w-full rounded border border-slate-300 bg-white px-2.5 py-1.5 text-sm">
                        <option value="">Todas</option>
                        @foreach ($specialties as $sp)
                            <option value="{{ $sp->id }}" @selected($filters['specialty_id'] === $sp->id)>{{ $sp->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-xs font-medium text-slate-600">Turno
                    <select name="shift_id" class="mt-1 block w-full rounded border border-slate-300 bg-white px-2.5 py-1.5 text-sm">
                        <option value="">Todos</option>
                        @foreach ($shifts as $sh)
                            <option value="{{ $sh->id }}" @selected($filters['shift_id'] === $sh->id)>{{ $sh->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <label class="block text-xs font-medium text-slate-600">Estado institucional
                <select name="active" class="mt-1 block w-full rounded border border-slate-300 bg-white px-2.5 py-1.5 text-sm">
                    <option value="" @selected($filters['active'] === '')>Todos</option>
                    <option value="1" @selected($filters['active'] === '1')>ACTIVO</option>
                    <option value="0" @selected($filters['active'] === '0')>INACTIVO (Baja/Pase/Libre)</option>
                </select>
            </label>
        </div>
        <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3 text-xs">
            <span class="text-slate-500">Mostrando <strong>{{ $students->total() }}</strong> estudiante(s) encontrado(s).</span>
            <div class="flex items-center gap-3">
                @if (array_filter($filters, fn ($v) => $v !== null && $v !== ''))
                    <a href="{{ route('people.students.index') }}" class="text-slate-500 hover:underline">Limpiar filtros</a>
                @endif
                <button class="rounded bg-slate-800 px-4 py-1.5 font-medium text-white hover:bg-slate-900">Buscar</button>
            </div>
        </div>
    </form>

    {{-- Tabla de estudiantes --}}
    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2.5">Apellido y Nombre</th>
                    <th class="px-3 py-2.5">DNI / CUIL</th>
                    <th class="px-3 py-2.5">Legajo / SINIDE</th>
                    <th class="px-3 py-2.5">Nacimiento</th>
                    <th class="px-3 py-2.5">Curso</th>
                    <th class="px-3 py-2.5">Matrícula / Estado</th>
                    <th class="px-3 py-2.5 text-center">Reg. Asist.</th>
                    <th class="px-3 py-2.5 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($students as $st)
                    <tr class="{{ $st->active ? '' : 'bg-slate-50/70 text-slate-500' }}">
                        <td class="px-3 py-2.5 font-semibold {{ $st->active ? 'text-slate-900' : 'text-slate-500' }}">
                            {{ $st->full_name }}
                        </td>
                        <td class="px-3 py-2.5 font-mono text-xs">
                            <div>{{ $st->dni ?: 'Sin DNI' }}</div>
                            @if ($st->cuil)
                                <div class="text-slate-400">{{ $st->cuil }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 font-mono text-xs">
                            <div>{{ $st->file_number ? 'Leg. '.$st->file_number : '—' }}</div>
                            @if ($st->sinide_code)
                                <div class="text-indigo-600">{{ $st->sinide_code }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-xs text-slate-600">
                            {{ $st->birth_date?->format('d/m/Y') ?? '—' }}
                        </td>
                        <td class="px-3 py-2.5">
                            <span class="font-semibold text-slate-800">{{ $st->course?->name ?? '—' }}</span>
                            <div class="text-[11px] text-slate-500">{{ $st->course?->shiftRef?->name ?? $st->course?->shift?->label() }}</div>
                        </td>
                        <td class="px-3 py-2.5">
                            <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $st->active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">
                                {{ $st->active ? 'ACTIVO' : 'INACTIVO · '.$st->status->label() }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5 text-center font-mono text-xs">{{ $st->attendances_count }}</td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-right text-xs">
                            <a href="{{ route('people.students.edit', $st) }}" class="font-medium text-indigo-600 hover:underline">Editar</a>
                            <form method="POST" action="{{ route('people.students.toggle', $st) }}" class="inline"
                                  onsubmit="return confirm('¿Cambiar estado lógico de {{ e($st->full_name) }}? No se borrarán sus asistencias.')">
                                @csrf @method('PATCH')
                                <button class="ml-3 font-medium {{ $st->active ? 'text-amber-700' : 'text-emerald-700' }} hover:underline">
                                    {{ $st->active ? 'Dar de baja' : 'Reactivar' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-slate-500">
                            No se encontraron estudiantes para los criterios indicados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $students->links() }}</div>
@endsection
