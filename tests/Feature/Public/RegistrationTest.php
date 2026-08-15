<?php

use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'full_name' => 'New Applicant', 'dni' => '12345678',
        'email' => 'applicant@gym.test', 'password' => 'password',
        'password_confirmation' => 'password', 'phone' => '555-0100',
        'emergency_contact' => 'Emergency Contact', 'injuries_notes' => 'Old injury',
        'medical_conditions_notes' => 'None',
    ], $overrides);
}

it('registers a pending client with a linked inactive CLIENT user', function () {
    $response = $this->post('/register', registrationPayload());

    $response->assertRedirect('/register/complete');
    $this->assertGuest();
    $client = Client::where('dni', '12345678')->firstOrFail();
    $user = User::where('email', 'applicant@gym.test')->firstOrFail();

    expect($client->status)->toBe(Client::STATUS_PENDING)
        ->and($client->email)->toBe($user->email)
        ->and($client->user_id)->toBe($user->id)
        ->and($user->is_active)->toBeFalse()
        ->and($user->hasRole(Role::CLIENT))->toBeTrue()
        ->and(Hash::check('password', $user->password))->toBeTrue();
});

it('rejects duplicate DNI and email and does not create another client', function () {
    Client::factory()->create(['dni' => '12345678']);
    User::factory()->create(['email' => 'taken@gym.test']);

    $this->post('/register', registrationPayload())->assertSessionHasErrors('dni');
    $this->post('/register', registrationPayload(['dni' => '87654321', 'email' => 'taken@gym.test']))
        ->assertSessionHasErrors('email');

    expect(Client::where('dni', '87654321')->exists())->toBeFalse();
});

it('does not allow the inactive registration account to log in before approval', function () {
    $this->post('/register', registrationPayload());
    auth()->logout();

    $this->post('/login', ['email' => 'applicant@gym.test', 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('keeps registration guest-only', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/register')->assertRedirect('/');
    $this->actingAs($user)->get('/register/complete')->assertRedirect('/');
});

it('rate limits registration submissions per IP', function () {
    $ip = '198.51.100.42';

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->withServerVariables(['REMOTE_ADDR' => $ip])->post('/register', [])->assertSessionHasErrors();
    }

    $this->withServerVariables(['REMOTE_ADDR' => $ip])->post('/register', [])->assertStatus(429);
});
