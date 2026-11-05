@props(['status'])
@php
    $colors = [
        'presente' => 'bg-emerald-100 text-emerald-800',
        'ausente' => 'bg-red-100 text-red-800',
        'tardanza' => 'bg-amber-100 text-amber-800',
        'media_falta' => 'bg-orange-100 text-orange-800',
        'justificada' => 'bg-sky-100 text-sky-800',
    ];
    $value = $status instanceof \BackedEnum ? $status->value : $status;
    $enum = \App\Enums\AttendanceStatus::from($value);
@endphp
<span title="{{ $enum->label() }}" class="inline-block rounded px-2 py-0.5 text-xs font-semibold {{ $colors[$value] }}">{{ $enum->code() }} · {{ $enum->label() }}</span>
