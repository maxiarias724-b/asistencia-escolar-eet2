@extends('layouts.app')
@section('title', 'Auditoría de asistencias y certificados médicos')

@section('content')
<div x-data="{ open: false, j: null, show(data) { this.j = data; this.open = true }, close() { this.open = false; this.j = null } }"
     @keydown.escape.window="close()">

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Auditoría de Asistencias y Visor de Certificados Médicos</h1>
            <p class="text-sm text-slate-500">Supervisión exclusiva de Dirección: estados, observaciones diarias, responsable de carga, textos justificativos y certificados adjuntos.</p>
        </div>
        <div class="flex gap-2 text-xs">
            <a href="{{ route('director.audit-logs.index') }}" class="rounded border border-slate-300 bg-white px-3 py-1.5 font-medium text-slate-700 hover:bg-slate-50">Bitácora de operaciones</a>
            <a href="{{ route('reports.index', ['from' => $filters['from'], 'to' => $filters['to'], 'course_id' => $filters['course_id'] ?? null]) }}"
               class="rounded bg-indigo-600 px-3 py-1.5 font-medium text-white hover:bg-indigo-700">Exportar en Reportes →</a>
        </div>
    </div>

    {{-- KPIs rápidos de auditoría --}}
    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg border border-slate-200 bg-white p-3.5 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">Registros auditados</div>
            <div class="mt-1 text-2xl font-bold text-slate-900">{{ $stats['total'] }}</div>
        </div>
        <div class="rounded-lg border border-sky-200 bg-sky-50 p-3.5 shadow-sm">
            <div class="text-xs font-semibold uppercase text-sky-700">Inasistencias justificadas</div>
            <div class="mt-1 text-2xl font-bold text-sky-900">{{ $stats['justified'] }}</div>
        </div>
        <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-3.5 shadow-sm">
            <div class="text-xs font-semibold uppercase text-indigo-700">Certificados adjuntos</div>
            <div class="mt-1 text-2xl font-bold text-indigo-900">{{ $stats['certificates'] }}</div>
        </div>
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3.5 shadow-sm">
            <div class="text-xs font-semibold uppercase text-amber-800">Con observación diaria</div>
            <div class="mt-1 text-2xl font-bold text-amber-900">{{ $stats['observations'] }}</div>
        </div>
    </div>

    <form method="GET" class="mb-4 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 text-sm shadow-sm sm:grid-cols-2 lg:grid-cols-4">
        <label>Desde <input type="date" name="from" value="{{ $filters['from'] }}" class="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5"></label>
        <label>Hasta <input type="date" name="to" value="{{ $filters['to'] }}" class="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5"></label>
        <label>Curso
            <select name="course_id" class="mt-1 block w-full rounded border border-slate-300 bg-white px-2 py-1.5">
                <option value="">Todos</option>
                @foreach ($courses as $c)
                    <option value="{{ $c->id }}" @selected(($filters['course_id'] ?? null) == $c->id)>{{ $c->full_name }}</option>
                @endforeach
            </select>
        </label>
        <label>Ámbito
            <select name="activity_type" class="mt-1 block w-full rounded border border-slate-300 bg-white px-2 py-1.5">
                <option value="">Todos</option>
                @foreach ($activities as $a)
                    <option value="{{ $a->value }}" @selected(($filters['activity_type'] ?? null) === $a->value)>{{ $a->label() }}</option>
                @endforeach
            </select>
        </label>
        <label>Estado
            <select name="status" class="mt-1 block w-full rounded border border-slate-300 bg-white px-2 py-1.5">
                <option value="">Todos</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s->value }}" @selected(($filters['status'] ?? null) === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>
        </label>
        <label>Cargado por
            <select name="user_id" class="mt-1 block w-full rounded border border-slate-300 bg-white px-2 py-1.5">
                <option value="">Todos</option>
                @foreach ($operators as $u)
                    <option value="{{ $u->id }}" @selected(($filters['user_id'] ?? null) == $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="lg:col-span-2">Alumno (apellido, nombre o DNI)
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Ej.: Mamani o 46123456…" class="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5">
        </label>
        <div class="col-span-full flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
            <div class="flex flex-wrap items-center gap-5 text-xs font-medium text-slate-700">
                <label class="flex items-center gap-2"><input type="checkbox" name="with_observation" value="1" @checked($filters['with_observation'])> Solo con observación</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="only_certificates" value="1" @checked($filters['only_certificates'])> Solo con certificado médico adjunto</label>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('director.audit.index') }}" class="text-xs text-slate-500 hover:underline">Limpiar</a>
                <button class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">Filtrar</button>
            </div>
        </div>
        @if ($errors->any() && ! $errors->has('delete'))
            <p class="col-span-full text-sm text-red-600">{{ $errors->first() }}</p>
        @endif
    </form>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr>
                <th class="px-3 py-2">Fecha</th>
                <th class="px-3 py-2">Alumno</th>
                <th class="px-3 py-2">Curso</th>
                <th class="px-3 py-2">Ámbito</th>
                <th class="px-3 py-2">Estado</th>
                <th class="px-3 py-2">Observación</th>
                <th class="px-3 py-2">Cargado por</th>
                <th class="px-3 py-2">Justificación / Certificado</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($attendances as $a)
                <tr class="align-top">
                    <td class="whitespace-nowrap px-3 py-2 font-mono text-xs">{{ $a->date->format('d/m/Y') }}</td>
                    <td class="px-3 py-2">
                        <a href="{{ route('reports.student-history', $a->student_id) }}" class="font-medium text-indigo-700 hover:underline">{{ $a->student->full_name }}</a>
                        <div class="text-xs text-slate-500">DNI {{ $a->student->dni ?: '—' }}</div>
                    </td>
                    <td class="whitespace-nowrap px-3 py-2">
                        {{ $a->course->name }}
                        <span class="text-xs text-slate-500">{{ $a->course->shiftRef?->name ?? $a->course->shift->label() }}</span>
                    </td>
                    <td class="px-3 py-2">{{ $a->scopeRef?->name ?? $a->activity_type->label() }}</td>
                    <td class="px-3 py-2"><x-status-badge :status="$a->status" /></td>
                    <td class="max-w-xs px-3 py-2 italic text-slate-600">{{ $a->observation ?: '—' }}</td>
                    <td class="px-3 py-2">{{ $a->user->name }}<div class="text-xs text-slate-400">{{ $a->updated_at->format('d/m/Y H:i') }}</div></td>
                    <td class="px-3 py-2">
                        @if ($a->justification_payload)
                            <button type="button" @click='show(@js($a->justification_payload))'
                                    class="rounded border border-sky-300 bg-sky-50 px-2.5 py-1 text-xs font-medium text-sky-800 hover:bg-sky-100">
                                Ver {{ $a->justification->reason_text ? '📝' : '' }}{{ $a->justification->certificate_path ? '📎 Certificado' : '' }}
                            </button>
                        @elseif ($a->status->countsAsUnjustifiedAbsence())
                            <span class="text-xs text-red-600">Sin justificar</span>
                        @else
                            <span class="text-xs text-slate-400">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-3 py-8 text-center text-slate-500">No hay registros con esos filtros.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $attendances->links() }}</div>

    {{-- Modal de auditoría de justificación y certificado médico --}}
    <div x-cloak x-show="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true">
        <div @click.outside="close()" class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white p-6 shadow-xl">
            <template x-if="j">
                <div>
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <h2 class="text-lg font-bold">Respaldo de Inasistencia Justificada</h2>
                            <p class="text-sm text-slate-500"><span x-text="j.student"></span> · <span x-text="j.course"></span> · <span x-text="j.date"></span></p>
                        </div>
                        <button type="button" @click="close()" class="text-2xl leading-none text-slate-400 hover:text-slate-700" aria-label="Cerrar">&times;</button>
                    </div>

                    <p class="mb-4 text-xs text-slate-500">Registrada por <strong x-text="j.by"></strong> el <span x-text="j.at"></span></p>

                    <h3 class="mb-1 text-sm font-semibold">Texto justificativo</h3>
                    <template x-if="j.reason"><p class="mb-4 whitespace-pre-line rounded bg-slate-50 p-3 text-sm" x-text="j.reason"></p></template>
                    <template x-if="!j.reason"><p class="mb-4 text-sm text-slate-400">No se ingresó texto justificativo.</p></template>

                    <h3 class="mb-1 text-sm font-semibold">Certificado médico / constancia adjunta</h3>
                    <template x-if="j.certificate_url && !j.certificate_is_pdf">
                        <div>
                            <a :href="j.certificate_url" target="_blank" rel="noopener"><img :src="j.certificate_url" alt="Certificado médico" class="max-h-96 rounded border"></a>
                            <div class="mt-2"><a :href="j.certificate_url" target="_blank" rel="noopener" class="text-xs font-medium text-indigo-600 hover:underline">Abrir imagen original en nueva pestaña ↗</a></div>
                        </div>
                    </template>
                    <template x-if="j.certificate_url && j.certificate_is_pdf">
                        <div>
                            <iframe :src="j.certificate_url" class="h-96 w-full rounded border" title="Certificado PDF"></iframe>
                            <a :href="j.certificate_url" target="_blank" rel="noopener" class="mt-2 inline-block text-sm font-medium text-indigo-600 hover:underline">Abrir PDF en otra pestaña ↗</a>
                        </div>
                    </template>
                    <template x-if="j.certificate_missing"><p class="text-sm text-red-600">El archivo del certificado no se encuentra en el servidor.</p></template>
                    <template x-if="!j.certificate_url && !j.certificate_missing"><p class="text-sm text-slate-400">No se adjuntó archivo de certificado.</p></template>
                </div>
            </template>
        </div>
    </div>
</div>
@endsection
