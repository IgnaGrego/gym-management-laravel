<?php

namespace App\Filament\Resources\MembershipResource\Pages;

use App\Actions\RenewMembership;
use App\Filament\Resources\MembershipResource;
use App\Models\Membership;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;

class ViewMembership extends ViewRecord
{
    protected static string $resource = MembershipResource::class;

    /**
     * Header actions on the detail view (SPEC-004 FR-005, FR-006).
     *
     * Same rules as the list row actions: Cancel is offered for
     * pending/active memberships (BR-008, ERR-004) and Renew for
     * active/expired memberships (AM-08, ERR-005). The pending -> active
     * transition is deliberately NOT offered here (FR-008, BR-006).
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel')
                ->label('Cancelar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (Membership $record): bool => in_array($record->status, [Membership::STATUS_PENDING, Membership::STATUS_ACTIVE], true))
                ->authorize(fn (Membership $record): bool => auth()->user()->can('update', $record))
                ->action(fn (Membership $record) => $record->cancel()),
            Actions\Action::make('renew')
                ->label('Renovar')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->visible(fn (Membership $record): bool => in_array($record->status, [Membership::STATUS_ACTIVE, Membership::STATUS_EXPIRED], true))
                ->authorize(fn (): bool => auth()->user()->can('create', Membership::class))
                ->form([
                    Forms\Components\DatePicker::make('start_date')
                        ->label('Fecha de inicio')
                        ->required()
                        ->default(fn (Membership $record): string => $record->end_date->copy()->addDay()->toDateString()),
                    Forms\Components\TextInput::make('duration_days')
                        ->label('Duración (días)')
                        ->numeric()
                        ->required()
                        ->integer()
                        ->minValue(1)
                        ->default(fn (Membership $record): int => $record->duration_days),
                ])
                ->action(function (Membership $record, array $data): void {
                    app(RenewMembership::class)->handle(
                        $record,
                        $data['start_date'] ?? null,
                        isset($data['duration_days']) && $data['duration_days'] !== '' ? (int) $data['duration_days'] : null,
                    );
                }),
        ];
    }
}
