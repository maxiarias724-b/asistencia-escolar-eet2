@extends('layouts.app')
@section('title', 'Buscar Historial por Estudiante')

@section('content')
@include('reports._tabs')

<form method="GET" action="{{ route('reports.student-search') }}" class="mb-5 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="sm:col-span-2">
            <label class="block text-xs font-semibold uppercase text-slate-600">Buscar Estudiante (Apellido, Nombre, DNI o Legajo)</label>
            <input type="text" name="q" value="{{ $q }}" placeholder="Ej. Flores, 46123987 o Legajo..."
                   class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-600">Filtrar por Curso</label>
            <select name="course_id" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="">Todos los cursos habilitados</option>
                @foreach ($courses as $c)
                    <option value="{{ $c->id }}" @selected($courseId == $c->id)>{{ $c->full_name }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="mt-3 flex items-center gap-2">
        <button type="submit" class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
            Buscar estudiante
        </button>
        <a href="{{ route('reports.student-search') }}" class="rounded border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50">
            Ver todos
        </a>
    </div>
</form>

<div class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
    <table class="min-w-full divide-y divide-slate-200 text-xs">
        <thead class="bg-slate-50 text-left font-semibold uppercase text-slate-600">
            <tr>
                <th class="px-3 py-2.5">Apellido y Nombres</th>
                <th class="px-3 py-2.5">DNI / CUIL</th>
                <th class="px-3 py-2.5">Legajo</th>
                <th class="px-3 py-2.5">Curso / División</th>
                <th class="px-3 py-2.5">Turno</th>
                <th class="px-3 py-2.5 text-center">Estado</th>
                <th class="px-3 py-2.5 text-center">Registros</th>
                <th class="px-3 py-2.5 text-right">Ficha Individual</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($students as $st)
                <tr class="hover:bg-slate-50">
                    <td class="px-3 py-2.5 font-semibold text-slate-900">{{ $st->full_name }}</td>
                    <td class="whitespace-nowrap px-3 py-2.5 font-mono text-slate-700">
                        <div>{{ $st->dni ?: 'S/D' }}</div>
                        @if ($st->cuil)
                            <div class="text-[10px] text-slate-400">CUIL {{ $st->cuil }}</div>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-3 py-2.5 text-slate-600">{{ $st->file_number ?: '—' }}</td>
                    <td class="px-3 py-2.5">
                        <div class="font-medium text-slate-800">{{ $st->course?->name ?? '-' }}</div>
                        <div class="text-[11px] text-slate-500">{{ $st->course?->specialty?->name ?? '' }}</div>
                    </td>
                    <td class="whitespace-nowrap px-3 py-2.5 text-slate-700">
                        {{ $st->course?->shiftRef?->name ?? $st->course?->shift?->label() ?? '-' }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-2.5 text-center">
                        @if ($st->active && $st->status->value === 'activo')
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">ACTIVO</span>
                        @else
                            <span class="rounded-full bg-slate-200 px-2 py-0.5 text-[11px] font-semibold text-slate-700">INACTIVO ({{ $st->status->label() }})</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-3 py-2.5 text-center font-mono font-semibold text-slate-700">
                        {{ $st->attendances_count }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-2.5 text-right">
                        <a href="{{ route('reports.student-history', $st) }}"
                           class="inline-flex items-center rounded bg-indigo-600 px-3 py-1 font-medium text-white hover:bg-indigo-700">
                            Ver historial completo →
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">
                        No se encontraron estudiantes con el criterio ingresado.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($students->hasPages())
    <div class="mt-4">
        {{ $students->links() }}
    </div>
@endif
@endsection
