<?php

use App\Actions\RegisterPayment;
use App\Filament\Resources\PaymentResource\Pages\CreatePayment;
use App\Filament\Resources\PaymentResource\Pages\ListPayments;
use App\Filament\Resources\PaymentResource\Pages\ViewPayment;
use App\Models\Client;
use App\Models\Cuota;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Role;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/*
 * Payment management feature tests (SPEC-005 FR-004, FR-005, FR-006; BR-004,
 * BR-005, BR-007, BR-014; ERR-001..ERR-005, ERR-010, ERR-011; AC-5, AC-6,
 * AC-7, AC-10, AC-11, AC-16, AC-19). Authorization is enforced server-side.
 */

it('registers a cash payment against a pending cuota and marks it paid', function () {
    // AC-5 (FR-004, FR-006, BR-005, BR-007, BR-014): the amount must equal the
    // cuota amount; the payment is persisted confirmed with recorded_by and the
    // cuota becomes paid.
    $admin = userWithRoles([Role::ADMIN]);
    $cuota = Membership::factory()->create(['status' => Membership::STATUS_ACTIVE])->cuota;

    Livewire::actingAs($admin)
        ->test(CreatePayment::class)
        ->fillForm([
            'cuota_id' => $cuota->id,
            'amount' => $cuota->amount,
            'method' => Payment::METHOD_CASH,
            'payment_date' => now()->toDateString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $payment = Payment::firstOrFail();

    expect($payment->cuota_id)->toBe($cuota->id);
    expect($payment->amount)->toBe($cuota->amount);
    expect($payment->method)->toBe(Payment::METHOD_CASH);
    expect($payment->payment_date->toDateString())->toBe(now()->toDateString());
    expect($payment->status)->toBe(Payment::STATUS_CONFIRMED);
    expect($payment->recorded_by)->toBe($admin->id);

    expect($cuota->fresh()->status)->toBe(Cuota::STATUS_PAID);
    expect($cuota->fresh()->paid_at)->not->toBeNull();
});

it('registers a bank transfer payment with a required reference', function () {
    // AC-6 (FR-004, ERR-005, PY-04).
    $admin = userWithRoles([Role::ADMIN]);
    $cuota = Membership::factory()->create(['status' => Membership::STATUS_ACTIVE])->cuota;

    Livewire::actingAs($admin)
        ->test(CreatePayment::class)
        ->fillForm([
            'cuota_id' => $cuota->id,
            'amount' => $cuota->amount,
            'method' => Payment::METHOD_TRANSFER,
            'reference' => 'TRF-123',
            'payment_date' => now()->toDateString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $payment = Payment::firstOrFail();

    expect($payment->method)->toBe(Payment::METHOD_TRANSFER);
    expect($payment->reference)->toBe('TRF-123');
    expect($payment->status)->toBe(Payment::STATUS_CONFIRMED);
});

it('rejects invalid payment input', function () {
    // AC-7 (ERR-001, ERR-002, ERR-003, ERR-004, ERR-005).
    $admin = userWithRoles([Role::ADMIN]);
    $cuota = Membership::factory()->create(['status' => Membership::STATUS_ACTIVE])->cuota;

    Livewire::actingAs($admin)
        ->test(CreatePayment::class)
        ->fillForm([
            'cuota_id' => 999999,
            'amount' => $cuota->amount,
            'method' => Payment::METHOD_CASH,
            'payment_date' => now()->toDateString(),
        ])
        ->call('create')
        ->assertHasFormErrors(['cuota_id' => 'exists']);

    foreach (['0', '-5.00'] as $amount) {
        Livewire::actingAs($admin)
            ->test(CreatePayment::class)
            ->fillForm([
                'cuota_id' => $cuota->id,
                'amount' => $amount,
                'method' => Payment::METHOD_CASH,
                'payment_date' => now()->toDateString(),
            ])
            ->call('create')
            ->assertHasFormErrors(['amount' => 'min']);
    }

    Livewire::actingAs($admin)
        ->test(CreatePayment::class)
        ->fillForm([
            'cuota_id' => $cuota->id,
            'amount' => $cuota->amount,
            'method' => 'mercadopago',
            'payment_date' => now()->toDateString(),
        ])
        ->call('create')
        ->assertHasFormErrors(['method' => 'in']);

    Livewire::actingAs($admin)
        ->test(CreatePayment::class)
        ->fillForm([
            'cuota_id' => $cuota->id,
            'amount' => $cuota->amount,
            'method' => Payment::METHOD_CASH,
            'payment_date' => now()->addDay()->toDateString(),
        ])
        ->call('create')
        ->assertHasFormErrors(['payment_date' => 'before_or_equal']);

    Livewire::actingAs($admin)
        ->test(CreatePayment::class)
        ->fillForm([
            'cuota_id' => $cuota->id,
            'amount' => $cuota->amount,
            'method' => Payment::METHOD_TRANSFER,
            'payment_date' => now()->toDateString(),
        ])
        ->call('create')
        ->assertHasFormErrors(['reference' => 'required_if']);

    expect(Payment::count())->toBe(0);
});

it('rejects a payment amount that does not equal the cuota amount', function () {
    // AC-16 (ERR-010, BR-014, NC-01): full payment only.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $cuota = Membership::factory()->create(['status' => Membership::STATUS_ACTIVE])->cuota;

    expect(fn () => app(RegisterPayment::class)->handle(
        $cuota->id,
        number_format((float) $cuota->amount + 1, 2, '.', ''),
        Payment::METHOD_CASH,
        now()->toDateString(),
    ))->toThrow(ValidationException::class);

    expect(Payment::count())->toBe(0);
    expect($cuota->fresh()->status)->toBe(Cuota::STATUS_PENDING);
});

it('rejects a payment against a non-payable cuota', function () {
    // AC-19 (ERR-011, BR-003, BR-014, BR-015, NC-04).
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);

    $paid = Membership::factory()->create()->cuota;
    $paid->markPaid();

    $cancelled = Membership::factory()->create()->cuota;
    $cancelled->cancel();

    expect(fn () => app(RegisterPayment::class)->handle(
        $paid->id,
        $paid->amount,
        Payment::METHOD_CASH,
        now()->toDateString(),
    ))->toThrow(ValidationException::class);

    expect(fn () => app(RegisterPayment::class)->handle(
        $cancelled->id,
        $cancelled->amount,
        Payment::METHOD_CASH,
        now()->toDateString(),
    ))->toThrow(ValidationException::class);

    expect(Payment::count())->toBe(0);
});

it('rejects a payment against a nonexistent cuota via the action', function () {
    // ERR-001.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);

    expect(fn () => app(RegisterPayment::class)->handle(
        999999,
        '10.00',
        Payment::METHOD_CASH,
        now()->toDateString(),
    ))->toThrow(ValidationException::class);

    expect(Payment::count())->toBe(0);
});

it('shows the payment detail and does not expose edit or delete', function () {
    // FR-005, AC-11 (BR-006, BR-009).
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create(['full_name' => 'Payment Client']);
    $plan = Plan::factory()->create(['name' => 'Payment Plan']);
    $membership = Membership::factory()->create([
        'client_id' => $client->id,
        'plan_id' => $plan->id,
    ]);
    $payment = Payment::factory()->create([
        'cuota_id' => $membership->cuota->id,
        'recorded_by' => $admin->id,
        'method' => Payment::METHOD_TRANSFER,
        'reference' => 'REF-1',
    ]);

    Livewire::actingAs($admin)
        ->test(ViewPayment::class, ['record' => $payment->getRouteKey()])
        ->assertSee('Payment Client')
        ->assertSee('Payment Plan')
        ->assertSee('REF-1')
        ->assertSee('Confirmed');

    Livewire::actingAs($admin)
        ->test(ListPayments::class)
        ->assertCanSeeTableRecords([$payment])
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete');
});
