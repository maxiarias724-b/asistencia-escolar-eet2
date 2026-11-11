@extends('layouts.app')
@section('title', 'Abrir Expediente Programa QR')

@section('content')
    <a href="{{ route('qr.index') }}" class="text-sm text-indigo-600 hover:underline">
        ← Volver al listado del Programa QR
    </a>
    <h1 class="mb-4 mt-2 text-2xl font-bold text-slate-900">
        Abrir expediente — Programa QR «Quiero Regresar»
    </h1>

    @include('qr._form')
@endsection
