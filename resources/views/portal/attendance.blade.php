@extends('layouts.app')

@section('title', 'Asistencia - El Area Gym')

@section('content')
    <section>
        <h1 class="text-3xl font-bold tracking-tight text-stone-900">Asistencia</h1>

        @include('partials.portal-nav')

        @if ($attendances->isEmpty())
            <p class="mt-6 text-stone-600">No se encontraron registros de asistencia.</p>
        @else
            <ul class="mt-6 space-y-4">
                @foreach ($attendances as $attendance)
                    <li class="rounded-lg border border-stone-200 bg-white p-4">
                        <p class="font-semibold text-stone-900">{{ $attendance->attended_at->format('Y-m-d H:i') }}</p>

                        @if ($attendance->turno)
                            <p class="mt-1 text-sm text-stone-600">
                                Turno: {{ $attendance->turno->date->format('Y-m-d') }}
                                {{ $attendance->turno->start_time }}–{{ $attendance->turno->end_time }}
                            </p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
