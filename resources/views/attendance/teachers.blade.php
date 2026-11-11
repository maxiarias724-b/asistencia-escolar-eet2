@extends('layouts.app')
@section('title', 'Asistencia Diaria de Docentes')

@php
    $initialTeacherRows = $teachers->map(function ($t) use ($existingByTeacher) {
        $att = $existingByTeacher->get($t->id);
        return [
            'teacher_id' => $t->id,
            'full_name' => $t->full_name,
            'dni' => $t->dni,
            'file_number' => $t->file_number,
            'specialty_area' => $t->specialty_area,
            'status' => $att?->status?->value ?? 'presente',
            'observation' => $att?->observation ?? '',
            'recorded' => $att !== null,
            'recorded_by_name' => $att?->recorder?->name,
            'recorded_by_role' => $att?->recorder?->role?->label(),
            'recorded_at' => $att?->updated_at?->format('d/m H:i'),
        ];
    })->values()->all();

    $shiftRecorders = $existingByTeacher
        ->map(fn ($att) => $att->recorder ? ($att->recorder->name . ' (' . $att->recorder->role->label() . ')') : null)
        ->filter()
        ->unique()
        ->values()
        ->all();
@endphp

@section('content')
<div x-data="{
        rows: @js($initialTeacherRows),
        canEditDay: @js($canEditDay),
        countBy(st) { return this.rows.filter(r => r.status === st).length; },
        markAllPresent() { if (this.canEditDay) this.rows.forEach(r => r.status = 'presente'); }
    }" x-cloak>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Registro Diario de Asistencia — Docentes</h1>
            <p class="text-sm text-slate-500">Control de presentismo, tardanzas y licencias/artículos del plantel docente por Fecha y Turno.</p>
        </div>
        @if (auth()->user()->isDirector())
            <a href="{{ route('people.teachers.index') }}" class="rounded border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                Gestionar Padrón Docente
            </a>
        @endif
    </div>

    {{-- Filtro Fecha -> Turno -> Actividad --}}
    <form method="GET" action="{{ route('attendance.teachers.index') }}" class="mb-5 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <label class="block text-xs font-semibold text-slate-700">1. Fecha
                <input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ $today->toDateString() }}"
                       class="mt-1 block w-full rounded border border-slate-300 px-2.5 py-2 text-sm">
            </label>
            <label class="block text-xs font-semibold text-slate-700">2. Turno *
                <select name="shift_id" class="mt-1 block w-full rounded border border-slate-300 bg-white px-2.5 py-2 text-sm">
                    @foreach ($shifts as $sh)
                        <option value="{{ $sh->id }}" @selected($selectedShift?->id === $sh->id)>{{ $sh->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-xs font-semibold text-slate-700">3. Actividad / Materia (opcional)
                <select name="activity_id" class="mt-1 block w-full rounded border border-slate-300 bg-white px-2.5 py-2 text-sm">
                    <option value="">General del turno</option>
                    @foreach ($activities as $act)
                        <option value="{{ $act->id }}" @selected($selectedActivityId === $act->id)>
                            {{ $act->scopeRef?->name }} — {{ $act->name }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label class="block text-xs font-semibold text-slate-700">Buscar docente
                <input type="search" name="q" value="{{ $q }}" placeholder="Apellido, DNI o área…"
                       class="mt-1 block w-full rounded border border-slate-300 px-2.5 py-2 text-sm">
            </label>
            <div>
                <button class="w-full rounded bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Cargar plantel</button>
            </div>
        </div>
    </form>

    @if ($errors->any())
        <div class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">
            <ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    @if ($isFuture)
        <div class="mb-4 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
            <strong>Fecha futura bloqueada:</strong> no se puede registrar asistencia docente por adelantado.
        </div>
    @elseif ($nonWorkingReason)
        <div class="mb-4 rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <strong>Día no laborable ({{ $date->translatedFormat('l d/m/Y') }}):</strong> {{ $nonWorkingReason }}.
        </div>
    @endif

    @if ($teachers->isEmpty() || ! $selectedShift)
        <div class="rounded-lg border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
            No se encontraron docentes activos para el filtro seleccionado.
        </div>
    @else
        <form method="POST" action="{{ route('attendance.teachers.store') }}" class="rounded-lg border border-slate-200 bg-white shadow-sm">
            @csrf
            <input type="hidden" name="date" value="{{ $date->toDateString() }}">
            <input type="hidden" name="shift_id" value="{{ $selectedShift->id }}">
            <input type="hidden" name="activity_id" value="{{ $selectedActivityId }}">

            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-4 py-3 text-xs">
                <div class="flex flex-wrap items-center gap-2 text-sm font-semibold text-slate-800">
                    <span>Turno {{ $selectedShift->name }} · {{ $date->translatedFormat('l d/m/Y') }} ({{ $teachers->count() }} docentes)</span>
                    @if ($existingByTeacher->isNotEmpty())
                        <span class="inline-flex items-center gap-1 rounded border border-emerald-300 bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                            ✓ Asistencia tomada ({{ $existingByTeacher->count() }}/{{ $teachers->count() }})
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 rounded border border-amber-300 bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
                            ⏳ Registro pendiente
                        </span>
                    @endif
                    @if (! empty($shiftRecorders))
                        <span class="rounded border border-slate-200 bg-white px-2.5 py-0.5 text-xs font-normal text-slate-600">
                            Tomada por: <strong class="font-semibold text-slate-900">{{ implode(', ', $shiftRecorders) }}</strong>
                        </span>
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <span>
                        P: <strong x-text="countBy('presente')"></strong> ·
                        A: <strong class="text-red-700" x-text="countBy('ausente')"></strong> ·
                        T: <strong class="text-amber-700" x-text="countBy('tardanza')"></strong> ·
                        IJ: <strong class="text-sky-700" x-text="countBy('justificada')"></strong>
                    </span>
                    @if ($canEditDay)
                        <button type="button" @click="markAllPresent()"
                                class="rounded border border-emerald-300 bg-emerald-50 px-3 py-1 font-semibold text-emerald-800 hover:bg-emerald-100">
                            ✔ Todos Presente (P)
                        </button>
                    @endif
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-100 text-left text-xs uppercase text-slate-600">
                        <tr>
                            <th class="px-3 py-2.5">#</th>
                            <th class="px-3 py-2.5">Docente</th>
                            <th class="px-3 py-2.5">DNI / Legajo</th>
                            <th class="px-3 py-2.5">Área / Especialidad</th>
                            <th class="px-3 py-2.5">Estado</th>
                            <th class="px-3 py-2.5">Observación / Artículo o Licencia (obligatoria en IJ)</th>
                            <th class="px-3 py-2.5">Tomada por</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="(row, idx) in rows" :key="row.teacher_id">
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-3 py-2 font-mono text-xs text-slate-400" x-text="idx + 1"></td>
                                <td class="px-3 py-2 font-semibold text-slate-900" x-text="row.full_name"></td>
                                <td class="px-3 py-2 font-mono text-xs text-slate-600">
                                    <div x-text="row.dni"></div>
                                    <div x-show="row.file_number" class="text-[11px] text-slate-400" x-text="row.file_number"></div>
                                </td>
                                <td class="px-3 py-2 text-xs text-slate-600" x-text="row.specialty_area || '—'"></td>
                                <td class="px-3 py-2">
                                    <input type="hidden" :name="`records[${idx}][teacher_id]`" :value="row.teacher_id">
                                    <input type="hidden" :name="`records[${idx}][status]`" :value="row.status">
                                    <div class="flex flex-wrap gap-1.5">
                                        <button type="button" :disabled="!canEditDay" @click="row.status = 'presente'"
                                                :class="row.status === 'presente' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-emerald-50'"
                                                class="rounded px-2.5 py-1 text-xs font-bold transition disabled:opacity-50">P</button>
                                        <button type="button" :disabled="!canEditDay" @click="row.status = 'ausente'"
                                                :class="row.status === 'ausente' ? 'bg-red-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-red-50'"
                                                class="rounded px-2.5 py-1 text-xs font-bold transition disabled:opacity-50">A</button>
                                        <button type="button" :disabled="!canEditDay" @click="row.status = 'tardanza'"
                                                :class="row.status === 'tardanza' ? 'bg-amber-500 text-white' : 'bg-slate-100 text-slate-700 hover:bg-amber-50'"
                                                class="rounded px-2.5 py-1 text-xs font-bold transition disabled:opacity-50">T</button>
                                        <button type="button" :disabled="!canEditDay" @click="row.status = 'justificada'"
                                                :class="row.status === 'justificada' ? 'bg-sky-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-sky-50'"
                                                class="rounded px-2.5 py-1 text-xs font-bold transition disabled:opacity-50">IJ</button>
                                    </div>
                                </td>
                                <td class="px-3 py-2">
                                    <input type="text" :name="`records[${idx}][observation]`" x-model="row.observation" :disabled="!canEditDay"
                                           :required="row.status === 'justificada'"
                                           :placeholder="row.status === 'justificada' ? 'Obligatorio: ej. Artículo 12 / Certificado médico (mín. 10 caract.)' : 'Observación opcional…'"
                                           :class="row.status === 'justificada' && row.observation.trim().length < 10 ? 'border-red-400 bg-red-50' : 'border-slate-300'"
                                           class="block w-full min-w-[16rem] rounded border px-2.5 py-1 text-xs disabled:bg-slate-100">
                                </td>
                                <td class="px-3 py-2 text-xs">
                                    <template x-if="row.recorded_by_name">
                                        <div>
                                            <div class="font-semibold text-slate-800" x-text="row.recorded_by_name"></div>
                                            <div class="mt-0.5 flex flex-wrap items-center gap-1">
                                                <span class="inline-block rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600" x-text="row.recorded_by_role"></span>
                                                <span x-show="row.recorded_at" class="text-[10px] text-slate-400" x-text="row.recorded_at"></span>
                                            </div>
                                        </div>
                                    </template>
                                    <template x-if="!row.recorded_by_name">
                                        <span class="text-[11px] text-slate-400">Sin registro previo</span>
                                    </template>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            @if ($canEditDay)
                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 bg-slate-50 px-4 py-3">
                    <span class="text-xs text-slate-500">
                        Responsable de carga actual: <strong class="text-slate-800">{{ auth()->user()->name }} ({{ auth()->user()->role->label() }})</strong>
                    </span>
                    <button class="rounded {{ $existingByTeacher->isNotEmpty() ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-indigo-600 hover:bg-indigo-700' }} px-5 py-2 text-sm font-semibold text-white shadow-sm">
                        {{ $existingByTeacher->isNotEmpty() ? '✓ Actualizar asistencia docente' : 'Guardar asistencia docente' }}
                    </button>
                </div>
            @endif
        </form>
    @endif
</div>
@endsection
