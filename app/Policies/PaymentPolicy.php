<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\Role;
use App\Models\User;

class PaymentPolicy
{
    /**
     * ADMIN and TRAINER may list / search payments (SPEC-005 BR-011, PY-01;
     * FR-005).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([Role::ADMIN, Role::TRAINER]);
    }

    /**
     * ADMIN and TRAINER may view a payment's detail (SPEC-005 BR-011, FR-005).
     * A CLIENT may view only their OWN payment, reached through its cuota and
     * membership (SPEC-013 BR-002, C-13; ADR-007).
     */
    public function view(User $user, Payment $payment): bool
    {
        return $user->hasAnyRole([Role::ADMIN, Role::TRAINER])
            || ($user->hasRole(Role::CLIENT) && $payment->cuota?->membership?->client_id === $user->clientId());
    }

    /**
     * ADMIN and TRAINER may register a payment (SPEC-005 BR-011, FR-004;
     * D-15 option 1, PY-01).
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole([Role::ADMIN, Role::TRAINER]);
    }

    /**
     * No update policy is registered on purpose: a confirmed payment is
     * immutable in the MVP — no edit, no status transition (BR-006, PY-05).
     *
     * No delete policy is registered on purpose: payments are never
     * hard-deleted (BR-009); no delete operation is provided.
     */
}
