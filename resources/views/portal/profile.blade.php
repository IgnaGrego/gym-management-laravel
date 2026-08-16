@extends('layouts.app')

@section('title', 'Perfil - El Area Gym')

@section('content')
    <section>
        <h1 class="text-3xl font-bold tracking-tight text-stone-900">Perfil</h1>

        @include('partials.portal-nav')

        @if ($errors->any())
            <div class="mt-6 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-red-800" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        @if (session('status'))
            <div class="mt-6 rounded-md border border-green-300 bg-green-50 px-4 py-3 text-green-800" role="status">
                {{ session('status') }}
            </div>
        @endif

        <dl class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="rounded-lg border border-stone-200 bg-white p-4">
                <dt class="text-sm font-semibold text-stone-600">Nombre</dt>
                <dd class="mt-1 text-stone-900">{{ $client->full_name }}</dd>
            </div>

            <div class="rounded-lg border border-stone-200 bg-white p-4">
                <dt class="text-sm font-semibold text-stone-600">DNI</dt>
                <dd class="mt-1 text-stone-900">{{ $client->dni }}</dd>
            </div>

            <div class="rounded-lg border border-stone-200 bg-white p-4">
                <dt class="text-sm font-semibold text-stone-600">Estado</dt>
                <dd class="mt-1 text-stone-900">{{ App\Models\Client::statusLabels()[$client->status] ?? $client->status }}</dd>
            </div>
        </dl>

        <h2 class="mt-8 text-xl font-semibold text-stone-900">Notas de salud</h2>

        <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="rounded-lg border border-stone-200 bg-white p-4">
                <dt class="text-sm font-semibold text-stone-600">Lesiones</dt>
                <dd class="mt-1 text-stone-900">{{ $client->injuries_notes ?? 'Ninguna' }}</dd>
            </div>

            <div class="rounded-lg border border-stone-200 bg-white p-4">
                <dt class="text-sm font-semibold text-stone-600">Condiciones médicas</dt>
                <dd class="mt-1 text-stone-900">{{ $client->medical_conditions_notes ?? 'Ninguna' }}</dd>
            </div>
        </dl>

        <h2 class="mt-8 text-xl font-semibold text-stone-900">Editar datos de contacto</h2>

        <form method="POST" action="{{ route('portal.profile.update') }}" class="mt-4 rounded-lg border border-stone-200 bg-white p-4">
            @csrf

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="email" class="block text-sm font-semibold text-stone-700">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $client->email) }}"
                           class="mt-1 w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-sm text-stone-900">
                    @error('email')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="phone" class="block text-sm font-semibold text-stone-700">Teléfono</label>
                    <input type="text" id="phone" name="phone" value="{{ old('phone', $client->phone) }}"
                           class="mt-1 w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-sm text-stone-900">
                    @error('phone')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="emergency_contact" class="block text-sm font-semibold text-stone-700">Contacto de emergencia</label>
                    <input type="text" id="emergency_contact" name="emergency_contact" value="{{ old('emergency_contact', $client->emergency_contact) }}"
                           class="mt-1 w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-sm text-stone-900">
                    @error('emergency_contact')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <button type="submit" class="mt-4 rounded-md bg-brand-600 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
                Guardar perfil
            </button>
        </form>
    </section>
@endsection
