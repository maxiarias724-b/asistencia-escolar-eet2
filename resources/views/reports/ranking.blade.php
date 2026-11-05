@extends('layouts.app')
@section('title', 'Ranking de Inasistencias')

@section('content')
@include('reports._tabs')

{{-- Filtros del ranking --}}
<form method="GET" action="{{ route('reports.ranking') }}" class="mb-5 rounded-lg border border-slate-200 bg-white p-4 shadow-sm print:hidden">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-600">Desde</label>
            <input type="date" name="from" value="{{ $filters['from'] }}"
                   class="mt-1 w-full rounded border border-slate-300 px-2.5 py-1.5 text-sm">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-600">Hasta</label>
            <input type="date" name="to" value="{{ $filters['to'] }}"
                   class="mt-1 w-full rounded border border-slate-300 px-2.5 py-1.5 text-sm">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-600">Turno</label>
            <select name="shift_id" class="mt-1 w-full rounded border border-slate-300 px-2.5 py-1.5 text-sm">
                <option value="">Todos los turnos</option>
                @foreach ($shifts as $sh)
                    <option value="{{ $sh->id }}" @selected(($filters['shift_id'] ?? null) == $sh->id)>{{ $sh->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-600">Ámbito</label>
            <select name="scope" class="mt-1 w-full rounded border border-slate-300 px-2.5 py-1.5 text-sm">
                <option value="">Todos los ámbitos</option>
                @foreach ($scopes as $sc)
                    <option value="{{ $sc->slug }}" @selected(($filters['scope'] ?? null) === $sc->slug)>{{ $sc->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-600">Especialidad</label>
            <select name="specialty_id" class="mt-1 w-full rounded border border-slate-300 px-2.5 py-1.5 text-sm">
                <option value="">Todas</option>
                @foreach ($specialties as $sp)
                    <option value="{{ $sp->id }}" @selected(($filters['specialty_id'] ?? null) == $sp->id)>{{ $sp->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-600">Curso</label>
            <select name="course_id" class="mt-1 w-full rounded border border-slate-300 px-2.5 py-1.5 text-sm">
                <option value="">Todos los cursos</option>
                @foreach ($courses as $c)
                    <option value="{{ $c->id }}" @selected(($filters['course_id'] ?? null) == $c->id)>{{ $c->full_name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3">
        <div class="flex items-center gap-2">
            <button type="submit" class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                Calcular ranking
            </button>
            <a href="{{ route('reports.ranking') }}" class="rounded border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50">
                Restablecer ciclo lectivo
            </a>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-xs">
            <span class="font-semibold uppercase text-slate-500">Exportar ranking:</span>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}"
               class="rounded border border-emerald-600 bg-emerald-600 px-3 py-1.5 font-semibold text-white hover:bg-emerald-700">
                ⬇ Excel (.xlsx)
            </a>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"
               class="rounded border border-slate-600 bg-slate-700 px-3 py-1.5 font-semibold text-white hover:bg-slate-800">
                ⬇ CSV (.csv)
            </a>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}"
               class="rounded border border-red-600 bg-red-600 px-3 py-1.5 font-semibold text-white hover:bg-red-700">
                ⬇ PDF (.pdf)
            </a>
        </div>
    </div>
</form>

{{-- Resumen rápido de alertas --}}
<div class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 text-xs text-slate-600">
    <div>
        Período evaluado: <strong>{{ \Carbon\Carbon::parse($filters['from'])->format('d/m/Y') }}</strong> al <strong>{{ \Carbon\Carbon::parse($filters['to'])->format('d/m/Y') }}</strong>
        · Estudiantes con inasistencias: <strong>{{ $ranking->count() }}</strong>
    </div>
    <div class="flex flex-wrap gap-2">
        <span class="rounded bg-red-100 px-2 py-0.5 font-semibold text-red-800">Riesgo Libre ≥ 25: {{ $ranking->where('total', '>=', 25)->count() }}</span>
        <span class="rounded bg-orange-100 px-2 py-0.5 font-semibold text-orange-800">Crítico 15–24.75: {{ $ranking->filter(fn ($r) => $r['total'] >= 15 && $r['total'] < 25)->count() }}</span>
        <span class="rounded bg-amber-100 px-2 py-0.5 font-semibold text-amber-800">Alerta 10–14.75: {{ $ranking->filter(fn ($r) => $r['total'] >= 10 && $r['total'] < 15)->count() }}</span>
    </div>
</div>

{{-- Tabla de Ranking --}}
<div class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
    <table class="min-w-full divide-y divide-slate-200 text-xs">
        <thead class="bg-slate-50 text-left font-semibold uppercase text-slate-600">
            <tr>
                <th class="px-3 py-2.5 text-center">#</th>
                <th class="px-3 py-2.5">Estudiante</th>
                <th class="px-3 py-2.5">DNI</th>
                <th class="px-3 py-2.5">Curso / Especialidad</th>
                <th class="px-3 py-2.5">Turno</th>
                <th class="px-3 py-2.5 text-right">Injustificadas</th>
                <th class="px-3 py-2.5 text-right">Justificadas</th>
                <th class="px-3 py-2.5 text-right">Total Faltas</th>
                <th class="px-3 py-2.5 text-center">Tardanzas</th>
                <th class="px-3 py-2.5 text-center">Nivel de Alerta</th>
                <th class="px-3 py-2.5 text-right print:hidden">Acción</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($ranking as $index => $item)
                @php $st = $item['student']; @endphp
                <tr class="hover:bg-slate-50">
                    <td class="px-3 py-2 text-center font-bold text-slate-500">{{ $index + 1 }}</td>
                    <td class="px-3 py-2 font-semibold text-slate-900">
                        <a href="{{ route('reports.student-history', ['student' => $st->id, 'from' => $filters['from'], 'to' => $filters['to']]) }}"
                           class="text-indigo-700 hover:underline">
                            {{ $st->full_name }}
                        </a>
                        @if ($st->status->value !== 'activo')
                            <span class="ml-1 rounded bg-slate-200 px-1.5 py-0.5 text-[10px] font-medium text-slate-700">{{ $st->status->label() }}</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 font-mono text-slate-700">{{ $st->dni ?: 'S/D' }}</td>
                    <td class="px-3 py-2">
                        <div class="font-medium text-slate-800">{{ $st->course?->name ?? '-' }}</div>
                        <div class="text-[11px] text-slate-500">{{ $st->course?->specialty?->name ?? '' }}</div>
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-slate-700">
                        {{ $st->course?->shiftRef?->name ?? $st->course?->shift?->label() ?? '-' }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-right font-mono font-semibold text-red-700">
                        {{ number_format($item['unjustified'], 2) }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-right font-mono text-sky-700">
                        {{ number_format($item['justified'], 2) }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-right font-mono text-sm font-bold text-slate-900">
                        {{ number_format($item['total'], 2) }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-center font-mono text-slate-700">
                        {{ $item['tardanzas'] }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-center">
                        <span class="inline-block rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $item['risk_badge'] }}">
                            {{ $item['risk_label'] }}
                        </span>
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-right print:hidden">
                        <a href="{{ route('reports.student-history', ['student' => $st->id, 'from' => $filters['from'], 'to' => $filters['to']]) }}"
                           class="rounded border border-indigo-200 bg-indigo-50 px-2.5 py-1 font-medium text-indigo-700 hover:bg-indigo-100">
                            Ver historial →
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="px-4 py-8 text-center text-sm text-slate-500">
                        No se registran estudiantes con inasistencias computables en el período seleccionado.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
