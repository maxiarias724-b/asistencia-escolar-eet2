@extends('layouts.app')
@section('title', 'Programa QR — Quiero Regresar')

@section('content')
    @php $u = auth()->user(); @endphp

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="flex items-center gap-2">
                <span class="rounded bg-amber-500 px-2 py-0.5 text-xs font-extrabold uppercase text-slate-950">Programa QR</span>
                <h1 class="text-2xl font-bold text-slate-900">«Quiero Regresar» — Seguimiento y Revinculación</h1>
            </div>
            <p class="mt-0.5 text-sm text-slate-500">
                Prevención del abandono escolar, articulación con equipos territoriales y registro de visitas domiciliarias.
            </p>
        </div>

        @can('create', \App\Models\QrIntervention::class)
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('qr.create') }}"
                   class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
                    + Abrir expediente QR
                </a>
            </div>
        @endcan
    </div>

    @cannot('create', \App\Models\QrIntervention::class)
        <div class="mb-4 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-xs text-sky-800">
            <strong>Modo de consulta para Preceptoría (solo lectura):</strong>
            Visualizando el estado de revinculación de los estudiantes pertenecientes a sus cursos asignados.
            La apertura y actualización de expedientes del Programa QR es reservada al Equipo Directivo.
        </div>
    @endcannot

    {{-- Indicadores por estado del programa --}}
    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-5">
        <div class="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Total expedientes</div>
            <div class="mt-1 font-mono text-2xl font-bold text-slate-900">{{ $summary['total'] }}</div>
        </div>
        <div class="rounded-lg border border-amber-200 bg-amber-50/70 p-3.5 shadow-sm">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-amber-800">En evaluación</div>
            <div class="mt-1 font-mono text-2xl font-bold text-amber-800">{{ $summary['evaluacion'] }}</div>
        </div>
        <div class="rounded-lg border border-sky-200 bg-sky-50/70 p-3.5 shadow-sm">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-sky-800">Intervención activa</div>
            <div class="mt-1 font-mono text-2xl font-bold text-sky-800">{{ $summary['intervencion_activa'] }}</div>
        </div>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50/70 p-3.5 shadow-sm">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-emerald-800">Revinculados</div>
            <div class="mt-1 font-mono text-2xl font-bold text-emerald-800">{{ $summary['revinculado'] }}</div>
        </div>
        <div class="rounded-lg border border-red-200 bg-red-50/70 p-3.5 shadow-sm">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-red-800">Abandono</div>
            <div class="mt-1 font-mono text-2xl font-bold text-red-800">{{ $summary['abandono'] }}</div>
        </div>
    </div>

    {{-- Buscador y filtros --}}
    <form method="GET" action="{{ route('qr.index') }}" class="mb-5 rounded-lg border border-slate-200 bg-white p-4 shadow-sm print:hidden">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <label class="block text-xs font-semibold uppercase text-slate-600 lg:col-span-2">
                Buscar (Estudiante, DNI, Referente Territorial o Apoyo)
                <input type="search" name="q" value="{{ $filters['q'] }}"
                       placeholder="Ej.: Mamani, 47000001 o Equipo Territorial…"
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
                Estado QR
                <select name="status" class="mt-1 block w-full rounded border border-slate-300 bg-white px-2.5 py-1.5 text-sm font-normal">
                    <option value="">Todos los estados</option>
                    @foreach ($statuses as $st)
                        <option value="{{ $st->value }}" @selected($filters['status'] === $st->value)>
                            {{ $st->label() }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="block text-xs font-semibold uppercase text-slate-600">
                Detección desde
                <input type="date" name="from" value="{{ $filters['from'] }}"
                       class="mt-1 block w-full rounded border border-slate-300 px-2.5 py-1.5 text-sm font-normal">
            </label>

            <label class="block text-xs font-semibold uppercase text-slate-600">
                Detección hasta
                <input type="date" name="to" value="{{ $filters['to'] }}"
                       class="mt-1 block w-full rounded border border-slate-300 px-2.5 py-1.5 text-sm font-normal">
            </label>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3 text-xs">
            <span class="text-slate-500">Mostrando <strong>{{ $interventions->total() }}</strong> expediente(s) del Programa QR.</span>
            <div class="flex items-center gap-3">
                @if (array_filter($filters, fn ($v) => $v !== null && $v !== ''))
                    <a href="{{ route('qr.index') }}" class="text-slate-500 hover:underline">Limpiar filtros</a>
                @endif
                <button type="submit" class="rounded bg-slate-800 px-4 py-1.5 font-medium text-white hover:bg-slate-900">
                    Filtrar
                </button>
            </div>
        </div>
    </form>

    {{-- Tabla de expedientes --}}
    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-600">
                <tr>
                    <th class="px-3 py-2.5">Detección</th>
                    <th class="px-3 py-2.5">Estudiante</th>
                    <th class="px-3 py-2.5">Curso / Turno</th>
                    <th class="px-3 py-2.5 text-center">Alerta Faltas</th>
                    <th class="px-3 py-2.5">Equipo Territorial</th>
                    <th class="px-3 py-2.5 text-center">Visita Domiciliaria</th>
                    <th class="px-3 py-2.5 text-center">Estado QR</th>
                    <th class="px-3 py-2.5 text-right print:hidden">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($interventions as $item)
                    @php $st = $item->student; @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-3 py-2.5 font-mono text-xs text-slate-700">
                            {{ $item->detection_date?->format('d/m/Y') }}
                        </td>
                        <td class="px-3 py-2.5">
                            <a href="{{ route('qr.show', $item) }}" class="font-semibold text-indigo-700 hover:underline">
                                {{ $st?->full_name ?? 'Estudiante #'.$item->student_id }}
                            </a>
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
                            <span class="inline-block rounded bg-red-100 px-2.5 py-0.5 font-mono text-xs font-bold text-red-800">
                                {{ $item->consecutive_absences_flag }} faltas
                            </span>
                        </td>
                        <td class="px-3 py-2.5 text-xs text-slate-700">
                            {{ $item->territorial_team_contact ?: 'Pendiente de asignación' }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-center font-mono text-xs text-slate-700">
                            {{ $item->home_visit_date?->format('d/m/Y') ?? 'Sin registrar' }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-center">
                            <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $item->status_badge }}">
                                {{ $item->status_label }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-right text-xs print:hidden">
                            <a href="{{ route('qr.show', $item) }}"
                               class="rounded border border-slate-300 bg-white px-2.5 py-1 font-medium text-slate-700 hover:bg-slate-50">
                                Ver detalle
                            </a>
                            @can('update', $item)
                                <a href="{{ route('qr.edit', $item) }}"
                                   class="ml-1.5 rounded border border-indigo-200 bg-indigo-50 px-2.5 py-1 font-medium text-indigo-700 hover:bg-indigo-100">
                                    Seguimiento
                                </a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-sm text-slate-500">
                            No se registran expedientes del Programa QR para los criterios seleccionados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $interventions->links() }}</div>
@endsection
