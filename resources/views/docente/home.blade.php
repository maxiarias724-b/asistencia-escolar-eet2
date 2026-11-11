@extends('layouts.app')
@section('title', 'Panel de Consulta Docente')

@section('content')
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Panel de consulta docente</h1>
            <p class="text-sm text-slate-500">
                Vista institucional de asistencia y carga de observaciones áulicas · Hoy es {{ now()->translatedFormat('l d/m/Y') }}
            </p>
        </div>
        <div>
            <a href="{{ route('teacher-observations.index') }}"
               class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                <span>📝 Nómina y Observaciones de Clase</span>
            </a>
        </div>
    </div>

    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase text-slate-500">Mis cursos asignados</div>
            <div class="mt-1 text-2xl font-bold text-slate-900">{{ $assignedCourses->count() }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs uppercase text-slate-500">Mis observaciones hoy</div>
            <div class="mt-1 text-2xl font-bold text-amber-700">{{ $myTodayObservationsCount }}</div>
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

    {{-- Mis cursos asignados con acceso directo a Nómina y Observaciones --}}
    <section class="mb-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="font-semibold text-slate-900">Mis cursos asignados — Carga de Observaciones por Materia</h2>
                <p class="text-xs text-slate-500">
                    Seleccioná un curso para ver la nómina de alumnos y registrar observaciones cuando un estudiante se ausente en tu hora o contraturno.
                </p>
            </div>
        </div>

        @if ($assignedCourses->isEmpty())
            <p class="rounded border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-900">
                Aún no tenés cursos asignados en <code>course_user</code>. Solicitá a Dirección la vinculación de tus cursos.
            </p>
        @else
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($assignedCourses as $course)
                    <div class="flex flex-col justify-between rounded-lg border border-slate-200 bg-slate-50/60 p-3.5">
                        <div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-base font-bold text-slate-900">{{ $course->name }}</span>
                                @if ($course->specialty)
                                    <span class="rounded bg-slate-200/80 px-2 py-0.5 text-[11px] font-medium text-slate-700">
                                        {{ $course->specialty->name }}
                                    </span>
                                @endif
                            </div>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $course->shiftRef?->name ?? $course->shift?->label() }} ·
                                <strong>{{ $course->active_students_count }}</strong> estudiante(s) activo(s)
                            </p>
                        </div>

                        <div class="mt-3 border-t border-slate-200/80 pt-2.5">
                            <a href="{{ route('teacher-observations.index', ['course_id' => $course->id]) }}"
                               class="inline-flex w-full items-center justify-center gap-1.5 rounded bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                                <span>📝 Ver nómina y cargar observaciones</span>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

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
