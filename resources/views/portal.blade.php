@extends('layouts.app')

@section('title', 'Client portal - El Area Gym')

@section('content')
    <section>
        <h1 class="text-3xl font-bold tracking-tight text-stone-900">Client portal</h1>

        @include('partials.portal-nav')

        @if ($client === null)
            <p class="mt-6 rounded-md border border-stone-300 bg-stone-100 px-4 py-3 text-stone-800" role="status">
                Perfil no disponible. Contactá a recepción.
            </p>
        @else
            <dl class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-lg border border-stone-200 bg-white p-4">
                    <dt class="text-sm font-semibold text-stone-600">Name</dt>
                    <dd class="mt-1 text-stone-900">{{ $client->full_name }}</dd>
                </div>

                <div class="rounded-lg border border-stone-200 bg-white p-4">
                    <dt class="text-sm font-semibold text-stone-600">DNI</dt>
                    <dd class="mt-1 text-stone-900">{{ $client->dni }}</dd>
                </div>

                <div class="rounded-lg border border-stone-200 bg-white p-4">
                    <dt class="text-sm font-semibold text-stone-600">Email</dt>
                    <dd class="mt-1 text-stone-900">{{ $client->email ?? 'Not provided' }}</dd>
                </div>

                <div class="rounded-lg border border-stone-200 bg-white p-4">
                    <dt class="text-sm font-semibold text-stone-600">Phone</dt>
                    <dd class="mt-1 text-stone-900">{{ $client->phone ?? 'Not provided' }}</dd>
                </div>

                <div class="rounded-lg border border-stone-200 bg-white p-4">
                    <dt class="text-sm font-semibold text-stone-600">Status</dt>
                    <dd class="mt-1 text-stone-900">{{ $client->status }}</dd>
                </div>
            </dl>
        @endif
    </section>
@endsection
