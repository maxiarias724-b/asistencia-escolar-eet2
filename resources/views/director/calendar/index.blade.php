@extends('layouts.app')
@section('title', 'Calendario escolar')

@section('content')
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Calendario escolar {{ $year }}</h1>
            <p class="text-sm text-slate-500">Feriados, recesos y días sin actividad. No cuentan como días hábiles en el registro de asistencia (los sábados y domingos nunca cuentan).</p>
        </div>
        <div class="flex gap-2 text-sm">
            <a href="{{ route('director.calendar.index', ['year' => $year - 1]) }}" class="rounded border border-slate-300 bg-white px-3 py-1.5 hover:bg-slate-50">← {{ $year - 1 }}</a>
            <a href="{{ route('director.calendar.index', ['year' => $year + 1]) }}" class="rounded border border-slate-300 bg-white px-3 py-1.5 hover:bg-slate-50">{{ $year + 1 }} →</a>
        </div>
    </div>

    @include('organization._tabs')

    @if ($errors->any())
        <div class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">
            <ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('director.calendar.store') }}" class="mb-6 grid gap-3 rounded-lg bg-white p-4 text-sm shadow-sm sm:grid-cols-4">
        @csrf
        <label>Desde <input type="date" name="date" value="{{ old('date') }}" required class="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5"></label>
        <label>Hasta <span class="text-slate-400">(opcional, para rangos)</span> <input type="date" name="date_to" value="{{ old('date_to') }}" class="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5"></label>
        <label>Motivo <input name="reason" value="{{ old('reason') }}" required maxlength="255" placeholder="Feriado nacional / Receso invernal…" class="mt-1 block w-full rounded border border-slate-300 px-2 py-1.5"></label>
        <div class="flex items-end"><button class="rounded bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700">Agregar</button></div>
    </form>

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Fecha</th><th class="px-3 py-2">Día</th><th class="px-3 py-2">Motivo</th><th class="px-3 py-2"></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($days as $d)
                <tr>
                    <td class="px-3 py-2">{{ $d->date->format('d/m/Y') }}</td>
                    <td class="px-3 py-2 capitalize">{{ $d->date->translatedFormat('l') }}</td>
                    <td class="px-3 py-2">{{ $d->reason }}</td>
                    <td class="px-3 py-2 text-right">
                        <form method="POST" action="{{ route('director.calendar.destroy', $d) }}" onsubmit="return confirm('¿Quitar este día del calendario? Pasará a ser hábil.')">
                            @csrf @method('DELETE')
                            <button class="text-red-600 hover:underline">Quitar</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-3 py-8 text-center text-slate-500">No hay días sin actividad cargados para {{ $year }}.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
