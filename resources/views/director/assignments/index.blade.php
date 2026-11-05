@extends('layouts.app')
@section('title', 'Preceptores y Asignaciones de Responsabilidad')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Preceptores — Asignación de Responsabilidades</h1>
            <p class="text-sm text-slate-500">Vinculá rápidamente Preceptores, Profesores de Ed. Física o Jefes de Taller con Ámbitos, Cursos/Divisiones y Turnos.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('director.users.index') }}" class="rounded border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Ver cuentas de usuario</a>
            <a href="{{ route('director.users.create') }}" class="rounded bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">+ Nuevo operador</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">
            <ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Formulario de asignación múltiple --}}
    <section class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
             x-data="{
                 shiftFilter: '',
                 selectAll(checked) {
                     this.$refs.courseList.querySelectorAll('input[type=checkbox]:not([disabled])').forEach(el => {
                         if (el.closest('[data-course-item]').offsetParent !== null) el.checked = checked;
                     });
                 }
             }">
        <h2 class="mb-3 text-base font-semibold text-slate-800">Nueva asignación de responsabilidad</h2>
        <form method="POST" action="{{ route('director.assignments.store') }}" class="space-y-4">
            @csrf
            <div class="grid gap-4 md:grid-cols-3">
                <label class="block text-sm font-medium text-slate-700">1. Preceptor / Operador activo *
                    <select name="user_id" required class="mt-1 block w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">Seleccionar operador…</option>
                        @foreach ($operators as $op)
                            <option value="{{ $op->id }}" @selected((int) old('user_id') === $op->id)>
                                {{ $op->name }} ({{ $op->username }})
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block text-sm font-medium text-slate-700">2. Ámbito de asistencia *
                    <select name="scope_id" required class="mt-1 block w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">Seleccionar ámbito…</option>
                        @foreach ($scopes as $sc)
                            <option value="{{ $sc->id }}" @selected((int) old('scope_id') === $sc->id)>
                                {{ $sc->name }} ({{ $sc->slug }})
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block text-sm font-medium text-slate-700">3. Filtrar/fijar Turno (opcional)
                    <select name="shift_id" x-model="shiftFilter" class="mt-1 block w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">Usar turno propio de cada curso</option>
                        @foreach ($shifts as $sh)
                            <option value="{{ $sh->id }}">{{ $sh->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div>
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <span class="text-sm font-medium text-slate-700">4. Cursos y divisiones a asignar *</span>
                    <div class="flex gap-3 text-xs">
                        <button type="button" @click="selectAll(true)" class="font-medium text-indigo-600 hover:underline">Marcar visibles</button>
                        <button type="button" @click="selectAll(false)" class="text-slate-500 hover:underline">Desmarcar visibles</button>
                    </div>
                </div>

                <div x-ref="courseList" class="grid gap-3 rounded border border-slate-200 bg-slate-50 p-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                    @foreach ($courses as $course)
                        <label data-course-item
                               x-show="!shiftFilter || shiftFilter == '{{ $course->shift_id }}'"
                               class="flex cursor-pointer items-start gap-2 rounded border border-slate-200 bg-white p-2 text-xs hover:border-indigo-300">
                            <input type="checkbox" name="course_ids[]" value="{{ $course->id }}"
                                   @checked(in_array($course->id, array_map('intval', (array) old('course_ids', [])), true))
                                   class="mt-0.5 rounded border-slate-300 text-indigo-600">
                            <span>
                                <strong class="font-semibold text-slate-800">{{ $course->name }}</strong>
                                <span class="block text-[11px] text-slate-500">
                                    {{ $course->specialty?->name ?? $course->cycle->label() }} · {{ $course->shiftRef?->name ?? $course->shift->label() }}
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end">
                <button class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Guardar asignaciones
                </button>
            </div>
        </form>
    </section>

    {{-- Filtro y tabla de responsabilidades vigentes --}}
    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-4">
            <h2 class="font-semibold text-slate-800">Responsabilidades vigentes ({{ $assignments->total() }})</h2>
            <form method="GET" class="flex flex-wrap items-center gap-2 text-sm">
                <select name="user_id" class="rounded border border-slate-300 bg-white px-2.5 py-1.5">
                    <option value="">Todos los operadores</option>
                    @foreach ($operators as $op)
                        <option value="{{ $op->id }}" @selected(($filters['user_id'] ?? null) == $op->id)>{{ $op->name }}</option>
                    @endforeach
                </select>
                <select name="scope_id" class="rounded border border-slate-300 bg-white px-2.5 py-1.5">
                    <option value="">Todos los ámbitos</option>
                    @foreach ($scopes as $sc)
                        <option value="{{ $sc->id }}" @selected(($filters['scope_id'] ?? null) == $sc->id)>{{ $sc->name }}</option>
                    @endforeach
                </select>
                <select name="course_id" class="rounded border border-slate-300 bg-white px-2.5 py-1.5">
                    <option value="">Todos los cursos</option>
                    @foreach ($courses as $c)
                        <option value="{{ $c->id }}" @selected(($filters['course_id'] ?? null) == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
                <button class="rounded border border-slate-300 bg-slate-50 px-3 py-1.5 font-medium hover:bg-slate-100">Filtrar</button>
                @if (! empty(array_filter($filters)))
                    <a href="{{ route('director.assignments.index') }}" class="text-xs text-slate-500 hover:underline">Limpiar</a>
                @endif
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-2.5">Operador / Preceptor</th>
                        <th class="px-4 py-2.5">Ámbito</th>
                        <th class="px-4 py-2.5">Curso / División</th>
                        <th class="px-4 py-2.5">Especialidad</th>
                        <th class="px-4 py-2.5">Turno</th>
                        <th class="px-4 py-2.5 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($assignments as $a)
                        <tr>
                            <td class="px-4 py-2.5 font-medium text-slate-800">
                                {{ $a->user?->name ?? '—' }}
                                <span class="ml-1 font-mono text-xs text-slate-400">{{ $a->user?->username }}</span>
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="rounded bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-800">
                                    {{ $a->scopeRef?->name ?? $a->activity_type->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 font-semibold">{{ $a->course?->name ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-slate-600">{{ $a->course?->specialty?->name ?? $a->course?->cycle?->label() ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-slate-600">{{ $a->shift?->name ?? $a->course?->shiftRef?->name ?? $a->course?->shift?->label() ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right">
                                @if ($a->course)
                                    <a href="{{ route('attendance.show', ['activity' => $a->scopeRef?->slug ?? $a->activity_type->value, 'course' => $a->course_id]) }}"
                                       class="mr-3 text-xs text-indigo-600 hover:underline">Ver planilla</a>
                                @endif
                                <form method="POST" action="{{ route('director.assignments.destroy', $a) }}" class="inline"
                                      onsubmit="return confirm('¿Revocar esta asignación de responsabilidad?')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs font-medium text-red-600 hover:underline">Revocar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">No hay asignaciones que coincidan con el filtro seleccionado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($assignments->hasPages())
            <div class="border-t border-slate-100 p-4">{{ $assignments->links() }}</div>
        @endif
    </section>
@endsection
