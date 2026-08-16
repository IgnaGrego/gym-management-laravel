<?php

use App\Actions\CreateBooking as CreateBookingAction;
use App\Filament\Resources\BookingResource\Pages\CreateBooking;
use App\Filament\Resources\BookingResource\Pages\ListBookings;
use App\Filament\Resources\BookingResource\Pages\ViewBooking;
use App\Filament\Resources\TurnoResource\Pages\ViewTurno;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/*
 * Booking management feature tests (SPEC-007 FR-001..FR-006; BR-002, BR-003,
 * BR-004, BR-006, BR-007, BR-011; ERR-001..ERR-005, ERR-009; AC-1..AC-5,
 * AC-10..AC-15). Authorization is enforced server-side (AGENTS.md §17).
 *
 * The Action's business-rule errors (ERR-003..ERR-005: turno state / time /
 * lead time) are asserted by driving the CreateBooking Action directly — the
 * same stance as the RegisterPayment business rules (SPEC-005). Form-level
 * required/exists rules are asserted through the Livewire create page.
 */

it('allows ADMIN to create a booking persisted as confirmed with booked_by', function () {
    // AC-1 (FR-001, FR-002, BR-002, BR-003, BK-12): the record is persisted
    // confirmed, booked_at set, booked_by = the current staff User, and appears
    // in the booking list.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->subDays(10)->toDateString(),
        'end_date' => today()->addDays(20)->toDateString(),
    ]);
    $turno = Turno::factory()->create([
        'date' => now()->addDay()->toDateString(),
        'start_time' => '08:00',
        'end_time' => '10:00',
    ]);

    Livewire::actingAs($admin)
        ->test(CreateBooking::class)
        ->fillForm([
            'client_id' => $client->id,
            'turno_id' => $turno->id,
            'notes' => 'Reserva de franja',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $booking = Booking::firstOrFail();

    expect($booking->client_id)->toBe($client->id);
    expect($booking->turno_id)->toBe($turno->id);
    expect($booking->status)->toBe(Booking::STATUS_CONFIRMED);
    expect($booking->booked_at)->toBeInstanceOf(Carbon\Carbon::class);
    expect($booking->booked_by)->toBe($admin->id);
    expect($booking->notes)->toBe('Reserva de franja');

    Livewire::actingAs($admin)
        ->test(ListBookings::class)
        ->assertCanSeeTableRecords([$booking]);
});

it('allows TRAINER to create a booking', function () {
    // AC-1 (BR-012, BK-02): TRAINER receives the full booking set.
    $trainer = userWithRoles([Role::TRAINER]);
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->subDays(10)->toDateString(),
        'end_date' => today()->addDays(20)->toDateString(),
    ]);
    $turno = Turno::factory()->create(['date' => now()->addDay()->toDateString()]);

    Livewire::actingAs($trainer)
        ->test(CreateBooking::class)
        ->fillForm([
            'client_id' => $client->id,
            'turno_id' => $turno->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Booking::count())->toBe(1);
    expect(Booking::first()->booked_by)->toBe($trainer->id);
    expect(Booking::first()->status)->toBe(Booking::STATUS_CONFIRMED);
});

it('rejects creating a booking without a client or turno', function () {
    // AC-2, ERR-001 (BR-002): both references are required.
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreateBooking::class)
        ->call('create')
        ->assertHasFormErrors([
            'client_id' => 'required',
            'turno_id' => 'required',
        ]);

    expect(Booking::count())->toBe(0);
});

it('rejects creating a booking for a nonexistent client or turno', function () {
    // AC-2, ERR-002 (BR-002): the references must exist.
    $admin = userWithRoles([Role::ADMIN]);
    $turno = Turno::factory()->create(['date' => now()->addDay()->toDateString()]);

    Livewire::actingAs($admin)
        ->test(CreateBooking::class)
        ->fillForm([
            'client_id' => 999999,
            'turno_id' => $turno->id,
        ])
        ->call('create')
        ->assertHasFormErrors(['client_id' => 'exists']);

    Livewire::actingAs($admin)
        ->test(CreateBooking::class)
        ->fillForm([
            'client_id' => Client::factory()->create()->id,
            'turno_id' => 999999,
        ])
        ->call('create')
        ->assertHasFormErrors(['turno_id' => 'exists']);

    expect(Booking::count())->toBe(0);
});

it('rejects creating a booking for an inactive or cancelled turno', function () {
    // AC-3 (ERR-003, BR-006): only active turnos are bookable.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->subDays(10)->toDateString(),
        'end_date' => today()->addDays(20)->toDateString(),
    ]);

    foreach ([Turno::STATUS_INACTIVE, Turno::STATUS_CANCELLED] as $status) {
        $turno = Turno::factory()->create([
            'date' => now()->addDay()->toDateString(),
            'status' => $status,
        ]);

        expect(fn () => app(CreateBookingAction::class)->handle($client->id, $turno->id))
            ->toThrow(ValidationException::class);
    }

    expect(Booking::count())->toBe(0);
});

it('rejects creating a booking for a turno in the past or already started', function () {
    // AC-4 (ERR-004, BR-007): a past date, or a same-day turno whose start time
    // has passed, cannot be booked.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->subDays(10)->toDateString(),
        'end_date' => today()->addDays(20)->toDateString(),
    ]);

    $past = Turno::factory()->create(['date' => now()->subDay()->toDateString()]);

    expect(fn () => app(CreateBookingAction::class)->handle($client->id, $past->id))
        ->toThrow(ValidationException::class);

    $started = Turno::factory()->create([
        'date' => today()->toDateString(),
        'start_time' => '00:00',
        'end_time' => '23:59',
    ]);

    expect(fn () => app(CreateBookingAction::class)->handle($client->id, $started->id))
        ->toThrow(ValidationException::class);

    expect(Booking::count())->toBe(0);
});

it('rejects creating a booking beyond the lead-time window', function () {
    // AC-5 (ERR-005, BR-007, BK-04): more than 7 days out is rejected.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->subDays(10)->toDateString(),
        'end_date' => today()->addDays(20)->toDateString(),
    ]);

    $turno = Turno::factory()->create(['date' => now()->addDays(8)->toDateString()]);

    expect(fn () => app(CreateBookingAction::class)->handle($client->id, $turno->id))
        ->toThrow(ValidationException::class);

    expect(Booking::count())->toBe(0);
});

it('lets ADMIN cancel a confirmed booking via the list action; it becomes cancelled and is terminal', function () {
    // AC-10 (FR-004, FR-005, BR-003, BR-004): confirmed -> cancelled; terminal.
    $admin = userWithRoles([Role::ADMIN]);
    $booking = Booking::factory()->create(['status' => Booking::STATUS_CONFIRMED]);

    Livewire::actingAs($admin)
        ->test(ListBookings::class)
        ->callTableAction('cancel', $booking);

    expect($booking->fresh()->status)->toBe(Booking::STATUS_CANCELLED);
    expect(Booking::find($booking->id))->not->toBeNull();

    expect(fn () => $booking->fresh()->cancel())->toThrow(DomainException::class);
});

it('lets ADMIN cancel a confirmed booking from the detail view header action', function () {
    // FR-004 path on the ViewBooking header.
    $admin = userWithRoles([Role::ADMIN]);
    $booking = Booking::factory()->create(['status' => Booking::STATUS_CONFIRMED]);

    Livewire::actingAs($admin)
        ->test(ViewBooking::class, ['record' => $booking->getRouteKey()])
        ->callAction('cancel');

    expect($booking->fresh()->status)->toBe(Booking::STATUS_CANCELLED);
});

it('rejects cancelling a cancelled booking', function () {
    // AC-11 (ERR-009, BR-004): only confirmed bookings can be cancelled.
    $booking = Booking::factory()->create(['status' => Booking::STATUS_CANCELLED]);

    expect(fn () => $booking->cancel())->toThrow(DomainException::class);
    expect($booking->fresh()->status)->toBe(Booking::STATUS_CANCELLED);
});

it('exposes no edit or delete path for bookings', function () {
    // AC-13 (BR-011): no edit page and no delete operation exists; the record
    // persists.
    $admin = userWithRoles([Role::ADMIN]);
    $booking = Booking::factory()->create();

    expect($admin->can('delete', $booking))->toBeFalse();

    Livewire::actingAs($admin)
        ->test(ListBookings::class)
        ->assertTableActionDoesNotExist('delete')
        ->assertTableActionDoesNotExist('edit');

    $this->actingAs($admin)
        ->get("/admin/bookings/{$booking->getRouteKey()}/edit")
        ->assertNotFound();

    expect(Booking::find($booking->id))->not->toBeNull();
});

it('does not create or modify any other record when creating or cancelling a booking', function () {
    // AC-14 (BR-001, BR-002, C-07): a booking touches only the bookings table;
    // no client, turno, membership, plan or user record is created or modified.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();
    $plan = Plan::factory()->create();
    $membership = Membership::factory()->create([
        'client_id' => $client->id,
        'plan_id' => $plan->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->subDays(10)->toDateString(),
        'end_date' => today()->addDays(20)->toDateString(),
    ]);
    $turno = Turno::factory()->create(['date' => now()->addDay()->toDateString()]);

    $clientBefore = $client->fresh()->toArray();
    $planBefore = $plan->fresh()->toArray();
    $turnoBefore = $turno->fresh()->toArray();
    $membershipBefore = $membership->fresh()->toArray();

    Livewire::actingAs($admin)
        ->test(CreateBooking::class)
        ->fillForm([
            'client_id' => $client->id,
            'turno_id' => $turno->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Booking::count())->toBe(1);
    expect(Client::count())->toBe(1);
    expect(Membership::count())->toBe(1);
    expect(Turno::count())->toBe(1);
    expect(Plan::count())->toBe(1);
    expect(User::count())->toBe(1); // only the acting admin
    expect(DB::table('payments')->count())->toBe(0);
    expect(DB::table('role_user')->count())->toBe(1);

    expect($client->fresh()->toArray())->toBe($clientBefore);
    expect($plan->fresh()->toArray())->toBe($planBefore);
    expect($turno->fresh()->toArray())->toBe($turnoBefore);
    expect($membership->fresh()->toArray())->toBe($membershipBefore);

    // Cancelling also touches only the booking's status.
    $booking = Booking::firstOrFail();
    $booking->cancel();

    expect($client->fresh()->toArray())->toBe($clientBefore);
    expect($turno->fresh()->toArray())->toBe($turnoBefore);
    expect($membership->fresh()->toArray())->toBe($membershipBefore);
    expect(Booking::first()->status)->toBe(Booking::STATUS_CANCELLED);
});

it('shows the occupied/capacity count on the turno detail view', function () {
    // AC-15, FR-006 (BK-14): the turno detail shows confirmed bookings vs.
    // capacity_limit (display only).
    $admin = userWithRoles([Role::ADMIN]);
    $turno = Turno::factory()->create([
        'date' => now()->addDay()->toDateString(),
        'capacity_limit' => 10,
    ]);
    Booking::factory()->create(['turno_id' => $turno->id, 'status' => Booking::STATUS_CONFIRMED]);
    Booking::factory()->create(['turno_id' => $turno->id, 'status' => Booking::STATUS_CANCELLED]);

    Livewire::actingAs($admin)
        ->test(ViewTurno::class, ['record' => $turno->getRouteKey()])
        ->assertSee('1 / 10');
});

it('lets ADMIN view the full booking detail', function () {
    // FR-003: the detail shows the client (name/DNI), the turno, the status,
    // booked_at, the booking staff User and notes.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create(['full_name' => 'Booking Client', 'dni' => '12345678']);
    $turno = Turno::factory()->create([
        'date' => now()->addDay()->toDateString(),
        'start_time' => '08:00',
        'end_time' => '10:00',
        'label' => 'Franja detalle',
    ]);
    $booking = Booking::factory()->create([
        'client_id' => $client->id,
        'turno_id' => $turno->id,
        'booked_by' => $admin->id,
        'notes' => 'Nota detalle',
    ]);

    Livewire::actingAs($admin)
        ->test(ViewBooking::class, ['record' => $booking->getRouteKey()])
        ->assertSee('Booking Client')
        ->assertSee('12345678')
        ->assertSee('Franja detalle')
        ->assertSee('Confirmada')
        ->assertSee($admin->name)
        ->assertSee('Nota detalle');
});

it('supports filtering the booking list by status, client and turno date', function () {
    // FR-002: filters by status, client and turno date.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create(['full_name' => 'Filter Client']);
    $otherClient = Client::factory()->create(['full_name' => 'Other Person']);

    $turnoToday = Turno::factory()->create(['date' => now()->addDay()->toDateString()]);
    $turnoLater = Turno::factory()->create(['date' => now()->addDays(3)->toDateString()]);

    $confirmed = Booking::factory()->create([
        'client_id' => $client->id,
        'turno_id' => $turnoToday->id,
        'booked_by' => $admin->id,
    ]);
    $cancelled = Booking::factory()->create([
        'client_id' => $otherClient->id,
        'turno_id' => $turnoLater->id,
        'booked_by' => $admin->id,
        'status' => Booking::STATUS_CANCELLED,
    ]);

    Livewire::actingAs($admin)
        ->test(ListBookings::class)
        ->filterTable('status', Booking::STATUS_CONFIRMED)
        ->assertCanSeeTableRecords([$confirmed])
        ->assertCanNotSeeTableRecords([$cancelled]);

    Livewire::actingAs($admin)
        ->test(ListBookings::class)
        ->filterTable('client_id', $client->id)
        ->assertCanSeeTableRecords([$confirmed])
        ->assertCanNotSeeTableRecords([$cancelled]);

    Livewire::actingAs($admin)
        ->test(ListBookings::class)
        ->filterTable('turno_date', ['date_from' => now()->addDays(3)->toDateString()])
        ->assertCanSeeTableRecords([$cancelled])
        ->assertCanNotSeeTableRecords([$confirmed]);
});
