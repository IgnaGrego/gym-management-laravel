<?php

namespace App\Actions;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Role;
use App\Models\Turno;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateBooking
{
    /**
     * Create a confirmed booking for a client on a turno (SPEC-007 FR-001,
     * BR-001..BR-010; ERR-001..ERR-008, ERR-011).
     *
     * The single enforcement point for the booking create path. Non-atomic
     * rules are validated first (client/turno exist, access gate, turno
     * active, lead time / time validity); the race-sensitive capacity and
     * duplicate checks are re-evaluated inside a transaction with a pessimistic
     * row lock on the turno (ADR-006), then the booking is inserted with
     * status `confirmed`, booked_at = now and booked_by = the authenticated
     * staff User (BK-12). Creating a booking never touches a Client, Turno,
     * Membership or Payment record (BR-002, C-07).
     *
     * @param  int  $clientId  the client record id (BR-002)
     * @param  int  $turnoId  the turno id (BR-002)
     * @param  string|null  $notes  optional free text
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException when the user
     *                                                         cannot create bookings
     * @throws ValidationException when any booking rule is violated
     */
    public function handle(int $clientId, int $turnoId, ?string $notes = null): Booking
    {
        $this->authorize($clientId);

        $client = Client::find($clientId);
        $turno = Turno::find($turnoId);

        $this->validate($client, $turno);

        return DB::transaction(function () use ($clientId, $turnoId, $notes): Booking {
            // Pessimistic row lock on the turno (ADR-006): concurrent booking
            // attempts for the same turno serialize on this lock, so the
            // capacity re-check below is atomic and overselling is impossible.
            $turno = Turno::query()->lockForUpdate()->findOrFail($turnoId);

            // Capacity (BR-008, ERR-007, ERR-011): only confirmed bookings
            // count; a full turno is rejected inside the lock.
            if (Booking::confirmedCountForTurno($turnoId) >= $turno->capacity_limit) {
                throw ValidationException::withMessages([
                    'turno_id' => 'Este turno está lleno.',
                ]);
            }

            // Duplicate invariant (BR-009, ERR-008): at most one confirmed
            // booking per client per turno.
            if (Booking::query()
                ->where('client_id', $clientId)
                ->where('turno_id', $turnoId)
                ->confirmed()
                ->exists()) {
                throw ValidationException::withMessages([
                    'turno_id' => 'Este cliente ya tiene una reserva confirmada para este turno.',
                ]);
            }

            return Booking::create([
                'client_id' => $clientId,
                'turno_id' => $turnoId,
                'status' => Booking::STATUS_CONFIRMED,
                'booked_at' => now(),
                // Self-service bookings persist booked_by = null (SPEC-013
                // CP-09, AF-007, SPEC-007 BK-12); staff bookings keep the
                // authenticated staff User.
                'booked_by' => auth()->user()?->client?->id === $clientId ? null : auth()->id(),
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Server-side enforcement (SPEC-007 BR-012, §9; AGENTS.md §17), extended
     * for the CLIENT self-service branch (SPEC-013 FR-006, BR-004; ADR-007).
     *
     * A CLIENT may book only their OWN linked Client record; a staff member
     * booking on behalf of any client (including their own) goes through the
     * unchanged ADMIN/TRAINER gate (BK-02). The Filament page is already gated
     * by the panel; this is defense in depth so the Action stays safe outside
     * the UI (the RegisterPayment / AssignRoutine precedent).
     */
    protected function authorize(int $clientId): void
    {
        $user = auth()->user();

        // Self-service branch: the actor is booking their own linked Client.
        if ($user !== null && $user->client?->id === $clientId) {
            abort_unless($user->hasRole(Role::CLIENT), 403);

            return;
        }

        // Staff branch (admin panel): unchanged ADMIN/TRAINER gate (BK-02).
        Gate::authorize('create', Booking::class);
    }

    /**
     * Validate the non-atomic booking rules (ERR-002..ERR-006, BR-002,
     * BR-005, BR-006, BR-007).
     *
     * The capacity and duplicate rules are NOT checked here: they are
     * re-evaluated inside the transaction under the row lock (ADR-006).
     */
    protected function validate(?Client $client, ?Turno $turno): void
    {
        if ($client === null) {
            throw ValidationException::withMessages([
                'client_id' => 'El cliente seleccionado no existe.',
            ]);
        }

        if ($turno === null) {
            throw ValidationException::withMessages([
                'turno_id' => 'El turno seleccionado no existe.',
            ]);
        }

        // Access gate (BR-005, D-05 option 1): an active membership with
        // end_date >= today is required, with no grace period.
        if (! $client->hasQualifyingMembership()) {
            throw ValidationException::withMessages([
                'client_id' => $this->denialMessage($client->accessDenialReason()),
            ]);
        }

        // Turno must be active (BR-006, ERR-003).
        if ($turno->status !== Turno::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'turno_id' => 'El turno no se puede reservar.',
            ]);
        }

        $today = Carbon::today();

        // Turno date must be today or later (BR-007, ERR-004).
        if ($turno->date->lt($today)) {
            throw ValidationException::withMessages([
                'turno_id' => 'La fecha del turno ya pasó.',
            ]);
        }

        // Lead time: within today .. today + 7 days inclusive (BR-007, BK-04,
        // ERR-005).
        if ($turno->date->gt($today->copy()->addDays(7))) {
            throw ValidationException::withMessages([
                'turno_id' => 'El turno está fuera de la ventana de anticipación de reserva.',
            ]);
        }

        // Same-day turno: the start time must not have passed (BR-007, ERR-004).
        if ($turno->date->isSameDay($today)) {
            $start = Carbon::createFromFormat('H:i', $turno->start_time);

            if ($start->lte(now())) {
                throw ValidationException::withMessages([
                    'turno_id' => 'El turno ya comenzó.',
                ]);
            }
        }
    }

    /**
     * Human-readable access-denial reason (ERR-006, BR-005).
     */
    protected function denialMessage(?string $reason): string
    {
        return match ($reason) {
            Client::ACCESS_DENIED_NO_MEMBERSHIP => 'Este cliente no tiene membresía y no puede reservarse.',
            Client::ACCESS_DENIED_MEMBERSHIP_EXPIRED => 'La membresía de este cliente venció y no puede reservarse.',
            Client::ACCESS_DENIED_NO_ACTIVE_MEMBERSHIP => 'Este cliente no tiene membresía activa y no puede reservarse.',
            default => 'Este cliente no puede reservarse.',
        };
    }
}
