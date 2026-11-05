@extends('layouts.app')
@section('title', 'Organización — Cursos y Divisiones')

@section('content')
    <div class="mb-4">
        <h1 class="text-2xl font-bold text-slate-900">Organización Escolar — Cursos y Divisiones</h1>
        <p class="text-sm text-slate-500">Estructura académica de 1° a 7° año de la EET N° 2 vinculada con Especialidad y Turno (baja lógica sin pérdida de historial).</p>
    </div>

    @include('organization._tabs')

    @if ($errors->any())
        <div class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">
            <ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Alta de nuevo curso --}}
    <section class="mb-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-600">Nuevo curso / división</h2>
        <form method="POST" action="{{ route('organization.courses.store') }}" class="grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-6">
            @csrf
            <label class="block text-xs font-medium text-slate-700">Año *
                <select name="year" required class="mt-1 block w-full rounded border border-slate-300 bg-white px-2.5 py-2 text-sm">
                    @for ($y = 1; $y <= 7; $y++)
                        <option value="{{ $y }}" @selected((int) old('year', 1) === $y)>{{ $y }}° Año</option>
                    @endfor
                </select>
            </label>
            <label class="block text-xs font-medium text-slate-700">División (número) *
                <input type="text" name="division" value="{{ old('division') }}" required placeholder="Ej.: 1, 2, 3"
                       class="mt-1 block w-full rounded border border-slate-300 px-2.5 py-2 text-sm">
            </label>
            <label class="block text-xs font-medium text-slate-700 lg:col-span-2">Especialidad *
                <select name="specialty_id" required class="mt-1 block w-full rounded border border-slate-300 bg-white px-2.5 py-2 text-sm">
                    <option value="">Seleccionar especialidad…</option>
                    @foreach ($specialties as $sp)
                        <option value="{{ $sp->id }}" @selected((int) old('specialty_id') === $sp->id)>
                            {{ $sp->name }}{{ $sp->code ? ' ('.$sp->code.')' : '' }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label class="block text-xs font-medium text-slate-700">Turno *
                <select name="shift_id" required class="mt-1 block w-full rounded border border-slate-300 bg-white px-2.5 py-2 text-sm">
                    <option value="">Seleccionar…</option>
                    @foreach ($shifts as $sh)
                        <option value="{{ $sh->id }}" @selected((int) old('shift_id') === $sh->id)>{{ $sh->name }}</option>
                    @endforeach
                </select>
            </label>
            <div>
                <input type="hidden" name="active" value="1">
                <button class="w-full rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">+ Crear curso</button>
            </div>
        </form>
    </section>

    {{-- Filtros y listado con edición en modal --}}
    <div x-data="{ editing: null }" class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-4">
            <h2 class="font-semibold text-slate-800">Cursos registrados ({{ $courses->count() }})</h2>
            <form method="GET" class="flex flex-wrap items-center gap-2 text-sm">
                <select name="specialty_id" class="rounded border border-slate-300 bg-white px-2.5 py-1.5">
                    <option value="">Todas las especialidades</option>
                    @foreach ($specialties as $sp)
                        <option value="{{ $sp->id }}" @selected($specialtyId === $sp->id)>{{ $sp->name }}</option>
                    @endforeach
                </select>
                <select name="shift_id" class="rounded border border-slate-300 bg-white px-2.5 py-1.5">
                    <option value="">Todos los turnos</option>
                    @foreach ($shifts as $sh)
                        <option value="{{ $sh->id }}" @selected($shiftId === $sh->id)>{{ $sh->name }}</option>
                    @endforeach
                </select>
                <button class="rounded border border-slate-300 bg-slate-50 px-3 py-1.5 font-medium hover:bg-slate-100">Filtrar</button>
                @if ($specialtyId || $shiftId)
                    <a href="{{ route('organization.courses.index') }}" class="text-xs text-slate-500 hover:underline">Limpiar</a>
                @endif
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-2.5">Curso / División</th>
                        <th class="px-4 py-2.5">Ciclo</th>
                        <th class="px-4 py-2.5">Especialidad</th>
                        <th class="px-4 py-2.5">Turno</th>
                        <th class="px-4 py-2.5 text-center">Estudiantes activos</th>
                        <th class="px-4 py-2.5">Estado</th>
                        <th class="px-4 py-2.5 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($courses as $course)
                        <tr>
                            <td class="px-4 py-2.5 font-semibold text-slate-900">{{ $course->name }}</td>
                            <td class="px-4 py-2.5 text-slate-600">{{ $course->cycle->label() }}</td>
                            <td class="px-4 py-2.5">
                                <span class="font-medium text-slate-800">{{ $course->specialty?->name ?? '—' }}</span>
                                @if ($course->specialty?->code)
                                    <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] text-slate-600">{{ $course->specialty->code }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-slate-600">{{ $course->shiftRef?->name ?? $course->shift->label() }}</td>
                            <td class="px-4 py-2.5 text-center font-mono">
                                <a href="{{ route('people.students.index', ['course_id' => $course->id]) }}" class="text-indigo-600 hover:underline">
                                    {{ $course->active_students_count }}
                                </a>
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $course->active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">
                                    {{ $course->active ? 'ACTIVO' : 'INACTIVO' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right">
                                <button type="button"
                                        @click="editing = @js([
                                            'id' => $course->id,
                                            'action' => route('organization.courses.update', $course),
                                            'year' => $course->year,
                                            'division' => $course->division,
                                            'specialty_id' => $course->specialty_id,
                                            'shift_id' => $course->shift_id,
                                            'active' => $course->active,
                                        ])"
                                        class="text-indigo-600 hover:underline">Editar</button>
                                <form method="POST" action="{{ route('organization.courses.toggle', $course) }}" class="inline">
                                    @csrf @method('PATCH')
                                    <button class="ml-3 text-amber-700 hover:underline">{{ $course->active ? 'Desactivar' : 'Activar' }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No hay cursos registrados con esos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Modal de edición --}}
        <div x-show="editing" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div @click.outside="editing = null" class="w-full max-w-lg rounded-lg bg-white p-5 shadow-xl">
                <h3 class="mb-4 text-lg font-bold text-slate-900">Editar curso</h3>
                <template x-if="editing">
                    <form method="POST" :action="editing.action" class="space-y-4">
                        @csrf @method('PUT')
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="block text-sm">Año *
                                <select name="year" x-model="editing.year" required class="mt-1 block w-full rounded border border-slate-300 bg-white px-3 py-2">
                                    @for ($y = 1; $y <= 7; $y++)
                                        <option value="{{ $y }}">{{ $y }}° Año</option>
                                    @endfor
                                </select>
                            </label>
                            <label class="block text-sm">División *
                                <input type="text" name="division" x-model="editing.division" required class="mt-1 block w-full rounded border border-slate-300 px-3 py-2">
                            </label>
                            <label class="block text-sm sm:col-span-2">Especialidad *
                                <select name="specialty_id" x-model="editing.specialty_id" required class="mt-1 block w-full rounded border border-slate-300 bg-white px-3 py-2">
                                    @foreach ($specialties as $sp)
                                        <option value="{{ $sp->id }}">{{ $sp->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block text-sm sm:col-span-2">Turno *
                                <select name="shift_id" x-model="editing.shift_id" required class="mt-1 block w-full rounded border border-slate-300 bg-white px-3 py-2">
                                    @foreach ($shifts as $sh)
                                        <option value="{{ $sh->id }}">{{ $sh->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="hidden" name="active" value="0">
                            <input type="checkbox" name="active" value="1" :checked="editing.active"> Curso activo
                        </label>
                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="editing = null" class="rounded border border-slate-300 px-3 py-1.5 text-sm">Cancelar</button>
                            <button class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">Guardar cambios</button>
                        </div>
                    </form>
                </template>
            </div>
        </div>
    </div>
@endsection
