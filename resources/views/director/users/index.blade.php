@extends('layouts.app')
@section('title', 'Usuarios y accesos')

@section('content')
    @if (session('credentials'))
        @php $cred = session('credentials'); @endphp
        <div class="mb-4 rounded border-2 border-indigo-300 bg-indigo-50 p-4 text-sm" x-data="{ copied: false }">
            <div class="mb-1 font-semibold">Credenciales de «{{ $cred['name'] }}» — entregalas ahora, no se vuelven a mostrar:</div>
            <div class="font-mono">Usuario: <strong>{{ $cred['username'] }}</strong> &nbsp; Contraseña: <strong>{{ $cred['password'] }}</strong></div>
            <button type="button" class="mt-2 rounded bg-indigo-600 px-2 py-1 text-xs text-white"
                    @click="navigator.clipboard.writeText(@js('Usuario: '.$cred['username'].' / Contraseña: '.$cred['password'])); copied = true"
                    x-text="copied ? 'Copiado ✓' : 'Copiar'"></button>
        </div>
    @endif

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Usuarios y accesos</h1>
            <p class="text-sm text-slate-500">Operadores de asistencia (Preceptores, Prof. de Ed. Física, Prof./Jefes de Taller, Secretarios) y cuentas de consulta Docente.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <select name="role_filter" class="rounded border border-slate-300 bg-white px-2.5 py-1.5 text-sm">
                    <option value="">Todos los roles</option>
                    <option value="operador" @selected($roleFilter === 'operador')>Preceptor / Operador</option>
                    <option value="docente" @selected($roleFilter === 'docente')>Docente (consulta)</option>
                </select>
                <input type="search" name="q" value="{{ $q }}" placeholder="Buscar nombre, usuario o email" class="rounded border border-slate-300 px-3 py-1.5 text-sm">
                <button class="rounded border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">Filtrar</button>
                @if ($q || $roleFilter)
                    <a href="{{ route('director.users.index') }}" class="text-xs text-slate-500 hover:underline">Limpiar</a>
                @endif
            </form>
            <a href="{{ route('director.assignments.index') }}" class="rounded border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-sm font-medium text-indigo-700 hover:bg-indigo-100">Matriz Preceptores</a>
            <a href="{{ route('director.users.create') }}" class="rounded bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">+ Nuevo usuario</a>
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr>
                <th class="px-3 py-2">Nombre</th>
                <th class="px-3 py-2">Usuario</th>
                <th class="px-3 py-2">Rol</th>
                <th class="px-3 py-2">Estado</th>
                <th class="px-3 py-2">Cursos y ámbitos asignados</th>
                <th class="px-3 py-2 text-right">Acciones</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($users as $u)
                <tr class="align-top">
                    <td class="px-3 py-2 font-medium">
                        {{ $u->name }}
                        @if ($u->email)
                            <div class="text-xs font-normal text-slate-500">{{ $u->email }}</div>
                        @endif
                    </td>
                    <td class="px-3 py-2 font-mono">{{ $u->username }}</td>
                    <td class="px-3 py-2">
                        <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $u->role === \App\Enums\Role::Operador ? 'bg-sky-100 text-sky-800' : 'bg-amber-100 text-amber-800' }}">
                            {{ $u->role->label() }}
                        </span>
                    </td>
                    <td class="px-3 py-2">
                        <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $u->active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">
                            {{ $u->active ? 'ACTIVO' : 'INACTIVO' }}
                        </span>
                    </td>
                    <td class="px-3 py-2">
                        @if ($u->role === \App\Enums\Role::Docente)
                            <span class="text-xs text-slate-400">Acceso institucional de solo consulta</span>
                        @else
                            @forelse ($u->assignments->groupBy(fn ($a) => $a->activity_type->value) as $activity => $items)
                                @php
                                    $scopeName = $scopeLabels[$activity] ?? \App\Enums\ActivityType::tryFrom($activity)?->label() ?? $activity;
                                @endphp
                                <div class="mb-1">
                                    <span class="mr-1 rounded bg-indigo-100 px-1.5 py-0.5 text-xs font-semibold text-indigo-800">{{ $scopeName }}</span>
                                    {{ $items->sortBy(fn ($i) => ($i->course?->year ?? 0).($i->course?->division ?? ''))->map(fn ($i) => $i->course?->name ?? '—')->implode(', ') }}
                                </div>
                            @empty
                                <span class="text-slate-400">Sin asignaciones</span>
                            @endforelse
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-right">
                        <a href="{{ route('director.users.edit', $u) }}" class="text-indigo-600 hover:underline">Editar</a>
                        <form method="POST" action="{{ route('director.users.toggle', $u) }}" class="inline">
                            @csrf @method('PATCH')
                            <button class="ml-2 text-amber-700 hover:underline">{{ $u->active ? 'Desactivar' : 'Activar' }}</button>
                        </form>
                        <form method="POST" action="{{ route('director.users.destroy', $u) }}" class="inline"
                              onsubmit="return confirm('¿Eliminar a {{ e($u->name) }}? Si tiene registros históricos se bloqueará el borrado.')">
                            @csrf @method('DELETE')
                            <button class="ml-2 text-red-600 hover:underline">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-3 py-8 text-center text-slate-500">No se encontraron usuarios con esos filtros.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
@endsection
