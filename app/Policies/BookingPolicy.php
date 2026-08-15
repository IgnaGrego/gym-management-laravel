<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\Role;
use App\Models\User;

class BookingPolicy
{
    /**
     * Only ADMIN or TRAINER may list / filter bookings (SPEC-007 BR-012,
     * FR-002, BK-02).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([Role::ADMIN, Role::TRAINER]);
    }

    /**
     * ADMIN or TRAINER may view a booking's detail (SPEC-007 BR-012, FR-003,
     * BK-02). A CLIENT may view only their OWN booking (SPEC-013 BR-002, C-13;
     * ADR-007).
     */
    public function view(User $user, Booking $booking): bool
    {
        return $user->hasAnyRole([Role::ADMIN, Role::TRAINER])
            || ($user->hasRole(Role::CLIENT) && $booking->client_id === $user->clientId());
    }

    /**
     * Only ADMIN or TRAINER may create a booking on behalf of a client
     * (SPEC-007 BR-012, FR-001, BK-02).
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole([Role::ADMIN, Role::TRAINER]);
    }

    /**
     * ADMIN or TRAINER may update a booking; this covers the cancel
     * transition (FR-004), the same way TurnoPolicy::update covers the
     * deactivate/reactivate/cancel transitions (SPEC-007 BR-012, BK-02). A
     * CLIENT may update (cancel) only their OWN booking (SPEC-013 FR-007,
     * BR-005; ADR-007).
     *
     * The state rule (BR-004 — a `cancelled` booking cannot be cancelled
     * again) is NOT an authorization rule: it is enforced by Booking::cancel().
     */
    public function update(User $user, Booking $booking): bool
    {
        return $user->hasAnyRole([Role::ADMIN, Role::TRAINER])
            || ($user->hasRole(Role::CLIENT) && $booking->client_id === $user->clientId());
    }

    /**
     * No delete policy is registered on purpose: booking records are never
     * hard-deleted in the MVP; cancellation is used instead and no delete
     * operation exists (SPEC-007 BR-011, AC-13).
     */
}
