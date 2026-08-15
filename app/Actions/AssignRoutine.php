<?php

namespace App\Actions;

use App\Models\Client;
use App\Models\Routine;
use App\Models\RoutineAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssignRoutine
{
    /**
     * Assign an `active` routine version to one or more clients (SPEC-010
     * FR-009, FR-010, BR-007, AR-03).
     *
     * Transactional: for each client, every existing active assignment is
     * deactivated (supersession, AF-002, AC-11 — history preserved) and a new
     * active RoutineAssignment is created with `assigned_at` (defaults to
     * now). This implements both the initial assignment (FR-009) and the
     * reassignment after versioning (FR-010, D-12 option 3). A client
     * therefore has at most one active assignment at a time (AR-03). The
     * action never creates, modifies or deletes any prescription row or
     * workout log (BR-007, AC-17).
     *
     * @param  Collection<int, Client>|array<int, Client>  $clients
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException when the user
     *                                                         cannot update routines
     * @throws ValidationException when the routine version is not active
     *                             (ERR-008) or a client does not exist
     */
    public function handle(Routine $routine, Collection|array $clients, ?Carbon $assignedAt = null): void
    {
        $this->authorize($routine);

        $this->validate($routine, $clients);

        $assignedAt ??= now();

        DB::transaction(function () use ($routine, $clients, $assignedAt): void {
            foreach ($clients as $client) {
                RoutineAssignment::query()
                    ->where('client_id', $client->id)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);

                RoutineAssignment::create([
                    'client_id' => $client->id,
                    'routine_id' => $routine->id,
                    'assigned_at' => $assignedAt,
                    'is_active' => true,
                ]);
            }
        });
    }

    /**
     * Server-side enforcement (SPEC-010 §9; AGENTS.md §17): assignment is an
     * update of the routine, authorized through RoutinePolicy::update
     * (ADMIN | TRAINER). The Filament action visibility already restricts the
     * UI; this check is defense in depth so the Action stays safe outside the
     * UI (the RenewMembership / ProvisionClientUser precedent).
     */
    protected function authorize(Routine $routine): void
    {
        Gate::authorize('update', $routine);
    }

    /**
     * Validate assignment input (SPEC-010 ERR-008, BR-007, AR-03): only
     * `active` versions are assignable; the clients must exist (FK integrity,
     * ERR-001 by analogy).
     *
     * @param  Collection<int, Client>|array<int, Client>  $clients
     */
    protected function validate(Routine $routine, Collection|array $clients): void
    {
        if ($routine->status !== Routine::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'routine' => 'Only an active routine version can be assigned to clients.',
            ]);
        }

        $clientIds = collect($clients)->pluck('id')->all();

        if (count($clientIds) > 0 && count($clientIds) !== Client::query()->whereKey($clientIds)->count()) {
            throw ValidationException::withMessages([
                'clients' => 'One or more selected clients do not exist.',
            ]);
        }
    }
}
