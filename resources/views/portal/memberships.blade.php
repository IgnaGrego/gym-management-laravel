@extends('layouts.app')

@section('title', 'Membresías - Vital Gym')

@section('content')
    <section>
        <h1 class="text-3xl font-bold tracking-tight text-stone-900">Membresías</h1>

        @include('partials.portal-nav')

        @if ($memberships->isEmpty())
            <p class="mt-6 text-stone-600">No se encontraron membresías.</p>
        @else
            <ul class="mt-6 space-y-4">
                @foreach ($memberships as $membership)
                    <li class="rounded-lg border border-stone-200 bg-white p-4">
                        <p class="font-semibold text-stone-900">{{ $membership->plan?->name ?? '—' }}</p>
                        <p class="mt-1 text-sm text-stone-600">
                            {{ $membership->start_date->format('Y-m-d') }} – {{ $membership->end_date->format('Y-m-d') }}
                            · {{ $membership->duration_days }} días
                        </p>
                        <p class="mt-1 text-sm text-stone-600">Estado: {{ App\Models\Membership::statusLabels()[$membership->status] ?? $membership->status }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
