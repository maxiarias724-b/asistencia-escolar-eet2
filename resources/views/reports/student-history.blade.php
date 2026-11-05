@extends('layouts.app')
@section('title', 'Historial de '.$student->full_name)

@section('content')
@include('reports._tabs')

{{-- Ficha institucional del estudiante --}}
<div class="mb-5 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-lg font-bold text-slate-900">{{ $student->full_name }}</h2>
                @if ($student->active && $student->status->value === 'activo')
                    <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800">ACTIVO</span>
                @else
                    <span class="rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-bold text-slate-700">INACTIVO ({{ $student->status->label() }})</span>
                @endif
            </div>
            <div class="mt-1 flex flex-wrap gap-x-5 gap-y-1 text-xs text-slate-600">
                <span><strong>DNI:</strong> {{ $student->dni ?: 'S/D' }}</span>
                @if ($student->cuil)<span><strong>CUIL:</strong> {{ $student->cuil }}</span>@endif
                <span><strong>Legajo:</strong> {{ $student->file_number ?: 'S/L' }}</span>
                @if ($student->sinide_code)<span><strong>Código SINIDE:</strong> {{ $student->sinide_code }}</span>@endif
                <span><strong>Curso:</strong> {{ $student->course?->full_name ?? '-' }}</span>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-xs print:hidden">
            <a href="{{ route('reports.student-search') }}" class="rounded border border-slate-300 bg-white px-3 py-1.5 font-medium text-slate-700 hover:bg-slate-50">
                ← Buscar otro estudiante
            </a>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}"
               class="rounded bg-emerald-600 px-3 py-1.5 font-semibold text-white hover:bg-emerald-700">
                ⬇ Excel (.xlsx)
            </a>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"
               class="rounded bg-slate-700 px-3 py-1.5 font-semibold text-white hover:bg-slate-800">
                ⬇ CSV (.csv)
            </a>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}"
               class="rounded bg-red-600 px-3 py-1.5 font-semibold text-white hover:bg-red-700">
                ⬇ PDF (.pdf)
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('reports.student-history', $student) }}" class="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-3 print:hidden">
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-600">Desde</label>
            <input type="date" name="from" value="{{ $from }}" class="mt-1 rounded border border-slate-300 px-2.5 py-1.5 text-sm">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-600">Hasta</label>
            <input type="date" name="to" value="{{ $to }}" class="mt-1 rounded border border-slate-300 px-2.5 py-1.5 text-sm">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-600">Ámbito</label>
            <select name="scope" class="mt-1 rounded border border-slate-300 px-2.5 py-1.5 text-sm">
                <option value="">Todos los ámbitos</option>
                @foreach ($scopes as $sc)
                    <option value="{{ $sc->slug }}" @selected($scope === $sc->slug)>{{ $sc->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
            Actualizar período
        </button>
    </form>
</div>

{{-- Resumen numérico --}}
<div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
    <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
        <div class="text-[11px] font-semibold uppercase text-slate-500">Registros</div>
        <div class="mt-1 text-xl font-bold text-slate-900">{{ $summary['total_records'] }}</div>
    </div>
    <div class="rounded-lg border border-emerald-200 bg-emerald-50/70 p-3 shadow-sm">
        <div class="text-[11px] font-semibold uppercase text-emerald-700">Presentes (P)</div>
        <div class="mt-1 text-xl font-bold text-emerald-800">{{ $summary['present_count'] }} <span class="text-xs font-normal">({{ $summary['percent_present'] }}%)</span></div>
    </div>
    <div class="rounded-lg border border-red-200 bg-red-50/70 p-3 shadow-sm">
        <div class="text-[11px] font-semibold uppercase text-red-700">Faltas Injustif.</div>
        <div class="mt-1 text-xl font-bold text-red-800">{{ number_format($summary['unjustified_sum'], 2) }}</div>
    </div>
    <div class="rounded-lg border border-sky-200 bg-sky-50/70 p-3 shadow-sm">
        <div class="text-[11px] font-semibold uppercase text-sky-700">Faltas Justif. (IJ)</div>
        <div class="mt-1 text-xl font-bold text-sky-800">{{ number_format($summary['justified_sum'], 2) }}</div>
    </div>
    <div class="rounded-lg border border-indigo-200 bg-indigo-50/70 p-3 shadow-sm">
        <div class="text-[11px] font-semibold uppercase text-indigo-700">Total Faltas</div>
        <div class="mt-1 text-xl font-bold text-indigo-900">{{ number_format($summary['total_absences'], 2) }}</div>
    </div>
    <div class="rounded-lg border border-amber-200 bg-amber-50/70 p-3 shadow-sm">
        <div class="text-[11px] font-semibold uppercase text-amber-700">Tardanzas (T)</div>
        <div class="mt-1 text-xl font-bold text-amber-800">{{ $summary['tardanzas_count'] }}</div>
    </div>
</div>

{{-- Desglose por ámbito --}}
@if (! empty($by_scope))
    <div class="mb-5 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-xs font-bold uppercase text-slate-600">
            Desglose por Ámbito Escolar
        </div>
        <table class="min-w-full divide-y divide-slate-200 text-xs">
            <thead class="bg-slate-50/60 text-left font-semibold text-slate-600">
                <tr>
                    <th class="px-4 py-2">Ámbito</th>
                    <th class="px-4 py-2 text-right">Días Registrados</th>
                    <th class="px-4 py-2 text-right">Presentes</th>
                    <th class="px-4 py-2 text-right">Injustificadas</th>
                    <th class="px-4 py-2 text-right">Justificadas</th>
                    <th class="px-4 py-2 text-right">Total Cómputo</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($by_scope as $scopeLabel => $info)
                    <tr>
                        <td class="px-4 py-2 font-semibold text-slate-800">{{ $scopeLabel }}</td>
                        <td class="px-4 py-2 text-right font-mono">{{ $info['total_records'] }}</td>
                        <td class="px-4 py-2 text-right font-mono text-emerald-700">{{ $info['present'] }}</td>
                        <td class="px-4 py-2 text-right font-mono text-red-700">{{ number_format($info['unjustified'], 2) }}</td>
                        <td class="px-4 py-2 text-right font-mono text-sky-700">{{ number_format($info['justified'], 2) }}</td>
                        <td class="px-4 py-2 text-right font-mono font-bold text-slate-900">{{ number_format($info['justified'] + $info['unjustified'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- Detalle cronológico --}}
<div class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-xs font-bold uppercase text-slate-600">
        Detalle Cronológico de Asistencia
    </div>
    <table class="min-w-full divide-y divide-slate-200 text-xs">
        <thead class="bg-slate-50 text-left font-semibold uppercase text-slate-600">
            <tr>
                <th class="px-3 py-2.5">Fecha</th>
                <th class="px-3 py-2.5">Ámbito</th>
                <th class="px-3 py-2.5 text-center">Estado</th>
                <th class="px-3 py-2.5 text-right">Valor</th>
                <th class="px-3 py-2.5">Observación diaria</th>
                <th class="px-3 py-2.5">Respaldo de Justificación</th>
                <th class="px-3 py-2.5">Cargado por</th>
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
                    <td class="whitespace-nowrap px-3 py-2 text-slate-700">{{ $a->scopeRef?->name ?? $a->activity_type->label() }}</td>
                    <td class="whitespace-nowrap px-3 py-2 text-center">
                        <span class="inline-flex items-center gap-1 rounded px-2 py-0.5 font-bold {{ $code['cls'] }}">
                            {{ $code['sigla'] }} · {{ $a->status->label() }}
                        </span>
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-right font-mono text-slate-700">{{ number_format((float) $a->absence_value, 2) }}</td>
                    <td class="px-3 py-2 text-slate-600">{{ $a->observation ?: '—' }}</td>
                    <td class="px-3 py-2 text-slate-600">
                        @if ($a->justification)
                            <div>{{ $a->justification->reason_text ?: 'Certificado presentado' }}</div>
                            @if ($a->justification->hasCertificate())
                                <a href="{{ route('attendance.certificate', ['activity' => $a->activity_type->value, 'course' => $a->course_id, 'attendance' => $a->id]) }}"
                                   target="_blank" class="text-[11px] font-semibold text-sky-700 underline hover:text-sky-900">
                                    📎 Ver archivo adjunto
                                </a>
                            @endif
                        @else
                            <span class="text-slate-300">—</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-slate-500">{{ $a->user?->name ?? 'Sistema' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">
                        El estudiante no registra asistencias en el período consultado.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
