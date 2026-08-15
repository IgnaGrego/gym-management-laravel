<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class UserPolicy
{
    /**
     * Only ADMIN may view the staff user list (SPEC-001 BR-006, FR-007).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * Only ADMIN may view a user record (SPEC-001 BR-006).
     */
    public function view(User $user, User $model): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * Only ADMIN may create staff users (SPEC-001 BR-006, FR-007).
     */
    public function create(User $user): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * Only ADMIN may update users (role changes and deactivation)
     * (SPEC-001 BR-006, FR-007).
     */
    public function update(User $user, User $model): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * No delete policy is registered on purpose: user records are never
     * hard-deleted in the MVP (SPEC-001 BR-007); deactivation is an update.
     */
}
