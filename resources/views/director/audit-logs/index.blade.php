@extends('layouts.app')
@section('title', 'Bitácora de Auditoría')

@section('content')
<div x-data="{ open: false, item: null, inspect(row) { this.item = row; this.open = true } }" @keydown.escape.window="open = false">
    <div class="mb-4">
        <h1 class="text-2xl font-bold text-slate-900">Bitácora de operaciones (Audit Logs)</h1>
        <p class="text-sm text-slate-500">Trazabilidad automática de altas, modificaciones, bajas lógicas y accesos registrados en el sistema.</p>
    </div>

    <form method="GET" class="mb-4 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 text-sm shadow-sm sm:grid-cols-2 lg:grid-cols-5">
        <label>Desde
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 block w-full rounded border border-slate-300 px-2 py-1">
        </label>
        <label>Hasta
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 block w-full rounded border border-slate-300 px-2 py-1">
        </label>
        <label>Usuario
            <select name="user_id" class="mt-1 block w-full rounded border border-slate-300 px-2 py-1">
                <option value="">Todos</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}" @selected(($filters['user_id'] ?? null) == $u->id)>{{ $u->name }} ({{ $u->username }})</option>
                @endforeach
            </select>
        </label>
        <label>Acción
            <select name="action" class="mt-1 block w-full rounded border border-slate-300 px-2 py-1">
                <option value="">Todas</option>
                @foreach (['created' => 'Creación', 'updated' => 'Actualización', 'deleted' => 'Eliminación', 'login' => 'Inicio de sesión', 'logout' => 'Cierre de sesión'] as $k => $lbl)
                    <option value="{{ $k }}" @selected(($filters['action'] ?? null) === $k)>{{ $lbl }}</option>
                @endforeach
            </select>
        </label>
        <div class="flex items-end gap-2">
            <label class="flex-1">Descripción
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar..." class="mt-1 block w-full rounded border border-slate-300 px-2 py-1">
            </label>
            <button class="rounded bg-slate-900 px-3 py-1.5 font-medium text-white hover:bg-slate-800">Filtrar</button>
        </div>
    </form>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr>
                <th class="px-3 py-2">Fecha y hora</th>
                <th class="px-3 py-2">Usuario</th>
                <th class="px-3 py-2">Acción</th>
                <th class="px-3 py-2">Entidad</th>
                <th class="px-3 py-2">Descripción</th>
                <th class="px-3 py-2">IP</th>
                <th class="px-3 py-2 text-right">Detalle</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($logs as $log)
                <tr>
                    <td class="whitespace-nowrap px-3 py-2 text-xs text-slate-500">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                    <td class="px-3 py-2 font-medium">{{ $log->user?->name ?? 'Sistema / Seeder' }}</td>
                    <td class="px-3 py-2">
                        @php
                            $badge = match ($log->action) {
                                'created' => 'bg-emerald-100 text-emerald-800',
                                'updated' => 'bg-amber-100 text-amber-800',
                                'deleted' => 'bg-red-100 text-red-800',
                                default => 'bg-slate-100 text-slate-700',
                            };
                        @endphp
                        <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $badge }}">{{ $log->action }}</span>
                    </td>
                    <td class="px-3 py-2 text-xs text-slate-600">{{ $log->entity_short_name }} {{ $log->auditable_id ? '#'.$log->auditable_id : '' }}</td>
                    <td class="px-3 py-2">{{ $log->description }}</td>
                    <td class="px-3 py-2 font-mono text-xs text-slate-500">{{ $log->ip_address ?? '—' }}</td>
                    <td class="px-3 py-2 text-right">
                        @if ($log->old_values || $log->new_values)
                            <button type="button"
                                    @click='inspect(@js(["desc" => $log->description, "old" => $log->old_values, "new" => $log->new_values]))'
                                    class="rounded border border-slate-300 px-2 py-0.5 text-xs hover:bg-slate-50">
                                Cambios
                            </button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-3 py-8 text-center text-slate-500">Sin eventos registrados para esos filtros.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>

    {{-- Modal de inspección de valores anteriores / nuevos --}}
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="open = false">
        <div class="max-h-[85vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white p-5 shadow-xl">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-bold text-slate-900" x-text="item?.desc"></h2>
                <button type="button" @click="open = false" class="text-xl text-slate-400 hover:text-slate-700">&times;</button>
            </div>
            <div class="grid gap-3 text-xs sm:grid-cols-2">
                <div>
                    <div class="mb-1 font-semibold uppercase text-slate-500">Valores anteriores</div>
                    <pre class="overflow-x-auto rounded bg-slate-900 p-3 text-slate-100" x-text="item?.old ? JSON.stringify(item.old, null, 2) : '—'"></pre>
                </div>
                <div>
                    <div class="mb-1 font-semibold uppercase text-slate-500">Valores nuevos</div>
                    <pre class="overflow-x-auto rounded bg-slate-900 p-3 text-slate-100" x-text="item?.new ? JSON.stringify(item.new, null, 2) : '—'"></pre>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
