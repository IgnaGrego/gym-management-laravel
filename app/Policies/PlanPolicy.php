<?php

namespace App\Policies;

use App\Models\Plan;
use App\Models\Role;
use App\Models\User;

class PlanPolicy
{
    /**
     * Only ADMIN may list / search plans (SPEC-003 BR-006, FR-002).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * Only ADMIN may view a plan's detail (SPEC-003 BR-006, FR-003).
     */
    public function view(User $user, Plan $plan): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * Only ADMIN may create plans (SPEC-003 BR-006, FR-001).
     */
    public function create(User $user): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * Only ADMIN may update plans; this covers field edits (FR-004) AND the
     * activate/deactivate lifecycle transitions (FR-005), the same way
     * UserPolicy::update covers user deactivation (SPEC-003 BR-006).
     */
    public function update(User $user, Plan $plan): bool
    {
        return $user->hasRole(Role::ADMIN);
    }

    /**
     * No delete policy is registered on purpose: plan records are never
     * hard-deleted in the MVP; deactivation is used instead and no delete
     * operation exists (SPEC-003 BR-004, AC-10).
     */
}
