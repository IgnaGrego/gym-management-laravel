<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\Role;
use App\Models\User;

class ClientPolicy
{
    /**
     * Only ADMIN may list / search client records (SPEC-002 BR-004, FR-002).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * ADMIN may view any client record, including health notes (SPEC-002
     * BR-004, BR-007, FR-003). A CLIENT may view only their OWN record
     * (SPEC-013 BR-002, C-13; ADR-007).
     */
    public function view(User $user, Client $client): bool
    {
        return $user->hasRole(Role::ADMIN)
            || ($user->hasRole(Role::CLIENT) && $client->id === $user->clientId());
    }

    /**
     * Only ADMIN may create client records (SPEC-002 BR-004, FR-001).
     */
    public function create(User $user): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * ADMIN may update client records; this also covers the link change
     * performed by provisioning (SPEC-002 BR-004, FR-004, FR-005). A CLIENT
     * may update only their OWN record (SPEC-013 BR-008, NC-01; ADR-007); the
     * editable-field whitelist (email/phone/emergency_contact) is enforced by
     * the UpdateProfileRequest, not here.
     */
    public function update(User $user, Client $client): bool
    {
        return $user->hasRole(Role::ADMIN)
            || ($user->hasRole(Role::CLIENT) && $client->id === $user->clientId());
    }

    /**
     * Only ADMIN may approve a pending registration (SPEC-012 BR-009, AS-06;
     * derived from SPEC-002 BR-004, client management is ADMIN-only).
     *
     * Authorization only answers who may act; the legal state (only `pending`
     * clients) is enforced by the Client::approve() transition guard
     * (SPEC-012 ERR-007, BR-005).
     */
    public function approve(User $user, Client $client): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * Only ADMIN may reject a pending registration (SPEC-012 BR-009, AS-06).
     *
     * See approve(): authorization is ADMIN-only; the state rule is enforced
     * by the Client::reject() transition guard (ERR-007, BR-005).
     */
    public function reject(User $user, Client $client): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * No delete policy is registered on purpose: client records are never
     * hard-deleted in the MVP; no delete operation is provided (SPEC-002
     * BR-006, AC-11).
     */
}
