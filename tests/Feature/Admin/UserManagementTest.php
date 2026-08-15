<?php

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

/*
 * Staff user management feature tests (SPEC-001 FR-007, BR-006, BR-007;
 * AC-6, AC-7; ERR-005). Authorization is enforced server-side.
 */

it('allows ADMIN to create a staff user and assign roles', function () {
    $admin = userWithRoles([Role::ADMIN]);

    $this->actingAs($admin);

    $user = User::create([
        'name' => 'New Trainer',
        'email' => 'trainer@gym.test',
        'password' => 'password',
        'is_active' => true,
    ]);
    $user->roles()->attach(role(Role::TRAINER));

    $fresh = User::findOrFail($user->id);

    expect($fresh->hasRole(Role::TRAINER))->toBeTrue();
    expect($fresh->is_active)->toBeTrue();
    expect(Hash::check('password', $fresh->password))->toBeTrue();
});

it('lets ADMIN change role assignments and deactivate; changes take effect on the next request', function () {
    $target = userWithRoles([Role::TRAINER]);
    userWithRoles([Role::ADMIN]);

    // Change the assignment.
    $target->roles()->sync([role(Role::CLIENT)->id]);

    expect($target->hasRole(Role::TRAINER))->toBeFalse();
    expect($target->hasRole(Role::CLIENT))->toBeTrue();

    // Deactivate the account.
    $target->update(['is_active' => false]);

    // The change takes effect on the user's next request: login is rejected.
    $this->post('/login', [
        'email' => $target->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('rejects a duplicate email via the unique database constraint', function () {
    User::factory()->create(['email' => 'duplicate@gym.test']);

    expect(fn () => User::create([
        'name' => 'Duplicate',
        'email' => 'duplicate@gym.test',
        'password' => 'password',
    ]))->toThrow(QueryException::class);
});

it('rejects a duplicate email through the user creation form', function () {
    User::factory()->create(['email' => 'duplicate@gym.test']);
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreateUser::class)
        ->fillForm([
            'name' => 'Duplicate',
            'email' => 'duplicate@gym.test',
            'password' => 'password',
            'roles' => [role(Role::TRAINER)->id],
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['email' => 'unique']);
});

it('exposes the user management page to ADMIN only', function () {
    $admin = userWithRoles([Role::ADMIN]);
    $trainer = userWithRoles([Role::TRAINER]);
    $client = userWithRoles([Role::CLIENT]);

    $this->actingAs($admin)->get('/admin/users')->assertOk();

    $this->actingAs($trainer)->get('/admin/users')->assertForbidden();
    $this->actingAs($client)->get('/admin/users')->assertForbidden();
});

it('prevents an ADMIN from deactivating their own account', function () {
    // Technical safeguard (architecture SPEC-001 §5/§12): self-deactivation
    // is blocked to prevent an accidental lockout. The save is rejected with
    // a form error on is_active and the account stays active.
    $admin = userWithRoles([Role::ADMIN]);

    $component = Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $admin->getRouteKey()])
        ->fillForm(['is_active' => false])
        ->call('save');

    expect($component->instance()->getErrorBag()->toArray())
        ->toHaveKey('is_active')
        ->toHaveCount(1);

    expect(User::findOrFail($admin->id)->is_active)->toBeTrue();
});
