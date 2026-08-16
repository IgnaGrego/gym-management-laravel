@extends('layouts.app')

@section('title', 'Rutina - Vital Gym')

@section('content')
    <section>
        <h1 class="text-3xl font-bold tracking-tight text-stone-900">Rutina</h1>

        @include('partials.portal-nav')

        @if ($routine === null)
            <p class="mt-6 text-stone-600">Aún no tienes una rutina asignada.</p>
        @else
            <h2 class="mt-6 text-xl font-semibold text-stone-900">{{ $routine->name }}</h2>

            @foreach ($routine->days as $day)
                <h3 class="mt-4 text-lg font-semibold text-stone-800">Día {{ $day->day_number }}</h3>

                <ul class="mt-2 space-y-2">
                    @foreach ($day->exercises as $row)
                        <li class="rounded-lg border border-stone-200 bg-white p-4">
                            <p class="font-semibold text-stone-900">{{ $row->exercise?->name ?? '—' }} · Serie {{ $row->set_number }}</p>
                            <p class="mt-1 text-sm text-stone-600">
                                {{ $row->target_reps }} repeticiones
                                @ {{ $row->target_weight === null ? 'Peso corporal' : $row->target_weight.' kg' }}
                                @if ($row->rest_seconds !== null)
                                    · descanso {{ $row->rest_seconds }}s
                                @endif
                            </p>
                            @if ($row->notes)
                                <p class="mt-1 text-sm text-stone-500">{{ $row->notes }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endforeach
        @endif
    </section>
@endsection
