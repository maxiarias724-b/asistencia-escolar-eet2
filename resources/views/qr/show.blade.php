@extends('layouts.app')
@section('title', 'Expediente Programa QR #'.$intervention->id)

@section('content')
    @php $st = $intervention->student; @endphp

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('qr.index') }}" class="text-sm text-indigo-600 hover:underline print:hidden">
                ← Volver al listado del Programa QR
            </a>
            <div class="mt-1 flex flex-wrap items-center gap-2.5">
                <span class="rounded bg-amber-500 px-2 py-0.5 text-xs font-extrabold uppercase text-slate-950">
                    Expediente QR #{{ $intervention->id }}
                </span>
                <h1 class="text-2xl font-bold text-slate-900">
                    {{ $st?->full_name ?? 'Estudiante #'.$intervention->student_id }}
                </h1>
                <span class="inline-block rounded-full px-3 py-0.5 text-xs font-bold {{ $intervention->status_badge }}">
                    {{ $intervention->status_label }}
                </span>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 print:hidden">
            <button type="button" onclick="window.print()"
                    class="rounded border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                🖨 Imprimir ficha QR
            </button>
            @if ($st)
                <a href="{{ route('reports.student-history', ['student' => $st->id]) }}"
                   class="rounded border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                    Ver historial de asistencias
                </a>
            @endif
            @can('update', $intervention)
                <a href="{{ route('qr.edit', $intervention) }}"
                   class="rounded bg-indigo-600 px-4 py-1.5 text-xs font-medium text-white hover:bg-indigo-700">
                    Editar seguimiento
                </a>
            @endcan
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Columna principal: Detalle de la intervención socioeducativa --}}
        <div class="space-y-6 lg:col-span-2">
            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 border-b border-slate-100 pb-2 text-base font-bold text-slate-900">
                    Seguimiento del Programa «Quiero Regresar»
                </h2>

                <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                    <div class="rounded border border-slate-100 bg-slate-50 p-3">
                        <dt class="text-xs font-semibold uppercase text-slate-500">Fecha de detección del riesgo</dt>
                        <dd class="mt-1 font-mono text-base font-bold text-slate-900">
                            {{ $intervention->detection_date?->format('d/m/Y') ?? '—' }}
                        </dd>
                    </div>

                    <div class="rounded border border-red-100 bg-red-50/70 p-3">
                        <dt class="text-xs font-semibold uppercase text-red-800">Faltas que detonaron la alerta</dt>
                        <dd class="mt-1 font-mono text-base font-bold text-red-800">
                            {{ $intervention->consecutive_absences_flag }} inasistencia(s)
                        </dd>
                    </div>

                    <div class="rounded border border-slate-100 bg-slate-50 p-3">
                        <dt class="text-xs font-semibold uppercase text-slate-500">Referente Equipo Territorial / Sede</dt>
                        <dd class="mt-1 font-medium text-slate-800">
                            {{ $intervention->territorial_team_contact ?: 'Pendiente de asignación' }}
                        </dd>
                    </div>

                    <div class="rounded border border-slate-100 bg-slate-50 p-3">
                        <dt class="text-xs font-semibold uppercase text-slate-500">Fecha de visita domiciliaria</dt>
                        <dd class="mt-1 font-mono font-medium text-slate-800">
                            {{ $intervention->home_visit_date?->format('d/m/Y') ?? 'Sin visita domiciliaria registrada' }}
                        </dd>
                    </div>

                    <div class="rounded border border-slate-100 bg-slate-50 p-4 sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase text-slate-500">
                            Acciones de apoyo pedagógico, entrevistas y acuerdos de revinculación
                        </dt>
                        <dd class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-800">
                            {{ $intervention->pedagogical_support_details ?: 'Aún no se han cargado observaciones de apoyo pedagógico en este expediente.' }}
                        </dd>
                    </div>
                </dl>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3 text-xs text-slate-500">
                    <span>Iniciado por: <strong>{{ $intervention->initiator?->name ?? '—' }}</strong></span>
                    <span>Última actualización: <strong>{{ $intervention->updated_at?->format('d/m/Y H:i') }}</strong></span>
                </div>
            </div>

            {{-- Últimas inasistencias registradas del estudiante --}}
            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-bold text-slate-900">Últimas inasistencias registradas</h2>
                    <span class="rounded bg-red-100 px-2.5 py-0.5 font-mono text-xs font-semibold text-red-800">
                        Total injustificadas: {{ number_format($totalUnjustified, 2) }}
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-xs">
                        <thead class="bg-slate-50 text-left font-semibold uppercase text-slate-600">
                            <tr>
                                <th class="px-3 py-2">Fecha</th>
                                <th class="px-3 py-2">Ámbito / Actividad</th>
                                <th class="px-3 py-2 text-center">Estado</th>
                                <th class="px-3 py-2 text-right">Cómputo</th>
                                <th class="px-3 py-2">Observación / Justificación</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($recentAbsences as $att)
                                <tr>
                                    <td class="whitespace-nowrap px-3 py-2 font-mono text-slate-700">
                                        {{ $att->date?->format('d/m/Y') }}
                                    </td>
                                    <td class="px-3 py-2 text-slate-700">
                                        {{ $att->scopeRef?->name ?? $att->activity_type?->label() }}
                                        @if ($att->activity)
                                            <span class="text-slate-400">· {{ $att->activity->name }}</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-center font-semibold">
                                        {{ $att->status->label() }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-right font-mono font-bold text-slate-800">
                                        {{ number_format((float) $att->absence_value, 2) }}
                                    </td>
                                    <td class="px-3 py-2 text-slate-600">
                                        {{ $att->justification?->reason_text ?? $att->observation ?? '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-6 text-center text-slate-500">
                                        El estudiante no registra inasistencias cargadas en el sistema.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Columna lateral: Ficha institucional del estudiante --}}
        <div class="space-y-6">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 border-b border-slate-100 pb-2 text-sm font-bold uppercase tracking-wide text-slate-700">
                    Datos del Estudiante
                </h2>
                @if ($st)
                    <dl class="space-y-2.5 text-sm">
                        <div>
                            <dt class="text-xs text-slate-500">Apellido y Nombre</dt>
                            <dd class="font-semibold text-slate-900">{{ $st->full_name }}</dd>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <dt class="text-xs text-slate-500">DNI</dt>
                                <dd class="font-mono text-slate-800">{{ $st->dni ?: 'S/D' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500">CUIL</dt>
                                <dd class="font-mono text-slate-800">{{ $st->cuil ?: '—' }}</dd>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <dt class="text-xs text-slate-500">Legajo</dt>
                                <dd class="font-mono text-slate-800">{{ $st->file_number ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500">Código SINIDE</dt>
                                <dd class="font-mono text-slate-800">{{ $st->sinide_code ?: '—' }}</dd>
                            </div>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Curso / Especialidad / Turno</dt>
                            <dd class="font-medium text-slate-800">
                                {{ $st->course?->name ?? '—' }}
                                · {{ $st->course?->specialty?->name ?? '' }}
                                ({{ $st->course?->shiftRef?->name ?? $st->course?->shift?->label() ?? '' }})
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Condición de matrícula</dt>
                            <dd>
                                <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $st->active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">
                                    {{ $st->status->label() }}
                                </span>
                            </dd>
                        </div>
                    </dl>
                @else
                    <p class="text-sm text-slate-500">Estudiante no encontrado.</p>
                @endif
            </div>

            @if ($otherInterventions->isNotEmpty())
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="mb-3 border-b border-slate-100 pb-2 text-sm font-bold uppercase tracking-wide text-slate-700">
                        Otros expedientes QR del estudiante
                    </h2>
                    <ul class="space-y-2 text-xs">
                        @foreach ($otherInterventions as $other)
                            <li class="flex items-center justify-between rounded border border-slate-100 bg-slate-50 px-3 py-2">
                                <div>
                                    <a href="{{ route('qr.show', $other) }}" class="font-semibold text-indigo-700 hover:underline">
                                        Expediente #{{ $other->id }} ({{ $other->detection_date?->format('d/m/Y') }})
                                    </a>
                                    <div class="text-slate-500">{{ $other->status_label }}</div>
                                </div>
                                <a href="{{ route('qr.show', $other) }}" class="text-indigo-600 hover:underline">Ver →</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
@endsection
