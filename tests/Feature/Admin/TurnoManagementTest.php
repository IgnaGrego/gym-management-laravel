<?php

use App\Filament\Resources\TurnoResource\Pages\CreateTurno;
use App\Filament\Resources\TurnoResource\Pages\EditTurno;
use App\Filament\Resources\TurnoResource\Pages\ListTurnos;
use App\Filament\Resources\TurnoResource\Pages\ViewTurno;
use App\Models\Role;
use App\Models\Turno;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
 * Turno CRUD and lifecycle feature tests (SPEC-006 FR-001..FR-008; BR-002,
 * BR-003, BR-004, BR-005, BR-006, BR-007, BR-008, BR-009; ERR-001..ERR-007;
 * AC-1..AC-14). Authorization is enforced server-side (AGENTS.md §17).
 */

it('allows ADMIN to create a turno persisted as active with an optional label', function () {
    // AC-1 (FR-001, FR-002, BR-002).
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreateTurno::class)
        ->fillForm([
            'date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'capacity_limit' => '10',
            'label' => 'Franja mañana',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $turno = Turno::firstOrFail();

    expect($turno->date->toDateString())->toBe(now()->addDay()->toDateString());
    expect($turno->start_time)->toBe('08:00');
    expect($turno->end_time)->toBe('10:00');
    expect($turno->capacity_limit)->toBe(10);
    expect($turno->label)->toBe('Franja mañana');
    expect($turno->status)->toBe(Turno::STATUS_ACTIVE);
});

it('allows TRAINER to create a turno', function () {
    // AC-1 (BR-012, AS-01): TRAINER receives the full management set.
    $trainer = userWithRoles([Role::TRAINER]);

    Livewire::actingAs($trainer)
        ->test(CreateTurno::class)
        ->fillForm([
            'date' => now()->addDay()->toDateString(),
            'start_time' => '18:00',
            'end_time' => '20:00',
            'capacity_limit' => '15',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Turno::count())->toBe(1);
    expect(Turno::first()->status)->toBe(Turno::STATUS_ACTIVE);
});

it('does not create or modify any other record when creating a turno', function () {
    // AC-14 (D-07, BR-013): creating a turno touches only the turnos table; no
    // booking, attendance or membership record is created and no
    // user/client/plan/membership record is created or modified.
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreateTurno::class)
        ->fillForm([
            'date' => now()->addDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'capacity_limit' => '10',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Turno::count())->toBe(1);
    expect(DB::table('users')->count())->toBe(1); // only the acting admin
    expect(DB::table('role_user')->count())->toBe(1); // only the admin's role
    expect(DB::table('clients')->count())->toBe(0);
    expect(DB::table('plans')->count())->toBe(0);
    expect(DB::table('memberships')->count())->toBe(0);
});

it('rejects creating a turno with an end time not strictly after the start time', function () {
    // AC-2 (ERR-002, BR-005): end <= start is rejected.
    $admin = userWithRoles([Role::ADMIN]);

    foreach ([['10:00', '10:00'], ['10:00', '08:00']] as [$start, $end]) {
        Livewire::actingAs($admin)
            ->test(CreateTurno::class)
            ->fillForm([
                'date' => now()->addDay()->toDateString(),
                'start_time' => $start,
                'end_time' => $end,
                'capacity_limit' => '10',
            ])
            ->call('create')
            ->assertHasFormErrors(['end_time' => 'after']);
    }

    expect(Turno::count())->toBe(0);
});

it('rejects a cross-midnight interval', function () {
    // ERR-007 (BR-005, AS-03): end time would fall on a different date than
    // the start time (23:00-01:00 is rejected by after:start_time).
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreateTurno::class)
        ->fillForm([
            'date' => now()->addDay()->toDateString(),
            'start_time' => '23:00',
            'end_time' => '01:00',
            'capacity_limit' => '10',
        ])
        ->call('create')
        ->assertHasFormErrors(['end_time' => 'after']);

    expect(Turno::count())->toBe(0);
});

it('rejects creating or editing a turno with a past date', function () {
    // AC-3 (ERR-003, BR-006): the date must be today or future on create and
    // edit.
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreateTurno::class)
        ->fillForm([
            'date' => now()->subDay()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'capacity_limit' => '10',
        ])
        ->call('create')
        ->assertHasFormErrors(['date' => 'after_or_equal']);

    $turno = Turno::factory()->create();

    Livewire::actingAs($admin)
        ->test(EditTurno::class, ['record' => $turno->getRouteKey()])
        ->fillForm(['date' => now()->subDay()->toDateString()])
        ->call('save')
        ->assertHasFormErrors(['date' => 'after_or_equal']);

    expect(Turno::count())->toBe(1);
    expect($turno->fresh()->date->toDateString())->toBe(today()->toDateString());
});

it('rejects a missing, non-integer or less-than-1 capacity limit', function () {
    // AC-4 (ERR-004, BR-007): the capacity limit is required and a positive
    // integer.
    $admin = userWithRoles([Role::ADMIN]);
    $date = now()->addDay()->toDateString();

    Livewire::actingAs($admin)
        ->test(CreateTurno::class)
        ->fillForm([
            'date' => $date,
            'start_time' => '08:00',
            'end_time' => '10:00',
        ])
        ->call('create')
        ->assertHasFormErrors(['capacity_limit' => 'required']);

    foreach (['0', '-5'] as $capacity) {
        Livewire::actingAs($admin)
            ->test(CreateTurno::class)
            ->fillForm([
                'date' => $date,
                'start_time' => '08:00',
                'end_time' => '10:00',
                'capacity_limit' => $capacity,
            ])
            ->call('create')
            ->assertHasFormErrors(['capacity_limit' => 'min']);
    }

    Livewire::actingAs($admin)
        ->test(CreateTurno::class)
        ->fillForm([
            'date' => $date,
            'start_time' => '08:00',
            'end_time' => '10:00',
            'capacity_limit' => '10.5',
        ])
        ->call('create')
        ->assertHasFormErrors(['capacity_limit' => 'integer']);

    expect(Turno::count())->toBe(0);
});

it('rejects creating a turno without the required fields', function () {
    // ERR-001 (FR-001): date, start time, end time and capacity are required.
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreateTurno::class)
        ->call('create')
        ->assertHasFormErrors([
            'date' => 'required',
            'start_time' => 'required',
            'end_time' => 'required',
            'capacity_limit' => 'required',
        ]);

    expect(Turno::count())->toBe(0);
});

it('lets ADMIN list turnos and filter them by status and date range', function () {
    // AC-5 (FR-002).
    $admin = userWithRoles([Role::ADMIN]);

    $active = Turno::factory()->create([
        'date' => now()->addDay()->toDateString(),
        'status' => Turno::STATUS_ACTIVE,
        'label' => 'ActiveSlot',
    ]);
    $inactive = Turno::factory()->create([
        'date' => now()->addDay()->toDateString(),
        'status' => Turno::STATUS_INACTIVE,
        'label' => 'InactiveSlot',
    ]);
    $cancelled = Turno::factory()->create([
        'date' => now()->addDays(2)->toDateString(),
        'status' => Turno::STATUS_CANCELLED,
        'label' => 'CancelledSlot',
    ]);

    Livewire::actingAs($admin)
        ->test(ListTurnos::class)
        ->filterTable('status', Turno::STATUS_ACTIVE)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$inactive, $cancelled]);

    Livewire::actingAs($admin)
        ->test(ListTurnos::class)
        ->filterTable('date', ['date_from' => now()->addDays(2)->toDateString()])
        ->assertCanSeeTableRecords([$cancelled])
        ->assertCanNotSeeTableRecords([$active, $inactive]);

    Livewire::actingAs($admin)
        ->test(ListTurnos::class)
        ->filterTable('date', ['date_until' => now()->addDay()->toDateString()])
        ->assertCanSeeTableRecords([$active, $inactive])
        ->assertCanNotSeeTableRecords([$cancelled]);
});

it('lets ADMIN view the full turno detail including status', function () {
    // AC-6 (FR-003, FR-008).
    $admin = userWithRoles([Role::ADMIN]);
    $date = now()->addDay()->toDateString();
    $turno = Turno::factory()->create([
        'date' => $date,
        'start_time' => '08:00',
        'end_time' => '10:00',
        'capacity_limit' => 10,
        'status' => Turno::STATUS_ACTIVE,
        'label' => 'Horario pico',
    ]);

    Livewire::actingAs($admin)
        ->test(ViewTurno::class, ['record' => $turno->getRouteKey()])
        ->assertSee($date)
        ->assertSee('08:00')
        ->assertSee('10:00')
        ->assertSee('Horario pico')
        ->assertSee('Activo');
});

it('lets ADMIN edit an active turno and persist the changes', function () {
    // AC-7 (FR-004, AF-003).
    $admin = userWithRoles([Role::ADMIN]);
    $turno = Turno::factory()->create();

    Livewire::actingAs($admin)
        ->test(EditTurno::class, ['record' => $turno->getRouteKey()])
        ->fillForm([
            'date' => now()->addDays(3)->toDateString(),
            'start_time' => '18:00',
            'end_time' => '20:00',
            'capacity_limit' => '25',
            'label' => 'Edited label',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $turno->refresh();

    expect($turno->date->toDateString())->toBe(now()->addDays(3)->toDateString());
    expect($turno->start_time)->toBe('18:00');
    expect($turno->end_time)->toBe('20:00');
    expect($turno->capacity_limit)->toBe(25);
    expect($turno->label)->toBe('Edited label');
    expect($turno->status)->toBe(Turno::STATUS_ACTIVE);
});

it('lets ADMIN edit an inactive turno and persist the changes', function () {
    // AC-7 (FR-004): editing is allowed while the turno is active or inactive.
    $admin = userWithRoles([Role::ADMIN]);
    $turno = Turno::factory()->create(['status' => Turno::STATUS_INACTIVE]);

    Livewire::actingAs($admin)
        ->test(EditTurno::class, ['record' => $turno->getRouteKey()])
        ->fillForm(['capacity_limit' => '30'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($turno->fresh()->capacity_limit)->toBe(30);
    expect($turno->fresh()->status)->toBe(Turno::STATUS_INACTIVE);
});

it('blocks editing a cancelled turno server-side with 403', function () {
    // AC-10 (ERR-006, BR-004): a cancelled turno cannot be edited; the edit
    // page is inaccessible server-side via TurnoResource::canEdit.
    $admin = userWithRoles([Role::ADMIN]);
    $cancelled = Turno::factory()->create(['status' => Turno::STATUS_CANCELLED]);

    $this->actingAs($admin)
        ->get("/admin/turnos/{$cancelled->getRouteKey()}/edit")
        ->assertForbidden();

    expect($cancelled->fresh()->status)->toBe(Turno::STATUS_CANCELLED);
});

it('lets ADMIN deactivate an active turno via the list action; it becomes inactive and displayed as such', function () {
    // AC-8 (FR-005, FR-008, BR-003).
    $admin = userWithRoles([Role::ADMIN]);
    $turno = Turno::factory()->create();

    Livewire::actingAs($admin)
        ->test(ListTurnos::class)
        ->callTableAction('deactivate', $turno);

    expect($turno->fresh()->status)->toBe(Turno::STATUS_INACTIVE);
    expect(Turno::find($turno->id))->not->toBeNull();
});

it('lets ADMIN reactivate an inactive turno via the list action', function () {
    // AC-9 (FR-006, AF-001, BR-003).
    $admin = userWithRoles([Role::ADMIN]);
    $turno = Turno::factory()->create(['status' => Turno::STATUS_INACTIVE]);

    Livewire::actingAs($admin)
        ->test(ListTurnos::class)
        ->callTableAction('reactivate', $turno);

    expect($turno->fresh()->status)->toBe(Turno::STATUS_ACTIVE);
});

it('lets ADMIN cancel an active or inactive turno; cancelled is terminal', function () {
    // AC-10 (FR-007, BR-003, BR-004): active/inactive -> cancelled; a
    // cancelled turno cannot be reactivated or cancelled again (ERR-006).
    $admin = userWithRoles([Role::ADMIN]);
    $active = Turno::factory()->create();
    $inactive = Turno::factory()->create(['status' => Turno::STATUS_INACTIVE]);

    Livewire::actingAs($admin)
        ->test(ListTurnos::class)
        ->callTableAction('cancel', $active)
        ->callTableAction('cancel', $inactive);

    expect($active->fresh()->status)->toBe(Turno::STATUS_CANCELLED);
    expect($inactive->fresh()->status)->toBe(Turno::STATUS_CANCELLED);

    expect(fn () => $active->fresh()->reactivate())->toThrow(DomainException::class);
    expect(fn () => $active->fresh()->cancel())->toThrow(DomainException::class);
    expect(fn () => $inactive->fresh()->cancel())->toThrow(DomainException::class);
});

it('shows the lifecycle actions according to the turno status', function () {
    // FR-005/FR-006/FR-007 visibility; the rules are still enforced
    // server-side (AGENTS.md §17).
    $admin = userWithRoles([Role::ADMIN]);
    $active = Turno::factory()->create();
    $inactive = Turno::factory()->create(['status' => Turno::STATUS_INACTIVE]);
    $cancelled = Turno::factory()->create(['status' => Turno::STATUS_CANCELLED]);

    Livewire::actingAs($admin)
        ->test(ListTurnos::class)
        ->assertTableActionVisible('deactivate', $active)
        ->assertTableActionHidden('deactivate', $inactive)
        ->assertTableActionHidden('deactivate', $cancelled)
        ->assertTableActionVisible('reactivate', $inactive)
        ->assertTableActionHidden('reactivate', $active)
        ->assertTableActionHidden('reactivate', $cancelled)
        ->assertTableActionVisible('cancel', $active)
        ->assertTableActionVisible('cancel', $inactive)
        ->assertTableActionHidden('cancel', $cancelled)
        ->assertTableActionHidden('edit', $cancelled);
});

it('lets ADMIN deactivate, reactivate and cancel from the detail view header actions', function () {
    // FR-005/FR-006/FR-007 paths on the detail page (ViewTurno header).
    $admin = userWithRoles([Role::ADMIN]);
    $turno = Turno::factory()->create();

    Livewire::actingAs($admin)
        ->test(ViewTurno::class, ['record' => $turno->getRouteKey()])
        ->callAction('deactivate');

    expect($turno->fresh()->status)->toBe(Turno::STATUS_INACTIVE);

    Livewire::actingAs($admin)
        ->test(ViewTurno::class, ['record' => $turno->getRouteKey()])
        ->callAction('reactivate');

    expect($turno->fresh()->status)->toBe(Turno::STATUS_ACTIVE);

    Livewire::actingAs($admin)
        ->test(ViewTurno::class, ['record' => $turno->getRouteKey()])
        ->callAction('cancel');

    expect($turno->fresh()->status)->toBe(Turno::STATUS_CANCELLED);
});

it('allows overlapping and identical turnos to coexist on the same date', function () {
    // AC-12 (BR-008, AF-005): no overlap or uniqueness constraint is imposed.
    $overlapA = Turno::factory()->create([
        'date' => now()->addDay()->toDateString(),
        'start_time' => '08:00',
        'end_time' => '12:00',
    ]);
    $overlapB = Turno::factory()->create([
        'date' => now()->addDay()->toDateString(),
        'start_time' => '10:00',
        'end_time' => '14:00',
    ]);
    $identical = Turno::factory()->create([
        'date' => now()->addDay()->toDateString(),
        'start_time' => '08:00',
        'end_time' => '12:00',
    ]);

    expect(Turno::count())->toBe(3);
    expect($overlapA->is($overlapB))->toBeFalse();
    expect($overlapA->is($identical))->toBeFalse();
});

it('does not expose a delete operation and preserves turnos after their date passes', function () {
    // AC-13 (BR-009, AF-004): no delete operation exists; a created turno
    // record persists even after its date passes and past turnos can still be
    // deactivated/reactivated/cancelled (BR-003, AS-04).
    $admin = userWithRoles([Role::ADMIN]);
    $pastActive = Turno::factory()->create(['date' => now()->subDays(5)->toDateString()]);
    $pastInactive = Turno::factory()->create([
        'date' => now()->subDays(5)->toDateString(),
        'status' => Turno::STATUS_INACTIVE,
    ]);

    expect($admin->can('delete', $pastActive))->toBeFalse();

    Livewire::actingAs($admin)
        ->test(ListTurnos::class)
        ->assertTableActionDoesNotExist('delete');

    $this->actingAs($admin)->get('/admin/turnos')->assertOk();

    // The records persist after their date has passed.
    expect(Turno::find($pastActive->id))->not->toBeNull();
    expect(Turno::find($pastInactive->id))->not->toBeNull();

    // Past turnos can still be lifecycle-transitioned (date-independent).
    $pastActive->deactivate();
    expect($pastActive->fresh()->status)->toBe(Turno::STATUS_INACTIVE);

    Livewire::actingAs($admin)
        ->test(ListTurnos::class)
        ->callTableAction('reactivate', $pastInactive)
        ->callTableAction('cancel', $pastActive);

    expect($pastInactive->fresh()->status)->toBe(Turno::STATUS_ACTIVE);
    expect($pastActive->fresh()->status)->toBe(Turno::STATUS_CANCELLED);
});
