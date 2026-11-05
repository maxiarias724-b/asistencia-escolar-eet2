@extends('layouts.app')
@section('title', 'Importador Masivo Excel / CSV (9 Pasos)')

@section('content')
    <div class="mb-4">
        <h1 class="text-2xl font-bold text-slate-900">Importador Masivo Excel / CSV en 9 Pasos</h1>
        <p class="text-sm text-slate-500">Carga segura con validación previa y actualización automática por DNI (Upsert) sin duplicar personas ni perder historial de asistencias.</p>
    </div>

    @include('people._tabs')
    @include('people.import._stepper', ['activeStep' => 2])

    @if ($errors->any())
        <div class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">
            <ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div x-data="{ entity: @js(old('entity', $entity)) }" class="grid gap-6 lg:grid-cols-3">
        {{-- Paso 1: Descarga de plantillas oficiales --}}
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-2 inline-block rounded bg-indigo-100 px-2 py-0.5 text-xs font-bold uppercase text-indigo-800">Paso 1</div>
            <h2 class="mb-2 text-lg font-bold text-slate-900">Descargar plantilla oficial</h2>
            <p class="mb-4 text-xs leading-relaxed text-slate-600">
                Descargá el modelo oficial con los encabezados exactos requeridos por la EET N° 2 y SINIDE. Podés trabajarlo en Microsoft Excel (`.xlsx`) o CSV separado por punto y coma (`.csv`).
            </p>

            <div class="space-y-4">
                <div class="rounded border border-slate-200 bg-slate-50 p-3">
                    <div class="mb-2 text-xs font-semibold uppercase text-slate-700">Estudiantes (SINIDE)</div>
                    <p class="mb-2.5 text-[11px] text-slate-500">Columnas: DNI, CUIL, Legajo, Código SINIDE, Apellidos, Nombres, Fecha de Nacimiento, Año, División, Turno, Estado.</p>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('people.import.template', ['entity' => 'students', 'format' => 'xlsx']) }}"
                           class="rounded bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                            ↓ Plantilla .XLSX
                        </a>
                        <a href="{{ route('people.import.template', ['entity' => 'students', 'format' => 'csv']) }}"
                           class="rounded border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100">
                            ↓ Plantilla .CSV
                        </a>
                    </div>
                </div>

                @if (auth()->user()->isDirector())
                    <div class="rounded border border-slate-200 bg-slate-50 p-3">
                        <div class="mb-2 text-xs font-semibold uppercase text-slate-700">Padrón de Docentes</div>
                        <p class="mb-2.5 text-[11px] text-slate-500">Columnas: DNI, CUIL, Legajo, Apellidos, Nombres, Email, Teléfono, Área/Especialidad, Estado.</p>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('people.import.template', ['entity' => 'teachers', 'format' => 'xlsx']) }}"
                               class="rounded bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">
                                ↓ Plantilla .XLSX
                            </a>
                            <a href="{{ route('people.import.template', ['entity' => 'teachers', 'format' => 'csv']) }}"
                               class="rounded border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100">
                                ↓ Plantilla .CSV
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </section>

        {{-- Paso 2: Subida del archivo para validación y vista previa --}}
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
            <div class="mb-2 inline-block rounded bg-indigo-100 px-2 py-0.5 text-xs font-bold uppercase text-indigo-800">Paso 2</div>
            <h2 class="mb-2 text-lg font-bold text-slate-900">Subir archivo y ejecutar validación previa</h2>
            <p class="mb-4 text-xs text-slate-600">
                Al subir el archivo el sistema validará encabezados (Paso 3), formatos y DNI (Paso 4), detectará duplicados (Paso 5) y te mostrará la <strong>Vista Previa</strong> (Paso 6) antes de guardar cualquier dato.
            </p>

            <form method="POST" action="{{ route('people.import.preview') }}" enctype="multipart/form-data" class="space-y-5">
                @csrf

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-medium text-slate-700">Tipo de padrón a importar *
                        <select name="entity" x-model="entity" class="mt-1 block w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm">
                            <option value="students">Estudiantes (Nómina SINIDE)</option>
                            @if (auth()->user()->isDirector())
                                <option value="teachers">Docentes de la institución</option>
                            @endif
                        </select>
                    </label>

                    <label x-show="entity === 'students'" class="block text-sm font-medium text-slate-700">
                        Curso destino por defecto (opcional)
                        <select name="default_course_id" class="mt-1 block w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm">
                            <option value="">Usar columnas Año y División del Excel</option>
                            @foreach ($courses as $course)
                                <option value="{{ $course->id }}" @selected((int) old('default_course_id') === $course->id)>
                                    {{ $course->name }} ({{ $course->shiftRef?->name ?? $course->shift->label() }})
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <label class="block text-sm font-medium text-slate-700">Archivo Excel (`.xlsx`) o CSV (`.csv`) — máx. 5 MB *
                    <input type="file" name="file" required accept=".xlsx,.csv,.txt"
                           class="mt-1 block w-full rounded border border-dashed border-slate-300 bg-slate-50 p-3 text-sm file:mr-4 file:rounded file:border-0 file:bg-indigo-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-indigo-700">
                </label>

                <div class="rounded border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
                    <strong>Regla Upsert por DNI:</strong> si un DNI del archivo ya existe en la base de datos, el sistema actualizará sus datos (nombre, curso, legajo, estado) manteniendo intacto su historial de asistencias; si no existe, creará el nuevo registro.
                </div>

                <div class="flex items-center justify-end gap-3 pt-1">
                    <button class="rounded bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                        Analizar archivo y ver Vista Previa →
                    </button>
                </div>
            </form>
        </section>
    </div>
@endsection
