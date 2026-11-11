@extends('layouts.app')
@section('title', 'Mis cursos asignados')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Mis cursos asignados</h1>
            <p class="text-sm text-slate-500">Solo visualizás y operás sobre los cursos y ámbitos delegados por Dirección.</p>
        </div>
        <div class="flex flex-wrap gap-2.5 text-sm">
            <div class="rounded-lg border border-slate-200 bg-white px-3.5 py-2 shadow-sm">
                <span class="text-xs text-slate-500">Asignaciones activas:</span>
                <strong class="ml-1 text-slate-900">{{ $assignedCount }}</strong>
            </div>
            <div class="rounded-lg border border-emerald-300 bg-emerald-50 px-3.5 py-2 shadow-sm">
                <span class="text-xs font-medium text-emerald-800">✓ Asistencia tomada hoy:</span>
                <strong class="ml-1 text-emerald-900">{{ $takenCoursesCount ?? 0 }}/{{ $assignedCount }}</strong>
            </div>
            <div class="rounded-lg border border-amber-300 bg-amber-50 px-3.5 py-2 shadow-sm">
                <span class="text-xs font-medium text-amber-800">⏳ Registro pendiente:</span>
                <strong class="ml-1 text-amber-900">{{ $pendingCoursesCount ?? 0 }}</strong>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white px-3.5 py-2 shadow-sm">
                <span class="text-xs text-slate-500">Registros hoy ({{ now()->format('d/m') }}):</span>
                <strong class="ml-1 text-emerald-700">{{ $todayRecords }}</strong>
            </div>
        </div>
    </div>

    @forelse ($activities as $activity)
        @continue(! $groups->has($activity->value))
        <section class="mb-6">
            <h2 class="mb-2 text-lg font-semibold text-slate-800">{{ $activity->label() }}</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($groups[$activity->value] as $assignment)
                    @php
                        $statusKey = "{$assignment->course_id}:{$activity->value}";
                        $courseStatus = $statusByAssignment[$statusKey] ?? ['taken' => false, 'recorded' => 0, 'total_students' => 0];
                        $isTaken = (bool) $courseStatus['taken'];
                    @endphp
                    <div class="flex flex-col justify-between rounded-lg border {{ $isTaken ? 'border-emerald-300' : 'border-slate-200' }} bg-white p-4 shadow-sm transition hover:border-slate-400 hover:shadow">
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="text-xl font-bold text-slate-900">{{ $assignment->course->name }}</span>
                                @if ($assignment->course->specialty)
                                    <span class="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">{{ $assignment->course->specialty->name }}</span>
                                @endif
                            </div>
                            <div class="mt-1 text-xs text-slate-500">
                                Turno {{ $assignment->course->shift->label() }} · {{ $assignment->course->cycle->label() }}
                            </div>

                            @if ($isTaken)
                                <div class="mt-3 flex items-center justify-between rounded border border-emerald-300 bg-emerald-50 px-2.5 py-1.5 text-xs">
                                    <span class="inline-flex items-center gap-1.5 font-semibold text-emerald-800">
                                        <span aria-hidden="true">✓</span> Asistencia tomada
                                    </span>
                                    <span class="font-medium text-emerald-700">
                                        {{ $courseStatus['recorded'] }}{{ $courseStatus['total_students'] > 0 ? '/' . $courseStatus['total_students'] : '' }} reg.
                                    </span>
                                </div>
                            @else
                                <div class="mt-3 flex items-center justify-between rounded border border-amber-300 bg-amber-50 px-2.5 py-1.5 text-xs">
                                    <span class="inline-flex items-center gap-1.5 font-semibold text-amber-800">
                                        <span aria-hidden="true">⏳</span> Registro pendiente
                                    </span>
                                    <span class="font-medium text-amber-700">
                                        {{ $courseStatus['total_students'] > 0 ? $courseStatus['total_students'] . ' est.' : 'Hoy' }}
                                    </span>
                                </div>
                            @endif
                        </div>
                        <div class="mt-4 flex flex-wrap gap-1.5 border-t border-slate-100 pt-3 text-xs font-medium">
                            @if ($isTaken)
                                <a href="{{ route('attendance.daily', ['course_id' => $assignment->course_id, 'scope_id' => $assignment->scope_id]) }}"
                                   class="flex-1 rounded border border-emerald-300 bg-emerald-100 px-2.5 py-1.5 text-center font-semibold text-emerald-900 hover:bg-emerald-50"
                                   title="La asistencia de hoy ya fue registrada. Hacé clic para ver o editar.">
                                    ✓ Tomada · Editar
                                </a>
                            @else
                                <a href="{{ route('attendance.daily', ['course_id' => $assignment->course_id, 'scope_id' => $assignment->scope_id]) }}"
                                   class="flex-1 rounded bg-emerald-600 px-2.5 py-1.5 text-center font-semibold text-white hover:bg-emerald-700"
                                   title="Pendiente de toma diaria hoy ({{ now()->format('d/m/Y') }})">
                                    Toma diaria
                                </a>
                            @endif
                            <a href="{{ route('attendance.show', ['activity' => $activity->value, 'course' => $assignment->course_id]) }}"
                               class="flex-1 rounded bg-slate-900 px-2.5 py-1.5 text-center text-white hover:bg-slate-800">
                                Planilla mes
                            </a>
                            <a href="{{ route('roster.index', ['activity' => $activity->value, 'course' => $assignment->course_id]) }}"
                               class="rounded border border-slate-300 px-2.5 py-1.5 text-slate-700 hover:bg-slate-50">
                                Nómina
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @empty
    @endforelse

    @if ($groups->isEmpty())
        <div class="mb-6 rounded border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800">
            Todavía no tenés cursos ni ámbitos asignados. Solicitá la habilitación a Dirección.
        </div>
    @endif

    @include('partials.teacher-attendance-today', ['teacherToday' => $teacherToday ?? null])
@endsection
