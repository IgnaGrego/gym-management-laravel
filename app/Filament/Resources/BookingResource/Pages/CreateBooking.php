<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Actions\CreateBooking as CreateBookingAction;
use App\Filament\Resources\BookingResource;
use App\Models\Booking;
use Filament\Resources\Pages\CreateRecord;

class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;

    /**
     * Delegate the whole create to the CreateBooking Action (SPEC-007 FR-001).
     *
     * The Action authorizes (BR-012), validates the booking rules (access gate,
     * turno state, lead time, capacity, duplicate — ERR-002..ERR-008, ERR-011)
     * and persists the confirmed booking with booked_at = now and booked_by =
     * the authenticated staff User (BK-12). ValidationException messages
     * surface as form errors.
     */
    protected function handleRecordCreation(array $data): Booking
    {
        return app(CreateBookingAction::class)->handle(
            (int) $data['client_id'],
            (int) $data['turno_id'],
            $data['notes'] ?? null,
        );
    }
}
