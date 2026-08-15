<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;

/*
 * Provisioning seeder tests (SPEC-001 FR-008, FR-004; AC-11; A-03, A-04,
 * OQ-06 default).
 */

function clearAdminEnv(): void
{
    foreach (['ADMIN_NAME', 'ADMIN_EMAIL', 'ADMIN_PASSWORD'] as $key) {
        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);
    }
}

function restoreAdminEnv(): void
{
    foreach ([
        'ADMIN_NAME' => 'Test Admin',
        'ADMIN_EMAIL' => 'admin@gym.test',
        'ADMIN_PASSWORD' => 'password',
    ] as $key => $value) {
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv("{$key}={$value}");
    }
}

beforeEach(function () {
    restoreAdminEnv();
});

afterEach(function () {
    restoreAdminEnv();
});

it('seeds exactly the fixed role catalog', function () {
    $this->seed(RoleSeeder::class);

    expect(Role::where('name', Role::ADMIN)->exists())->toBeTrue();
    expect(Role::where('name', Role::TRAINER)->exists())->toBeTrue();
    expect(Role::where('name', Role::CLIENT)->exists())->toBeTrue();
    expect(Role::count())->toBe(3);
});

it('creates an ADMIN user from the environment variables who can log in', function () {
    $this->seed([RoleSeeder::class, AdminUserSeeder::class]);

    $admin = User::where('email', env('ADMIN_EMAIL'))->first();

    expect($admin)->not->toBeNull();
    expect($admin->name)->toBe('Test Admin');
    expect($admin->is_active)->toBeTrue();
    expect($admin->hasRole(Role::ADMIN))->toBeTrue();
    expect(Hash::check(env('ADMIN_PASSWORD'), $admin->password))->toBeTrue();

    $this->post('/login', [
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ])->assertRedirect('/admin');

    $this->assertAuthenticatedAs($admin);
});

it('is idempotent when run more than once', function () {
    $this->seed([RoleSeeder::class, AdminUserSeeder::class]);
    $this->seed([RoleSeeder::class, AdminUserSeeder::class]);

    expect(Role::count())->toBe(3);
    expect(User::where('email', env('ADMIN_EMAIL'))->count())->toBe(1);
});

it('falls back to documented local-dev defaults outside production', function () {
    clearAdminEnv();

    $this->seed(AdminUserSeeder::class);

    $admin = User::where('email', 'admin@gym.test')->first();

    expect($admin)->not->toBeNull();
    expect($admin->hasRole(Role::ADMIN))->toBeTrue();
    expect(Hash::check('password', $admin->password))->toBeTrue();
});

it('aborts in production when the environment variables are missing', function () {
    clearAdminEnv();
    app()['env'] = 'production';

    (new AdminUserSeeder())->run();
})->throws(RuntimeException::class, 'AdminUserSeeder requires ADMIN_NAME');
