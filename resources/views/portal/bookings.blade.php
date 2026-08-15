@extends('layouts.app')

@section('title', 'Bookings - El Area Gym')

@section('content')
    <section>
        <h1 class="text-3xl font-bold tracking-tight text-stone-900">Bookings</h1>

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

        @if ($bookings->isEmpty())
            <p class="mt-6 text-stone-600">No bookings found.</p>
        @else
            <ul class="mt-6 space-y-4">
                @foreach ($bookings as $booking)
                    <li class="rounded-lg border border-stone-200 bg-white p-4">
                        <p class="font-semibold text-stone-900">
                            {{ $booking->turno->date->format('Y-m-d') }}
                            {{ $booking->turno->start_time }}–{{ $booking->turno->end_time }}
                        </p>
                        <p class="mt-1 text-sm text-stone-600">
                            Status: {{ $booking->status }}
                            · Booked at {{ $booking->booked_at->format('Y-m-d H:i') }}
                        </p>

                        @if ($booking->status === App\Models\Booking::STATUS_CONFIRMED)
                            <form method="POST" action="{{ route('portal.bookings.cancel', $booking) }}" class="mt-3">
                                @csrf
                                <button type="submit" class="rounded-md bg-stone-900 px-3 py-2 text-sm font-semibold text-white hover:bg-stone-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
                                    Cancel booking
                                </button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
