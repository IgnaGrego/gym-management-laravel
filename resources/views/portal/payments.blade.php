@extends('layouts.app')

@section('title', 'Payments - El Area Gym')

@section('content')
    <section>
        <h1 class="text-3xl font-bold tracking-tight text-stone-900">Payments &amp; cuotas</h1>

        @include('partials.portal-nav')

        @if ($memberships->isEmpty())
            <p class="mt-6 text-stone-600">No memberships found.</p>
        @else
            <ul class="mt-6 space-y-4">
                @foreach ($memberships as $membership)
                    <li class="rounded-lg border border-stone-200 bg-white p-4">
                        <p class="font-semibold text-stone-900">{{ $membership->plan?->name ?? '—' }}</p>

                        <p class="mt-1 text-sm text-stone-600">
                            Cuota:
                            <span class="font-medium text-stone-800">{{ $membership->cuota?->amount ?? '—' }}</span>
                            · status {{ $membership->cuota?->status ?? '—' }}
                        </p>

                        @if ($membership->cuota && $membership->cuota->payments->isNotEmpty())
                            <ul class="mt-2 space-y-1 pl-4">
                                @foreach ($membership->cuota->payments as $payment)
                                    <li class="text-sm text-stone-600">
                                        {{ $payment->amount }} · {{ $payment->method }}
                                        · {{ $payment->payment_date->format('Y-m-d') }}
                                        · {{ $payment->status }}
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-2 text-sm text-stone-500">No payments recorded.</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
