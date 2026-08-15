@php
    $links = [
        'portal' => ['label' => 'Overview', 'route' => 'portal'],
        'portal.memberships' => ['label' => 'Memberships', 'route' => 'portal.memberships'],
        'portal.payments' => ['label' => 'Payments', 'route' => 'portal.payments'],
        'portal.attendance' => ['label' => 'Attendance', 'route' => 'portal.attendance'],
        'portal.turnos' => ['label' => 'Turnos', 'route' => 'portal.turnos'],
        'portal.bookings' => ['label' => 'Bookings', 'route' => 'portal.bookings'],
        'portal.routine' => ['label' => 'Routine', 'route' => 'portal.routine'],
        'portal.workouts' => ['label' => 'Workouts', 'route' => 'portal.workouts'],
        'portal.profile' => ['label' => 'Profile', 'route' => 'portal.profile'],
    ];
@endphp

<nav aria-label="Portal" class="mt-6">
    <ul class="flex flex-wrap gap-2">
        @foreach ($links as $name => $link)
            <li>
                <a href="{{ route($link['route']) }}"
                   @class([
                       'rounded-md px-3 py-2 text-sm font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2',
                       'bg-brand-600 text-white' => request()->routeIs($name),
                       'text-stone-700 hover:bg-stone-100 hover:text-stone-900' => ! request()->routeIs($name),
                   ])>
                    {{ $link['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
