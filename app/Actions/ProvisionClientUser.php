<?php

namespace App\Actions;

use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProvisionClientUser
{
    /**
     * Provision the linked CLIENT user account for an existing client
     * (SPEC-002 FR-005; BR-002, BR-003).
     *
     * Explicit, optional and transactional: it creates a User with the CLIENT
     * role and links it to the client via clients.user_id. No event, no
     * notification and no email is dispatched (welcome email is out of scope,
     * SPEC-002 §12).
     */
    public function handle(Client $client, string $loginEmail, string $password): User
    {
        $this->authorize($client);

        $this->validate($client, $loginEmail, $password);

        return DB::transaction(function () use ($client, $loginEmail, $password): User {
            $user = User::create([
                // users.name is a snapshot of the client's full name taken at
                // provisioning time; editing the Client never syncs it
                // (SPEC-002 architecture §12 design default for OQ-05).
                'name' => $client->full_name,
                'email' => $loginEmail,
                'password' => $password,
                'is_active' => true,
            ]);

            $user->roles()->attach(Role::firstOrCreate(['name' => Role::CLIENT]));

            $client->user()->associate($user);
            $client->save();

            return $user;
        });
    }

    /**
     * Server-side enforcement (SPEC-002 §9; AGENTS.md §17): only an ADMIN may
     * manage client records (ClientPolicy) and create users (UserPolicy). The
     * Filament page gate already restricts the route to ADMIN; this check is
     * defense in depth so the Action stays safe outside the UI.
     */
    protected function authorize(Client $client): void
    {
        Gate::authorize('update', $client);
        Gate::authorize('create', User::class);
    }

    /**
     * Validate provisioning input (SPEC-002 ERR-003, ERR-004; SPEC-001
     * ERR-005, A-05): the login email must be valid and unique among users;
     * the password must satisfy the framework default policy (min length 8);
     * a client may have at most one linked account.
     */
    protected function validate(Client $client, string $loginEmail, string $password): void
    {
        Validator::make([
            'login_email' => $loginEmail,
            'password' => $password,
        ], [
            'login_email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ])->validate();

        if ($client->user_id !== null) {
            throw ValidationException::withMessages([
                'login_email' => 'This client already has a linked user account.',
            ]);
        }
    }
}
