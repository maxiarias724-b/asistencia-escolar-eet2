@extends('layouts.app')
@section('title', 'Toma de Asistencia Diaria — Estudiantes')

@php
    $initialRows = $students->map(function ($st) use ($existingByStudent, $scope, $course) {
        $att = $existingByStudent->get($st->id);
        $just = $att?->justification;
        return [
            'student_id' => $st->id,
            'full_name' => $st->full_name,
            'dni' => $st->dni ?: 'Sin DNI',
            'file_number' => $st->file_number,
            'status' => $att?->status?->value ?? 'presente',
            'recorded' => $att !== null,
            'recorded_by_name' => $att?->recorder?->name,
            'recorded_by_role' => $att?->recorder?->role?->label(),
            'recorded_at' => $att?->updated_at?->format('d/m H:i'),
            'observation' => $att?->observation ?? '',
            'justified_reason' => $just?->reason_text ?? '',
            'has_certificate' => (bool) $just?->hasCertificate(),
            'certificate_url' => ($att && $just?->hasCertificate() && $scope && $course)
                ? route('attendance.certificate', ['activity' => $scope->slug, 'course' => $course->id, 'attendance' => $att->id])
                : null,
        ];
    })->values()->all();

    $courseRecorders = $existingByStudent
        ->map(fn ($att) => $att->recorder ? ($att->recorder->name . ' (' . $att->recorder->role->label() . ')') : null)
        ->filter()
        ->unique()
        ->values()
        ->all();

    $justifyEndpoint = ($scope && $course)
        ? route('attendance.justify', ['activity' => $scope->slug, 'course' => $course->id])
        : null;
@endphp

@section('content')
<div x-data="dailyStudentAttendance({
        catalog: @js($catalog),
        selected: @js($selected),
        date: @js($date->toDateString()),
        rows: @js($initialRows),
        canEditDay: @js($canEditDay),
        csrf: @js(csrf_token()),
        justifyUrl: @js($justifyEndpoint),
    })" x-cloak>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Toma de Asistencia Diaria — Estudiantes</h1>
            <p class="text-sm text-slate-500">
                Sincronizado con reloj del servidor ({{ $today->translatedFormat('l d/m/Y') }}). Seleccioná Fecha → Turno → Ámbito → Curso/División → Actividad.
            </p>
        </div>
        <div class="flex flex-wrap gap-2 text-xs">
            @if ($scope && $course)
                <a href="{{ route('attendance.show', ['activity' => $scope->slug, 'course' => $course->id, 'month' => $date->format('Y-m')]) }}"
                   class="rounded border border-indigo-300 bg-indigo-50 px-3 py-1.5 font-semibold text-indigo-800 hover:bg-indigo-100">
                    📅 Ver Grilla Mensual SINIDE ({{ $course->name }})
                </a>
                <a href="{{ route('reports.monthly-sheet', ['month' => $date->format('Y-m'), 'scope' => $scope->slug, 'course_id' => $course->id]) }}"
                   class="rounded border border-slate-300 bg-white px-3 py-1.5 font-medium text-slate-700 hover:bg-slate-50">
                    📄 Exportar Planilla Mensual
                </a>
            @endif
        </div>
    </div>

    {{-- Filtro encadenado dependiente: Fecha -> Turno -> Ámbito -> Curso/División -> Actividad --}}
    <form method="GET" action="{{ route('attendance.daily') }}" class="mb-5 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <label class="block text-xs font-semibold text-slate-700">1. Fecha
                <input type="date" name="date" x-model="filterDate" max="{{ $today->toDateString() }}"
                       class="mt-1 block w-full rounded border border-slate-300 px-2.5 py-2 text-sm">
            </label>

            <label class="block text-xs font-semibold text-slate-700">2. Turno
                <select name="shift_id" x-model="shiftId" @change="onShiftChange()"
                        class="mt-1 block w-full rounded border border-slate-300 bg-white px-2.5 py-2 text-sm">
                    <template x-for="sh in availableShifts" :key="sh.id">
                        <option :value="sh.id" x-text="sh.name" :selected="sh.id == shiftId"></option>
                    </template>
                </select>
            </label>

            <label class="block text-xs font-semibold text-slate-700">3. Ámbito
                <select name="scope_id" x-model="scopeId" @change="onScopeChange()"
                        class="mt-1 block w-full rounded border border-slate-300 bg-white px-2.5 py-2 text-sm">
                    <template x-for="sc in availableScopes" :key="sc.id">
                        <option :value="sc.id" x-text="sc.name" :selected="sc.id == scopeId"></option>
                    </template>
                </select>
            </label>

            <label class="block text-xs font-semibold text-slate-700">4. Curso y División
                <select name="course_id" x-model="courseId"
                        class="mt-1 block w-full rounded border border-slate-300 bg-white px-2.5 py-2 text-sm">
                    <template x-for="co in availableCourses" :key="co.id">
                        <option :value="co.id" x-text="co.label" :selected="co.id == courseId"></option>
                    </template>
                </select>
            </label>

            <label class="block text-xs font-semibold text-slate-700">5. Actividad / Materia
                <select name="activity_id" x-model="activityId"
                        class="mt-1 block w-full rounded border border-slate-300 bg-white px-2.5 py-2 text-sm">
                    <option value="">General del ámbito</option>
                    <template x-for="ac in availableActivities" :key="ac.id">
                        <option :value="ac.id" x-text="ac.name" :selected="ac.id == activityId"></option>
                    </template>
                </select>
            </label>

            <div>
                <button class="w-full rounded bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                    Cargar nómina
                </button>
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
            <strong>Fecha futura bloqueada:</strong> no está permitido tomar asistencia por adelantado ({{ $date->format('d/m/Y') }}).
        </div>
    @elseif (! $isWorkingDay)
        <div class="mb-4 rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <strong>Día sin actividad escolar ({{ $date->translatedFormat('l d/m/Y') }}):</strong> {{ $nonWorkingReason }}. No se computa asistencia en esta fecha.
        </div>
    @endif

    @if (! $course || ! $scope)
        <div class="rounded-lg border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
            No tenés cursos o ámbitos habilitados para tomar asistencia. Solicitá la asignación correspondiente en Dirección.
        </div>
    @elseif ($students->isEmpty())
        <div class="rounded-lg border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
            El curso <strong>{{ $course->full_name }}</strong> aún no posee estudiantes activos cargados.
            <a href="{{ route('people.students.create', ['course_id' => $course->id]) }}" class="ml-1 font-semibold text-indigo-600 underline">Registrar estudiante</a>
        </div>
    @else
        {{-- Grilla interactiva de toma de asistencia diaria --}}
        <form method="POST" action="{{ route('attendance.daily.store') }}" class="rounded-lg border border-slate-200 bg-white shadow-sm">
            @csrf
            <input type="hidden" name="date" value="{{ $date->toDateString() }}">
            <input type="hidden" name="shift_id" :value="shiftId">
            <input type="hidden" name="scope_id" value="{{ $scope->id }}">
            <input type="hidden" name="course_id" value="{{ $course->id }}">
            <input type="hidden" name="activity_id" :value="activityId">

            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-4 py-3">
                <div class="flex flex-wrap items-center gap-1.5 text-sm">
                    <span class="font-bold text-slate-900">{{ $course->full_name }}</span>
                    <span class="mx-0.5 text-slate-400">·</span>
                    <span class="rounded bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-800">{{ $scope->name }}</span>
                    <span class="mx-0.5 text-slate-400">·</span>
                    <span class="font-medium text-slate-700">{{ $date->translatedFormat('l d/m/Y') }}</span>
                    @if ($existingByStudent->isNotEmpty())
                        <span class="ml-1 inline-flex items-center gap-1 rounded border border-emerald-300 bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                            ✓ Asistencia tomada ({{ $existingByStudent->count() }}/{{ $students->count() }})
                        </span>
                    @else
                        <span class="ml-1 inline-flex items-center gap-1 rounded border border-amber-300 bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
                            ⏳ Registro pendiente
                        </span>
                    @endif
                    @if (! empty($courseRecorders))
                        <span class="ml-1 rounded border border-slate-200 bg-white px-2.5 py-0.5 text-xs font-normal text-slate-600">
                            Tomada por: <strong class="font-semibold text-slate-900">{{ implode(', ', $courseRecorders) }}</strong>
                        </span>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-3 text-xs">
                    <span class="text-slate-600">
                        P: <strong x-text="countBy('presente')"></strong> ·
                        A: <strong class="text-red-700" x-text="countBy('ausente')"></strong> ·
                        T: <strong class="text-amber-700" x-text="countBy('tardanza')"></strong> ·
                        M: <strong class="text-orange-700" x-text="countBy('media_falta')"></strong> ·
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
                            <th class="px-3 py-2.5">Estudiante (SINIDE)</th>
                            <th class="px-3 py-2.5">DNI / Legajo</th>
                            <th class="px-3 py-2.5">Estado de Asistencia</th>
                            <th class="px-3 py-2.5">Observación diaria</th>
                            <th class="px-3 py-2.5">Tomada por</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="(row, idx) in rows" :key="row.student_id">
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-3 py-2 font-mono text-xs text-slate-400" x-text="idx + 1"></td>
                                <td class="px-3 py-2 font-semibold text-slate-900">
                                    <span x-text="row.full_name"></span>
                                    <template x-if="row.status === 'justificada'">
                                        <span class="ml-1 inline-block rounded bg-sky-100 px-1.5 py-0.5 text-[10px] font-bold text-sky-800">
                                            Respaldo cargado ✓
                                        </span>
                                    </template>
                                </td>
                                <td class="px-3 py-2 font-mono text-xs text-slate-600">
                                    <div x-text="row.dni"></div>
                                    <div x-show="row.file_number" class="text-[11px] text-slate-400" x-text="'Leg. ' + row.file_number"></div>
                                </td>
                                <td class="px-3 py-2">
                                    <input type="hidden" :name="`records[${idx}][student_id]`" :value="row.student_id">
                                    <input type="hidden" :name="`records[${idx}][status]`" :value="row.status">

                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <button type="button" :disabled="!canEditDay" @click="setStatus(row, 'presente')"
                                                :class="row.status === 'presente' ? 'bg-emerald-600 text-white ring-2 ring-emerald-300' : 'bg-slate-100 text-slate-700 hover:bg-emerald-50'"
                                                class="rounded px-2.5 py-1 text-xs font-bold transition disabled:opacity-50" title="Presente">
                                            P · Presente
                                        </button>
                                        <button type="button" :disabled="!canEditDay" @click="setStatus(row, 'ausente')"
                                                :class="row.status === 'ausente' ? 'bg-red-600 text-white ring-2 ring-red-300' : 'bg-slate-100 text-slate-700 hover:bg-red-50'"
                                                class="rounded px-2.5 py-1 text-xs font-bold transition disabled:opacity-50" title="Ausente (1 falta)">
                                            A · Ausente
                                        </button>
                                        <button type="button" :disabled="!canEditDay" @click="setStatus(row, 'tardanza')"
                                                :class="row.status === 'tardanza' ? 'bg-amber-500 text-white ring-2 ring-amber-300' : 'bg-slate-100 text-slate-700 hover:bg-amber-50'"
                                                class="rounded px-2.5 py-1 text-xs font-bold transition disabled:opacity-50" title="Tardanza (¼ falta)">
                                            T · Tardanza
                                        </button>
                                        <button type="button" :disabled="!canEditDay" @click="setStatus(row, 'media_falta')"
                                                :class="row.status === 'media_falta' ? 'bg-orange-600 text-white ring-2 ring-orange-300' : 'bg-slate-100 text-slate-700 hover:bg-orange-50'"
                                                class="rounded px-2 py-1 text-xs font-bold transition disabled:opacity-50" title="Media falta SINIDE (½ falta)">
                                            M · ½
                                        </button>
                                        {{-- IJ exige abrir el modal obligatorio: nunca marca en vacío --}}
                                        <button type="button" :disabled="!canEditDay" @click="openJustifyModal(row)"
                                                :class="row.status === 'justificada' ? 'bg-sky-600 text-white ring-2 ring-sky-300' : 'border border-sky-300 bg-sky-50 text-sky-800 hover:bg-sky-100'"
                                                class="rounded px-2.5 py-1 text-xs font-bold transition disabled:opacity-50" title="Inasistencia Justificada (requiere certificado o texto)">
                                            📎 IJ · Justificar
                                        </button>
                                    </div>
                                </td>
                                <td class="px-3 py-2">
                                    <input type="text" :name="`records[${idx}][observation]`" x-model="row.observation" :disabled="!canEditDay"
                                           maxlength="1000" placeholder="Observación opcional del día…"
                                           class="block w-full min-w-[14rem] rounded border border-slate-300 px-2.5 py-1 text-xs disabled:bg-slate-100">
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
                        @if ($existingByStudent->isNotEmpty())
                            <strong class="text-emerald-700">✓ Asistencia ya registrada ({{ $existingByStudent->count() }}/{{ $students->count() }})</strong>
                            @if (! empty($courseRecorders))
                                por <strong class="text-slate-800">{{ implode(', ', $courseRecorders) }}</strong>.
                            @endif
                        @else
                            Recordá que las justificaciones (IJ) se guardan al instante desde su modal con respaldo obligatorio.
                        @endif
                        <span class="ml-1 text-slate-500">· Responsable actual: <strong class="text-slate-800">{{ auth()->user()->name }} ({{ auth()->user()->role->label() }})</strong></span>
                    </span>
                    <button class="rounded {{ $existingByStudent->isNotEmpty() ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-indigo-600 hover:bg-indigo-700' }} px-5 py-2 text-sm font-semibold text-white shadow-sm">
                        {{ $existingByStudent->isNotEmpty() ? '✓ Actualizar asistencia del día' : 'Guardar asistencia del día' }}
                    </button>
                </div>
            @endif
        </form>
    @endif

    {{-- Modal Obligatorio de Inasistencia Justificada (IJ) --}}
    <div x-show="justModal.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4" role="dialog" aria-modal="true" @keydown.escape.window="justModal.open = false">
        <div @click.outside="justModal.open = false" class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-lg bg-white p-5 shadow-xl">
            <div class="mb-3 flex items-start justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Justificar Inasistencia (IJ)</h2>
                    <p class="text-xs text-slate-500"><span x-text="justModal.row?.full_name"></span> · {{ $date->format('d/m/Y') }}</p>
                </div>
                <button type="button" @click="justModal.open = false" class="text-2xl leading-none text-slate-400 hover:text-slate-700">&times;</button>
            </div>

            <div class="mb-3 rounded border border-sky-200 bg-sky-50 p-2.5 text-xs text-sky-900">
                <strong>Respaldo obligatorio:</strong> no se permite marcar <code>IJ</code> con un clic vacío. Adjuntá el certificado médico (<code>.png</code>, <code>.jpg</code>, <code>.jpeg</code> o <code>.pdf</code>, máx. 5 MB) y/o ingresá el motivo detallado (mínimo 10 caracteres).
            </div>

            <label class="mb-3 block text-sm font-medium text-slate-700">Texto justificativo (mín. 10 caracteres si no adjuntás archivo)
                <textarea x-model="justModal.reason" rows="3" maxlength="2000"
                          placeholder="Ej.: Presentó certificado médico por cuadro gripal (48 hs de reposo)."
                          class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm font-normal"></textarea>
            </label>

            <div class="mb-3 text-sm font-medium text-slate-700">Certificado médico o constancia (.PNG, .JPG, .JPEG, .PDF)
                <template x-if="justModal.existingCertUrl && !justModal.removeCert">
                    <div class="mt-1 flex items-center justify-between rounded border border-slate-200 bg-slate-50 p-2 text-xs font-normal">
                        <a :href="justModal.existingCertUrl" target="_blank" class="font-medium text-indigo-600 underline">Ver certificado actual ↗</a>
                        <label class="flex items-center gap-1 text-red-600"><input type="checkbox" x-model="justModal.removeCert"> Quitar archivo</label>
                    </div>
                </template>
                <input type="file" accept=".png,.jpg,.jpeg,.pdf" @change="justModal.file = $event.target.files[0] ?? null"
                       class="mt-1.5 block w-full text-xs font-normal file:mr-3 file:rounded file:border-0 file:bg-slate-200 file:px-3 file:py-1.5 file:font-semibold">
            </div>

            <p x-show="justModal.error" class="mb-3 rounded border border-red-300 bg-red-50 p-2 text-xs text-red-700" x-text="justModal.error"></p>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="justModal.open = false" class="rounded border border-slate-300 px-3 py-1.5 text-sm text-slate-700">Cancelar</button>
                <button type="button" @click="submitJustification()" :disabled="justModal.saving"
                        class="rounded bg-sky-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-sky-700 disabled:opacity-50">
                    <span x-text="justModal.saving ? 'Guardando…' : 'Guardar justificación (IJ)'"></span>
                </button>
            </div>
        </div>
    </div>

    <div x-show="toast" x-transition class="fixed bottom-4 right-4 z-50 rounded bg-slate-900 px-4 py-2 text-xs font-medium text-white shadow-lg" x-text="toast"></div>
</div>
@endsection

@push('scripts')
<script>
function dailyStudentAttendance(init) {
    return {
        catalog: init.catalog,
        filterDate: init.date,
        shiftId: init.selected.shift_id ?? '',
        scopeId: init.selected.scope_id ?? '',
        courseId: init.selected.course_id ?? '',
        activityId: init.selected.activity_id ?? '',
        rows: init.rows,
        canEditDay: init.canEditDay,
        csrf: init.csrf,
        justifyUrl: init.justifyUrl,
        toast: null,
        justModal: { open: false, row: null, reason: '', file: null, removeCert: false, existingCertUrl: null, error: '', saving: false },

        get availableShifts() {
            const seen = {};
            return this.catalog.combinations
                .filter(c => !seen[c.shift_id] && (seen[c.shift_id] = true))
                .map(c => ({ id: c.shift_id, name: c.shift_name }));
        },
        get availableScopes() {
            const seen = {};
            return this.catalog.combinations
                .filter(c => (!this.shiftId || c.shift_id == this.shiftId) && !seen[c.scope_id] && (seen[c.scope_id] = true))
                .map(c => ({ id: c.scope_id, name: c.scope_name }));
        },
        get availableCourses() {
            const seen = {};
            return this.catalog.combinations
                .filter(c => (!this.shiftId || c.shift_id == this.shiftId) && (!this.scopeId || c.scope_id == this.scopeId) && !seen[c.course_id] && (seen[c.course_id] = true))
                .map(c => ({
                    id: c.course_id,
                    name: c.course_name,
                    taken: !!c.taken,
                    recorded_count: c.recorded_count ?? 0,
                    label: c.course_name + (c.taken ? ' · ✓ Asistencia tomada' : ' · ⏳ Registro pendiente'),
                }));
        },
        get availableActivities() {
            return this.catalog.activities_by_scope[this.scopeId] ?? [];
        },

        onShiftChange() {
            if (!this.availableScopes.some(s => s.id == this.scopeId)) {
                this.scopeId = this.availableScopes[0]?.id ?? '';
            }
            this.onScopeChange();
        },
        onScopeChange() {
            if (!this.availableCourses.some(c => c.id == this.courseId)) {
                this.courseId = this.availableCourses[0]?.id ?? '';
            }
            if (!this.availableActivities.some(a => a.id == this.activityId)) {
                this.activityId = this.availableActivities[0]?.id ?? '';
            }
        },

        countBy(status) {
            return this.rows.filter(r => r.status === status).length;
        },
        setStatus(row, status) {
            if (!this.canEditDay) return;
            row.status = status;
        },
        markAllPresent() {
            if (!this.canEditDay) return;
            this.rows.forEach(r => { if (r.status !== 'justificada') r.status = 'presente'; });
        },

        openJustifyModal(row) {
            if (!this.canEditDay || !this.justifyUrl) return;
            this.justModal = {
                open: true,
                row,
                reason: row.justified_reason || '',
                file: null,
                removeCert: false,
                existingCertUrl: row.has_certificate ? row.certificate_url : null,
                error: '',
                saving: false,
            };
        },

        async submitJustification() {
            const m = this.justModal;
            m.error = '';

            const cleanReason = m.reason.trim();
            const keepsCert = !!m.existingCertUrl && !m.removeCert;

            if (!cleanReason && !m.file && !keepsCert) {
                m.error = 'Debés adjuntar un certificado (.png, .jpg, .jpeg, .pdf) y/o escribir un motivo justificativo.';
                return;
            }
            if (cleanReason && cleanReason.length < 10) {
                m.error = 'El texto justificativo debe tener al menos 10 caracteres.';
                return;
            }
            if (m.file) {
                if (!/\.(png|jpe?g|pdf)$/i.test(m.file.name)) {
                    m.error = 'Formato inválido. Solo se aceptan archivos PNG, JPG, JPEG o PDF.';
                    return;
                }
                if (m.file.size > 5 * 1024 * 1024) {
                    m.error = 'El archivo supera el tamaño máximo de 5 MB.';
                    return;
                }
            }

            const fd = new FormData();
            fd.append('student_id', m.row.student_id);
            fd.append('date', this.filterDate);
            fd.append('reason_text', cleanReason);
            fd.append('create_absence_if_missing', '1');
            if (m.file) fd.append('certificate', m.file);
            if (m.removeCert) fd.append('remove_certificate', '1');

            m.saving = true;
            try {
                const res = await fetch(this.justifyUrl, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd,
                });
                const data = await res.json();
                m.saving = false;

                if (!res.ok) {
                    m.error = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'No se pudo guardar la justificación.');
                    return;
                }

                m.row.status = 'justificada';
                m.row.justified_reason = data.attendance?.reason_text ?? cleanReason;
                m.row.has_certificate = !!data.attendance?.has_certificate;
                m.row.certificate_url = data.attendance?.certificate_url ?? null;
                m.open = false;

                this.toast = 'Inasistencia justificada guardada con respaldo.';
                setTimeout(() => this.toast = null, 3500);
            } catch (err) {
                m.saving = false;
                m.error = 'Error de conexión al guardar la justificación.';
            }
        }
    };
}
</script>
@endpush
