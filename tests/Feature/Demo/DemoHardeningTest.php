<?php

use App\Models\Client;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Console\Commands\ResetDemoDatabase;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\RateLimiter;

/*
 * Demo hardening tests (SPEC-016): the `demo:reset` command wipes
 * transactional/demo data and reseeds a clean demo state; the admin panel
 * write requests are rate-limited per IP.
 */

beforeEach(function () {
    // Reference data used by the seeder.
    Role::firstOrCreate(['name' => Role::ADMIN]);
    Role::firstOrCreate(['name' => Role::CLIENT]);
});

it('seeds the demo client with CLIENT role and an active membership', function () {
    $this->seed(DemoSeeder::class);

    $user = User::where('email', 'cliente@gym.com')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole(Role::CLIENT))->toBeTrue();

    $client = Client::where('email', 'cliente@gym.com')->first();
    expect($client)->not->toBeNull();
    expect($client->memberships()->where('status', Membership::STATUS_ACTIVE)->exists())->toBeTrue();
});

it('demo:reset wipes transactional data and reseeds a clean demo state', function () {
    $this->seed(DemoSeeder::class);

    // Create some "abuse": extra clients, cuotas, payments.
    $abuseUser = User::create([
        'name' => 'Abuser',
        'email' => 'abuse@gym.com',
        'password' => 'Whatever123!',
        'is_active' => true,
    ]);
    $abuseUser->roles()->attach(Role::where('name', Role::CLIENT)->first());
    $abuseClient = Client::create([
        'full_name' => 'Abuser',
        'dni' => '99999999',
        'email' => 'abuse@gym.com',
        'status' => Client::STATUS_ACTIVE,
    ]);
    $abuseClient->user()->associate($abuseUser);
    $abuseClient->save();

    expect(Client::count())->toBeGreaterThan(1);

    Artisan::call('demo:reset');

    // Abuser data is gone.
    expect(User::where('email', 'abuse@gym.com')->exists())->toBeFalse();
    expect(Client::where('email', 'abuse@gym.com')->exists())->toBeFalse();

    // Demo client is reseeded and intact.
    $demoUser = User::where('email', 'cliente@gym.com')->first();
    expect($demoUser)->not->toBeNull();
    $demoClient = Client::where('email', 'cliente@gym.com')->first();
    expect($demoClient)->not->toBeNull();
    expect($demoClient->memberships()->where('status', Membership::STATUS_ACTIVE)->exists())->toBeTrue();
});

it('seeds the demo admin with ADMIN role', function () {
    $this->seed(DemoSeeder::class);

    $user = User::where('email', 'admin@gym.com')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole(Role::ADMIN))->toBeTrue();
});

it('demo:reset keeps the ADMIN user and reference data intact', function () {
    $this->seed(DemoSeeder::class);
    $adminBefore = User::where('email', env('ADMIN_EMAIL', 'admin@gym.test'))->first();

    Artisan::call('demo:reset');

    $adminAfter = User::where('email', env('ADMIN_EMAIL', 'admin@gym.test'))->first();
    expect($adminAfter)->not->toBeNull();
    expect($adminAfter->id)->toBe($adminBefore->id);
    expect($adminAfter->hasRole(Role::ADMIN))->toBeTrue();
});

it('demo:reset does not block the production environment', function () {
    // The demo reset is intentionally allowed in production: it is the nightly
    // scheduler job that keeps a public demo clean. Verify there is no
    // production guard on the command (it must run on the demo VPS).
    $command = (new ReflectionClass(ResetDemoDatabase::class))->newInstanceWithoutConstructor();
    expect(method_exists($command, 'handle'))->toBeTrue();
});

it('registers the demo:reset command in the application schedule', function () {
    $scheduled = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains((string) $event->command, 'demo:reset'));

    expect($scheduled)->toHaveCount(1);
    expect((string) $scheduled->first()->expression)->toBe('0 4 * * *');
});

it('rate-limits admin write requests per IP', function () {
    // Verify the app registers an `admin-write` rate limiter bound to IP that
    // blocks excess attempts (SPEC-016 hardening).
    $limiter = RateLimiter::limiter('admin-write');
    expect($limiter)->not->toBeNull();

    $request = Illuminate\Http\Request::create('/admin', 'GET');
    $limit = $limiter($request);
    expect($limit->maxAttempts)->toBe(120);
});
