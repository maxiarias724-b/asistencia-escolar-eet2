@extends('layouts.app')
@section('title', 'Consulta Histórica de Asistencia')

@section('content')
@include('reports._tabs')

{{-- Filtros combinados --}}
<form method="GET" action="{{ route('reports.index') }}" class="mb-5 rounded-lg border border-slate-200 bg-white p-4 shadow-sm print:hidden">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
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
            <label class="block text-xs font-semibold uppercase text-slate-600">Curso y División</label>
            <select name="course_id" class="mt-1 w-full rounded border border-slate-300 px-2.5 py-1.5 text-sm">
                <option value="">Todos los cursos</option>
                @foreach ($courses as $c)
                    <option value="{{ $c->id }}" @selected(($filters['course_id'] ?? null) == $c->id)>{{ $c->full_name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-600">Especialidad</label>
            <select name="specialty_id" class="mt-1 w-full rounded border border-slate-300 px-2.5 py-1.5 text-sm">
                <option value="">Todas las especialidades</option>
                @foreach ($specialties as $sp)
                    <option value="{{ $sp->id }}" @selected(($filters['specialty_id'] ?? null) == $sp->id)>{{ $sp->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-600">Estado de Asistencia</label>
            <select name="status" class="mt-1 w-full rounded border border-slate-300 px-2.5 py-1.5 text-sm">
                <option value="">Todos los estados</option>
                @foreach ($statuses as $st)
                    <option value="{{ $st->value }}" @selected(($filters['status'] ?? null) === $st->value)>{{ $st->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-600">Estudiante (DNI / Apellido / Legajo)</label>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Ej. 45123456 o Mamani"
                   class="mt-1 w-full rounded border border-slate-300 px-2.5 py-1.5 text-sm">
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3">
        <div class="flex items-center gap-2">
            <button type="submit" class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                Filtrar registros
            </button>
            <a href="{{ route('reports.index') }}" class="rounded border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50">
                Limpiar
            </a>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-xs">
            <span class="font-semibold uppercase text-slate-500">Exportar consulta:</span>
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

{{-- Tarjetas KPI --}}
<div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
    <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
        <div class="text-[11px] font-semibold uppercase text-slate-500">Total Registros</div>
        <div class="mt-1 text-xl font-bold text-slate-900">{{ number_format($kpis['total']) }}</div>
    </div>
    <div class="rounded-lg border border-emerald-200 bg-emerald-50/70 p-3 shadow-sm">
        <div class="text-[11px] font-semibold uppercase text-emerald-700">Presentes (P)</div>
        <div class="mt-1 text-xl font-bold text-emerald-800">{{ number_format($kpis['presente']) }}</div>
    </div>
    <div class="rounded-lg border border-red-200 bg-red-50/70 p-3 shadow-sm">
        <div class="text-[11px] font-semibold uppercase text-red-700">Ausentes (A)</div>
        <div class="mt-1 text-xl font-bold text-red-800">{{ number_format($kpis['ausente']) }}</div>
    </div>
    <div class="rounded-lg border border-amber-200 bg-amber-50/70 p-3 shadow-sm">
        <div class="text-[11px] font-semibold uppercase text-amber-700">Tardanzas / Media</div>
        <div class="mt-1 text-xl font-bold text-amber-800">{{ number_format($kpis['tardanza']) }}</div>
    </div>
    <div class="rounded-lg border border-sky-200 bg-sky-50/70 p-3 shadow-sm">
        <div class="text-[11px] font-semibold uppercase text-sky-700">Justificadas (IJ)</div>
        <div class="mt-1 text-xl font-bold text-sky-800">{{ number_format($kpis['justificada']) }}</div>
    </div>
    <div class="rounded-lg border border-indigo-200 bg-indigo-50/70 p-3 shadow-sm">
        <div class="text-[11px] font-semibold uppercase text-indigo-700">% Presencia</div>
        <div class="mt-1 text-xl font-bold text-indigo-800">{{ number_format($kpis['percent_present'], 1) }}%</div>
    </div>
</div>

{{-- Tabla de resultados --}}
<div class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
    <table class="min-w-full divide-y divide-slate-200 text-xs">
        <thead class="bg-slate-50 text-left font-semibold uppercase text-slate-600">
            <tr>
                <th class="px-3 py-2.5">Fecha</th>
                <th class="px-3 py-2.5">Estudiante</th>
                <th class="px-3 py-2.5">Curso / Turno</th>
                <th class="px-3 py-2.5">Ámbito</th>
                <th class="px-3 py-2.5 text-center">Estado</th>
                <th class="px-3 py-2.5 text-right">Valor</th>
                <th class="px-3 py-2.5">Observación / Justificación</th>
                <th class="px-3 py-2.5">Registrado por</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($records as $a)
                @php
                    $code = match ($a->status->value) {
                        'presente' => ['sigla' => 'P', 'cls' => 'bg-emerald-100 text-emerald-800'],
                        'ausente' => ['sigla' => 'A', 'cls' => 'bg-red-100 text-red-800'],
                        'tardanza' => ['sigla' => 'T', 'cls' => 'bg-amber-100 text-amber-800'],
                        'media_falta' => ['sigla' => 'M', 'cls' => 'bg-orange-100 text-orange-800'],
                        'justificada' => ['sigla' => 'IJ', 'cls' => 'bg-sky-100 text-sky-800'],
                        default => ['sigla' => '-', 'cls' => 'bg-slate-100 text-slate-700'],
                    };
                @endphp
                <tr class="hover:bg-slate-50">
                    <td class="whitespace-nowrap px-3 py-2 font-medium text-slate-800">{{ $a->date->format('d/m/Y') }}</td>
                    <td class="px-3 py-2">
                        @if ($a->student)
                            <a href="{{ route('reports.student-history', $a->student_id) }}" class="font-semibold text-indigo-700 hover:underline">
                                {{ $a->student->full_name }}
                            </a>
                            <div class="text-[11px] text-slate-500">DNI {{ $a->student->dni ?: 'S/D' }}</div>
                        @else
                            <span class="text-slate-400">Estudiante no disponible</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-3 py-2">
                        <div class="font-medium text-slate-800">{{ $a->course?->name ?? '-' }}</div>
                        <div class="text-[11px] text-slate-500">{{ $a->course?->shiftRef?->name ?? $a->course?->shift?->label() ?? '' }}</div>
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-slate-700">
                        {{ $a->scopeRef?->name ?? $a->activity_type->label() }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-center">
                        <span class="inline-flex items-center gap-1 rounded px-2 py-0.5 font-bold {{ $code['cls'] }}">
                            <span>{{ $code['sigla'] }}</span>
                            <span class="font-normal">· {{ $a->status->label() }}</span>
                        </span>
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-right font-mono text-slate-700">
                        {{ number_format((float) $a->absence_value, 2) }}
                    </td>
                    <td class="max-w-xs px-3 py-2 text-slate-600">
                        @if ($a->observation)
                            <div>{{ $a->observation }}</div>
                        @endif
                        @if ($a->justification)
                            <div class="mt-0.5 text-[11px] text-sky-700">
                                @if ($a->justification->reason_text)
                                    <span><strong>Justif.:</strong> {{ $a->justification->reason_text }}</span>
                                @endif
                                @if ($a->justification->hasCertificate())
                                    <a href="{{ route('attendance.certificate', ['activity' => $a->activity_type->value, 'course' => $a->course_id, 'attendance' => $a->id]) }}"
                                       target="_blank" class="ml-1 font-semibold underline hover:text-sky-900">
                                        📎 Ver certificado
                                    </a>
                                @endif
                            </div>
                        @endif
                        @if (! $a->observation && ! $a->justification)
                            <span class="text-slate-300">—</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-slate-500">
                        {{ $a->user?->name ?? 'Sistema' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">
                        No se encontraron registros de asistencia para los criterios seleccionados.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($records->hasPages())
    <div class="mt-4 print:hidden">
        {{ $records->links() }}
    </div>
@endif
@endsection
