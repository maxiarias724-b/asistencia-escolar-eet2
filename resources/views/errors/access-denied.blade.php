@extends('layouts.app')
@section('title', 'Acceso Denegado')

@section('content')
    <div class="mx-auto mt-10 max-w-xl rounded-lg border-2 border-red-300 bg-white p-8 text-center shadow" role="alert">
        <div class="mb-3 text-5xl">⛔</div>
        <h1 class="mb-3 text-xl font-bold text-red-700">{{ $message }}</h1>
        <p class="mb-6 text-sm text-slate-500">
            Si creés que se trata de un error, pedile al Director que revise tus cursos y actividades asignados.
        </p>
        @auth
            <a href="{{ route('home') }}" class="inline-block rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Volver a mis cursos</a>
        @else
            <a href="{{ route('login') }}" class="inline-block rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Ir al ingreso</a>
        @endauth
    </div>
@endsection
