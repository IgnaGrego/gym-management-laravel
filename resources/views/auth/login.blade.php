@extends('layouts.app')

@section('title', 'Iniciar sesión - El Area Gym')

@section('content')
    <section class="mx-auto w-full max-w-md py-12">
        <form method="POST" action="{{ route('login') }}" class="rounded-lg border border-stone-200 bg-white p-6 shadow-sm sm:p-8">
            @csrf

            <h1 class="text-2xl font-bold text-stone-900">Iniciar sesión</h1>

            @if (session('status'))
                <p class="mt-4 rounded-md border border-stone-300 bg-stone-100 px-4 py-3 text-sm text-stone-800" role="status" aria-live="polite">
                    {{ session('status') }}
                </p>
            @endif

            <div class="mt-6">
                <label for="email" class="label-base">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="input-base">
                @error('email')
                    <p class="mt-2 text-sm font-medium text-red-700" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-4">
                <label for="password" class="label-base">Contraseña</label>
                <input id="password" type="password" name="password" required autocomplete="current-password" class="input-base">
                @error('password')
                    <p class="mt-2 text-sm font-medium text-red-700" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="btn-brand mt-6 w-full">Iniciar sesión</button>
        </form>
    </section>
@endsection
