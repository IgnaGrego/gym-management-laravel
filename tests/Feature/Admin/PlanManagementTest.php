<?php

use App\Filament\Resources\PlanResource\Pages\CreatePlan;
use App\Filament\Resources\PlanResource\Pages\EditPlan;
use App\Filament\Resources\PlanResource\Pages\ListPlans;
use App\Filament\Resources\PlanResource\Pages\ViewPlan;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
 * Plan CRUD and lifecycle feature tests (SPEC-003 FR-001..FR-006; BR-002,
 * BR-003, BR-004, BR-005, BR-007; ERR-001, ERR-002, ERR-003; AC-1..AC-8,
 * AC-10, AC-11). Authorization is enforced server-side.
 */

it('allows ADMIN to create a plan with required and optional fields', function () {
    // AC-1 (FR-001): name + price are required; description and enrollment
    // fee are optional and persisted; the record is stored active (AP-02).
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreatePlan::class)
        ->fillForm([
            'name' => 'Mensual',
            'description' => 'Mensualidad basica con acceso libre.',
            'price' => '12000.00',
            'enrollment_fee' => '3000.00',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $plan = Plan::where('name', 'Mensual')->firstOrFail();

    expect($plan->description)->toBe('Mensualidad basica con acceso libre.');
    expect($plan->price)->toBe('12000.00');
    expect($plan->enrollment_fee)->toBe('3000.00');
    expect($plan->is_active)->toBeTrue();
});

it('allows ADMIN to create a plan with only the required fields', function () {
    // AC-1 (FR-001, AP-01): a valid plan needs only name + price; absent
    // optional fields are stored as null, not as 0 (ADR-003).
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreatePlan::class)
        ->fillForm([
            'name' => 'Minimal Plan',
            'price' => '5000.00',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $plan = Plan::where('name', 'Minimal Plan')->firstOrFail();

    expect($plan->description)->toBeNull();
    expect($plan->enrollment_fee)->toBeNull();
    expect($plan->price)->toBe('5000.00');
    expect($plan->is_active)->toBeTrue();
});

it('does not create any membership or payment record when creating a plan', function () {
    // AC-11 (BR-007, C-07): creating a plan touches only the plans table; no
    // other business table gains rows (membership/payment tables do not exist
    // yet and must not be created by this operation).
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreatePlan::class)
        ->fillForm([
            'name' => 'Standalone Plan',
            'price' => '9000.00',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Plan::where('name', 'Standalone Plan')->exists())->toBeTrue();
    expect(Plan::count())->toBe(1);
    expect(User::count())->toBe(1); // only the acting admin
    expect(DB::table('role_user')->count())->toBe(1); // only the admin's role
});

it('rejects creating a plan with a duplicate name', function () {
    // AC-2 (ERR-002, BR-003).
    Plan::factory()->create(['name' => 'Mensual']);
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreatePlan::class)
        ->fillForm([
            'name' => 'Mensual',
            'price' => '12000.00',
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique']);

    expect(Plan::count())->toBe(1);
});

it('rejects editing a plan onto another plan name', function () {
    // AC-2 (ERR-002, BR-003) on update: the current record's own name is
    // ignored, but another plan's name collides.
    $other = Plan::factory()->create(['name' => 'Trimestral']);
    $plan = Plan::factory()->create(['name' => 'Anual']);
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(EditPlan::class, ['record' => $plan->getRouteKey()])
        ->fillForm(['name' => 'Trimestral'])
        ->call('save')
        ->assertHasFormErrors(['name' => 'unique']);

    expect($plan->fresh()->name)->toBe('Anual');
    expect($other->fresh()->name)->toBe('Trimestral');
});

it('rejects creating a plan without the required fields', function () {
    // ERR-001 (FR-001): name and price are required.
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreatePlan::class)
        ->call('create')
        ->assertHasFormErrors(['name' => 'required', 'price' => 'required']);

    expect(Plan::count())->toBe(0);
});

it('rejects a non-positive price on create', function () {
    // AC-3 (ERR-003, BR-002): the price must be a positive amount.
    $admin = userWithRoles([Role::ADMIN]);

    foreach (['0', '-100.00'] as $price) {
        Livewire::actingAs($admin)
            ->test(CreatePlan::class)
            ->fillForm([
                'name' => 'Plan with price '.$price,
                'price' => $price,
            ])
            ->call('create')
            ->assertHasFormErrors(['price' => 'min']);
    }

    expect(Plan::count())->toBe(0);
});

it('rejects a negative enrollment fee and accepts a zero fee', function () {
    // AC-3 (ERR-003, BR-002): the fee, when present, must be zero or a
    // positive amount; a zero fee is accepted and an absent fee is null.
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreatePlan::class)
        ->fillForm([
            'name' => 'Negative Fee',
            'price' => '10000.00',
            'enrollment_fee' => '-1.00',
        ])
        ->call('create')
        ->assertHasFormErrors(['enrollment_fee' => 'min']);

    expect(Plan::count())->toBe(0);

    Livewire::actingAs($admin)
        ->test(CreatePlan::class)
        ->fillForm([
            'name' => 'Zero Fee',
            'price' => '10000.00',
            'enrollment_fee' => '0',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Plan::where('name', 'Zero Fee')->firstOrFail()->enrollment_fee)
        ->toBe('0.00');
});

it('lets ADMIN search plans by name and description', function () {
    // AC-4 (FR-002): search is by name and description.
    $admin = userWithRoles([Role::ADMIN]);

    $byName = Plan::factory()->create(['name' => 'Searchable Plan']);
    $byDescription = Plan::factory()->create([
        'description' => 'Description containing UniqueDescriptionKeyword',
    ]);
    $other = Plan::factory()->create(['name' => 'Another Plan']);

    Livewire::actingAs($admin)
        ->test(ListPlans::class)
        ->searchTable('Searchable')
        ->assertCanSeeTableRecords([$byName])
        ->assertCanNotSeeTableRecords([$byDescription, $other]);

    Livewire::actingAs($admin)
        ->test(ListPlans::class)
        ->searchTable('UniqueDescriptionKeyword')
        ->assertCanSeeTableRecords([$byDescription])
        ->assertCanNotSeeTableRecords([$byName, $other]);
});

it('lets ADMIN view the full plan detail including status', function () {
    // AC-5 (FR-003, FR-006).
    $admin = userWithRoles([Role::ADMIN]);
    $plan = Plan::factory()->create([
        'name' => 'Detail Plan',
        'description' => 'A detailed plan description.',
        'price' => '950.00',
        'enrollment_fee' => '250.00',
        'is_active' => true,
    ]);

    Livewire::actingAs($admin)
        ->test(ViewPlan::class, ['record' => $plan->getRouteKey()])
        ->assertSee('Detail Plan')
        ->assertSee('A detailed plan description.')
        ->assertSee('950.00')
        ->assertSee('250.00')
        ->assertSee('Activo');
});

it('lets ADMIN edit a plan and persist the changes', function () {
    // AC-6 (FR-004).
    $admin = userWithRoles([Role::ADMIN]);
    $plan = Plan::factory()->create([
        'name' => 'Original Plan',
        'description' => 'Original description',
        'price' => '10000.00',
        'enrollment_fee' => '1000.00',
        'is_active' => true,
    ]);

    Livewire::actingAs($admin)
        ->test(EditPlan::class, ['record' => $plan->getRouteKey()])
        ->fillForm([
            'name' => 'Updated Plan',
            'description' => 'Updated description',
            'price' => '12000.00',
            'enrollment_fee' => '2000.00',
            'is_active' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $plan->refresh();

    expect($plan->name)->toBe('Updated Plan');
    expect($plan->description)->toBe('Updated description');
    expect($plan->price)->toBe('12000.00');
    expect($plan->enrollment_fee)->toBe('2000.00');
});

it('lets ADMIN deactivate an active plan via the list action', function () {
    // AC-7 (FR-005, FR-006, BR-005; AF-001): the record remains in the system
    // and is marked inactive.
    $admin = userWithRoles([Role::ADMIN]);
    $plan = Plan::factory()->create(['name' => 'Deactivatable', 'is_active' => true]);

    Livewire::actingAs($admin)
        ->test(ListPlans::class)
        ->callTableAction('deactivate', $plan);

    expect($plan->fresh()->is_active)->toBeFalse();
    expect(Plan::find($plan->id))->not->toBeNull();
});

it('lets ADMIN reactivate an inactive plan via the list action', function () {
    // AC-8 (FR-005, AF-002).
    $admin = userWithRoles([Role::ADMIN]);
    $plan = Plan::factory()->create(['name' => 'Reactivatible', 'is_active' => false]);

    Livewire::actingAs($admin)
        ->test(ListPlans::class)
        ->callTableAction('activate', $plan);

    expect($plan->fresh()->is_active)->toBeTrue();
});

it('shows the lifecycle actions according to the plan status', function () {
    // FR-005: Deactivate is only offered for active plans and Activate only
    // for inactive plans (BR-005).
    $admin = userWithRoles([Role::ADMIN]);
    $active = Plan::factory()->create(['name' => 'Active Plan', 'is_active' => true]);
    $inactive = Plan::factory()->create(['name' => 'Inactive Plan', 'is_active' => false]);

    Livewire::actingAs($admin)
        ->test(ListPlans::class)
        ->assertTableActionVisible('deactivate', $active)
        ->assertTableActionHidden('deactivate', $inactive)
        ->assertTableActionVisible('activate', $inactive)
        ->assertTableActionHidden('activate', $active);
});

it('lets ADMIN toggle the status during an edit', function () {
    // FR-005 path: the status can also be changed from the edit form.
    $admin = userWithRoles([Role::ADMIN]);
    $plan = Plan::factory()->create(['name' => 'Toggle Me', 'is_active' => true]);

    Livewire::actingAs($admin)
        ->test(EditPlan::class, ['record' => $plan->getRouteKey()])
        ->fillForm(['is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($plan->fresh()->is_active)->toBeFalse();
});

it('does not expose a delete operation for plan records', function () {
    // AC-10 (BR-004): no delete policy is registered, so deletion is denied
    // for everyone, and no delete action is reachable on the list.
    $admin = userWithRoles([Role::ADMIN]);
    $plan = Plan::factory()->create(['name' => 'Eternal Plan']);

    expect($admin->can('delete', $plan))->toBeFalse();

    Livewire::actingAs($admin)
        ->test(ListPlans::class)
        ->assertTableActionDoesNotExist('delete');

    $this->actingAs($admin)->get('/admin/plans')->assertOk();

    expect(Plan::find($plan->id))->not->toBeNull();
});
