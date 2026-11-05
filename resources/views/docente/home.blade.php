@extends('layouts.app')
@section('title', 'Panel de Consulta Docente')

@section('content')
    <div class="mb-5">
        <h1 class="text-2xl font-bold text-slate-900">Panel de consulta docente</h1>
        <p class="text-sm text-slate-500">
            Vista institucional de solo lectura · Hoy es {{ now()->translatedFormat('l d/m/Y') }}
        </p>
    </div>

    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase text-slate-500">Cursos activos</div>
            <div class="mt-1 text-2xl font-bold text-slate-900">{{ $counters['courses'] }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase text-slate-500">Estudiantes activos</div>
            <div class="mt-1 text-2xl font-bold text-slate-900">{{ $counters['students'] }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase text-slate-500">% Asistencia del mes</div>
            <div class="mt-1 text-2xl font-bold text-indigo-700">{{ is_null($totals['attendance_rate']) ? '—' : $totals['attendance_rate'].'%' }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase text-slate-500">Presentes hoy</div>
            <div class="mt-1 text-2xl font-bold text-emerald-700">{{ $todayTotals['students']['presente'] }}</div>
        </div>
    </div>

    <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <h2 class="mb-3 font-semibold">Resumen mensual de asistencia por curso</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-500">
                <tr>
                    <th class="py-2 pr-4">Curso</th>
                    <th class="px-2">Presentes</th>
                    <th class="px-2">Ausentes</th>
                    <th class="px-2">Tardanzas</th>
                    <th class="px-2">½ Falta</th>
                    <th class="px-2">Justificadas</th>
                    <th class="px-2">% Asistencia</th>
                </tr>
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
                        <td class="px-2 font-medium">{{ is_null($row['rate']) ? '—' : $row['rate'].'%' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-6 text-center text-slate-500">Aún no hay registros cargados en el mes actual.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
