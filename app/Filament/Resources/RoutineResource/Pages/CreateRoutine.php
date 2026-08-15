<?php

namespace App\Filament\Resources\RoutineResource\Pages;

use App\Filament\Resources\RoutineResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRoutine extends CreateRecord
{
    protected static string $resource = RoutineResource::class;

    /**
     * Set the audit-only creator on create (SPEC-010 FR-001, BR-011; AR-08):
     * status / version_number / replaces_id come from the DB defaults; the
     * created_by FK is filled here, never by a user-entered form field.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
