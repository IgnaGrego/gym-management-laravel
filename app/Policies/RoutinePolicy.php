<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\Routine;
use App\Models\User;

class RoutinePolicy
{
    /**
     * Only ADMIN or TRAINER may list / search / filter routines (SPEC-010
     * BR-009, FR-002; AR-08 — the TurnoPolicy / ExercisePolicy precedent).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([Role::ADMIN, Role::TRAINER]);
    }

    /**
     * ADMIN or TRAINER may view a routine version's detail and version
     * history (SPEC-010 BR-009, FR-003, FR-004; AR-08). A CLIENT may view a
     * routine version only when it is their CURRENT active assignment
     * (SPEC-013 BR-007; ADR-007).
     */
    public function view(User $user, Routine $routine): bool
    {
        return $user->hasAnyRole([Role::ADMIN, Role::TRAINER])
            || ($user->hasRole(Role::CLIENT)
                && $routine->assignments()
                    ->where('client_id', $user->clientId())
                    ->where('is_active', true)
                    ->exists());
    }

    /**
     * Only ADMIN or TRAINER may create routines (SPEC-010 BR-009, FR-001;
     * AR-08).
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole([Role::ADMIN, Role::TRAINER]);
    }

    /**
     * Only ADMIN or TRAINER may update routines; this covers draft in-place
     * edits (FR-005), active versioning (FR-006), activation (FR-007) and the
     * assignment operations (FR-009..FR-011), the same way
     * TurnoPolicy::update covers the turno lifecycle and ExercisePolicy::update
     * covers activate/deactivate (SPEC-010 BR-009, AR-08).
     *
     * The state rules (ERR-006 — an archived version is read-only; ERR-008 —
     * only active versions assignable) are NOT authorization rules: they are
     * enforced by the model methods, the Actions and RoutineResource::canEdit.
     */
    public function update(User $user, Routine $routine): bool
    {
        return $user->hasAnyRole([Role::ADMIN, Role::TRAINER]);
    }

    /**
     * No delete policy is registered on purpose: routine records are never
     * hard-deleted in the MVP; archiving and assignment deactivation are used
     * instead and no delete operation exists (SPEC-010 BR-008, ERR-009).
     */
}
