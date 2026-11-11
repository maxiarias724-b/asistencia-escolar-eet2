@extends('layouts.app')
@section('title', 'Amonestaciones y Disciplina')

@section('content')
    @php $u = auth()->user(); @endphp

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Libro de Amonestaciones (Disciplina)</h1>
            <p class="text-sm text-slate-500">
                Seguimiento institucional de sanciones disciplinarias con preservación histórica mediante baja lógica.
            </p>
        </div>

        @can('create', \App\Models\DisciplinaryRecord::class)
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('disciplinary.create') }}"
                   class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
                    + Registrar amonestación
                </a>
            </div>
        @endcan
    </div>

    @cannot('create', \App\Models\DisciplinaryRecord::class)
        <div class="mb-4 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-xs text-sky-800">
            <strong>Modo de consulta (solo lectura):</strong>
            Visualizando exclusivamente los registros disciplinarios de estudiantes pertenecientes a sus cursos asignados.
            La carga y modificación de sanciones es exclusiva del Equipo Directivo.
        </div>
    @endcannot

    {{-- Resumen de métricas disciplinarias --}}
    <div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sanciones vigentes</div>
            <div class="mt-1 font-mono text-2xl font-bold text-slate-900">{{ $summary['active_records'] }}</div>
            <div class="mt-0.5 text-xs text-slate-500">Registros disciplinarios activos</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Amonestaciones acumuladas</div>
            <div class="mt-1 font-mono text-2xl font-bold text-amber-700">{{ $summary['active_points'] }}</div>
            <div class="mt-0.5 text-xs text-slate-500">Suma total de amonestaciones activas</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Alerta disciplinaria (≥ 15)</div>
            <div class="mt-1 font-mono text-2xl font-bold text-red-700">{{ $summary['students_at_risk'] }}</div>
            <div class="mt-0.5 text-xs text-slate-500">Estudiante(s) con 15 o más amonestaciones</div>
        </div>
    </div>

    {{-- Buscador y filtros --}}
    <form method="GET" action="{{ route('disciplinary.index') }}" class="mb-5 rounded-lg border border-slate-200 bg-white p-4 shadow-sm print:hidden">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <label class="block text-xs font-semibold uppercase text-slate-600 lg:col-span-2">
                Buscar (Estudiante, DNI, Legajo o Motivo)
                <input type="search" name="q" value="{{ $filters['q'] }}"
                       placeholder="Ej.: Gómez, 45111222 o convivencia…"
                       class="mt-1 block w-full rounded border border-slate-300 px-3 py-1.5 text-sm font-normal">
            </label>

            <label class="block text-xs font-semibold uppercase text-slate-600">
                Curso / División
                <select name="course_id" class="mt-1 block w-full rounded border border-slate-300 bg-white px-2.5 py-1.5 text-sm font-normal">
                    <option value="">Todos los habilitados</option>
                    @foreach ($courses as $c)
                        <option value="{{ $c->id }}" @selected($filters['course_id'] === $c->id)>
                            {{ $c->name }} ({{ $c->shiftRef?->name ?? $c->shift?->label() }})
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="block text-xs font-semibold uppercase text-slate-600">
                Desde
                <input type="date" name="from" value="{{ $filters['from'] }}"
                       class="mt-1 block w-full rounded border border-slate-300 px-2.5 py-1.5 text-sm font-normal">
            </label>

            <label class="block text-xs font-semibold uppercase text-slate-600">
                Hasta
                <input type="date" name="to" value="{{ $filters['to'] }}"
                       class="mt-1 block w-full rounded border border-slate-300 px-2.5 py-1.5 text-sm font-normal">
            </label>

            <label class="block text-xs font-semibold uppercase text-slate-600">
                Estado
                <select name="active" class="mt-1 block w-full rounded border border-slate-300 bg-white px-2.5 py-1.5 text-sm font-normal">
                    <option value="" @selected($filters['active'] === '')>Todas</option>
                    <option value="1" @selected($filters['active'] === '1')>Vigentes</option>
                    <option value="0" @selected($filters['active'] === '0')>Dadas de baja</option>
                </select>
            </label>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3 text-xs">
            <span class="text-slate-500">Mostrando <strong>{{ $records->total() }}</strong> registro(s) disciplinario(s).</span>
            <div class="flex items-center gap-3">
                @if (array_filter($filters, fn ($v) => $v !== null && $v !== ''))
                    <a href="{{ route('disciplinary.index') }}" class="text-slate-500 hover:underline">Limpiar filtros</a>
                @endif
                <button type="submit" class="rounded bg-slate-800 px-4 py-1.5 font-medium text-white hover:bg-slate-900">
                    Filtrar
                </button>
            </div>
        </div>
    </form>

    {{-- Tabla de amonestaciones --}}
    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-600">
                <tr>
                    <th class="px-3 py-2.5">Fecha</th>
                    <th class="px-3 py-2.5">Estudiante</th>
                    <th class="px-3 py-2.5">Curso / Turno</th>
                    <th class="px-3 py-2.5 text-center">Sanción</th>
                    <th class="px-3 py-2.5 text-center">Acum. Activo</th>
                    <th class="px-3 py-2.5">Motivo de la sanción</th>
                    <th class="px-3 py-2.5">Aplicada por</th>
                    <th class="px-3 py-2.5 text-center">Estado</th>
                    @if ($u->isDirector())
                        <th class="px-3 py-2.5 text-right print:hidden">Acciones</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($records as $rec)
                    @php
                        $st = $rec->student;
                        $accum = (int) ($studentTotals[$rec->student_id] ?? 0);
                    @endphp
                    <tr class="{{ $rec->active ? 'hover:bg-slate-50' : 'bg-slate-50/70 text-slate-500' }}">
                        <td class="whitespace-nowrap px-3 py-2.5 font-mono text-xs text-slate-700">
                            {{ $rec->date?->format('d/m/Y') }}
                        </td>
                        <td class="px-3 py-2.5">
                            <div class="font-semibold {{ $rec->active ? 'text-slate-900' : 'text-slate-500' }}">
                                {{ $st?->full_name ?? 'Estudiante #'.$rec->student_id }}
                            </div>
                            <div class="font-mono text-xs text-slate-500">
                                DNI {{ $st?->dni ?: 'S/D' }}
                                @if ($st?->file_number)
                                    · Leg. {{ $st->file_number }}
                                @endif
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-xs">
                            <div class="font-semibold text-slate-800">{{ $st?->course?->name ?? '—' }}</div>
                            <div class="text-slate-500">{{ $st?->course?->shiftRef?->name ?? $st?->course?->shift?->label() ?? '' }}</div>
                        </td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-center">
                            <span class="inline-block rounded bg-amber-100 px-2.5 py-0.5 font-mono text-xs font-bold text-amber-800">
                                +{{ $rec->amount }} amon.
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-center font-mono text-xs">
                            <span class="rounded px-2 py-0.5 font-bold {{ $accum >= 15 ? 'bg-red-100 text-red-800' : ($accum >= 10 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700') }}">
                                {{ $accum }} / 25
                            </span>
                        </td>
                        <td class="max-w-md px-3 py-2.5 text-xs text-slate-700">
                            {{ $rec->reason }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-xs text-slate-600">
                            {{ $rec->recorder?->name ?? '—' }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-center">
                            <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $rec->active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">
                                {{ $rec->active ? 'VIGENTE' : 'BAJA LÓGICA' }}
                            </span>
                        </td>
                        @if ($u->isDirector())
                            <td class="whitespace-nowrap px-3 py-2.5 text-right text-xs print:hidden">
                                @can('update', $rec)
                                    <a href="{{ route('disciplinary.edit', $rec) }}" class="font-medium text-indigo-600 hover:underline">
                                        Editar
                                    </a>
                                @endcan
                                @can('delete', $rec)
                                    <form method="POST" action="{{ route('disciplinary.toggle', $rec) }}" class="inline"
                                          onsubmit="return confirm('¿Cambiar estado lógico de esta amonestación?')">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="ml-3 font-medium {{ $rec->active ? 'text-red-700' : 'text-emerald-700' }} hover:underline">
                                            {{ $rec->active ? 'Dar de baja' : 'Reactivar' }}
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $u->isDirector() ? 9 : 8 }}" class="px-4 py-10 text-center text-sm text-slate-500">
                            No se registran amonestaciones para los criterios seleccionados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $records->links() }}</div>
@endsection
