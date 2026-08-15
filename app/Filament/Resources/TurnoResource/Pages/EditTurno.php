<?php

namespace App\Filament\Resources\TurnoResource\Pages;

use App\Filament\Resources\TurnoResource;
use DomainException;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditTurno extends EditRecord
{
    protected static string $resource = TurnoResource::class;

    /**
     * Capacity-lowering guard (SPEC-007 BR-014, ERR-012, NC-01).
     *
     * The business rule lives on Turno::assertCapacityLimitNotBelowConfirmed();
     * this page is thin glue: on a DomainException it surfaces a validation
     * error on the capacity_limit field (keyed under Filament's `data.` form
     * state path so the error renders on the field) and the edit is rejected.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        try {
            $this->record->assertCapacityLimitNotBelowConfirmed((int) $data['capacity_limit']);
        } catch (DomainException $e) {
            throw ValidationException::withMessages([
                'data.capacity_limit' => $e->getMessage(),
            ]);
        }

        return $data;
    }
}

