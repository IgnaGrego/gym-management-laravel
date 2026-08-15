<?php

use App\Filament\Resources\AttendanceResource\Pages\CreateAttendance;
use App\Filament\Resources\AttendanceResource\Pages\ListAttendances;
use App\Filament\Resources\AttendanceResource\Pages\ViewAttendance;
use App\Models\Attendance;
use App\Models\Client;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

/*
 * Attendance check-in CRUD and gate feature tests (SPEC-008 FR-001..FR-006;
 * BR-002, BR-003, BR-007, BR-008, BR-011, BR-012; ERR-001..ERR-006; AC-1..AC-8,
 * AC-11..AC-14; AF-001, AF-002, AF-004). Authorization is enforced server-side
 * (AGENTS.md §17). As SPEC-005 is BLOCKED, the gate tests use active
 * memberships created directly via factories with an explicit period.
 */

it('allows ADMIN to record a check-in for a qualifying client', function () {
    // AC-1 (FR-001, FR-002, BR-002, BR-003, BR-011): the record is persisted
    // with attended_at (default now), recorded_by = the current staff User,
    // and appears in the attendance list.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->subDays(10)->toDateString(),
        'end_date' => today()->addDays(20)->toDateString(),
    ]);

    Livewire::actingAs($admin)
        ->test(CreateAttendance::class)
        ->fillForm(['client_id' => $client->id])
        ->call('create')
        ->assertHasNoFormErrors();

    $attendance = Attendance::firstOrFail();

    expect($attendance->client_id)->toBe($client->id);
    expect($attendance->recorded_by)->toBe($admin->id);
    expect($attendance->attended_at)->toBeInstanceOf(Carbon\Carbon::class);
    expect($attendance->attended_at->isPast())->toBeTrue();
    expect($attendance->turno_id)->toBeNull();
    expect($attendance->notes)->toBeNull();

    Livewire::actingAs($admin)
        ->test(ListAttendances::class)
        ->assertCanSeeTableRecords([$attendance]);
});

it('allows TRAINER to record a check-in', function () {
    // AC-1 (BR-009, AT-01; D-19 option 1): TRAINER receives the full
    // attendance set, including recording check-ins.
    $trainer = userWithRoles([Role::TRAINER]);
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->subDays(10)->toDateString(),
        'end_date' => today()->addDays(20)->toDateString(),
    ]);

    Livewire::actingAs($trainer)
        ->test(CreateAttendance::class)
        ->fillForm(['client_id' => $client->id])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Attendance::count())->toBe(1);
    expect(Attendance::first()->recorded_by)->toBe($trainer->id);
});

it('rejects a check-in for a client with no membership and shows the no-membership reason', function () {
    // AC-2, AC-13 (ERR-003, FR-005): rejected with the "no membership" reason
    // and no record is created.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();

    Livewire::actingAs($admin)
        ->test(CreateAttendance::class)
        ->fillForm(['client_id' => $client->id])
        ->call('create')
        ->assertHasFormErrors(['client_id'])
        ->assertSee('has no membership');

    expect(Attendance::count())->toBe(0);
});

it('rejects a check-in for a client whose memberships are all pending', function () {
    // AC-3, AC-13 (ERR-004): rejected with the "no active membership" reason.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_PENDING,
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDays(30)->toDateString(),
    ]);

    Livewire::actingAs($admin)
        ->test(CreateAttendance::class)
        ->fillForm(['client_id' => $client->id])
        ->call('create')
        ->assertHasFormErrors(['client_id'])
        ->assertSee('no active membership');

    expect(Attendance::count())->toBe(0);
});

it('rejects a check-in for a client whose memberships are all expired', function () {
    // AC-3, AC-13 (ERR-004).
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_EXPIRED,
        'start_date' => today()->subDays(60)->toDateString(),
        'end_date' => today()->subDays(30)->toDateString(),
    ]);

    Livewire::actingAs($admin)
        ->test(CreateAttendance::class)
        ->fillForm(['client_id' => $client->id])
        ->call('create')
        ->assertHasFormErrors(['client_id'])
        ->assertSee('no active membership');

    expect(Attendance::count())->toBe(0);
});

it('rejects a check-in for a client whose memberships are all cancelled', function () {
    // AC-3, AC-13 (ERR-004).
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_CANCELLED,
        'start_date' => today()->toDateString(),
        'end_date' => today()->addDays(30)->toDateString(),
    ]);

    Livewire::actingAs($admin)
        ->test(CreateAttendance::class)
        ->fillForm(['client_id' => $client->id])
        ->call('create')
        ->assertHasFormErrors(['client_id'])
        ->assertSee('no active membership');

    expect(Attendance::count())->toBe(0);
});

it('rejects a check-in for a client whose only active membership has an expired end date', function () {
    // AC-3, AC-13 (E-01 at the door; no grace period — D-05 option 1): an
    // active membership whose end date has passed does not qualify; the reason
    // is "membership expired".
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->subDays(60)->toDateString(),
        'end_date' => today()->subDay()->toDateString(),
    ]);

    Livewire::actingAs($admin)
        ->test(CreateAttendance::class)
        ->fillForm(['client_id' => $client->id])
        ->call('create')
        ->assertHasFormErrors(['client_id'])
        ->assertSee('has expired');

    expect(Attendance::count())->toBe(0);
});

it('allows a check-in for a client with several concurrent active memberships', function () {
    // AC-4 (D-06 option 2): at least one qualifying membership suffices.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->subDays(10)->toDateString(),
        'end_date' => today()->addDays(20)->toDateString(),
    ]);
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->subDays(5)->toDateString(),
        'end_date' => today()->addDays(30)->toDateString(),
    ]);

    Livewire::actingAs($admin)
        ->test(CreateAttendance::class)
        ->fillForm(['client_id' => $client->id])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Attendance::count())->toBe(1);
});

it('shows the access decision for the selected client', function () {
    // AC-13, FR-005: the check-in flow displays the access decision and, on
    // denial, the reason (display only; enforcement is the closure rule).
    $admin = userWithRoles([Role::ADMIN]);
    $qualified = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $qualified->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->subDays(10)->toDateString(),
        'end_date' => today()->addDays(20)->toDateString(),
    ]);
    $denied = Client::factory()->create();

    Livewire::actingAs($admin)
        ->test(CreateAttendance::class)
        ->fillForm(['client_id' => $qualified->id])
        ->assertSee('Qualified');

    Livewire::actingAs($admin)
        ->test(CreateAttendance::class)
        ->fillForm(['client_id' => $denied->id])
        ->assertSee('has no membership');
});

it('rejects a check-in with attended_at in the future', function () {
    // AC-5 (ERR-005, BR-007): a future access timestamp is rejected.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->subDays(10)->toDateString(),
        'end_date' => today()->addDays(20)->toDateString(),
    ]);

    Livewire::actingAs($admin)
        ->test(CreateAttendance::class)
        ->fillForm([
            'client_id' => $client->id,
            'attended_at' => now()->addHour()->format('Y-m-d H:i'),
        ])
        ->call('create')
        ->assertHasFormErrors(['attended_at' => 'before_or_equal']);

    expect(Attendance::count())->toBe(0);
});

it('accepts a backdated check-in', function () {
    // AC-5, AF-001 (AT-05): backdating is allowed; attended_at must still not
    // be in the future.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->subDays(10)->toDateString(),
        'end_date' => today()->addDays(20)->toDateString(),
    ]);

    Livewire::actingAs($admin)
        ->test(CreateAttendance::class)
        ->fillForm([
            'client_id' => $client->id,
            'attended_at' => now()->subHours(2)->format('Y-m-d H:i'),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $attendance = Attendance::firstOrFail();

    expect($attendance->attended_at->format('Y-m-d H:i'))->toBe(now()->subHours(2)->format('Y-m-d H:i'));
});

it('rejects a check-in without a client', function () {
    // ERR-001 (BR-002): an attendance record always references a client.
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreateAttendance::class)
        ->call('create')
        ->assertHasFormErrors(['client_id' => 'required']);

    expect(Attendance::count())->toBe(0);
});

it('rejects a check-in for a nonexistent client', function () {
    // ERR-002 (BR-002): the client must exist.
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreateAttendance::class)
        ->fillForm(['client_id' => 999999])
        ->call('create')
        ->assertHasFormErrors(['client_id' => 'exists']);

    expect(Attendance::count())->toBe(0);
});

it('rejects a check-in referencing a nonexistent turno', function () {
    // AC-6 (ERR-006, AT-06): the optional turno link must reference an
    // existing turno.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->subDays(10)->toDateString(),
        'end_date' => today()->addDays(20)->toDateString(),
    ]);

    Livewire::actingAs($admin)
        ->test(CreateAttendance::class)
        ->fillForm([
            'client_id' => $client->id,
            'turno_id' => 999999,
        ])
        ->call('create')
        ->assertHasFormErrors(['turno_id' => 'exists']);

    expect(Attendance::count())->toBe(0);
});

it('accepts a check-in with no turno or with an existing turno without modifying the turno', function () {
    // AC-6, AF-002 (AT-06, BR-012): no turno and an existing turno are
    // accepted; the turno record is not modified.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->subDays(10)->toDateString(),
        'end_date' => today()->addDays(20)->toDateString(),
    ]);
    $turno = Turno::factory()->create(['label' => 'Franja mañana']);
    $turnoBefore = $turno->fresh()->toArray();

    Livewire::actingAs($admin)
        ->test(CreateAttendance::class)
        ->fillForm(['client_id' => $client->id])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Attendance::firstOrFail()->turno_id)->toBeNull();

    Livewire::actingAs($admin)
        ->test(CreateAttendance::class)
        ->fillForm([
            'client_id' => $client->id,
            'turno_id' => $turno->id,
            'notes' => 'Acceso con turno',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $second = Attendance::latest('id')->firstOrFail();

    expect($second->turno_id)->toBe($turno->id);
    expect($second->notes)->toBe('Acceso con turno');
    expect($turno->fresh()->toArray())->toBe($turnoBefore);
    expect(Turno::count())->toBe(1);
});

it('records multiple check-ins for the same client on the same day as independent records', function () {
    // AC-7 (AT-03, AF-004): no uniqueness on (client, day); each check-in is
    // an independent gym-access event.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->subDays(10)->toDateString(),
        'end_date' => today()->addDays(20)->toDateString(),
    ]);

    foreach (['08:00', '18:30'] as $time) {
        Livewire::actingAs($admin)
            ->test(CreateAttendance::class)
            ->fillForm([
                'client_id' => $client->id,
                'attended_at' => now()->subDay()->format('Y-m-d').' '.$time,
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    expect(Attendance::count())->toBe(2);
    expect(Attendance::where('client_id', $client->id)->count())->toBe(2);
    expect(Attendance::where('client_id', $client->id)->get()->pluck('attended_at')->unique()->count())->toBe(2);
});

it('exposes no edit or delete path and keeps created records unchanged', function () {
    // AC-8 (BR-008, ERR-008, AT-07): no edit/delete page, action or policy
    // ability exists; a created record persists unchanged.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();
    Membership::factory()->create([
        'client_id' => $client->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->subDays(10)->toDateString(),
        'end_date' => today()->addDays(20)->toDateString(),
    ]);
    $attendance = Attendance::factory()->create([
        'client_id' => $client->id,
        'recorded_by' => $admin->id,
        'attended_at' => now()->subHour(),
        'notes' => 'Registro original',
    ]);

    expect($admin->can('update', $attendance))->toBeFalse();
    expect($admin->can('delete', $attendance))->toBeFalse();

    Livewire::actingAs($admin)
        ->test(ListAttendances::class)
        ->assertTableActionDoesNotExist('delete');

    $this->actingAs($admin)
        ->get("/admin/attendances/{$attendance->getRouteKey()}/edit")
        ->assertNotFound();

    expect($attendance->fresh()->notes)->toBe('Registro original');
    expect($attendance->fresh()->attended_at->toDateTimeString())->toBe($attendance->attended_at->toDateTimeString());
});

it('lets ADMIN view the full attendance detail', function () {
    // FR-003, FR-006 (AC-11): the detail view shows the client (name/DNI),
    // the access timestamp, the recording staff User, the optional turno link
    // and the optional notes.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create(['full_name' => 'Detail Member', 'dni' => '12345678']);
    $turno = Turno::factory()->create(['label' => 'Turno detalle']);
    $attendance = Attendance::factory()->create([
        'client_id' => $client->id,
        'recorded_by' => $admin->id,
        'attended_at' => today()->setTime(9, 30),
        'turno_id' => $turno->id,
        'notes' => 'Nota detalle',
    ]);

    Livewire::actingAs($admin)
        ->test(ViewAttendance::class, ['record' => $attendance->getRouteKey()])
        ->assertSee('Detail Member')
        ->assertSee('12345678')
        ->assertSee($admin->name)
        ->assertSee('Turno detalle')
        ->assertSee('Nota detalle');
});

it('shows recorded_by and supports filtering by client, date range, recorded_by and turno', function () {
    // AC-11, FR-002, FR-006: the list shows the recording staff User and
    // filters by client, date range on attended_at, recorded_by and turno.
    $admin = userWithRoles([Role::ADMIN]);
    $otherStaff = userWithRoles([Role::TRAINER]);
    $client = Client::factory()->create(['full_name' => 'Filter Client']);
    $otherClient = Client::factory()->create(['full_name' => 'Other Person']);
    $turno = Turno::factory()->create(['label' => 'Franja filtro']);

    $withTurno = Attendance::factory()->create([
        'client_id' => $client->id,
        'recorded_by' => $admin->id,
        'attended_at' => today()->subDay()->setTime(10, 0),
        'turno_id' => $turno->id,
    ]);
    $other = Attendance::factory()->create([
        'client_id' => $otherClient->id,
        'recorded_by' => $otherStaff->id,
        'attended_at' => today()->setTime(9, 0),
    ]);

    Livewire::actingAs($admin)
        ->test(ListAttendances::class)
        ->filterTable('client_id', $client->id)
        ->assertCanSeeTableRecords([$withTurno])
        ->assertCanNotSeeTableRecords([$other]);

    Livewire::actingAs($admin)
        ->test(ListAttendances::class)
        ->filterTable('attended_at', ['attended_from' => today()->toDateString()])
        ->assertCanSeeTableRecords([$other])
        ->assertCanNotSeeTableRecords([$withTurno]);

    Livewire::actingAs($admin)
        ->test(ListAttendances::class)
        ->filterTable('recorded_by', $admin->id)
        ->assertCanSeeTableRecords([$withTurno])
        ->assertCanNotSeeTableRecords([$other]);

    Livewire::actingAs($admin)
        ->test(ListAttendances::class)
        ->filterTable('turno_id', $turno->id)
        ->assertCanSeeTableRecords([$withTurno])
        ->assertCanNotSeeTableRecords([$other]);

    // FR-006: recorded_by is visible in the list (the other staff user's name
    // appears only in their record's recorded-by column, not in the account
    // widget of the acting ADMIN).
    Livewire::actingAs($admin)
        ->test(ListAttendances::class)
        ->assertSee($otherStaff->name);
});

it('lists a client attendance history in chronological order', function () {
    // AC-11, FR-004: the list is ordered by attended_at ascending; the client
    // filter isolates one client's history.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();

    $oldest = Attendance::factory()->create([
        'client_id' => $client->id,
        'recorded_by' => $admin->id,
        'attended_at' => today()->subDays(2)->setTime(9, 0),
    ]);
    $middle = Attendance::factory()->create([
        'client_id' => $client->id,
        'recorded_by' => $admin->id,
        'attended_at' => today()->subDay()->setTime(9, 0),
    ]);
    $newest = Attendance::factory()->create([
        'client_id' => $client->id,
        'recorded_by' => $admin->id,
        'attended_at' => today()->setTime(9, 0),
    ]);
    $other = Attendance::factory()->create([
        'recorded_by' => $admin->id,
        'attended_at' => today()->setTime(12, 0),
    ]);

    Livewire::actingAs($admin)
        ->test(ListAttendances::class)
        ->filterTable('client_id', $client->id)
        ->assertCanSeeTableRecords([$oldest, $middle, $newest], inOrder: true)
        ->assertCanNotSeeTableRecords([$other]);
});

it('lets staff find a client by DNI in the check-in select and the client filter', function () {
    // FR-001, FR-002: staff search and select clients by name OR DNI. Both the
    // create-form client select and the list client SelectFilter search the
    // clients table on full_name AND dni; typing a DNI must yield the client
    // as a selectable option.
    $admin = userWithRoles([Role::ADMIN]);
    $byDni = Client::factory()->create(['full_name' => 'DNI Search Member', 'dni' => '40123456']);
    Client::factory()->create(['full_name' => 'Another Member', 'dni' => '99887766']);

    $create = Livewire::actingAs($admin)->test(CreateAttendance::class);
    $clientOptions = $create->instance()->getFormSelectSearchResults('data.client_id', $byDni->dni);

    expect(collect($clientOptions)->pluck('value'))->toContain((string) $byDni->id)
        ->and(collect($clientOptions)->pluck('label'))->toContain($byDni->full_name);

    $list = Livewire::actingAs($admin)->test(ListAttendances::class);
    $filterOptions = $list->instance()->getFormSelectSearchResults('tableFilters.client_id.value', $byDni->dni);

    expect(collect($filterOptions)->pluck('value'))->toContain((string) $byDni->id)
        ->and(collect($filterOptions)->pluck('label'))->toContain($byDni->full_name);
});

it('does not create or modify any other record when recording a check-in', function () {
    // AC-12 (BR-012): recording a check-in touches only the attendances table;
    // no client, membership, turno, plan or user record is created or
    // modified.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();
    $plan = Plan::factory()->create();
    $turno = Turno::factory()->create();
    $membership = Membership::factory()->create([
        'client_id' => $client->id,
        'plan_id' => $plan->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => today()->subDays(10)->toDateString(),
        'end_date' => today()->addDays(20)->toDateString(),
    ]);

    $clientBefore = $client->fresh()->toArray();
    $planBefore = $plan->fresh()->toArray();
    $turnoBefore = $turno->fresh()->toArray();
    $membershipBefore = $membership->fresh()->toArray();

    Livewire::actingAs($admin)
        ->test(CreateAttendance::class)
        ->fillForm([
            'client_id' => $client->id,
            'turno_id' => $turno->id,
            'notes' => 'Registro de acceso',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Attendance::count())->toBe(1);
    expect(Client::count())->toBe(1);
    expect(Membership::count())->toBe(1);
    expect(Turno::count())->toBe(1);
    expect(Plan::count())->toBe(1);
    expect(User::count())->toBe(1); // only the acting admin
    expect(DB::table('role_user')->count())->toBe(1); // only the admin's role

    expect($client->fresh()->toArray())->toBe($clientBefore);
    expect($plan->fresh()->toArray())->toBe($planBefore);
    expect($turno->fresh()->toArray())->toBe($turnoBefore);
    expect($membership->fresh()->toArray())->toBe($membershipBefore);
});

it('introduces no booking_id column and no status column', function () {
    // AC-14 (AT-04, BR-001): the attendances schema has no booking_id column
    // (deferred to SPEC-007) and no status column (an event, not a stateful
    // entity).
    expect(Schema::hasColumn('attendances', 'booking_id'))->toBeFalse();
    expect(Schema::hasColumn('attendances', 'status'))->toBeFalse();
});
