@extends('layouts.app')
@section('title', 'Seguimiento Expediente Programa QR')

@section('content')
    <a href="{{ route('qr.show', $intervention) }}" class="text-sm text-indigo-600 hover:underline">
        ← Volver al expediente #{{ $intervention->id }}
    </a>
    <h1 class="mb-4 mt-2 text-2xl font-bold text-slate-900">
        Seguimiento Programa QR #{{ $intervention->id }} — {{ $intervention->student?->full_name }}
    </h1>

    @include('qr._form')
@endsection
