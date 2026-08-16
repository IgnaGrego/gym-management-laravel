@extends('layouts.app')

@section('title', 'El Area Gym')

@section('content')
    <section class="py-12 text-center sm:py-20">
        <h1 class="text-4xl font-bold tracking-tight text-stone-900 sm:text-5xl">
            El Area Gym
        </h1>

        <p class="mx-auto mt-4 max-w-2xl text-lg text-stone-600">
            Bienvenido al sistema de gestión del gimnasio.
        </p>

        @if (session('status'))
            <p class="mx-auto mt-6 max-w-xl rounded-md border border-stone-300 bg-stone-100 px-4 py-3 text-sm text-stone-800" role="status" aria-live="polite">
                {{ session('status') }}
            </p>
        @endif

        <p class="mt-8">
            <a href="{{ route('login') }}" class="btn-brand">Iniciar sesión</a>
        </p>
    </section>
@endsection
