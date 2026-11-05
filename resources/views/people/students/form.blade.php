@extends('layouts.app')
@php $editing = $student->exists; @endphp
@section('title', $editing ? 'Editar estudiante' : 'Nuevo estudiante')

@section('content')
    <a href="{{ route('people.students.index') }}" class="text-sm text-indigo-600 hover:underline">← Volver al padrón de estudiantes</a>
    <h1 class="mb-4 mt-2 text-2xl font-bold text-slate-900">
        {{ $editing ? 'Editar estudiante «'.$student->full_name.'»' : 'Registrar nuevo estudiante (Nómina SINIDE)' }}
    </h1>

    <form method="POST" action="{{ $editing ? route('people.students.update', $student) : route('people.students.store') }}"
          class="max-w-3xl space-y-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @if ($editing) @method('PUT') @endif

        @if ($errors->any())
            <div class="rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">
                <ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm font-medium text-slate-700">Apellido(s) *
                <input type="text" name="last_name" value="{{ old('last_name', $student->last_name) }}" required
                       class="mt-1 block w-full rounded border border-slate-300 px-3 py-2" placeholder="Ej.: Aramayo">
            </label>

            <label class="block text-sm font-medium text-slate-700">Nombre(s) *
                <input type="text" name="first_name" value="{{ old('first_name', $student->first_name) }}" required
                       class="mt-1 block w-full rounded border border-slate-300 px-3 py-2" placeholder="Ej.: Lautaro Ezequiel">
            </label>

            <label class="block text-sm font-medium text-slate-700">DNI (sin puntos, 7 a 9 dígitos) *
                <input type="text" name="dni" value="{{ old('dni', $student->dni) }}" required
                       class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 font-mono" placeholder="46123456">
            </label>

            <label class="block text-sm font-medium text-slate-700">CUIL (11 dígitos, opcional)
                <input type="text" name="cuil" value="{{ old('cuil', $student->cuil) }}"
                       class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 font-mono" placeholder="20-46123456-3">
            </label>

            <label class="block text-sm font-medium text-slate-700">Legajo institucional
                <input type="text" name="file_number" value="{{ old('file_number', $student->file_number) }}"
                       class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 font-mono" placeholder="LEG-2026-001">
            </label>

            <label class="block text-sm font-medium text-slate-700">Código SINIDE
                <input type="text" name="sinide_code" value="{{ old('sinide_code', $student->sinide_code) }}"
                       class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 font-mono" placeholder="SIN-0001">
            </label>

            <label class="block text-sm font-medium text-slate-700">Fecha de nacimiento
                <input type="date" name="birth_date" value="{{ old('birth_date', $student->birth_date?->format('Y-m-d')) }}"
                       class="mt-1 block w-full rounded border border-slate-300 px-3 py-2">
            </label>

            <label class="block text-sm font-medium text-slate-700">Curso y División *
                <select name="course_id" required class="mt-1 block w-full rounded border border-slate-300 bg-white px-3 py-2">
                    <option value="">Seleccionar curso…</option>
                    @foreach ($courses as $course)
                        <option value="{{ $course->id }}" @selected((int) old('course_id', $student->course_id) === $course->id)>
                            {{ $course->name }} — {{ $course->specialty?->name ?? $course->cycle->label() }} ({{ $course->shiftRef?->name ?? $course->shift->label() }})
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="block text-sm font-medium text-slate-700 sm:col-span-2">Estado de matrícula SINIDE *
                <select name="status" required class="mt-1 block w-full rounded border border-slate-300 bg-white px-3 py-2">
                    @foreach ($statuses as $st)
                        <option value="{{ $st->value }}" @selected(old('status', $student->status?->value ?? 'activo') === $st->value)>
                            {{ $st->label() }} ({{ $st === \App\Enums\StudentStatus::Activo ? 'ACTIVO en planillas' : 'INACTIVO — baja lógica' }})
                        </option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="rounded border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600">
            <strong>Preservación histórica:</strong> ningún estudiante se elimina físicamente de la base de datos. Al pasar su matrícula a <em>Baja</em>, <em>Pase</em> o <em>Libre</em> queda en estado `INACTIVO` conservando todas sus asistencias y justificaciones previas.
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button class="rounded bg-indigo-600 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                {{ $editing ? 'Guardar cambios' : 'Registrar estudiante' }}
            </button>
            <a href="{{ route('people.students.index') }}" class="px-3 py-2 text-sm text-slate-600 hover:underline">Cancelar</a>
        </div>
    </form>
@endsection
