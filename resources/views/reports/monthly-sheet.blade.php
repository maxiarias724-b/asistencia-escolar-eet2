@extends('layouts.app')
@section('title', 'Planilla Mensual de Asistencia (01 al 31)')
@section('main_class', 'mx-auto max-w-[110rem] px-3 py-5')

@section('content')
<div class="mx-auto max-w-7xl">
    @include('reports._tabs')
</div>

{{-- Barra de selección y exportación --}}
<form method="GET" action="{{ route('reports.monthly-sheet') }}"
      class="mx-auto mb-5 max-w-7xl rounded-lg border border-slate-200 bg-white p-4 shadow-sm print:hidden">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-600">Mes Lectivo</label>
            <input type="month" name="month" value="{{ $month->format('Y-m') }}"
                   class="mt-1 w-full rounded border border-slate-300 px-2.5 py-1.5 text-sm">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-600">Ámbito</label>
            <select name="scope" class="mt-1 w-full rounded border border-slate-300 px-2.5 py-1.5 text-sm">
                @foreach ($scopes as $sc)
                    <option value="{{ $sc->slug }}" @selected($selectedScope?->slug === $sc->slug)>{{ $sc->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="lg:col-span-2">
            <label class="block text-xs font-semibold uppercase text-slate-600">Curso y División</label>
            <select name="course_id" class="mt-1 w-full rounded border border-slate-300 px-2.5 py-1.5 text-sm">
                @foreach ($courses as $c)
                    <option value="{{ $c->id }}" @selected($selectedCourse?->id === $c->id)>{{ $c->full_name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-600">Nomenclatura de Siglas</label>
            <select name="notation" class="mt-1 w-full rounded border border-slate-300 px-2.5 py-1.5 text-sm">
                <option value="patij" @selected($notation === 'patij')>Institucional (P, A, T, IJ)</option>
                <option value="sinide" @selected($notation === 'sinide')>SINIDE Oficial (P, C, Q, M, J)</option>
            </select>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3">
        <div class="flex flex-wrap items-center gap-2">
            <button type="submit" class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                Generar planilla
            </button>
            @if ($selectedCourse && $selectedScope)
                <a href="{{ route('attendance.show', ['activity' => $selectedScope->slug, 'course' => $selectedCourse->id, 'month' => $month->format('Y-m')]) }}"
                   class="rounded border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                    ✏️ Ir a grilla interactiva
                </a>
                <a href="{{ route('attendance.daily', ['scope_id' => $selectedScope->id, 'course_id' => $selectedCourse->id]) }}"
                   class="rounded border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                    📋 Toma diaria
                </a>
            @endif
        </div>

        @if ($selectedCourse && $selectedScope)
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="font-semibold uppercase text-slate-500">Exportar:</span>
                <a href="{{ route('reports.monthly-sheet', ['month' => $month->format('Y-m'), 'scope' => $selectedScope->slug, 'course_id' => $selectedCourse->id, 'notation' => $notation, 'export' => 'xlsx']) }}"
                   class="rounded bg-emerald-600 px-3 py-1.5 font-semibold text-white hover:bg-emerald-700">
                    ⬇ Excel (.xlsx)
                </a>
                <a href="{{ route('reports.monthly-sheet', ['month' => $month->format('Y-m'), 'scope' => $selectedScope->slug, 'course_id' => $selectedCourse->id, 'notation' => $notation, 'export' => 'csv']) }}"
                   class="rounded bg-slate-700 px-3 py-1.5 font-semibold text-white hover:bg-slate-800">
                    ⬇ CSV (.csv)
                </a>
                <a href="{{ route('reports.monthly-sheet', ['month' => $month->format('Y-m'), 'scope' => $selectedScope->slug, 'course_id' => $selectedCourse->id, 'notation' => $notation, 'export' => 'pdf']) }}"
                   class="rounded bg-red-600 px-3 py-1.5 font-semibold text-white hover:bg-red-700">
                    ⬇ PDF (.pdf)
                </a>
                <button type="button" onclick="window.print()"
                        class="rounded border border-slate-300 bg-white px-3 py-1.5 font-semibold text-slate-700 hover:bg-slate-50">
                    🖨 Imprimir
                </button>
            </div>
        @endif
    </div>
</form>

@if (! $sheet)
    <div class="mx-auto max-w-7xl rounded-lg border border-amber-200 bg-amber-50 p-6 text-center text-sm text-amber-800">
        No hay cursos o ámbitos disponibles para mostrar la planilla mensual.
    </div>
@else
    {{-- Encabezado Oficial EET N° 2 / SINIDE --}}
    <div class="mb-3 rounded-lg border border-slate-300 bg-white p-4 shadow-sm print:border-0 print:p-0 print:shadow-none">
        <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2 border-b border-slate-200 pb-2">
            <div>
                <div class="text-xs font-extrabold uppercase tracking-wide text-slate-900">
                    ESCUELA DE EDUCACIÓN TÉCNICA N° 2 «PROF. JESÚS RAÚL SALAZAR»
                </div>
                <div class="text-sm font-bold uppercase text-slate-800">
                    REGISTRO DE ASISTENCIA MENSUAL CORRESPONDIENTE AL {{ $selectedCourse->name }}
                    ({{ $selectedCourse->shiftRef?->name ?? $selectedCourse->shift->label() }} · {{ $selectedScope->name }})
                </div>
            </div>
            <div class="text-xs text-slate-700">
                <strong>Mes de {{ $sheet['month_label'] }}</strong> &nbsp;·&nbsp;
                Días hábiles del mes: <strong>{{ $sheet['working_days'] }}</strong>
                @if ($sheet['base_days'] !== $sheet['working_days'])
                    <span class="text-slate-500">(computados a la fecha: {{ $sheet['base_days'] }})</span>
                @endif
            </div>
        </div>

        {{-- Leyenda de siglas --}}
        <div class="mb-3 flex flex-wrap items-center gap-4 text-[11px] text-slate-600 print:hidden">
            @if ($notation === 'sinide')
                <span><strong class="text-emerald-700">P</strong> = Presente (0)</span>
                <span><strong class="text-red-700">C</strong> = Ausente completa (1.00)</span>
                <span><strong class="text-amber-700">Q</strong> = Tardanza / Cuarto (0.25)</span>
                <span><strong class="text-orange-700">M</strong> = Media falta (0.50)</span>
                <span><strong class="text-sky-700">J</strong> = Inasistencia Justificada</span>
            @else
                <span><strong class="text-emerald-700">P</strong> = Presente (0)</span>
                <span><strong class="text-red-700">A</strong> = Ausente (1.00)</span>
                <span><strong class="text-amber-700">T</strong> = Tardanza (0.25)</span>
                <span><strong class="text-orange-700">M</strong> = Media falta (0.50)</span>
                <span><strong class="text-sky-700">IJ</strong> = Inasistencia Justificada</span>
            @endif
            <span><strong>-</strong> = Fin de semana / Feriado</span>
        </div>

        {{-- Grilla Mensual del 01 al 31 --}}
        <div class="overflow-x-auto print:overflow-visible">
            <table class="min-w-full border-collapse text-[11px]">
                <thead>
                    <tr class="bg-slate-100 text-slate-800">
                        <th rowspan="2" class="min-w-[12rem] border border-slate-400 px-2 py-1 text-left font-bold">Apellido y Nombre</th>
                        @foreach ($sheet['days'] as $d)
                            <th rowspan="2" class="w-6 min-w-[1.45rem] border border-slate-400 px-0.5 py-1 text-center font-bold {{ ! $d['working'] ? 'bg-slate-200 text-slate-500' : '' }}"
                                title="{{ $d['reason'] ?? $d['weekday'] }}">
                                {{ str_pad((string) $d['n'], 2, '0', STR_PAD_LEFT) }}
                            </th>
                        @endforeach
                        <th rowspan="2" class="border border-slate-400 px-1.5 py-1 text-center font-bold">ASIST.</th>
                        <th colspan="2" class="border border-slate-400 px-1.5 py-0.5 text-center font-bold">INASIST. DEL MES</th>
                        <th rowspan="2" class="border border-slate-400 px-1.5 py-1 text-center font-bold">Anterior*</th>
                        <th rowspan="2" class="border border-slate-400 px-1.5 py-1 text-center font-bold">TOTAL</th>
                        <th rowspan="2" class="min-w-[10rem] border border-slate-400 px-2 py-1 text-left font-bold">OBSERVACIONES</th>
                    </tr>
                    <tr class="bg-slate-100 text-slate-800">
                        <th class="border border-slate-400 px-1.5 py-0.5 text-center font-bold">J.</th>
                        <th class="border border-slate-400 px-1.5 py-0.5 text-center font-bold">INJ.</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sheet['rows'] as $row)
                        <tr class="hover:bg-slate-50 {{ ! $row['active'] ? 'bg-slate-50 text-slate-500' : '' }}">
                            <td class="border border-slate-300 px-2 py-1 font-medium">
                                <a href="{{ route('reports.student-history', $row['id']) }}" class="hover:underline">
                                    {{ $row['name'] }}
                                </a>
                                @if (! $row['active'])
                                    <span class="text-[9px] uppercase text-slate-400">({{ $row['status_label'] }})</span>
                                @endif
                            </td>
                            @foreach ($sheet['days'] as $d)
                                @php
                                    $cell = $row['cells'][$d['n']] ?? null;
                                    $code = ! $d['working'] ? '-' : ($cell['display_code'] ?? '');
                                    $cellCls = match ($cell['status'] ?? null) {
                                        'presente' => 'text-emerald-700 font-semibold',
                                        'ausente' => 'bg-red-50 text-red-700 font-bold',
                                        'tardanza' => 'bg-amber-50 text-amber-800 font-semibold',
                                        'media_falta' => 'bg-orange-50 text-orange-800 font-semibold',
                                        'justificada' => 'bg-sky-50 text-sky-800 font-bold',
                                        default => ! $d['working'] ? 'bg-slate-100 text-slate-400' : 'text-slate-300',
                                    };
                                @endphp
                                <td class="border border-slate-300 px-0.5 py-1 text-center {{ $cellCls }}">
                                    {{ $code }}
                                </td>
                            @endforeach
                            <td class="border border-slate-300 px-1.5 py-1 text-right font-mono">{{ number_format($row['asist'], 2) }}</td>
                            <td class="border border-slate-300 px-1.5 py-1 text-right font-mono">{{ number_format($row['j'], 2) }}</td>
                            <td class="border border-slate-300 px-1.5 py-1 text-right font-mono">{{ number_format($row['inj'], 2) }}</td>
                            <td class="border border-slate-300 px-1.5 py-1 text-right font-mono text-slate-600">{{ number_format($row['anterior'], 2) }}</td>
                            <td class="border border-slate-300 px-1.5 py-1 text-right font-mono font-bold">{{ number_format($row['total'], 2) }}</td>
                            <td class="border border-slate-300 px-2 py-1 text-[10px] text-slate-600">{{ $row['observations'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($sheet['days']) + 7 }}" class="border border-slate-300 px-4 py-6 text-center text-sm text-slate-500">
                                Este curso aún no posee estudiantes activos ni asistencias registradas en el mes.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="mt-2 text-[10px] text-slate-500">* Inasistencias acumuladas del ciclo lectivo hasta el mes anterior.</p>

        {{-- Cuadros resumen oficiales estilo SINIDE + Firmas --}}
        <div class="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-3">
            <table class="w-full max-w-xs border-collapse text-xs">
                <tbody>
                    <tr><td class="border border-slate-400 px-2.5 py-1">Alumnos/as Inscriptos</td><td class="border border-slate-400 px-2.5 py-1 text-right font-bold">{{ $sheet['footer']['enrolled'] }}</td></tr>
                    <tr><td class="border border-slate-400 px-2.5 py-1">Alumnos/as Entrada</td><td class="border border-slate-400 px-2.5 py-1 text-right font-bold">{{ $sheet['footer']['entered'] }}</td></tr>
                    <tr><td class="border border-slate-400 px-2.5 py-1">Alumnos/as Salida</td><td class="border border-slate-400 px-2.5 py-1 text-right font-bold">{{ $sheet['footer']['left'] }}</td></tr>
                    <tr><td class="border border-slate-400 px-2.5 py-1">Quedan el último día</td><td class="border border-slate-400 px-2.5 py-1 text-right font-bold">{{ $sheet['footer']['remaining'] }}</td></tr>
                </tbody>
            </table>

            <table class="w-full max-w-xs border-collapse text-xs">
                <tbody>
                    <tr><td class="border border-slate-400 px-2.5 py-1">Total de Asistencias</td><td class="border border-slate-400 px-2.5 py-1 text-right font-bold">{{ number_format($sheet['footer']['total_asist'], 2) }}</td></tr>
                    <tr><td class="border border-slate-400 px-2.5 py-1">Total de Inasistencias</td><td class="border border-slate-400 px-2.5 py-1 text-right font-bold">{{ number_format($sheet['footer']['total_inasist'], 2) }}</td></tr>
                    <tr><td class="border border-slate-400 px-2.5 py-1">Asistencia Media</td><td class="border border-slate-400 px-2.5 py-1 text-right font-bold">{{ number_format($sheet['footer']['media'], 2) }}</td></tr>
                    <tr><td class="border border-slate-400 px-2.5 py-1">% de Asistencia</td><td class="border border-slate-400 px-2.5 py-1 text-right font-bold">{{ number_format($sheet['footer']['percent'], 2) }}%</td></tr>
                </tbody>
            </table>

            <div class="flex items-end justify-around gap-6 pb-2 pt-8 text-center text-xs text-slate-700">
                <div class="w-36 border-t border-slate-500 pt-1 font-semibold">FIRMA</div>
                <div class="w-36 border-t border-slate-500 pt-1 font-semibold">V° B° DIRECCIÓN</div>
            </div>
        </div>

        {{-- Anexo Institucional: Observaciones Docentes de Clase --}}
        <div class="mt-6 border-t-2 border-slate-300 pt-4">
            <div class="mb-2 flex items-center justify-between">
                <h3 class="text-xs font-extrabold uppercase tracking-wide text-slate-900">
                    Anexo Institucional: Observaciones Docentes de Clase (Incidencias por Materia)
                </h3>
                <span class="text-[11px] text-slate-500">
                    Total del mes: <strong>{{ count($sheet['teacher_observations'] ?? []) }}</strong> registro(s)
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-100 text-left text-[11px] font-bold uppercase text-slate-700">
                            <th class="w-24 border border-slate-300 px-2.5 py-1.5">Fecha</th>
                            <th class="w-56 border border-slate-300 px-2.5 py-1.5">Alumno</th>
                            <th class="w-48 border border-slate-300 px-2.5 py-1.5">Materia</th>
                            <th class="w-48 border border-slate-300 px-2.5 py-1.5">Docente</th>
                            <th class="border border-slate-300 px-2.5 py-1.5">Observación</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sheet['teacher_observations'] ?? [] as $tObs)
                            <tr class="hover:bg-slate-50">
                                <td class="border border-slate-300 px-2.5 py-1.5 font-mono">{{ $tObs['date_formatted'] }}</td>
                                <td class="border border-slate-300 px-2.5 py-1.5 font-semibold text-slate-900">{{ $tObs['student_name'] }}</td>
                                <td class="border border-slate-300 px-2.5 py-1.5">{{ $tObs['activity_name'] }}</td>
                                <td class="border border-slate-300 px-2.5 py-1.5">{{ $tObs['teacher_name'] }}</td>
                                <td class="border border-slate-300 px-2.5 py-1.5 text-slate-700">{{ $tObs['observation_text'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="border border-slate-300 px-3 py-3 text-center text-xs text-slate-500">
                                    Sin observaciones docentes de clase registradas en este curso durante el período seleccionado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
@endsection
