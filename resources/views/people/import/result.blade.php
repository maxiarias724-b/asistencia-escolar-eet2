@extends('layouts.app')
@section('title', 'Resumen de Importación Masiva')

@section('content')
    <div class="mb-4">
        <h1 class="text-2xl font-bold text-slate-900">Paso 9: Resumen Final de Importación</h1>
        <p class="text-sm text-slate-500">Operación registrada en la bitácora de auditoría el {{ $completed_at }} sobre el archivo <strong class="font-mono">{{ $filename }}</strong>.</p>
    </div>

    @include('people._tabs')
    @include('people.import._stepper', ['activeStep' => 9])

    <section class="rounded-lg border border-emerald-200 bg-white p-6 shadow-sm">
        <div class="mb-4 flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 text-lg font-bold text-emerald-700">✓</span>
            <div>
                <h2 class="text-lg font-bold text-slate-900">
                    Proceso Upsert finalizado — {{ $entity === 'teachers' ? 'Docentes' : 'Estudiantes SINIDE' }}
                </h2>
                <p class="text-xs text-slate-500">Todos los cambios se aplicaron dentro de una transacción segura preservando el historial de asistencias.</p>
            </div>
        </div>

        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded border border-emerald-200 bg-emerald-50 p-4">
                <div class="text-xs font-semibold uppercase text-emerald-700">Nuevos creados</div>
                <div class="mt-1 text-3xl font-bold text-emerald-900">{{ $result['created'] }}</div>
            </div>
            <div class="rounded border border-sky-200 bg-sky-50 p-4">
                <div class="text-xs font-semibold uppercase text-sky-700">Actualizados por DNI</div>
                <div class="mt-1 text-3xl font-bold text-sky-900">{{ $result['updated'] }}</div>
            </div>
            <div class="rounded border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs font-semibold uppercase text-slate-600">Sin cambios</div>
                <div class="mt-1 text-3xl font-bold text-slate-800">{{ $result['unchanged'] }}</div>
            </div>
            <div class="rounded border border-amber-200 bg-amber-50 p-4">
                <div class="text-xs font-semibold uppercase text-amber-800">Omitidos por error</div>
                <div class="mt-1 text-3xl font-bold text-amber-900">{{ $result['errors'] }}</div>
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            @if ($entity === 'teachers')
                <a href="{{ route('people.teachers.index') }}" class="rounded bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Ir al padrón de Docentes →
                </a>
            @else
                <a href="{{ route('people.students.index') }}" class="rounded bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Ir al padrón de Estudiantes →
                </a>
            @endif
            <a href="{{ route('people.import.index', ['entity' => $entity]) }}" class="rounded border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Realizar otra importación
            </a>
        </div>
    </section>
@endsection
