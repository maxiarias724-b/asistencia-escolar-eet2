@extends('layouts.app')
@section('title', 'Editar Amonestación')

@section('content')
    <a href="{{ route('disciplinary.index') }}" class="text-sm text-indigo-600 hover:underline">
        ← Volver al libro de amonestaciones
    </a>
    <h1 class="mb-4 mt-2 text-2xl font-bold text-slate-900">
        Editar amonestación #{{ $record->id }} — {{ $record->student?->full_name }}
    </h1>

    @include('disciplinary._form')
@endsection
