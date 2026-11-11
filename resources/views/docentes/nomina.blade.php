@extends('layouts.app')
@section('title', 'Nómina y Observaciones Docentes')

@section('content')
    @php
        $u = auth()->user();
        $studentsJson = $students->map(function (\App\Models\Student $st) use ($u) {
            return [
                'id' => $st->id,
                'full_name' => $st->full_name,
                'dni' => $st->dni ?: 'S/D',
                'file_number' => $st->file_number ?: '—',
                'observations' => $st->teacherClassObservations->map(fn (\App\Models\TeacherClassObservation $obs) => [
                    'id' => $obs->id,
                    'student_id' => $obs->student_id,
                    'teacher_id' => $obs->teacher_id,
                    'teacher_name' => $obs->teacher?->name ?? 'Docente',
                    'activity_id' => $obs->activity_id,
                    'activity_name' => $obs->activity?->name ?? 'General / Sin materia específica',
                    'date' => $obs->date?->toDateString(),
                    'date_formatted' => $obs->date?->format('d/m/Y'),
                    'observation_text' => $obs->observation_text,
                    'created_at_human' => $obs->created_at?->format('H:i') ?? '',
                    'can_delete' => $u->isDirector() || ((int) $obs->teacher_id === (int) $u->id),
                ])->values()->all(),
            ];
        })->keyBy('id');
    @endphp

    <div x-data="teacherObservationsRoster({
            students: @js($studentsJson),
            defaultDate: @js($dateYmd),
            storeUrl: @js(route('teacher-observations.store')),
            destroyUrlTemplate: @js(route('teacher-observations.destroy', ['teacherObservation' => '__ID__'])),
            csrfToken: @js(csrf_token()),
         })">

        {{-- Encabezado institucional --}}
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Nómina de Cursos y Observaciones Docentes</h1>
                <p class="text-sm text-slate-500">
                    Consulta de asistencia diaria (solo lectura) y registro de novedades áulicas por materia para alertar a Preceptoría.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('docente.home') }}"
                   class="rounded border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                    ← Panel Docente
                </a>
            </div>
        </div>

        {{-- Aviso de regla de negocio --}}
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50/80 px-4 py-3 text-xs text-amber-900">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <strong>Regla institucional de asistencia:</strong>
                    El estado oficial (<strong>P, C, Q, M, J</strong>) es administrado exclusivamente por Preceptoría.
                    Si un estudiante figura <em>Presente</em> pero se ausenta en tu materia o contraturno, hacé clic en
                    <strong>«Observaciones»</strong> para dejar constancia y activar la alerta visual en la grilla del preceptor.
                </div>
            </div>
        </div>

        {{-- Selector rápido de cursos asignados --}}
        @if ($courses->isNotEmpty())
            <div class="mb-4 flex flex-wrap items-center gap-1.5">
                <span class="mr-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Mis cursos:</span>
                @foreach ($courses as $courseItem)
                    @php $isActiveCourse = $selectedCourse && $selectedCourse->id === $courseItem->id; @endphp
                    <a href="{{ route('teacher-observations.index', ['course_id' => $courseItem->id, 'date' => $dateYmd]) }}"
                       class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-semibold transition {{ $isActiveCourse ? 'border-[#dc2626] bg-[#dc2626] text-white shadow-sm' : 'border-slate-300 bg-white text-slate-700 hover:border-slate-400 hover:bg-slate-50' }}">
                        <span>{{ $courseItem->name }}</span>
                        <span class="text-[10px] opacity-80">({{ $courseItem->shiftRef?->name ?? $courseItem->shift?->label() }})</span>
                    </a>
                @endforeach
            </div>
        @endif

        {{-- Barra de filtros --}}
        <form method="GET" action="{{ route('teacher-observations.index') }}"
              class="mb-5 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <label class="block text-xs font-semibold uppercase text-slate-600">
                    Curso asignado
                    <select name="course_id" onchange="this.form.submit()"
                            class="mt-1 block w-full rounded border border-slate-300 bg-white px-2.5 py-1.5 text-sm font-normal">
                        @forelse ($courses as $courseOption)
                            <option value="{{ $courseOption->id }}" @selected($selectedCourse?->id === $courseOption->id)>
                                {{ $courseOption->name }} — {{ $courseOption->shiftRef?->name ?? $courseOption->shift?->label() }}
                                @if ($courseOption->specialty) ({{ $courseOption->specialty->name }}) @endif
                            </option>
                        @empty
                            <option value="">Sin cursos asignados</option>
                        @endforelse
                    </select>
                </label>

                <label class="block text-xs font-semibold uppercase text-slate-600">
                    Fecha de clase
                    <input type="date" name="date" value="{{ $dateYmd }}" max="{{ now()->toDateString() }}"
                           onchange="this.form.submit()"
                           class="mt-1 block w-full rounded border border-slate-300 px-2.5 py-1.5 text-sm font-normal">
                </label>

                <label class="block text-xs font-semibold uppercase text-slate-600 lg:col-span-2">
                    Buscar estudiante (Apellido, Nombre, DNI o Legajo)
                    <input type="search" name="q" value="{{ $search }}"
                           placeholder="Ej.: Pérez, 46123456…"
                           class="mt-1 block w-full rounded border border-slate-300 px-3 py-1.5 text-sm font-normal">
                </label>

                <div class="flex items-end gap-2">
                    <button type="submit"
                            class="w-full rounded bg-slate-900 px-4 py-1.5 text-sm font-semibold text-white transition hover:bg-slate-800">
                        Consultar
                    </button>
                    @if ($search !== '')
                        <a href="{{ route('teacher-observations.index', ['course_id' => $selectedCourse?->id, 'date' => $dateYmd]) }}"
                           class="rounded border border-slate-300 px-3 py-1.5 text-xs text-slate-600 hover:bg-slate-50">
                            Limpiar
                        </a>
                    @endif
                </div>
            </div>
        </form>

        {{-- Toast de confirmación AJAX --}}
        <div x-show="toastMessage" x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="mb-4 flex items-center justify-between rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-900 shadow-sm">
            <div class="flex items-center gap-2">
                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-600 text-xs font-bold text-white">✓</span>
                <span x-text="toastMessage"></span>
            </div>
            <button type="button" @click="toastMessage = ''" class="text-xs font-semibold text-emerald-800 hover:underline">Cerrar</button>
        </div>

        @if ($courses->isEmpty())
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-6 text-center text-sm text-amber-900">
                Aún no tenés cursos asignados en tu perfil docente. Solicitá a Dirección la vinculación de tus cursos en <strong>Usuarios y credenciales</strong>.
            </div>
        @elseif (! $selectedCourse)
            <div class="rounded-lg border border-slate-200 bg-white p-6 text-center text-sm text-slate-500">
                Seleccioná un curso para visualizar su nómina de estudiantes.
            </div>
        @else
            {{-- Cabecera del curso seleccionado --}}
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded bg-[#111318] px-2.5 py-1 text-xs font-extrabold uppercase tracking-wide text-[#8cf064]">
                        {{ $selectedCourse->name }}
                    </span>
                    <span class="text-sm font-semibold text-slate-800">
                        {{ $selectedCourse->shiftRef?->name ?? $selectedCourse->shift?->label() }}
                    </span>
                    @if ($selectedCourse->specialty)
                        <span class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600">
                            {{ $selectedCourse->specialty->name }}
                        </span>
                    @endif
                </div>
                <div class="text-xs text-slate-500">
                    Fecha consultada: <strong class="text-slate-800">{{ $selectedDate->format('d/m/Y') }}</strong> ·
                    Estudiantes en nómina: <strong class="text-slate-800">{{ $students->count() }}</strong>
                </div>
            </div>

            {{-- Tabla de nómina de estudiantes del curso --}}
            <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                        <tr>
                            <th class="w-12 px-3 py-2.5">#</th>
                            <th class="px-3 py-2.5">Estudiante</th>
                            <th class="px-3 py-2.5">DNI / Legajo</th>
                            <th class="px-3 py-2.5">Estado Oficial Preceptoría <span class="text-[10px] font-normal text-slate-400">(Solo lectura)</span></th>
                            <th class="px-3 py-2.5">Observaciones Docentes ({{ $selectedDate->format('d/m/Y') }})</th>
                            <th class="px-3 py-2.5 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($students as $index => $student)
                            <tr class="align-top transition hover:bg-slate-50/70">
                                <td class="px-3 py-3 font-mono text-xs text-slate-400">{{ $index + 1 }}</td>
                                <td class="px-3 py-3">
                                    <div class="font-semibold text-slate-900">{{ $student->full_name }}</div>
                                </td>
                                <td class="px-3 py-3 font-mono text-xs text-slate-600">
                                    <div>DNI: {{ $student->dni ?: 'S/D' }}</div>
                                    @if ($student->file_number)
                                        <div class="text-[11px] text-slate-400">Leg.: {{ $student->file_number }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    @if ($student->attendances->isEmpty())
                                        <span class="inline-flex items-center gap-1 rounded border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs text-slate-500"
                                              title="El preceptor aún no cargó el parte de este día">
                                            🔒 Sin parte cargado
                                        </span>
                                    @else
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach ($student->attendances as $att)
                                                @php
                                                    $badgeStyle = match ($att->status) {
                                                        \App\Enums\AttendanceStatus::Presente => 'border-emerald-300 bg-emerald-50 text-emerald-800',
                                                        \App\Enums\AttendanceStatus::Ausente => 'border-red-300 bg-red-50 text-red-800',
                                                        \App\Enums\AttendanceStatus::Tardanza => 'border-amber-300 bg-amber-50 text-amber-800',
                                                        \App\Enums\AttendanceStatus::MediaFalta => 'border-orange-300 bg-orange-50 text-orange-800',
                                                        \App\Enums\AttendanceStatus::Justificada => 'border-sky-300 bg-sky-50 text-sky-800',
                                                    };
                                                @endphp
                                                <span class="inline-flex items-center gap-1 rounded border px-2 py-0.5 text-xs font-semibold {{ $badgeStyle }}"
                                                      title="Estado oficial administrado por Preceptoría (Solo lectura)">
                                                    <span>{{ $att->status->code() }} · {{ $att->status->label() }}</span>
                                                    <span class="text-[10px] opacity-75">({{ $att->scopeRef?->name ?? $att->activity_type->label() }})</span>
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <template x-if="getObservations({{ $student->id }}).length === 0">
                                        <span class="text-xs italic text-slate-400">Sin observaciones registradas en la fecha.</span>
                                    </template>

                                    <div class="space-y-1.5" x-show="getObservations({{ $student->id }}).length > 0" x-cloak>
                                        <template x-for="obs in getObservations({{ $student->id }})" :key="obs.id">
                                            <div class="rounded-md border border-amber-200 bg-amber-50/70 px-2.5 py-1.5 text-xs text-slate-800">
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="font-semibold text-amber-900" x-text="obs.activity_name"></span>
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-[11px] text-slate-500" x-text="'Prof. ' + obs.teacher_name + (obs.created_at_human ? ' · ' + obs.created_at_human : '')"></span>
                                                        <template x-if="obs.can_delete">
                                                            <button type="button"
                                                                    @click="deleteObservation(obs)"
                                                                    class="rounded px-1 text-[11px] font-semibold text-red-600 hover:bg-red-100"
                                                                    title="Eliminar esta observación">
                                                                ✕
                                                            </button>
                                                        </template>
                                                    </div>
                                                </div>
                                                <p class="mt-0.5 whitespace-pre-line text-slate-700" x-text="obs.observation_text"></p>
                                            </div>
                                        </template>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <button type="button"
                                            @click="openModal({{ $student->id }})"
                                            class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                                        <span>📝 Observaciones</span>
                                        <span x-show="getObservations({{ $student->id }}).length > 0"
                                              x-text="getObservations({{ $student->id }}).length"
                                              class="inline-flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-white/25 px-1 text-[10px] font-bold"></span>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">
                                    No se encontraron estudiantes activos en este curso con los filtros aplicados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Modal de Carga de Observación Docente (Alpine.js + Fetch AJAX) --}}
        <div x-show="modalOpen" x-cloak
             @keydown.escape.window="closeModal()"
             class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-[1px]"
             role="dialog" aria-modal="true" aria-labelledby="modal-obs-title">
            <div @click.outside="closeModal()"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 class="w-full max-w-lg overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">

                {{-- Cabecera del modal con identidad EET N° 2 --}}
                <div class="flex items-center justify-between border-b-2 border-[#dc2626] bg-[#111318] px-5 py-3.5 text-white">
                    <div>
                        <h2 id="modal-obs-title" class="text-base font-bold text-white">
                            Registrar Observación Docente de Clase
                        </h2>
                        <p class="text-xs text-[#8cf064]" x-text="activeStudent ? activeStudent.full_name + ' · DNI ' + activeStudent.dni : ''"></p>
                    </div>
                    <button type="button" @click="closeModal()"
                            class="rounded-md p-1 text-slate-300 hover:bg-white/10 hover:text-white"
                            aria-label="Cerrar modal">
                        ✕
                    </button>
                </div>

                <form @submit.prevent="submitObservation()" class="p-5 space-y-4">
                    <div x-show="errorMessage" x-cloak
                         class="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-xs text-red-800"
                         x-text="errorMessage"></div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="block text-xs font-semibold uppercase text-slate-600">
                            Materia / Actividad
                            <select x-model="form.activity_id"
                                    class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-2.5 py-2 text-sm font-normal">
                                <option value="">General / Sin materia específica</option>
                                @foreach ($activities as $activityItem)
                                    <option value="{{ $activityItem->id }}">
                                        {{ $activityItem->name }}
                                        @if ($activityItem->scopeRef) ({{ $activityItem->scopeRef->name }}) @endif
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block text-xs font-semibold uppercase text-slate-600">
                            Fecha de la incidencia
                            <input type="date" x-model="form.date" max="{{ now()->toDateString() }}" required
                                   class="mt-1 block w-full rounded-md border border-slate-300 px-2.5 py-2 text-sm font-normal">
                        </label>
                    </div>

                    {{-- Plantillas rápidas para agilizar la carga áulica --}}
                    <div>
                        <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                            Motivos frecuentes (clic para completar):
                        </span>
                        <div class="mt-1.5 flex flex-wrap gap-1.5">
                            <button type="button"
                                    @click="useSuggestion('Ausente en la hora de la materia (figuraba Presente en el parte general).')"
                                    class="rounded-full border border-slate-300 bg-slate-50 px-2.5 py-1 text-[11px] text-slate-700 hover:border-slate-400 hover:bg-slate-100">
                                Ausente en la materia
                            </button>
                            <button type="button"
                                    @click="useSuggestion('Se retiró del aula sin autorización y no regresó a la clase.')"
                                    class="rounded-full border border-slate-300 bg-slate-50 px-2.5 py-1 text-[11px] text-slate-700 hover:border-slate-400 hover:bg-slate-100">
                                Se retiró sin regresar
                            </button>
                            <button type="button"
                                    @click="useSuggestion('Ingresó con tardanza significativa al bloque de la materia.')"
                                    class="rounded-full border border-slate-300 bg-slate-50 px-2.5 py-1 text-[11px] text-slate-700 hover:border-slate-400 hover:bg-slate-100">
                                Tardanza en bloque horario
                            </button>
                            <button type="button"
                                    @click="useSuggestion('Ausente en el contraturno de la tarde.')"
                                    class="rounded-full border border-slate-300 bg-slate-50 px-2.5 py-1 text-[11px] text-slate-700 hover:border-slate-400 hover:bg-slate-100">
                                Ausente a contraturno
                            </button>
                        </div>
                    </div>

                    <label class="block text-xs font-semibold uppercase text-slate-600">
                        Detalle de la observación docente *
                        <textarea x-model="form.observation_text" rows="4" required minlength="3" maxlength="1000"
                                  placeholder="Ej.: Ausente en la hora de Matemática (5ª y 6ª hora). Sus compañeros informan que se retiró antes..."
                                  class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-normal focus:border-[#dc2626] focus:outline-none"></textarea>
                        <span class="mt-1 block text-[11px] font-normal text-slate-400">
                            Esta observación generará una alerta visual inmediata en la grilla del preceptor y se anexará en la planilla mensual exportable.
                        </span>
                    </label>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-200 pt-3">
                        <button type="button" @click="closeModal()"
                                class="rounded-md border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            Cancelar
                        </button>
                        <button type="submit" :disabled="submitting"
                                class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60">
                            <span x-text="submitting ? 'Guardando…' : 'Guardar observación'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function teacherObservationsRoster(config) {
                return {
                    students: config.students || {},
                    modalOpen: false,
                    submitting: false,
                    activeStudentId: null,
                    toastMessage: '',
                    errorMessage: '',
                    form: {
                        student_id: null,
                        activity_id: '',
                        date: config.defaultDate,
                        observation_text: '',
                    },
                    get activeStudent() {
                        return this.activeStudentId ? this.students[this.activeStudentId] : null;
                    },
                    getObservations(studentId) {
                        return (this.students[studentId] && this.students[studentId].observations)
                            ? this.students[studentId].observations
                            : [];
                    },
                    openModal(studentId) {
                        this.activeStudentId = studentId;
                        this.errorMessage = '';
                        this.form.student_id = studentId;
                        this.form.date = config.defaultDate;
                        this.form.observation_text = '';
                        this.modalOpen = true;
                    },
                    closeModal() {
                        this.modalOpen = false;
                        this.errorMessage = '';
                    },
                    useSuggestion(text) {
                        if (!this.form.observation_text) {
                            this.form.observation_text = text;
                        } else {
                            this.form.observation_text = this.form.observation_text.trim() + ' ' + text;
                        }
                    },
                    async submitObservation() {
                        if (this.submitting) return;
                        this.submitting = true;
                        this.errorMessage = '';

                        try {
                            const response = await fetch(config.storeUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': config.csrfToken,
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: JSON.stringify({
                                    student_id: this.form.student_id,
                                    activity_id: this.form.activity_id || null,
                                    date: this.form.date,
                                    observation_text: this.form.observation_text,
                                }),
                            });

                            const data = await response.json();

                            if (!response.ok || !data.ok) {
                                if (data && data.errors) {
                                    const firstErr = Object.values(data.errors)[0];
                                    this.errorMessage = Array.isArray(firstErr) ? firstErr[0] : String(firstErr);
                                } else {
                                    this.errorMessage = (data && data.message) ? data.message : 'No se pudo guardar la observación.';
                                }
                                return;
                            }

                            const obs = data.observation;
                            obs.can_delete = true;

                            if (this.students[obs.student_id] && obs.date === config.defaultDate) {
                                this.students[obs.student_id].observations.unshift(obs);
                            }

                            this.toastMessage = data.message || 'Observación docente registrada correctamente.';
                            this.closeModal();
                        } catch (e) {
                            this.errorMessage = 'Error de red al guardar la observación. Verificá tu conexión.';
                        } finally {
                            this.submitting = false;
                        }
                    },
                    async deleteObservation(obs) {
                        if (!confirm('¿Eliminar esta observación docente?')) return;

                        const url = config.destroyUrlTemplate.replace('__ID__', obs.id);
                        try {
                            const response = await fetch(url, {
                                method: 'DELETE',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': config.csrfToken,
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                            });
                            const data = await response.json();
                            if (response.ok && data.ok && this.students[obs.student_id]) {
                                this.students[obs.student_id].observations = this.students[obs.student_id].observations.filter(
                                    (item) => item.id !== obs.id
                                );
                                this.toastMessage = 'Observación eliminada.';
                            }
                        } catch (e) {
                            alert('No se pudo eliminar la observación.');
                        }
                    }
                };
            }
        </script>
    @endpush
@endsection
