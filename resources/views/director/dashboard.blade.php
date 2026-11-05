@extends('layouts.app')
@section('title', 'Dashboard global')

@section('content')
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Dashboard global</h1>
            <p class="text-sm text-slate-500">
                EET N° 2 "Prof. Jesús Raúl Salazar" · Período: {{ ucfirst($stats->from->translatedFormat('F Y')) }} · Hoy: {{ now()->translatedFormat('l d/m/Y') }}
            </p>
        </div>

        <form method="GET" class="flex flex-wrap items-end gap-2 text-sm">
            <label class="block">Mes
                <input type="month" name="month" value="{{ $filters['month'] }}" class="mt-1 block rounded border border-slate-300 bg-white px-2 py-1">
            </label>
            <label class="block">Curso
                <select name="course_id" class="mt-1 block rounded border border-slate-300 bg-white px-2 py-1">
                    <option value="">Todos</option>
                    @foreach ($courses as $c)
                        <option value="{{ $c->id }}" @selected(($filters['course_id'] ?? null) == $c->id)>{{ $c->full_name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">Ámbito
                <select name="activity_type" class="mt-1 block rounded border border-slate-300 bg-white px-2 py-1">
                    <option value="">Todos</option>
                    @foreach ($activities as $a)
                        <option value="{{ $a->value }}" @selected(($filters['activity_type'] ?? null) === $a->value)>{{ $a->label() }}</option>
                    @endforeach
                </select>
            </label>
            <button class="rounded bg-slate-900 px-3 py-1.5 font-medium text-white hover:bg-slate-800">Aplicar</button>
            <a href="{{ route('director.dashboard') }}" class="px-2 py-1.5 text-slate-500 hover:underline">Limpiar</a>
        </form>
    </div>

    {{-- Bloque 1: Indicadores Institucionales + Resumen en Vivo del Día Actual --}}
    <div class="mb-6 grid gap-4 lg:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Padrón institucional activo</div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div>
                    <div class="text-xs text-slate-500">Estudiantes</div>
                    <div class="text-2xl font-bold text-slate-900">{{ $counters['students'] }}</div>
                </div>
                <div>
                    <div class="text-xs text-slate-500">Docentes</div>
                    <div class="text-2xl font-bold text-slate-900">{{ $counters['teachers'] }}</div>
                </div>
                <div>
                    <div class="text-xs text-slate-500">Preceptores</div>
                    <div class="text-2xl font-bold text-slate-900">{{ $counters['operators'] }}</div>
                </div>
                <div>
                    <div class="text-xs text-slate-500">Cursos</div>
                    <div class="text-2xl font-bold text-slate-900">{{ $counters['courses'] }}</div>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-2 flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Asistencia registrada hoy ({{ now()->format('d/m/Y') }})</span>
                <span class="text-xs text-slate-400">Docentes hoy: {{ $todayTotals['teachers']['total'] }}</span>
            </div>
            <div class="grid grid-cols-5 gap-2 text-center">
                <div class="rounded bg-emerald-50 p-2">
                    <div class="text-[11px] font-medium text-emerald-700">Presentes</div>
                    <div class="text-xl font-bold text-emerald-800">{{ $todayTotals['students']['presente'] }}</div>
                </div>
                <div class="rounded bg-red-50 p-2">
                    <div class="text-[11px] font-medium text-red-700">Ausentes</div>
                    <div class="text-xl font-bold text-red-800">{{ $todayTotals['students']['ausente'] }}</div>
                </div>
                <div class="rounded bg-amber-50 p-2">
                    <div class="text-[11px] font-medium text-amber-700">Tardes</div>
                    <div class="text-xl font-bold text-amber-800">{{ $todayTotals['students']['tardanza'] }}</div>
                </div>
                <div class="rounded bg-orange-50 p-2">
                    <div class="text-[11px] font-medium text-orange-700">½ Falta</div>
                    <div class="text-xl font-bold text-orange-800">{{ $todayTotals['students']['media_falta'] }}</div>
                </div>
                <div class="rounded bg-sky-50 p-2">
                    <div class="text-[11px] font-medium text-sky-700">Justif.</div>
                    <div class="text-xl font-bold text-sky-800">{{ $todayTotals['students']['justificada'] }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Bloque 2: Tarjetas acumuladas del período filtrado (SINIDE) --}}
    <div class="mb-3 grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-7">
        @php
            $cards = [
                ['% Asistencia', is_null($totals['attendance_rate']) ? '—' : $totals['attendance_rate'].'%', 'text-indigo-700'],
                ['Presentes (P)', $totals['presente'], 'text-emerald-700'],
                ['Ausentes (C)', $totals['ausente'], 'text-red-700'],
                ['Tardanzas (Q)', $totals['tardanza'], 'text-amber-700'],
                ['Medias faltas (M)', $totals['media_falta'], 'text-orange-700'],
                ['Justificadas (J)', $totals['justificada'], 'text-sky-700'],
                ['Con observación', $totals['observations'], 'text-slate-700'],
            ];
        @endphp
        @foreach ($cards as [$label, $value, $color])
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-xs uppercase tracking-wide text-slate-500">{{ $label }}</div>
                <div class="mt-1 text-2xl font-bold {{ $color }}">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <p class="mb-6 text-xs text-slate-500">
        {{ $totals['total'] }} registros en el período seleccionado · % Asistencia ponderado SINIDE = (registros − inasistencia ponderada) / registros, donde C = 1, M = ½, Q = ¼ y J conserva el peso de la falta original sin computar como injustificada. Inasistencia ponderada del período: <strong>{{ $totals['absence_value'] }}</strong>.
    </p>

    {{-- Por curso --}}
    <section class="mb-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <h2 class="mb-3 font-semibold">Asistencia por curso</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-500">
                <tr><th class="py-2 pr-4">Curso</th><th class="px-2">P</th><th class="px-2">C</th><th class="px-2">Q</th><th class="px-2">M</th><th class="px-2">J</th><th class="px-2">Total</th><th class="px-2">% Asist.</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($byCourse as $row)
                    <tr>
                        <td class="py-2 pr-4 font-medium">{{ $row['label'] }}</td>
                        <td class="px-2">{{ $row['presente'] }}</td>
                        <td class="px-2 text-red-700">{{ $row['ausente'] }}</td>
                        <td class="px-2">{{ $row['tardanza'] }}</td>
                        <td class="px-2">{{ $row['media_falta'] }}</td>
                        <td class="px-2">{{ $row['justificada'] }}</td>
                        <td class="px-2">{{ $row['total'] }}</td>
                        <td class="px-2">
                            @if (is_null($row['rate'])) — @else
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-24 rounded bg-slate-200"><div class="h-2 rounded {{ $row['rate'] >= 85 ? 'bg-emerald-500' : ($row['rate'] >= 70 ? 'bg-amber-500' : 'bg-red-500') }}" style="width: {{ $row['rate'] }}%"></div></div>
                                    {{ $row['rate'] }}%
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-4 text-center text-slate-500">Sin registros de asistencia para este período.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        {{-- Por ámbito --}}
        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="mb-3 font-semibold">Desglose por ámbito</h2>
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-500">
                <tr><th class="py-2">Ámbito</th><th>P</th><th>C</th><th>Q</th><th>M</th><th>J</th><th>% Asist.</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($byActivity as $row)
                    <tr>
                        <td class="py-2 font-medium">{{ $row['label'] }}</td>
                        <td>{{ $row['presente'] }}</td><td class="text-red-700">{{ $row['ausente'] }}</td>
                        <td>{{ $row['tardanza'] }}</td><td>{{ $row['media_falta'] }}</td><td>{{ $row['justificada'] }}</td>
                        <td>{{ is_null($row['rate']) ? '—' : $row['rate'].'%' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-4 text-center text-slate-500">Sin datos.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>

        {{-- Top inasistencias --}}
        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 class="font-semibold">Ranking de estudiantes con más inasistencia injustificada</h2>
                <a href="{{ route('reports.ranking') }}" class="text-xs font-medium text-indigo-600 hover:underline">Ver ranking completo →</a>
            </div>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($topUnjustified as $row)
                    <li class="flex items-center justify-between py-2">
                        <a href="{{ route('reports.student-history', $row->student_id) }}" class="hover:underline">
                            {{ $row->student->full_name }} <span class="text-slate-500">· {{ $row->student->course->name }}</span>
                        </a>
                        <span class="rounded bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">{{ rtrim(rtrim(number_format($row->value, 2), '0'), '.') }}</span>
                    </li>
                @empty
                    <li class="py-4 text-center text-slate-500">Sin inasistencias injustificadas.</li>
                @endforelse
            </ul>
        </section>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-semibold">Últimas observaciones diarias</h2>
                <a href="{{ route('director.audit.index', ['with_observation' => 1, 'from' => $stats->from->toDateString(), 'to' => $stats->to->toDateString()]) }}" class="text-sm text-indigo-600 hover:underline">Ver todas</a>
            </div>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($recentObservations as $a)
                    <li class="py-2">
                        <div class="flex items-center justify-between gap-2">
                            <strong>{{ $a->student->full_name }}</strong>
                            <x-status-badge :status="$a->status" />
                        </div>
                        <div class="text-xs text-slate-500">{{ $a->date->format('d/m/Y') }} · {{ $a->course->name }} · {{ $a->activity_type->label() }} · {{ $a->user->name }}</div>
                        <p class="mt-1 italic">“{{ $a->observation }}”</p>
                    </li>
                @empty
                    <li class="py-4 text-center text-slate-500">Sin observaciones en el período.</li>
                @endforelse
            </ul>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-semibold">Últimas justificaciones documentadas</h2>
                <a href="{{ route('director.audit.index', ['status' => 'justificada', 'from' => $stats->from->toDateString(), 'to' => $stats->to->toDateString()]) }}" class="text-sm text-indigo-600 hover:underline">Auditar</a>
            </div>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($recentJustifications as $j)
                    <li class="py-2">
                        <strong>{{ $j->attendance->student->full_name }}</strong>
                        <span class="text-slate-500">· {{ $j->attendance->course->name }} · {{ $j->attendance->date->format('d/m/Y') }}</span>
                        <div class="text-xs text-slate-500">
                            Justificada por {{ $j->justifiedBy?->name }} el {{ $j->justified_at->format('d/m/Y H:i') }}
                            · {{ $j->reason_text ? '📝 texto' : '' }} {{ $j->certificate_path ? '📎 certificado' : '' }}
                        </div>
                    </li>
                @empty
                    <li class="py-4 text-center text-slate-500">Sin justificaciones en el período.</li>
                @endforelse
            </ul>
        </section>
    </div>
@endsection
