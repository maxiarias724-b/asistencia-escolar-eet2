@php
    $f = fn (string $key) => old($key, $student?->{$key} instanceof \BackedEnum ? $student->{$key}->value : ($student?->{$key} instanceof \Carbon\Carbon ? $student->{$key}->format('Y-m-d') : $student?->{$key}));
    $input = 'mt-1 block w-full rounded border border-slate-300 px-2 py-1.5 text-sm';
@endphp
<label class="block text-sm">Apellido *<input name="last_name" value="{{ $f('last_name') }}" required class="{{ $input }}"></label>
<label class="block text-sm">Nombre *<input name="first_name" value="{{ $f('first_name') }}" required class="{{ $input }}"></label>
<label class="block text-sm">DNI <span class="text-slate-400">(opcional)</span><input name="dni" value="{{ $f('dni') }}" inputmode="numeric" class="{{ $input }}"></label>
<label class="block text-sm">CUIL <span class="text-slate-400">(opcional)</span><input name="cuil" value="{{ $f('cuil') }}" placeholder="20-12345678-9" class="{{ $input }}"></label>
<label class="block text-sm">Fecha de nacimiento<input type="date" name="birth_date" value="{{ $f('birth_date') }}" max="{{ now()->subDay()->toDateString() }}" class="{{ $input }}"></label>
<label class="block text-sm">Estado de matrícula
    <select name="status" class="{{ $input }}">
        @foreach ($statuses as $s)
            <option value="{{ $s->value }}" @selected(($f('status') ?: 'activo') === $s->value)>{{ $s->label() }}</option>
        @endforeach
    </select>
</label>
<label class="block text-sm">Código SINIDE<input name="sinide_code" value="{{ $f('sinide_code') }}" class="{{ $input }}"></label>
