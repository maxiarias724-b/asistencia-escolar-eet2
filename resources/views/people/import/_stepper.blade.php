@php
    $steps = [
        1 => 'Plantilla oficial',
        2 => 'Subir archivo',
        3 => 'Columnas',
        4 => 'Validar DNI/datos',
        5 => 'Duplicados',
        6 => 'Vista previa',
        7 => 'Confirmar',
        8 => 'Upsert DNI',
        9 => 'Resumen final',
    ];
@endphp
<div class="mb-6 rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
    <div class="grid grid-cols-3 gap-2 text-xs sm:grid-cols-5 lg:grid-cols-9">
        @foreach ($steps as $num => $label)
            @php
                $done = $num < $activeStep;
                $current = $num === $activeStep;
            @endphp
            <div class="flex items-center gap-1.5 rounded px-2 py-1.5 {{ $current ? 'bg-indigo-600 font-semibold text-white' : ($done ? 'bg-emerald-50 font-medium text-emerald-800' : 'bg-slate-50 text-slate-400') }}">
                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[11px] font-bold {{ $current ? 'bg-white text-indigo-700' : ($done ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-600') }}">
                    {{ $done ? '✓' : $num }}
                </span>
                <span class="truncate">{{ $label }}</span>
            </div>
        @endforeach
    </div>
</div>
