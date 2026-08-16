<?php

namespace App\Filament\Resources\TurnoResource\Pages;

use App\Filament\Resources\TurnoResource;
use App\Models\Turno;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTurno extends ViewRecord
{
    protected static string $resource = TurnoResource::class;

    /**
     * Header actions on the detail view (SPEC-006 FR-003, FR-005, FR-006,
     * FR-007).
     *
     * Same visibility/authorization rules as the list row actions: Edit is
     * auto-hidden on `cancelled` turnos via TurnoResource::canEdit (BR-004);
     * Deactivate for active, Reactivate for inactive, Cancel for
     * active/inactive (BR-003). Each lifecycle action calls the model method,
     * which is the final state-rule enforcement (ERR-006).
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('deactivate')
                ->label('Desactivar')
                ->icon('heroicon-o-arrow-down-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (Turno $record): bool => $record->status === Turno::STATUS_ACTIVE)
                ->authorize(fn (Turno $record): bool => auth()->user()->can('update', $record))
                ->action(fn (Turno $record) => $record->deactivate()),
            Actions\Action::make('reactivate')
                ->label('Reactivar')
                ->icon('heroicon-o-arrow-up-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (Turno $record): bool => $record->status === Turno::STATUS_INACTIVE)
                ->authorize(fn (Turno $record): bool => auth()->user()->can('update', $record))
                ->action(fn (Turno $record) => $record->reactivate()),
            Actions\Action::make('cancel')
                ->label('Cancelar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (Turno $record): bool => in_array($record->status, [Turno::STATUS_ACTIVE, Turno::STATUS_INACTIVE], true))
                ->authorize(fn (Turno $record): bool => auth()->user()->can('update', $record))
                ->action(fn (Turno $record) => $record->cancel()),
        ];
    }
}
