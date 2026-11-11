@php
    $editing = $record->exists;
    $studentOptions = $students->map(fn ($st) => [
        'id' => (int) $st->id,
        'course_id' => (int) $st->course_id,
        'label' => $st->full_name.' — DNI '.($st->dni ?: 'S/D').' ('.($st->course?->name ?? 'Sin curso').')',
        'search' => mb_strtolower($st->full_name.' '.$st->dni.' '.($st->file_number ?? '').' '.($st->course?->name ?? '')),
    ])->values();
@endphp

<form method="POST"
      action="{{ $editing ? route('disciplinary.update', $record) : route('disciplinary.store') }}"
      x-data="{
          search: '',
          courseFilter: '',
          selectedId: '{{ (int) old('student_id', $record->student_id) ?: '' }}',
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

    {{-- Buscador interactivo de estudiante --}}
    <div class="rounded-md border border-slate-200 bg-slate-50 p-4">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-600">1. Seleccionar estudiante sancionado</p>
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
                            @selected((int) old('student_id', $record->student_id) === $st->id)>
                        {{ $st->full_name }} — DNI {{ $st->dni ?: 'S/D' }} ({{ $st->course?->name ?? 'Sin curso' }})
                    </option>
                @endforeach
            </select>
        </label>
    </div>

    {{-- Datos de la sanción --}}
    <div class="grid gap-4 sm:grid-cols-2">
        <label class="block text-sm font-medium text-slate-700">
            Fecha de la sanción *
            <input type="date" name="date"
                   value="{{ old('date', $record->date?->format('Y-m-d') ?? now()->toDateString()) }}"
                   max="{{ now()->toDateString() }}" required
                   class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm">
        </label>

        <label class="block text-sm font-medium text-slate-700">
            Cantidad de amonestaciones (1 a 25) *
            <input type="number" name="amount" min="1" max="25"
                   value="{{ old('amount', $record->amount ?? 1) }}" required
                   class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 font-mono text-sm">
        </label>

        <label class="block text-sm font-medium text-slate-700 sm:col-span-2">
            Motivo detallado de la sanción *
            <textarea name="reason" rows="4" required
                      placeholder="Describa el hecho ocurrido, las normas de convivencia involucradas y las actuaciones labradas…"
                      class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm">{{ old('reason', $record->reason) }}</textarea>
        </label>

        @if ($editing)
            <label class="block text-sm font-medium text-slate-700 sm:col-span-2">
                Estado del registro
                <select name="active" class="mt-1 block w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm">
                    <option value="1" @selected((bool) old('active', $record->active) === true)>VIGENTE (computa en el acumulado del estudiante)</option>
                    <option value="0" @selected((bool) old('active', $record->active) === false)>BAJA LÓGICA (anulada / sin efecto activo)</option>
                </select>
            </label>
        @endif
    </div>

    <div class="rounded border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600">
        <strong>Trazabilidad institucional:</strong> La sanción quedará vinculada a la credencial directiva de
        <strong>{{ auth()->user()->name }}</strong> y registrada en la bitácora de auditoría de la EET N° 2.
    </div>

    <div class="flex items-center gap-3 pt-2">
        <button type="submit" class="rounded bg-indigo-600 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            {{ $editing ? 'Guardar cambios' : 'Registrar amonestación' }}
        </button>
        <a href="{{ route('disciplinary.index') }}" class="px-3 py-2 text-sm text-slate-600 hover:underline">
            Cancelar
        </a>
    </div>
</form>
