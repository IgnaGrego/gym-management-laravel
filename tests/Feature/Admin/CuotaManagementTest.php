<?php

use App\Filament\Resources\CuotaResource\Pages\ListCuotas;
use App\Filament\Resources\CuotaResource\Pages\ViewCuota;
use App\Filament\Resources\CuotaResource\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\MembershipResource\Pages\CreateMembership;
use App\Filament\Resources\MembershipResource\Pages\ListMemberships;
use App\Models\Client;
use App\Models\Cuota;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Role;
use Livewire\Livewire;

/*
 * Cuota management feature tests (SPEC-005 FR-001, FR-002, FR-003; BR-001,
 * BR-002, BR-012, BR-014; NC-02, NC-03; ERR-006, ERR-007; AC-1..AC-4, AC-14,
 * AC-17, AC-18). Authorization is enforced server-side.
 */

it('auto-generates one pending cuota with the plan price when a membership is created', function () {
    // AC-1, AC-18 (FR-001, BR-001, BR-002, BR-003; NC-03): one cuota, amount =
    // plan price (NOT price + enrollment_fee), no Payment created.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();
    $plan = Plan::factory()->create(['price' => '12000.00', 'enrollment_fee' => '3000.00']);

    Livewire::actingAs($admin)
        ->test(CreateMembership::class)
        ->fillForm([
            'client_id' => $client->id,
            'plan_id' => $plan->id,
            'start_date' => '2026-08-15',
            'duration_days' => '30',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $membership = Membership::where('client_id', $client->id)->firstOrFail();

    expect(Cuota::count())->toBe(1);
    expect(Payment::count())->toBe(0);

    $cuota = $membership->cuota;

    expect($cuota)->not->toBeNull();
    expect($cuota->amount)->toBe('12000.00');
    expect($cuota->status)->toBe(Cuota::STATUS_PENDING);
});

it('auto-generates a new cuota when a membership is renewed', function () {
    // AC-1, AC-17 (FR-001, BR-001, NC-02): renewal creates a new membership
    // and a new cuota for it; the original cuota is unchanged.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();
    $plan = Plan::factory()->create(['price' => '9000.00']);
    $membership = Membership::factory()->create([
        'client_id' => $client->id,
        'plan_id' => $plan->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => '2026-01-01',
        'duration_days' => 30,
    ]);

    Livewire::actingAs($admin)
        ->test(ListMemberships::class)
        ->callTableAction('renew', $membership);

    $renewal = Membership::where('id', '!=', $membership->id)->firstOrFail();

    expect(Cuota::count())->toBe(2);
    expect($renewal->cuota)->not->toBeNull();
    expect($renewal->cuota->amount)->toBe('9000.00');
    expect($renewal->cuota->status)->toBe(Cuota::STATUS_PENDING);
    expect($membership->fresh()->cuota->status)->toBe(Cuota::STATUS_PENDING);
});

it('lets ADMIN edit the amount of a pending cuota via the row action', function () {
    // AC-2 (FR-002, BR-012).
    $admin = userWithRoles([Role::ADMIN]);
    $cuota = Membership::factory()->create()->cuota;

    Livewire::actingAs($admin)
        ->test(ListCuotas::class)
        ->callTableAction('editAmount', $cuota, ['amount' => '99.99']);

    expect($cuota->fresh()->amount)->toBe('99.99');
});

it('lets TRAINER edit the amount of a pending cuota', function () {
    // PY-01 (D-02 option 2): "staff may edit" = ADMIN + TRAINER.
    $trainer = userWithRoles([Role::TRAINER]);
    $cuota = Membership::factory()->create()->cuota;

    Livewire::actingAs($trainer)
        ->test(ListCuotas::class)
        ->callTableAction('editAmount', $cuota, ['amount' => '50.00']);

    expect($cuota->fresh()->amount)->toBe('50.00');
});

it('rejects a zero or negative cuota amount edit', function () {
    // AC-3 (ERR-006, BR-002): the amount must be positive.
    $admin = userWithRoles([Role::ADMIN]);
    $cuota = Membership::factory()->create()->cuota;
    $original = $cuota->amount;

    foreach (['0', '-5.00'] as $amount) {
        Livewire::actingAs($admin)
            ->test(ListCuotas::class)
            ->callTableAction('editAmount', $cuota, ['amount' => $amount])
            ->assertHasTableActionErrors(['amount' => 'min']);
    }

    expect($cuota->fresh()->amount)->toBe($original);
});

it('rejects editing the amount of a paid or cancelled cuota', function () {
    // AC-4 (ERR-007, BR-003, BR-012): only pending cuotas are editable.
    $admin = userWithRoles([Role::ADMIN]);
    $paid = Membership::factory()->create()->cuota;
    $paid->markPaid();
    $cancelled = Membership::factory()->create()->cuota;
    $cancelled->cancel();

    expect(fn () => $paid->updateAmount('1.00'))->toThrow(DomainException::class);
    expect(fn () => $cancelled->updateAmount('1.00'))->toThrow(DomainException::class);

    Livewire::actingAs($admin)
        ->test(ListCuotas::class)
        ->assertTableActionHidden('editAmount', $paid)
        ->assertTableActionHidden('editAmount', $cancelled);
});

it('does not change an existing cuota amount when the plan price changes or is deactivated', function () {
    // AC-14 (BR-010, SPEC-004 BR-013): generation-time amount is immutable.
    $plan = Plan::factory()->create(['price' => '12000.00']);
    $membership = Membership::factory()->create(['plan_id' => $plan->id]);
    $cuota = $membership->cuota;

    $plan->update(['price' => '1.00', 'is_active' => false]);

    expect($cuota->fresh()->amount)->toBe('12000.00');
});

it('shows the cuota status in the list and detail view', function () {
    // FR-003, FR-007.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create(['full_name' => 'Cuota Client']);
    $plan = Plan::factory()->create(['name' => 'Cuota Plan']);
    $membership = Membership::factory()->create([
        'client_id' => $client->id,
        'plan_id' => $plan->id,
    ]);
    $cuota = $membership->cuota;

    Livewire::actingAs($admin)
        ->test(ListCuotas::class)
        ->assertCanSeeTableRecords([$cuota]);

    Livewire::actingAs($admin)
        ->test(ViewCuota::class, ['record' => $cuota->getRouteKey()])
        ->assertSee('Cuota Client')
        ->assertSee('Cuota Plan')
        ->assertSee('Pendiente');
});

it('lists a cuota payment history in the relation manager', function () {
    // FR-003 "payment history".
    $admin = userWithRoles([Role::ADMIN]);
    $membership = Membership::factory()->create();
    $cuota = $membership->cuota;
    $payment = Payment::factory()->create([
        'cuota_id' => $cuota->id,
        'recorded_by' => $admin->id,
        'amount' => $cuota->amount,
    ]);
    $otherPayment = Payment::factory()->create();

    Livewire::actingAs($admin)
        ->test(PaymentsRelationManager::class, [
            'ownerRecord' => $cuota,
            'pageClass' => ViewCuota::class,
        ])
        ->assertCanSeeTableRecords([$payment])
        ->assertCanNotSeeTableRecords([$otherPayment]);
});
