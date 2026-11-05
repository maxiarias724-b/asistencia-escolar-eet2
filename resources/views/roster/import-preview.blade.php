@extends('layouts.app')
@section('title', 'Vista previa de importación')

@php
    $badge = [
        'crear' => 'bg-emerald-100 text-emerald-800',
        'actualizar' => 'bg-sky-100 text-sky-800',
        'sin_cambios' => 'bg-slate-100 text-slate-600',
        'error' => 'bg-red-100 text-red-800',
    ];
    $label = ['crear' => 'Nuevo', 'actualizar' => 'Actualiza', 'sin_cambios' => 'Sin cambios', 'error' => 'Error'];
    $importable = ($summary['crear'] ?? 0) + ($summary['actualizar'] ?? 0);
@endphp

@section('content')
    <a href="{{ route('roster.index', ['activity' => $activity->value, 'course' => $course->id]) }}" class="text-sm text-indigo-600 hover:underline">← Volver a la nómina</a>
    <h1 class="mb-1 mt-2 text-2xl font-bold">Vista previa · {{ $course->full_name }}</h1>
    <p class="mb-4 text-sm text-slate-500">Archivo: <strong>{{ $filename }}</strong>. Todavía no se guardó nada.</p>

    <div class="mb-4 flex flex-wrap gap-3 text-sm">
        @foreach ($label as $key => $text)
            <span class="rounded px-3 py-1 font-semibold {{ $badge[$key] }}">{{ $text }}: {{ $summary[$key] ?? 0 }}</span>
        @endforeach
    </div>

    @if (($summary['error'] ?? 0) > 0)
        <div class="mb-4 rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">
            Las filas con error <strong>no se importarán</strong>. Corregilas en el archivo y volvé a subirlo, o continuá con las válidas.
        </div>
    @endif

    <div class="mb-4 max-h-[60vh] overflow-auto rounded-lg bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="sticky top-0 bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr><th class="px-3 py-2">Línea</th><th class="px-3 py-2">Acción</th><th class="px-3 py-2">Apellido y Nombre</th><th class="px-3 py-2">DNI</th><th class="px-3 py-2">CUIL</th><th class="px-3 py-2">Nacimiento</th><th class="px-3 py-2">Estado</th><th class="px-3 py-2">Detalle</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @foreach ($rows as $r)
                <tr class="{{ $r['action'] === 'error' ? 'bg-red-50' : '' }}">
                    <td class="px-3 py-1.5 text-slate-400">{{ $r['line'] }}</td>
                    <td class="px-3 py-1.5"><span class="rounded px-2 py-0.5 text-xs font-semibold {{ $badge[$r['action']] }}">{{ $label[$r['action']] }}</span></td>
                    <td class="px-3 py-1.5 font-medium">{{ ($r['data']['last_name'] ?? '—') }}, {{ $r['data']['first_name'] ?? '—' }}</td>
                    <td class="px-3 py-1.5">{{ $r['data']['dni'] ?? '—' }}</td>
                    <td class="px-3 py-1.5">{{ $r['data']['cuil'] ?? '—' }}</td>
                    <td class="px-3 py-1.5">{{ isset($r['data']['birth_date']) ? \Carbon\Carbon::parse($r['data']['birth_date'])->format('d/m/Y') : '—' }}</td>
                    <td class="px-3 py-1.5">{{ $r['data']['status'] ?? 'activo' }}</td>
                    <td class="px-3 py-1.5 text-xs text-red-700">{{ implode(' ', $r['errors']) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <form method="POST" action="{{ route('roster.import.confirm', ['activity' => $activity->value, 'course' => $course->id]) }}" class="flex items-center gap-3">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <button class="rounded bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700 disabled:opacity-50" @disabled($importable === 0)>
            Confirmar importación ({{ $importable }} {{ $importable === 1 ? 'alumno' : 'alumnos' }})
        </button>
        <a href="{{ route('roster.index', ['activity' => $activity->value, 'course' => $course->id]) }}" class="text-slate-600 hover:underline">Cancelar</a>
    </form>
@endsection
