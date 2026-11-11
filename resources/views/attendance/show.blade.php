@extends('layouts.app')
@section('title', 'Asistencia '.$course->name.' · '.$activity->label())
@section('main_class', 'mx-auto max-w-[110rem] px-3 py-4')

@section('content')
<div x-data="attendanceRegister(@js($register), @js($config))" x-cloak class="relative">

    {{-- Selector Actividad -> Curso + navegación de mes (no se imprime) --}}
    <div class="mb-3 flex flex-wrap items-end justify-between gap-3 print:hidden">
        <form method="GET" action="{{ route('attendance.index') }}" class="flex flex-wrap items-end gap-2 text-sm"
              x-data="{ activity: @js($activity->value), course: {{ $course->id }}, opts: @js($options),
                        get activities() { const seen = {}; return this.opts.filter(o => !seen[o.activity] && (seen[o.activity] = true)); },
                        get courses() { return this.opts.filter(o => o.activity === this.activity); },
                        pick() { if (!this.courses.some(c => c.course_id == this.course)) this.course = this.courses[0]?.course_id; } }">
            <label class="block">Actividad
                <select name="activity" x-model="activity" @change="pick()" class="mt-1 block rounded border border-slate-300 px-2 py-1.5">
                    <template x-for="a in activities" :key="a.activity"><option :value="a.activity" x-text="a.activity_label" :selected="a.activity === activity"></option></template>
                </select>
            </label>
            <label class="block">Curso y división
                <select name="course_id" x-model="course" class="mt-1 block rounded border border-slate-300 px-2 py-1.5">
                    <template x-for="c in courses" :key="c.course_id"><option :value="c.course_id" x-text="c.course_label" :selected="c.course_id == course"></option></template>
                </select>
            </label>
            <button class="rounded bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700">Abrir</button>
        </form>

        <div class="flex flex-wrap items-center gap-2 text-sm">
            <a href="{{ route('attendance.daily', ['course_id' => $course->id]) }}" class="rounded bg-emerald-600 px-3 py-1.5 text-white hover:bg-emerald-700">📋 Toma diaria</a>
            <a href="{{ route('attendance.show', ['activity' => $activity->value, 'course' => $course->id, 'month' => $prevMonth]) }}" class="rounded border border-slate-300 bg-white px-3 py-1.5 hover:bg-slate-50">← Mes anterior</a>
            <a href="{{ route('attendance.show', ['activity' => $activity->value, 'course' => $course->id]) }}" class="rounded border border-slate-300 bg-white px-3 py-1.5 hover:bg-slate-50">Mes actual</a>
            <a href="{{ route('attendance.show', ['activity' => $activity->value, 'course' => $course->id, 'month' => $nextMonth]) }}" class="rounded border border-slate-300 bg-white px-3 py-1.5 hover:bg-slate-50">Mes siguiente →</a>
            <a href="{{ route('roster.index', ['activity' => $activity->value, 'course' => $course->id]) }}" class="rounded bg-slate-700 px-3 py-1.5 text-white hover:bg-slate-800">👥 Nómina SINIDE</a>
            <a href="{{ route('reports.monthly-sheet', ['month' => $register['month'], 'scope' => $activity->value, 'course_id' => $course->id, 'notation' => 'sinide', 'export' => 'xlsx']) }}" class="rounded border border-emerald-600 bg-white px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">⬇ Excel</a>
            <a href="{{ route('reports.monthly-sheet', ['month' => $register['month'], 'scope' => $activity->value, 'course_id' => $course->id, 'notation' => 'sinide', 'export' => 'pdf']) }}" class="rounded border border-red-600 bg-white px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">⬇ PDF</a>
            <button type="button" onclick="window.print()" class="rounded border border-slate-300 bg-white px-3 py-1.5 hover:bg-slate-50">🖨 Imprimir</button>
        </div>
    </div>

    {{-- Encabezado estilo SINIDE --}}
    <div class="mb-2">
        <div class="text-sm font-bold uppercase">Registro de asistencia mensual correspondiente a {{ $course->name }} · {{ $course->shift->label() }} — {{ $activity->label() }}</div>
        <div class="text-sm">
            <strong>Mes de <span x-text="reg.month_label"></span></strong> &nbsp; Días hábiles: <strong x-text="reg.working_days"></strong>
            <template x-if="reg.base_days !== reg.working_days"><span class="text-slate-500"> (transcurridos: <span x-text="reg.base_days"></span>)</span></template>
            <span class="ml-3 text-xs text-slate-500">Hoy: {{ $today->translatedFormat('l d/m/Y') }}</span>
        </div>
    </div>

    <div class="mb-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs print:hidden">
        <template x-for="s in legend" :key="s.code"><span><span class="inline-block w-5 rounded text-center font-bold" :class="s.cls" x-text="s.code"></span> <span x-text="s.label"></span></span></template>
        <span class="text-slate-500">· «-» día sin actividad. Hacé clic en una celda para cargar el estado y la observación.</span>
    </div>

    <div x-show="reg.pending_days.length" class="mb-2 rounded border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800 print:hidden">
        ⚠ Hay días hábiles sin ningún registro de asistencia: <strong x-text="reg.pending_days.join(', ')"></strong>.
    </div>

    <div x-show="toast" x-transition class="fixed bottom-4 right-4 z-50 rounded bg-slate-800 px-4 py-2 text-sm text-white shadow print:hidden" x-text="toast"></div>

    {{-- Grilla --}}
    <div class="overflow-x-auto rounded border border-slate-300 bg-white print:overflow-visible print:border-0">
        <table class="min-w-full border-collapse text-xs">
            <thead>
            <tr class="bg-slate-100">
                <th rowspan="2" class="sticky left-0 z-10 min-w-[11rem] border border-slate-300 bg-slate-100 px-2 py-1 text-left">Apellido y Nombre</th>
                <template x-for="d in reg.days" :key="d.n">
                    <th class="w-7 min-w-[1.75rem] border border-slate-300 px-0 py-0.5 text-center font-semibold"
                        :class="{ 'bg-slate-200 text-slate-400': !d.working, 'bg-indigo-100': d.today }" :title="d.reason ?? ''">
                        <div x-text="d.n"></div>
                        <div class="text-[9px] font-normal text-slate-500" x-text="d.weekday"></div>
                        <button type="button" x-show="d.working && !d.future" @click="fillDay(d)" title="Marcar Presente a todos los alumnos sin registro"
                                class="text-[9px] text-indigo-600 hover:underline print:hidden">✔todos</button>
                    </th>
                </template>
                <th rowspan="2" class="border border-slate-300 px-1">ASIST.</th>
                <th colspan="2" class="border border-slate-300 px-1">INASIST. DEL MES</th>
                <th rowspan="2" class="border border-slate-300 px-1" title="Inasistencias acumuladas hasta el mes anterior">Anterior*</th>
                <th rowspan="2" class="border border-slate-300 px-1" title="Inasistencias totales del mes (J + INJ)">TOTAL</th>
                <th rowspan="2" class="min-w-[14rem] border border-slate-300 px-2">OBSERVACIONES</th>
            </tr>
            <tr class="bg-slate-100">
                <th class="border border-slate-300 px-1" title="Justificadas">J.</th>
                <th class="border border-slate-300 px-1" title="Injustificadas">INJ.</th>
            </tr>
            </thead>
            <tbody>
            <template x-for="row in reg.rows" :key="row.id">
                <tr :class="row.active ? '' : 'bg-slate-50 text-slate-400'">
                    <td class="sticky left-0 z-10 border border-slate-300 px-2 py-1 font-medium" :class="row.active ? 'bg-white' : 'bg-slate-50'">
                        <span x-text="row.name"></span>
                        <span x-show="!row.active" class="ml-1 rounded bg-slate-200 px-1 text-[10px]" x-text="row.status_label"></span>
                    </td>
                    <template x-for="d in reg.days" :key="d.n">
                        <td class="border border-slate-300 p-0 text-center" :class="{ 'bg-slate-100 text-slate-400': !d.working }">
                            <template x-if="!d.working"><span x-text="'-'"></span></template>
                            <template x-if="d.working">
                                <button type="button" @click="openEditor(row, d)" :disabled="d.future || !row.active"
                                        class="block h-6 w-full font-bold leading-6 transition"
                                        :class="cellClass(row, d)"
                                        :title="cellTitle(row, d)">
                                    <span x-text="cellOf(row, d)?.code ?? (d.future ? '' : '·')"></span><span x-show="cellOf(row, d)?.observation" class="align-super text-[8px] text-slate-500">●</span>
                                </button>
                            </template>
                        </td>
                    </template>
                    <td class="border border-slate-300 px-1 text-center" x-text="fmt(row.asist)"></td>
                    <td class="border border-slate-300 px-1 text-center" x-text="fmt(row.j)"></td>
                    <td class="border border-slate-300 px-1 text-center" x-text="fmt(row.inj)"></td>
                    <td class="border border-slate-300 px-1 text-center" x-text="fmt(row.anterior)"></td>
                    <td class="border border-slate-300 px-1 text-center font-semibold" x-text="fmt(row.total)"></td>
                    <td class="border border-slate-300 px-2 py-1 text-[11px]" x-text="row.observations"></td>
                </tr>
            </template>
            <tr x-show="reg.rows.length === 0">
                <td :colspan="reg.days.length + 7" class="px-3 py-8 text-center text-slate-500">
                    Este curso todavía no tiene alumnos cargados.
                    <a href="{{ route('roster.index', ['activity' => $activity->value, 'course' => $course->id]) }}" class="text-indigo-600 underline">Cargar la nómina SINIDE</a>
                </td>
            </tr>
            </tbody>
        </table>
    </div>

    <p class="mt-1 text-[11px] text-slate-500">* Inasistencias acumuladas hasta el mes anterior (ciclo lectivo desde marzo). ASIST. = días hábiles transcurridos − (J + INJ).</p>

    {{-- Pie estilo SINIDE --}}
    <div class="mt-4 grid max-w-4xl gap-4 text-xs sm:grid-cols-2 lg:grid-cols-4">
        <table class="border border-slate-400">
            <tr><td class="border border-slate-300 px-2 py-1">Alumnos/as Inscriptos</td><td class="border border-slate-300 px-2 py-1 text-right" x-text="reg.footer.enrolled"></td></tr>
            <tr><td class="border border-slate-300 px-2 py-1">Alumnos/as Entrada</td><td class="border border-slate-300 px-2 py-1 text-right" x-text="reg.footer.entered"></td></tr>
            <tr><td class="border border-slate-300 px-2 py-1">Alumnos/as Salida</td><td class="border border-slate-300 px-2 py-1 text-right" x-text="reg.footer.left"></td></tr>
            <tr><td class="border border-slate-300 px-2 py-1">Quedan el último día</td><td class="border border-slate-300 px-2 py-1 text-right" x-text="reg.footer.remaining"></td></tr>
        </table>
        <table class="border border-slate-400">
            <tr><td class="border border-slate-300 px-2 py-1">Total de Asistencias</td><td class="border border-slate-300 px-2 py-1 text-right" x-text="fmt(reg.footer.total_asist)"></td></tr>
            <tr><td class="border border-slate-300 px-2 py-1">Total de Inasistencias</td><td class="border border-slate-300 px-2 py-1 text-right" x-text="fmt(reg.footer.total_inasist)"></td></tr>
            <tr><td class="border border-slate-300 px-2 py-1">Asistencia Media</td><td class="border border-slate-300 px-2 py-1 text-right" x-text="fmt(reg.footer.media)"></td></tr>
            <tr><td class="border border-slate-300 px-2 py-1">% de Asistencia</td><td class="border border-slate-300 px-2 py-1 text-right" x-text="fmt(reg.footer.percent) + '%'"></td></tr>
        </table>
        <div class="hidden self-end border-t border-slate-500 pt-1 text-center lg:block print:block">FIRMA</div>
        <div class="hidden self-end border-t border-slate-500 pt-1 text-center lg:block print:block">V° B°</div>
    </div>

    {{-- ===================== Modal: editar celda ===================== --}}
    <div x-show="editor.open" x-transition.opacity class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4 print:hidden" role="dialog" aria-modal="true" @keydown.escape.window="editor.open = false">
        <div @click.outside="!just.open && (editor.open = false)" class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-lg bg-white p-5 shadow-xl">
            <template x-if="editor.row">
                <div>
                    <div class="mb-3 flex items-start justify-between">
                        <div>
                            <h2 class="text-lg font-bold" x-text="editor.row.name"></h2>
                            <p class="text-sm text-slate-500">{{ $activity->label() }} · {{ $course->name }} · <span x-text="editor.dateLabel"></span></p>
                        </div>
                        <button type="button" class="text-2xl leading-none text-slate-400 hover:text-slate-700" @click="editor.open = false" aria-label="Cerrar">&times;</button>
                    </div>

                    <div class="mb-3 rounded border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                        <template x-if="editor.cell?.recorded_by">
                            <div>
                                Tomada por: <strong class="text-slate-900" x-text="editor.cell.recorded_by"></strong>
                                <span x-show="editor.cell.recorded_by_role" class="ml-1 inline-block rounded bg-slate-200 px-1.5 py-0.5 text-[10px] font-semibold text-slate-700" x-text="editor.cell.recorded_by_role"></span>
                                <span x-show="editor.cell.recorded_at" class="ml-1 text-slate-400" x-text="'· ' + editor.cell.recorded_at"></span>
                            </div>
                        </template>
                        <template x-if="!editor.cell?.recorded_by">
                            <div>
                                Sin registro previo · Responsable actual: <strong class="text-slate-900">{{ auth()->user()->name }} ({{ auth()->user()->role->label() }})</strong>
                            </div>
                        </template>
                    </div>

                    <div class="mb-3 grid grid-cols-2 gap-2">
                        <template x-for="s in choices" :key="s.value">
                            <button type="button" @click="editor.status = s.value"
                                    class="rounded border px-3 py-2 text-left text-sm font-medium"
                                    :class="editor.status === s.value ? s.active : 'border-slate-300 bg-white hover:bg-slate-50'">
                                <span class="mr-1 inline-block w-5 rounded text-center font-bold" :class="s.cls" x-text="s.code"></span><span x-text="s.label"></span>
                                <span class="block text-[11px] font-normal text-slate-500" x-text="s.hint"></span>
                            </button>
                        </template>
                        <button type="button" x-show="editor.cell?.status === 'justificada'" @click="editor.status = 'justificada'"
                                class="col-span-2 rounded border px-3 py-2 text-left text-sm font-medium"
                                :class="editor.status === 'justificada' ? 'border-sky-500 bg-sky-50' : 'border-slate-300 bg-white hover:bg-slate-50'">
                            <span class="mr-1 inline-block w-5 rounded bg-sky-100 text-center font-bold text-sky-800">J</span>Inasistencia justificada <span class="text-[11px] font-normal text-slate-500">(actual)</span>
                        </button>
                    </div>

                    <label class="mb-3 block text-sm">Observación (opcional, se informa a SINIDE)
                        <textarea x-model="editor.observation" rows="3" maxlength="1000" placeholder="Ej.: Tuvo un accidente / Se retiró por dolor de cabeza"
                                  class="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5"></textarea>
                    </label>

                    <p x-show="willDropJustification" class="mb-3 rounded border border-amber-300 bg-amber-50 p-2 text-sm text-amber-800">
                        ⚠ Al guardar con otro estado se <strong>eliminará la justificación</strong> (texto y certificado adjunto).
                    </p>
                    <p x-show="editor.error" class="mb-3 rounded border border-red-300 bg-red-50 p-2 text-sm text-red-700" x-text="editor.error"></p>

                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" @click="saveEditor()" :disabled="editor.saving" class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                            <span x-text="editor.saving ? 'Guardando…' : 'Guardar'"></span>
                        </button>

                        {{-- Botón de acción para justificar: solo sobre una inasistencia ya registrada --}}
                        <button type="button" @click="openJustify()" x-show="canJustify" class="rounded border border-sky-400 bg-sky-50 px-3 py-2 text-sm font-medium text-sky-800 hover:bg-sky-100">
                            <span x-text="editor.cell?.status === 'justificada' ? '📎 Ver / editar justificación' : '📎 Justificar…'"></span>
                        </button>

                        <button type="button" @click="removeRecord()" x-show="editor.cell" class="ml-auto text-sm text-red-600 hover:underline">Quitar registro</button>
                    </div>
                    <p x-show="!canJustify && editor.row" class="mt-2 text-[11px] text-slate-500">Para justificar, primero guardá la inasistencia (C, M o Q) de ese día.</p>
                </div>
            </template>
        </div>
    </div>

    {{-- ===================== Modal obligatorio: justificar inasistencia ===================== --}}
    <div x-show="just.open" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 print:hidden" role="dialog" aria-modal="true" @keydown.escape.window="just.open = false">
        <div class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-lg bg-white p-5 shadow-xl">
            <div class="mb-3 flex items-start justify-between">
                <div>
                    <h2 class="text-lg font-bold">Justificar inasistencia</h2>
                    <p class="text-sm text-slate-500"><span x-text="editor.row?.name"></span> · <span x-text="editor.dateLabel"></span></p>
                </div>
                <button type="button" class="text-2xl leading-none text-slate-400 hover:text-slate-700" @click="just.open = false" aria-label="Cerrar">&times;</button>
            </div>

            <p class="mb-3 rounded bg-slate-50 p-2 text-xs text-slate-600">
                Para guardar es obligatorio <strong>al menos uno</strong>: el certificado (PNG, JPG, JPEG o PDF) <strong>o</strong> un texto justificativo detallado. No se puede justificar sin respaldo.
            </p>

            <label class="mb-3 block text-sm font-medium">Texto justificativo
                <textarea x-model="just.reason" rows="4" maxlength="2000" placeholder="Ej.: El alumno tuvo un viaje imprevisto por cuestiones familiares."
                          class="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 font-normal"></textarea>
            </label>

            <div class="mb-3 text-sm font-medium">Certificado médico (foto o escaneo)
                <template x-if="just.existingCertUrl && !just.removeCert">
                    <div class="mb-2 mt-1 flex items-center gap-3 rounded border border-slate-200 p-2 font-normal">
                        <a :href="just.existingCertUrl" target="_blank" rel="noopener" class="text-indigo-600 underline">Ver certificado actual</a>
                        <label class="ml-auto flex items-center gap-1 text-xs text-red-600"><input type="checkbox" x-model="just.removeCert"> Quitar</label>
                    </div>
                </template>
                <input type="file" accept=".png,.jpg,.jpeg,.pdf,image/png,image/jpeg,application/pdf" @change="just.file = $event.target.files[0] ?? null"
                       class="mt-1 block w-full text-sm font-normal file:mr-3 file:rounded file:border-0 file:bg-slate-200 file:px-3 file:py-1.5">
                <p class="mt-1 text-[11px] font-normal text-slate-500">Máximo 5 MB. <span x-show="just.existingCertUrl">Si subís un archivo nuevo, reemplaza al actual.</span></p>
            </div>

            <p x-show="just.error" class="mb-3 rounded border border-red-300 bg-red-50 p-2 text-sm text-red-700" x-text="just.error"></p>

            <div class="flex gap-2">
                <button type="button" @click="saveJustification()" :disabled="just.saving" class="rounded bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700 disabled:opacity-50">
                    <span x-text="just.saving ? 'Guardando…' : 'Guardar justificación'"></span>
                </button>
                <button type="button" @click="just.open = false" class="px-3 py-2 text-sm text-slate-600 hover:underline">Cancelar</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function attendanceRegister(initial, cfg) {
        const statusMeta = {
            presente:    { code: 'P', label: 'Presente',     hint: 'Asistió',                     cls: 'bg-emerald-100 text-emerald-800', active: 'border-emerald-500 bg-emerald-50' },
            ausente:     { code: 'C', label: 'Ausente',      hint: 'Falta completa (1)',          cls: 'bg-red-100 text-red-800',         active: 'border-red-500 bg-red-50' },
            tardanza:    { code: 'Q', label: 'Tardanza',     hint: 'Cuenta ¼ de falta',           cls: 'bg-amber-100 text-amber-800',     active: 'border-amber-500 bg-amber-50' },
            media_falta: { code: 'M', label: 'Media falta',  hint: 'Cuenta ½ falta',              cls: 'bg-orange-100 text-orange-800',   active: 'border-orange-500 bg-orange-50' },
            justificada: { code: 'J', label: 'Justificada',  hint: 'Con certificado o texto',     cls: 'bg-sky-100 text-sky-800',         active: 'border-sky-500 bg-sky-50' },
        };

        return {
            reg: initial,
            cfg,
            toast: null,
            toastTimer: null,
            legend: Object.values(statusMeta),
            choices: ['presente', 'ausente', 'tardanza', 'media_falta'].map(v => ({ value: v, ...statusMeta[v] })),
            editor: { open: false, row: null, day: null, cell: null, status: null, observation: '', error: '', saving: false, dateLabel: '' },
            just: { open: false, reason: '', file: null, removeCert: false, existingCertUrl: null, error: '', saving: false },

            fmt(n) { return Number(n ?? 0).toFixed(2); },
            cellOf(row, d) { return row.cells[d.n] ?? null; },

            cellClass(row, d) {
                const c = this.cellOf(row, d);
                if (d.future || !row.active) return 'cursor-default text-slate-300';
                if (!c) return 'text-slate-300 hover:bg-indigo-50 cursor-pointer';
                return statusMeta[c.status].cls + ' cursor-pointer hover:brightness-95';
            },
            cellTitle(row, d) {
                const c = this.cellOf(row, d);
                if (!row.active) return 'Alumno ' + row.status_label.toLowerCase();
                if (d.future) return 'Fecha futura';
                if (!c) return 'Sin registrar';
                const recorder = c.recorded_by ? ' · Tomada por: ' + c.recorded_by + (c.recorded_by_role ? ' (' + c.recorded_by_role + ')' : '') : '';
                return statusMeta[c.status].label + (c.observation ? ' — ' + c.observation : '') + recorder;
            },

            get canJustify() {
                const s = this.editor.cell?.status;
                return !!s && s !== 'presente';
            },
            get willDropJustification() {
                return this.editor.cell?.status === 'justificada' && this.editor.status && this.editor.status !== 'justificada';
            },

            flash(msg) {
                this.toast = msg;
                clearTimeout(this.toastTimer);
                this.toastTimer = setTimeout(() => this.toast = null, 3500);
            },

            async send(method, url, body, multipart = false) {
                const headers = { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.cfg.csrf, 'X-Requested-With': 'XMLHttpRequest' };
                if (!multipart) headers['Content-Type'] = 'application/json';

                let res;
                try {
                    res = await fetch(url, { method, headers, body: multipart ? body : JSON.stringify(body) });
                } catch (e) {
                    return { ok: false, status: 0, data: { message: 'No se pudo conectar con el servidor.' } };
                }

                let data = {};
                try { data = await res.json(); } catch (e) { /* respuesta sin JSON */ }
                if (res.status === 419) data = { message: 'La sesión expiró. Recargá la página e ingresá de nuevo.' };

                return { ok: res.ok, status: res.status, data };
            },

            errorText(data) {
                if (data.errors) return Object.values(data.errors).flat().join(' ');
                return data.message || 'Ocurrió un error inesperado.';
            },

            openEditor(row, d) {
                if (d.future || !row.active) return;
                const cell = this.cellOf(row, d);
                this.editor = {
                    open: true, row, day: d, cell,
                    status: cell?.status ?? null,
                    observation: cell?.observation ?? '',
                    error: '', saving: false,
                    dateLabel: d.weekday + ' ' + d.date.split('-').reverse().join('/'),
                };
            },

            async saveEditor() {
                const e = this.editor;
                if (!e.status) { e.error = 'Elegí un estado.'; return; }
                e.saving = true; e.error = '';

                const r = await this.send('PUT', this.cfg.urls.store, {
                    student_id: e.row.id, date: e.day.date, status: e.status,
                    observation: e.observation?.trim() || null,
                    confirm_unjustify: this.willDropJustification,
                });

                e.saving = false;
                if (!r.ok) { e.error = this.errorText(r.data); return; }

                this.reg = r.data.register;
                this.flash(r.data.message);
                e.open = false;
            },

            async removeRecord() {
                const e = this.editor;
                const extra = e.cell?.status === 'justificada' ? ' Se eliminará también la justificación.' : '';
                if (!confirm('¿Quitar el registro de ' + e.row.name + ' del ' + e.dateLabel + '?' + extra)) return;

                const r = await this.send('DELETE', this.cfg.urls.destroy, { student_id: e.row.id, date: e.day.date });
                if (!r.ok) { e.error = this.errorText(r.data); return; }

                this.reg = r.data.register;
                this.flash(r.data.message);
                e.open = false;
            },

            async fillDay(d) {
                if (!confirm('¿Marcar «Presente» a todos los alumnos sin registro del día ' + d.n + '? Los que ya tienen estado no se modifican.')) return;

                const r = await this.send('POST', this.cfg.urls.fillDay, { date: d.date });
                if (!r.ok) { alert(this.errorText(r.data)); return; }

                this.reg = r.data.register;
                this.flash(r.data.message);
            },

            openJustify() {
                const j = this.editor.cell?.justification;
                this.just = {
                    open: true,
                    reason: j?.reason ?? '',
                    file: null, removeCert: false,
                    existingCertUrl: j?.has_certificate ? j.certificate_url : null,
                    error: '', saving: false,
                };
            },

            async saveJustification() {
                const j = this.just, e = this.editor;
                j.error = '';

                const hasReason = j.reason.trim() !== '';
                const keepsFile = !!j.existingCertUrl && !j.removeCert;

                // Regla crítica (también validada en el servidor): no se justifica "en vacío".
                if (!hasReason && !j.file && !keepsFile) {
                    j.error = 'Para justificar tenés que adjuntar el certificado (PNG, JPG o PDF) o escribir un texto justificativo.';
                    return;
                }
                if (hasReason && j.reason.trim().length < 10) {
                    j.error = 'El texto justificativo debe ser una explicación de al menos 10 caracteres.';
                    return;
                }
                if (j.file) {
                    if (!/\.(png|jpe?g|pdf)$/i.test(j.file.name)) { j.error = 'El certificado debe ser PNG, JPG, JPEG o PDF.'; return; }
                    if (j.file.size > 5 * 1024 * 1024) { j.error = 'El certificado no puede superar los 5 MB.'; return; }
                }

                const fd = new FormData();
                fd.append('student_id', e.row.id);
                fd.append('date', e.day.date);
                fd.append('reason_text', j.reason.trim());
                if (j.file) fd.append('certificate', j.file);
                if (j.removeCert) fd.append('remove_certificate', '1');

                j.saving = true;
                const r = await this.send('POST', this.cfg.urls.justify, fd, true);
                j.saving = false;

                if (!r.ok) { j.error = this.errorText(r.data); return; }

                this.reg = r.data.register;
                this.flash(r.data.message);
                j.open = false;
                e.open = false;
            },
        };
    }
</script>
@endpush
