@php
    $editing = $intervention->exists;
    $studentOptions = $students->map(fn ($st) => [
        'id' => (int) $st->id,
        'course_id' => (int) $st->course_id,
        'label' => $st->full_name.' — DNI '.($st->dni ?: 'S/D').' ('.($st->course?->name ?? 'Sin curso').')',
        'search' => mb_strtolower($st->full_name.' '.$st->dni.' '.($st->file_number ?? '').' '.($st->course?->name ?? '')),
    ])->values();
@endphp

<form method="POST"
      action="{{ $editing ? route('qr.update', $intervention) : route('qr.store') }}"
      x-data="{
          search: '',
          courseFilter: '',
          selectedId: '{{ (int) old('student_id', $intervention->student_id) ?: '' }}',
          students: @js($studentOptions),
          get filteredStudents() {
              const term = this.search.trim().toLowerCase();
              const cid = this.courseFilter ? Number(this.courseFilter) : null;
              return this.students.filter(s => {
                  if (cid && s.course_id !== cid && String(s.id) !== String(this.selectedId)) return false;
                  if (term !== '' && !s.search.includes(term) && String(s.id) !== String(this.selectedId)) return false;
                  return true;
              });
          }
      }"
      class="max-w-3xl space-y-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    @csrf
    @if ($editing)
        @method('PUT')
    @endif

    @if ($errors->any())
        <div class="rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- 1. Selección del estudiante en riesgo --}}
    <div class="rounded-md border border-slate-200 bg-slate-50 p-4">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-600">
            1. Estudiante con trayectoria discontinua o riesgo de abandono
        </p>
        <div class="mb-3 grid gap-3 sm:grid-cols-2">
            <label class="block text-xs font-medium text-slate-600">
                Filtrar por curso
                <select x-model="courseFilter" class="mt-1 block w-full rounded border border-slate-300 bg-white px-2.5 py-1.5 text-sm">
                    <option value="">Todos los cursos</option>
                    @foreach ($courses as $course)
                        <option value="{{ $course->id }}">
                            {{ $course->name }} ({{ $course->shiftRef?->name ?? $course->shift?->label() }})
                        </option>
                    @endforeach
                </select>
            </label>
            <label class="block text-xs font-medium text-slate-600">
                Buscador rápido (Apellido, Nombre o DNI)
                <input type="search" x-model="search" placeholder="Escriba para filtrar el listado…"
                       class="mt-1 block w-full rounded border border-slate-300 bg-white px-3 py-1.5 text-sm">
            </label>
        </div>

        <label class="block text-sm font-medium text-slate-700">
            Estudiante *
            <select name="student_id" x-model="selectedId" required
                    class="mt-1 block w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm">
                <option value="">Seleccione un estudiante…</option>
                @foreach ($students as $st)
                    <option value="{{ $st->id }}"
                            x-show="filteredStudents.some(s => s.id === {{ (int) $st->id }})"
                            @selected((int) old('student_id', $intervention->student_id) === $st->id)>
                        {{ $st->full_name }} — DNI {{ $st->dni ?: 'S/D' }} ({{ $st->course?->name ?? 'Sin curso' }})
                    </option>
                @endforeach
            </select>
        </label>
    </div>

    {{-- 2. Detección y estado del expediente --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <label class="block text-sm font-medium text-slate-700">
            Fecha de detección *
            <input type="date" name="detection_date"
                   value="{{ old('detection_date', $intervention->detection_date?->format('Y-m-d') ?? now()->toDateString()) }}"
                   max="{{ now()->toDateString() }}" required
                   class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm">
        </label>

        <label class="block text-sm font-medium text-slate-700">
            Faltas detonantes de alerta *
            <input type="number" name="consecutive_absences_flag" min="1" max="365"
                   value="{{ old('consecutive_absences_flag', $intervention->consecutive_absences_flag ?? 3) }}" required
                   class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 font-mono text-sm">
        </label>

        <label class="block text-sm font-medium text-slate-700">
            Estado del expediente QR *
            <select name="status" required class="mt-1 block w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm">
                @foreach ($statuses as $st)
                    <option value="{{ $st->value }}"
                            @selected(old('status', $intervention->status?->value ?? 'evaluacion') === $st->value)>
                        {{ $st->label() }}
                    </option>
                @endforeach
            </select>
        </label>
    </div>

    {{-- 3. Articulación territorial y visita domiciliaria --}}
    <div class="grid gap-4 sm:grid-cols-2">
        <label class="block text-sm font-medium text-slate-700">
            Referente del Equipo Territorial / Escuela Sede
            <input type="text" name="territorial_team_contact"
                   value="{{ old('territorial_team_contact', $intervention->territorial_team_contact) }}"
                   placeholder="Ej.: Lic. María Quispe — Región I (Tel. 388-4000000)"
                   class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm">
        </label>

        <label class="block text-sm font-medium text-slate-700">
            Fecha de visita domiciliaria
            <input type="date" name="home_visit_date"
                   value="{{ old('home_visit_date', $intervention->home_visit_date?->format('Y-m-d')) }}"
                   class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm">
        </label>

        <label class="block text-sm font-medium text-slate-700 sm:col-span-2">
            Acciones de apoyo pedagógico y acuerdos socioeducativos
            <textarea name="pedagogical_support_details" rows="4"
                      placeholder="Detalle entrevistas con la familia, resultado de la visita domiciliaria, asignación de clases de apoyo PECOS, acuerdos de reincorporación…"
                      class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm">{{ old('pedagogical_support_details', $intervention->pedagogical_support_details) }}</textarea>
        </label>
    </div>

    <div class="rounded border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900">
        <strong>Información reservada:</strong> Los expedientes del Programa QR «Quiero Regresar» contienen datos sociofamiliares
        protegidos. Solo pueden ser editados por el Equipo Directivo y consultados en modo lectura por el Preceptor del curso.
    </div>

    <div class="flex items-center gap-3 pt-2">
        <button type="submit" class="rounded bg-indigo-600 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            {{ $editing ? 'Actualizar expediente QR' : 'Iniciar expediente QR' }}
        </button>
        <a href="{{ $editing ? route('qr.show', $intervention) : route('qr.index') }}"
           class="px-3 py-2 text-sm text-slate-600 hover:underline">
            Cancelar
        </a>
    </div>
</form>
