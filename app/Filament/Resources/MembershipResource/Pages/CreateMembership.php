<?php

namespace App\Filament\Resources\MembershipResource\Pages;

use App\Filament\Resources\MembershipResource;
use App\Models\Membership;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateMembership extends CreateRecord
{
    protected static string $resource = MembershipResource::class;

    /**
     * Wrap the create in a transaction (ADR-005).
     *
     * The Membership `created` hook auto-generates the membership's single
     * cuota (SPEC-005 FR-001); wrapping the create makes membership + cuota
     * commit atomically, matching the RenewMembership precedent.
     */
    protected function handleRecordCreation(array $data): Membership
    {
        return DB::transaction(fn (): Membership => Membership::create($data));
    }
}
