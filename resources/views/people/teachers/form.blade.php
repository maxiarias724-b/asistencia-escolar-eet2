@extends('layouts.app')
@php $editing = $teacher->exists; @endphp
@section('title', $editing ? 'Editar docente' : 'Nuevo docente')

@section('content')
    <a href="{{ route('people.teachers.index') }}" class="text-sm text-indigo-600 hover:underline">← Volver al padrón de docentes</a>
    <h1 class="mb-4 mt-2 text-2xl font-bold text-slate-900">
        {{ $editing ? 'Editar docente «'.$teacher->full_name.'»' : 'Registrar nuevo docente' }}
    </h1>

    <form method="POST" action="{{ $editing ? route('people.teachers.update', $teacher) : route('people.teachers.store') }}"
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
                <input type="text" name="last_name" value="{{ old('last_name', $teacher->last_name) }}" required
                       class="mt-1 block w-full rounded border border-slate-300 px-3 py-2" placeholder="Ej.: Salazar">
            </label>

            <label class="block text-sm font-medium text-slate-700">Nombre(s) *
                <input type="text" name="first_name" value="{{ old('first_name', $teacher->first_name) }}" required
                       class="mt-1 block w-full rounded border border-slate-300 px-3 py-2" placeholder="Ej.: Roberto Carlos">
            </label>

            <label class="block text-sm font-medium text-slate-700">DNI (sin puntos, 7 a 9 dígitos) *
                <input type="text" name="dni" value="{{ old('dni', $teacher->dni) }}" required
                       class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 font-mono" placeholder="28456123">
            </label>

            <label class="block text-sm font-medium text-slate-700">CUIL (11 dígitos, opcional)
                <input type="text" name="cuil" value="{{ old('cuil', $teacher->cuil) }}"
                       class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 font-mono" placeholder="20-28456123-4">
            </label>

            <label class="block text-sm font-medium text-slate-700">Legajo docente
                <input type="text" name="file_number" value="{{ old('file_number', $teacher->file_number) }}"
                       class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 font-mono" placeholder="DOC-042">
            </label>

            <label class="block text-sm font-medium text-slate-700">Área / Especialidad / Cargo
                <input type="text" name="specialty_area" value="{{ old('specialty_area', $teacher->specialty_area) }}"
                       class="mt-1 block w-full rounded border border-slate-300 px-3 py-2" placeholder="Ej.: Taller de Programación / Matemática">
            </label>

            <label class="block text-sm font-medium text-slate-700">Correo electrónico
                <input type="email" name="email" value="{{ old('email', $teacher->email) }}"
                       class="mt-1 block w-full rounded border border-slate-300 px-3 py-2" placeholder="docente@eet2jujuy.edu.ar">
            </label>

            <label class="block text-sm font-medium text-slate-700">Teléfono de contacto
                <input type="text" name="phone" value="{{ old('phone', $teacher->phone) }}"
                       class="mt-1 block w-full rounded border border-slate-300 px-3 py-2" placeholder="388-4123456">
            </label>
        </div>

        <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
            <input type="hidden" name="active" value="0">
            <input type="checkbox" name="active" value="1" @checked((bool) old('active', $teacher->active))>
            Docente ACTIVO en el padrón institucional
        </label>

        <div class="flex items-center gap-3 pt-2">
            <button class="rounded bg-indigo-600 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                {{ $editing ? 'Guardar cambios' : 'Registrar docente' }}
            </button>
            <a href="{{ route('people.teachers.index') }}" class="px-3 py-2 text-sm text-slate-600 hover:underline">Cancelar</a>
        </div>
    </form>
@endsection
