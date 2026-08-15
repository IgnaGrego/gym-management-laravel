<?php

namespace App\Policies;

use App\Models\Membership;
use App\Models\Role;
use App\Models\User;

class MembershipPolicy
{
    /**
     * Only ADMIN may list / search memberships (SPEC-004 BR-015, FR-002).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * ADMIN may view a membership's detail (SPEC-004 BR-015, FR-003). A
     * CLIENT may view only their OWN membership (SPEC-013 BR-002, C-13;
     * ADR-007).
     */
    public function view(User $user, Membership $membership): bool
    {
        return $user->hasRole(Role::ADMIN)
            || ($user->hasRole(Role::CLIENT) && $membership->client_id === $user->clientId());
    }

    /**
     * Only ADMIN may create memberships (SPEC-004 BR-015, FR-001). This also
     * covers renewal, which creates a NEW membership record (FR-005).
     */
    public function create(User $user): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * Only ADMIN may update memberships; this covers the cancel transition
     * (SPEC-004 BR-015, FR-006), the same way PlanPolicy::update covers
     * activate/deactivate.
     */
    public function update(User $user, Membership $membership): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * No delete policy is registered on purpose: membership records are never
     * hard-deleted in the MVP; no delete operation is provided (SPEC-004
     * BR-014, AC-17).
     *
     * No activate ability is registered on purpose: the pending -> active
     * transition is NOT an ADMIN UI operation; it is a system-internal
     * contract invoked by SPEC-005 through Membership::activate() (SPEC-004
     * §9, BR-006, FR-008).
     */
}
