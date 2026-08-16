<?php

use App\Actions\RenewMembership;
use App\Filament\Resources\ClientResource\Pages\ViewClient;
use App\Filament\Resources\ClientResource\RelationManagers\MembershipsRelationManager;
use App\Filament\Resources\MembershipResource\Pages\CreateMembership;
use App\Filament\Resources\MembershipResource\Pages\ListMemberships;
use App\Filament\Resources\MembershipResource\Pages\ViewMembership;
use App\Models\Client;
use App\Models\Cuota;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/*
 * Membership CRUD and lifecycle feature tests (SPEC-004 FR-001..FR-006;
 * BR-002, BR-003, BR-005, BR-008, BR-010, BR-011, BR-012, BR-013, BR-014;
 * ERR-001, ERR-002, ERR-003, ERR-005, ERR-007; AC-1..AC-9, AC-13, AC-14,
 * AC-17, AC-18). Authorization is enforced server-side.
 */

it('allows ADMIN to create a membership with a pending status and computed end date', function () {
    // AC-1 (FR-001, FR-002, BR-003, BR-005): the record is persisted with
    // status `pending` and end_date = start_date + duration_days - 1.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();
    $plan = Plan::factory()->create();

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

    expect($membership->plan_id)->toBe($plan->id);
    expect($membership->status)->toBe(Membership::STATUS_PENDING);
    expect($membership->start_date->toDateString())->toBe('2026-08-15');
    expect($membership->duration_days)->toBe(30);
    expect($membership->end_date->toDateString())->toBe('2026-09-13');
});

it('creates one cuota and does not modify other records when creating a membership', function () {
    // SPEC-005 AC-1 supersedes the SPEC-004 AC-18 "no cuota on create"
    // expectation (ADR-005): creating a membership now auto-generates exactly
    // one pending cuota with amount = plan price, still creates NO Payment,
    // and the client, plan and user records are untouched (BR-010, C-07).
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();
    $plan = Plan::factory()->create(['price' => '7500.00']);
    $planBefore = $plan->fresh()->toArray();

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

    expect(Membership::count())->toBe(1);
    expect(Cuota::count())->toBe(1);
    expect(Payment::count())->toBe(0);
    expect(Client::count())->toBe(1);
    expect(Plan::count())->toBe(1);
    expect(User::count())->toBe(1); // only the acting admin
    expect(DB::table('role_user')->count())->toBe(1); // only the admin's role
    expect($plan->fresh()->toArray())->toBe($planBefore);
    expect($client->fresh()->user_id)->toBeNull();

    $membership = Membership::firstOrFail();
    expect($membership->cuota->amount)->toBe('7500.00');
    expect($membership->cuota->status)->toBe(Cuota::STATUS_PENDING);
});

it('rejects creating a membership against an inactive plan', function () {
    // AC-2 (ERR-001, BR-012, AM-09): only active plans can be selected.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();
    $plan = Plan::factory()->create(['is_active' => false]);

    Livewire::actingAs($admin)
        ->test(CreateMembership::class)
        ->fillForm([
            'client_id' => $client->id,
            'plan_id' => $plan->id,
            'start_date' => '2026-08-15',
            'duration_days' => '30',
        ])
        ->call('create')
        ->assertHasFormErrors(['plan_id' => 'exists']);

    expect(Membership::count())->toBe(0);
});

it('rejects a zero, negative or non-integer duration', function () {
    // AC-3 (ERR-003, BR-003): the duration must be a positive integer.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();
    $plan = Plan::factory()->create();

    foreach (['0', '-5'] as $duration) {
        Livewire::actingAs($admin)
            ->test(CreateMembership::class)
            ->fillForm([
                'client_id' => $client->id,
                'plan_id' => $plan->id,
                'start_date' => '2026-08-15',
                'duration_days' => $duration,
            ])
            ->call('create')
            ->assertHasFormErrors(['duration_days' => 'min']);
    }

    Livewire::actingAs($admin)
        ->test(CreateMembership::class)
        ->fillForm([
            'client_id' => $client->id,
            'plan_id' => $plan->id,
            'start_date' => '2026-08-15',
            'duration_days' => '30.5',
        ])
        ->call('create')
        ->assertHasFormErrors(['duration_days' => 'integer']);

    expect(Membership::count())->toBe(0);
});

it('rejects creating a membership without the required fields', function () {
    // AC-4 (ERR-002, BR-002): client, plan, start date and duration are all
    // required.
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreateMembership::class)
        ->call('create')
        ->assertHasFormErrors([
            'client_id' => 'required',
            'plan_id' => 'required',
            'start_date' => 'required',
            'duration_days' => 'required',
        ]);

    expect(Membership::count())->toBe(0);
});

it('rejects a nonexistent client or plan', function () {
    // ERR-007 (BR-002): the membership references an existing client and plan.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();
    $plan = Plan::factory()->create();

    Livewire::actingAs($admin)
        ->test(CreateMembership::class)
        ->fillForm([
            'client_id' => 999999,
            'plan_id' => $plan->id,
            'start_date' => '2026-08-15',
            'duration_days' => '30',
        ])
        ->call('create')
        ->assertHasFormErrors(['client_id' => 'exists']);

    Livewire::actingAs($admin)
        ->test(CreateMembership::class)
        ->fillForm([
            'client_id' => $client->id,
            'plan_id' => 999999,
            'start_date' => '2026-08-15',
            'duration_days' => '30',
        ])
        ->call('create')
        ->assertHasFormErrors(['plan_id' => 'exists']);

    expect(Membership::count())->toBe(0);
});

it('lets ADMIN search memberships by client name, client dni and plan name', function () {
    // AC-5 (FR-002): search by client (name/DNI) and plan (name). The search
    // tokens are single words disjoint across the fixtures so each search is
    // unambiguous (Filament's global search ANDs the search words).
    $admin = userWithRoles([Role::ADMIN]);

    $clientAlpha = Client::factory()->create(['full_name' => 'AlphaClient Member']);
    $clientBeta = Client::factory()->create(['full_name' => 'Beta Client Person', 'dni' => '33332222']);
    $planGamma = Plan::factory()->create(['name' => 'GammaPlan Offer']);
    $planDelta = Plan::factory()->create(['name' => 'DeltaPlan Offer']);

    $byName = Membership::factory()->create(['client_id' => $clientAlpha->id, 'plan_id' => $planGamma->id]);
    $byDni = Membership::factory()->create(['client_id' => $clientBeta->id, 'plan_id' => $planGamma->id]);
    $byPlan = Membership::factory()->create(['client_id' => $clientAlpha->id, 'plan_id' => $planDelta->id]);
    $other = Membership::factory()->create();

    Livewire::actingAs($admin)
        ->test(ListMemberships::class)
        ->searchTable('AlphaClient')
        ->assertCanSeeTableRecords([$byName, $byPlan])
        ->assertCanNotSeeTableRecords([$byDni, $other]);

    Livewire::actingAs($admin)
        ->test(ListMemberships::class)
        ->searchTable('33332222')
        ->assertCanSeeTableRecords([$byDni])
        ->assertCanNotSeeTableRecords([$byName, $byPlan, $other]);

    Livewire::actingAs($admin)
        ->test(ListMemberships::class)
        ->searchTable('GammaPlan')
        ->assertCanSeeTableRecords([$byName, $byDni])
        ->assertCanNotSeeTableRecords([$byPlan, $other]);
});

it('filters memberships by status and period dates', function () {
    // AC-5 (FR-002): status filter and start/end period date filters.
    $admin = userWithRoles([Role::ADMIN]);

    $pending = Membership::factory()->create([
        'status' => Membership::STATUS_PENDING,
        'start_date' => '2026-08-01',
        'duration_days' => 30,
        'end_date' => '2026-08-30',
    ]);
    $active = Membership::factory()->create([
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => '2026-08-10',
        'duration_days' => 30,
        'end_date' => '2026-09-08',
    ]);
    $expired = Membership::factory()->create([
        'status' => Membership::STATUS_EXPIRED,
        'start_date' => '2026-01-01',
        'duration_days' => 30,
        'end_date' => '2026-01-30',
    ]);

    Livewire::actingAs($admin)
        ->test(ListMemberships::class)
        ->filterTable('status', Membership::STATUS_ACTIVE)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$pending, $expired]);

    Livewire::actingAs($admin)
        ->test(ListMemberships::class)
        ->filterTable('start_date', ['start_from' => '2026-08-01'])
        ->assertCanSeeTableRecords([$pending, $active])
        ->assertCanNotSeeTableRecords([$expired]);

    Livewire::actingAs($admin)
        ->test(ListMemberships::class)
        ->filterTable('end_date', ['end_until' => '2026-08-31'])
        ->assertCanSeeTableRecords([$pending, $expired])
        ->assertCanNotSeeTableRecords([$active]);
});

it('lets ADMIN view the full membership detail including client, plan, period and status', function () {
    // AC-6 (FR-003, FR-007).
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create(['full_name' => 'Detail Member']);
    $plan = Plan::factory()->create(['name' => 'Detail Plan']);
    $membership = Membership::factory()->create([
        'client_id' => $client->id,
        'plan_id' => $plan->id,
        'start_date' => '2026-08-15',
        'duration_days' => 30,
        'status' => Membership::STATUS_ACTIVE,
    ]);

    Livewire::actingAs($admin)
        ->test(ViewMembership::class, ['record' => $membership->getRouteKey()])
        ->assertSee('Detail Member')
        ->assertSee('Detail Plan')
        ->assertSee('2026-08-15')
        ->assertSee('2026-09-13')
        ->assertSee('Activo');
});

it('lets ADMIN cancel a pending or active membership via the list action', function () {
    // AC-10 (FR-006, BR-008): cancellation is a manual terminal transition.
    $admin = userWithRoles([Role::ADMIN]);
    $pending = Membership::factory()->create(['status' => Membership::STATUS_PENDING]);
    $active = Membership::factory()->create(['status' => Membership::STATUS_ACTIVE]);

    Livewire::actingAs($admin)
        ->test(ListMemberships::class)
        ->callTableAction('cancel', $pending)
        ->callTableAction('cancel', $active);

    expect($pending->fresh()->status)->toBe(Membership::STATUS_CANCELLED);
    expect($active->fresh()->status)->toBe(Membership::STATUS_CANCELLED);
});

it('rejects cancelling an expired or cancelled membership', function () {
    // AC-11 (ERR-004, BR-009): expired/cancelled are terminal.
    $admin = userWithRoles([Role::ADMIN]);
    $expired = Membership::factory()->create(['status' => Membership::STATUS_EXPIRED]);
    $cancelled = Membership::factory()->create(['status' => Membership::STATUS_CANCELLED]);

    expect(fn () => $expired->cancel())->toThrow(DomainException::class);
    expect(fn () => $cancelled->cancel())->toThrow(DomainException::class);

    expect($expired->fresh()->status)->toBe(Membership::STATUS_EXPIRED);
    expect($cancelled->fresh()->status)->toBe(Membership::STATUS_CANCELLED);
});

it('shows the cancel and renew actions according to the membership status', function () {
    // FR-005/FR-006 visibility: Cancel for pending/active, Renew for
    // active/expired; the rule is still enforced server-side (AGENTS.md §17).
    $admin = userWithRoles([Role::ADMIN]);
    $pending = Membership::factory()->create(['status' => Membership::STATUS_PENDING]);
    $active = Membership::factory()->create(['status' => Membership::STATUS_ACTIVE]);
    $expired = Membership::factory()->create(['status' => Membership::STATUS_EXPIRED]);
    $cancelled = Membership::factory()->create(['status' => Membership::STATUS_CANCELLED]);

    Livewire::actingAs($admin)
        ->test(ListMemberships::class)
        ->assertTableActionVisible('cancel', $pending)
        ->assertTableActionVisible('cancel', $active)
        ->assertTableActionHidden('cancel', $expired)
        ->assertTableActionHidden('cancel', $cancelled)
        ->assertTableActionVisible('renew', $active)
        ->assertTableActionVisible('renew', $expired)
        ->assertTableActionHidden('renew', $pending)
        ->assertTableActionHidden('renew', $cancelled);
});

it('lets ADMIN cancel a membership from the detail view header action', function () {
    // FR-006 path on the detail page (ViewMembership header action).
    $admin = userWithRoles([Role::ADMIN]);
    $membership = Membership::factory()->create(['status' => Membership::STATUS_ACTIVE]);

    Livewire::actingAs($admin)
        ->test(ViewMembership::class, ['record' => $membership->getRouteKey()])
        ->callAction('cancel');

    expect($membership->fresh()->status)->toBe(Membership::STATUS_CANCELLED);
});

it('allows a client to hold several concurrent memberships, including multiple active ones', function () {
    // AC-13 (BR-010, AM-04, C-08): no restriction on overlapping memberships.
    $client = Client::factory()->create();
    $plan = Plan::factory()->create();

    Membership::factory()->create([
        'client_id' => $client->id,
        'plan_id' => $plan->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);
    Membership::factory()->create([
        'client_id' => $client->id,
        'plan_id' => $plan->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => '2026-06-01',
        'end_date' => '2026-12-31',
    ]);

    expect($client->memberships()->count())->toBe(2);
    expect($client->memberships()->where('status', Membership::STATUS_ACTIVE)->count())->toBe(2);
});

it('does not modify existing memberships when a plan is deactivated, and rejects new memberships and renewals on it', function () {
    // AC-14 (BR-012, BR-013, AM-09): plan edits/deactivation never change
    // existing memberships; an inactive plan cannot be used for new
    // memberships or renewals.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create();
    $plan = Plan::factory()->create(['name' => 'Deactivatable Plan']);
    $membership = Membership::factory()->create([
        'client_id' => $client->id,
        'plan_id' => $plan->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => '2026-01-01',
        'duration_days' => 30,
    ]);

    $plan->update(['is_active' => false, 'price' => '1.00']);

    $fresh = $membership->fresh();
    expect($fresh->status)->toBe(Membership::STATUS_ACTIVE);
    expect($fresh->plan_id)->toBe($plan->id);
    expect($fresh->start_date->toDateString())->toBe('2026-01-01');
    expect($fresh->end_date->toDateString())->toBe('2026-01-30');

    Livewire::actingAs($admin)
        ->test(CreateMembership::class)
        ->fillForm([
            'client_id' => $client->id,
            'plan_id' => $plan->id,
            'start_date' => '2026-08-15',
            'duration_days' => '30',
        ])
        ->call('create')
        ->assertHasFormErrors(['plan_id' => 'exists']);

    expect(Membership::count())->toBe(1);

    expect(fn () => app(RenewMembership::class)->handle($membership))
        ->toThrow(ValidationException::class);

    expect(Membership::count())->toBe(1);
});

it('does not expose a delete operation for membership records', function () {
    // AC-17 (BR-014): no delete policy is registered, so deletion is denied
    // for everyone, and no delete action is reachable on the list.
    $admin = userWithRoles([Role::ADMIN]);
    $membership = Membership::factory()->create();

    expect($admin->can('delete', $membership))->toBeFalse();

    Livewire::actingAs($admin)
        ->test(ListMemberships::class)
        ->assertTableActionDoesNotExist('delete');

    $this->actingAs($admin)->get('/admin/memberships')->assertOk();

    expect(Membership::find($membership->id))->not->toBeNull();
});

it('shows the client membership history in chronological order from the client record', function () {
    // AC-7 (FR-004, C-08): the MembershipsRelationManager lists all
    // memberships of the client, including past states, ordered by start_date.
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();
    $plan = Plan::factory()->create();

    $newest = Membership::factory()->create([
        'client_id' => $client->id,
        'plan_id' => $plan->id,
        'status' => Membership::STATUS_ACTIVE,
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-30',
    ]);
    $oldest = Membership::factory()->create([
        'client_id' => $client->id,
        'plan_id' => $plan->id,
        'status' => Membership::STATUS_CANCELLED,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-30',
    ]);
    $other = Membership::factory()->create();

    Livewire::actingAs($admin)
        ->test(MembershipsRelationManager::class, [
            'ownerRecord' => $client,
            'pageClass' => ViewClient::class,
        ])
        ->assertCanSeeTableRecords([$oldest, $newest], inOrder: true)
        ->assertCanNotSeeTableRecords([$other]);
});

it('lets ADMIN renew an active membership creating a new pending record without modifying the original', function () {
    // AC-8 (FR-005, BR-011): renewal creates a NEW record for the same client
    // and plan with status `pending`; the original is untouched. Defaults:
    // start = day after previous end date; duration = previous duration
    // (AM-08, OQ-05 design default).
    $admin = userWithRoles([Role::ADMIN]);
    $client = Client::factory()->create();
    $plan = Plan::factory()->create();
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

    expect($renewal->client_id)->toBe($client->id);
    expect($renewal->plan_id)->toBe($plan->id);
    expect($renewal->status)->toBe(Membership::STATUS_PENDING);
    expect($renewal->start_date->toDateString())->toBe('2026-01-31');
    expect($renewal->duration_days)->toBe(30);
    expect($renewal->end_date->toDateString())->toBe('2026-03-01');

    $original = $membership->fresh();
    expect($original->status)->toBe(Membership::STATUS_ACTIVE);
    expect($original->start_date->toDateString())->toBe('2026-01-01');
    expect($original->end_date->toDateString())->toBe('2026-01-30');
    expect($original->duration_days)->toBe(30);
});

it('lets ADMIN renew an expired membership via the renew action', function () {
    // AF-003 (FR-005): an expired membership can be renewed; the expired
    // record remains terminal and the renewal is a fresh pending record.
    $admin = userWithRoles([Role::ADMIN]);
    $membership = Membership::factory()->create([
        'status' => Membership::STATUS_EXPIRED,
        'start_date' => '2026-01-01',
        'duration_days' => 30,
    ]);

    Livewire::actingAs($admin)
        ->test(ListMemberships::class)
        ->callTableAction('renew', $membership);

    $renewal = Membership::where('id', '!=', $membership->id)->firstOrFail();

    expect($renewal->status)->toBe(Membership::STATUS_PENDING);
    expect($renewal->client_id)->toBe($membership->client_id);
    expect($renewal->plan_id)->toBe($membership->plan_id);

    expect($membership->fresh()->status)->toBe(Membership::STATUS_EXPIRED);
    expect(Membership::count())->toBe(2);
});

it('renews with explicit start date and duration via the action', function () {
    // FR-005: the ADMIN may change the start date and duration of the renewal.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $client = Client::factory()->create();
    $plan = Plan::factory()->create();
    $membership = Membership::factory()->create([
        'client_id' => $client->id,
        'plan_id' => $plan->id,
        'status' => Membership::STATUS_EXPIRED,
        'start_date' => '2026-01-01',
        'duration_days' => 30,
    ]);

    $renewal = app(RenewMembership::class)->handle($membership, '2026-02-15', 10);

    expect($renewal->client_id)->toBe($client->id);
    expect($renewal->plan_id)->toBe($plan->id);
    expect($renewal->status)->toBe(Membership::STATUS_PENDING);
    expect($renewal->start_date->toDateString())->toBe('2026-02-15');
    expect($renewal->duration_days)->toBe(10);
    expect($renewal->end_date->toDateString())->toBe('2026-02-24');

    expect($membership->fresh()->status)->toBe(Membership::STATUS_EXPIRED);
    expect(Membership::count())->toBe(2);
});

it('rejects renewing a pending or cancelled membership', function () {
    // AC-9 (ERR-005, AM-08, BR-009): renewal is available only for active and
    // expired memberships.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $pending = Membership::factory()->create(['status' => Membership::STATUS_PENDING]);
    $cancelled = Membership::factory()->create(['status' => Membership::STATUS_CANCELLED]);

    expect(fn () => app(RenewMembership::class)->handle($pending))
        ->toThrow(ValidationException::class);
    expect(fn () => app(RenewMembership::class)->handle($cancelled))
        ->toThrow(ValidationException::class);

    expect(Membership::count())->toBe(2);
});

it('rejects renewing with an invalid duration', function () {
    // ERR-003 (BR-003): the new duration must be a positive integer.
    $admin = userWithRoles([Role::ADMIN]);
    $this->actingAs($admin);
    $membership = Membership::factory()->create(['status' => Membership::STATUS_ACTIVE]);

    expect(fn () => app(RenewMembership::class)->handle($membership, '2026-03-01', 0))
        ->toThrow(ValidationException::class);
    expect(fn () => app(RenewMembership::class)->handle($membership, '2026-03-01', -5))
        ->toThrow(ValidationException::class);

    expect(Membership::count())->toBe(1);
});
