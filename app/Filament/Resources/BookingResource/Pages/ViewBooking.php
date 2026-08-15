<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use App\Models\Booking;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBooking extends ViewRecord
{
    protected static string $resource = BookingResource::class;

    /**
     * Header action on the detail view (SPEC-007 FR-004).
     *
     * Cancel is available only for `confirmed` bookings (BR-004); it is
     * authorized through the update policy and calls Booking::cancel(), which
     * is the final state-rule enforcement (ERR-009). No edit or delete action
     * exists (BR-011).
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel')
                ->label('Cancel')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (Booking $record): bool => $record->status === Booking::STATUS_CONFIRMED)
                ->authorize(fn (Booking $record): bool => auth()->user()->can('update', $record))
                ->action(fn (Booking $record) => $record->cancel()),
        ];
    }
}
