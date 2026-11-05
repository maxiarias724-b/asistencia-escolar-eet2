@extends('layouts.app')
@section('title', 'Nómina '.$course->name)

@section('content')
    <a href="{{ route('attendance.show', ['activity' => $activity->value, 'course' => $course->id]) }}" class="text-sm text-indigo-600 hover:underline">← Volver al registro de asistencia</a>
    <div class="mb-4 mt-2 flex flex-wrap items-end justify-between gap-2">
        <div>
            <h1 class="text-2xl font-bold">Nómina SINIDE · {{ $course->full_name }}</h1>
            <p class="text-sm text-slate-500">
                {{ $students->count() }} alumnos ·
                @foreach ($statuses as $s) {{ $s->label() }}: {{ $counts[$s->value] ?? 0 }}{{ ! $loop->last ? ' · ' : '' }} @endforeach
            </p>
        </div>
    </div>

    @if ($errors->any() && ! $errors->has('roster'))
        <div class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">
            <ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="mb-6 grid gap-4 lg:grid-cols-2">
        {{-- Carga manual --}}
        <section class="rounded-lg bg-white p-4 shadow-sm" x-data="{ open: {{ $errors->has('last_name') || $errors->has('first_name') || $errors->has('dni') ? 'true' : 'false' }} }">
            <button type="button" @click="open = !open" class="flex w-full items-center justify-between font-semibold">
                <span>➕ Agregar alumno manualmente</span><span x-text="open ? '−' : '+'"></span>
            </button>
            <form x-show="open" x-cloak method="POST" action="{{ route('roster.store', ['activity' => $activity->value, 'course' => $course->id]) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                @csrf
                @include('roster._fields', ['student' => null, 'statuses' => $statuses])
                <div class="sm:col-span-2"><button class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Agregar a la nómina</button></div>
            </form>
        </section>

        {{-- Importación --}}
        <section class="rounded-lg bg-white p-4 shadow-sm">
            <h2 class="mb-1 font-semibold">📥 Importar nómina (CSV / Excel)</h2>
            <p class="mb-3 text-xs text-slate-500">
                Columnas: <strong>Apellido</strong> y <strong>Nombre</strong> (o «Apellido y Nombre» como «Pérez, Juan»), y opcionalmente DNI, CUIL, Fecha de nacimiento, Estado de matrícula y Código SINIDE.
                Verás una vista previa antes de guardar. Si tu listado de SINIDE está en PDF, exportalo a Excel/CSV.
            </p>
            <form method="POST" enctype="multipart/form-data" action="{{ route('roster.import', ['activity' => $activity->value, 'course' => $course->id]) }}" class="flex flex-wrap items-center gap-2">
                @csrf
                <input type="file" name="file" required accept=".csv,.txt,.xlsx" class="text-sm file:mr-3 file:rounded file:border-0 file:bg-slate-200 file:px-3 file:py-1.5">
                <button class="rounded bg-slate-700 px-3 py-1.5 text-sm text-white hover:bg-slate-800">Ver vista previa</button>
                <a href="{{ route('roster.template', ['activity' => $activity->value, 'course' => $course->id]) }}" class="text-sm text-indigo-600 hover:underline">Descargar plantilla</a>
            </form>
        </section>
    </div>

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr><th class="px-3 py-2">#</th><th class="px-3 py-2">Apellido y Nombre</th><th class="px-3 py-2">DNI</th><th class="px-3 py-2">CUIL</th><th class="px-3 py-2">Nacimiento</th><th class="px-3 py-2">Estado</th><th class="px-3 py-2">Cód. SINIDE</th><th class="px-3 py-2 text-right">Acciones</th></tr>
            </thead>
            @forelse ($students as $s)
                <tbody x-data="{ edit: false }" class="divide-y divide-slate-100 border-t border-slate-100">
                <tr :class="edit ? 'bg-indigo-50' : ''">
                    <td class="px-3 py-2 text-slate-400">{{ $loop->iteration }}</td>
                    <td class="px-3 py-2 font-medium">{{ $s->last_name }}, {{ $s->first_name }}</td>
                    <td class="px-3 py-2">{{ $s->dni ?? '—' }}</td>
                    <td class="px-3 py-2">{{ $s->cuil ?? '—' }}</td>
                    <td class="px-3 py-2">{{ $s->birth_date?->format('d/m/Y') ?? '—' }}</td>
                    <td class="px-3 py-2">
                        <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $s->status->value === 'activo' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">{{ $s->status->label() }}</span>
                    </td>
                    <td class="px-3 py-2">{{ $s->sinide_code ?? '—' }}</td>
                    <td class="whitespace-nowrap px-3 py-2 text-right">
                        <button type="button" @click="edit = !edit" class="text-indigo-600 hover:underline" x-text="edit ? 'Cancelar' : 'Editar'"></button>
                        <form method="POST" class="inline" action="{{ route('roster.destroy', ['activity' => $activity->value, 'course' => $course->id, 'student' => $s->id]) }}"
                              onsubmit="return confirm('¿Eliminar a {{ e($s->last_name) }}, {{ e($s->first_name) }} de la nómina?')">
                            @csrf @method('DELETE')
                            <button class="ml-2 text-red-600 hover:underline" @if ($s->attendances_count) title="Tiene {{ $s->attendances_count }} asistencias: usá estado Baja/Pase" @endif>Eliminar</button>
                        </form>
                    </td>
                </tr>
                <tr x-show="edit" x-cloak class="bg-indigo-50">
                    <td colspan="8" class="px-3 pb-3">
                        <form method="POST" action="{{ route('roster.update', ['activity' => $activity->value, 'course' => $course->id, 'student' => $s->id]) }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            @csrf @method('PUT')
                            @include('roster._fields', ['student' => $s, 'statuses' => $statuses])
                            <div class="flex items-end"><button class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Guardar cambios</button></div>
                        </form>
                    </td>
                </tr>
                </tbody>
            @empty
                <tbody><tr><td colspan="8" class="px-3 py-8 text-center text-slate-500">Todavía no hay alumnos. Agregalos manualmente o importá el listado de SINIDE.</td></tr></tbody>
            @endforelse
        </table>
    </div>
@endsection
