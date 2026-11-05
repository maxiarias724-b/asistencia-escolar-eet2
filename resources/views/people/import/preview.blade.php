@extends('layouts.app')
@section('title', 'Vista Previa de Importación Masiva')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">
                Paso 6: Vista Previa — {{ $entity === 'teachers' ? 'Padrón de Docentes' : 'Nómina de Estudiantes SINIDE' }}
            </h1>
            <p class="text-sm text-slate-500">Archivo analizado: <strong class="font-mono text-slate-700">{{ $filename }}</strong> · Revisá el diagnóstico antes de confirmar el guardado.</p>
        </div>
        <a href="{{ route('people.import.index', ['entity' => $entity]) }}" class="rounded border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
            ← Subir otro archivo
        </a>
    </div>

    @include('people._tabs')
    @include('people.import._stepper', ['activeStep' => 6])

    {{-- Tarjetas de métricas del análisis (Pasos 3 a 6) --}}
    <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-500">Total encontrados</div>
            <div class="mt-1 text-2xl font-bold text-slate-900">{{ $summary['total'] }}</div>
        </div>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-emerald-700">Válidos (Nuevos)</div>
            <div class="mt-1 text-2xl font-bold text-emerald-900">{{ $summary['valid_new'] }}</div>
        </div>
        <div class="rounded-lg border border-sky-200 bg-sky-50 p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-sky-700">DNI existente (Actualizar)</div>
            <div class="mt-1 text-2xl font-bold text-sky-900">{{ $summary['valid_update'] }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-600">Sin cambios</div>
            <div class="mt-1 text-2xl font-bold text-slate-700">{{ $summary['unchanged'] }}</div>
        </div>
        <div class="rounded-lg border border-red-200 bg-red-50 p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase text-red-700">Duplicados / Con errores</div>
            <div class="mt-1 text-2xl font-bold text-red-900">{{ $summary['errors'] }}</div>
        </div>
    </div>

    {{-- Paso 7: Barra de confirmación final --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-lg border border-indigo-200 bg-indigo-50 p-4">
        <div class="text-sm text-indigo-950">
            @if ($summary['ready'] > 0)
                <strong>Paso 7 — Listo para confirmar:</strong> se procesarán <strong>{{ $summary['ready'] }}</strong> registro(s) válido(s) mediante <em>Upsert por DNI</em> ({{ $summary['valid_new'] }} altas nuevas y {{ $summary['valid_update'] }} actualizaciones).
                @if ($summary['errors'] > 0)
                    Las {{ $summary['errors'] }} fila(s) marcadas con error serán omitidas de forma segura.
                @endif
            @else
                <strong>Sin cambios aplicables:</strong> no hay filas nuevas ni con cambios válidos para persistir. Corregí las observaciones o verificá el archivo.
            @endif
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('people.import.index', ['entity' => $entity]) }}" class="rounded border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Cancelar
            </a>
            @if ($summary['ready'] > 0 || $summary['unchanged'] > 0)
                <form method="POST" action="{{ route('people.import.confirm') }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <button class="rounded bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">
                        ✓ Confirmar e importar (Upsert por DNI)
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- Detalle fila por fila --}}
    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2.5">Fila</th>
                    <th class="px-3 py-2.5">DNI</th>
                    <th class="px-3 py-2.5">Apellido y Nombre</th>
                    <th class="px-3 py-2.5">{{ $entity === 'teachers' ? 'Área / Contacto' : 'Curso destino' }}</th>
                    <th class="px-3 py-2.5">Estado</th>
                    <th class="px-3 py-2.5">Acción Upsert</th>
                    <th class="px-3 py-2.5">Validaciones / Observaciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($rows as $row)
                    <tr class="{{ $row['action'] === 'error' ? 'bg-red-50/60' : '' }}">
                        <td class="px-3 py-2 font-mono text-xs text-slate-500">#{{ $row['line'] }}</td>
                        <td class="px-3 py-2 font-mono text-xs font-semibold">{{ $row['dni'] ?: '—' }}</td>
                        <td class="px-3 py-2 font-medium text-slate-900">{{ $row['full_name'] }}</td>
                        <td class="px-3 py-2 text-xs text-slate-600">{{ $row['extra'] }}</td>
                        <td class="px-3 py-2 text-xs font-medium text-slate-700">{{ $row['status_label'] }}</td>
                        <td class="px-3 py-2">
                            @if ($row['action'] === 'crear')
                                <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">Crear nuevo</span>
                            @elseif ($row['action'] === 'actualizar')
                                <span class="rounded bg-sky-100 px-2 py-0.5 text-xs font-semibold text-sky-800">Actualizar DNI</span>
                            @elseif ($row['action'] === 'sin_cambios')
                                <span class="rounded bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-700">Sin cambios</span>
                            @else
                                <span class="rounded bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">Con error</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-xs">
                            @if ($row['errors'])
                                <ul class="list-inside list-disc text-red-700">
                                    @foreach ($row['errors'] as $err)
                                        <li>{{ $err }}</li>
                                    @endforeach
                                </ul>
                            @else
                                <span class="text-emerald-700">✓ Datos y DNI verificados</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
