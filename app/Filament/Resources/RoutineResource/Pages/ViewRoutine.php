<?php

namespace App\Filament\Resources\RoutineResource\Pages;

use App\Actions\AssignRoutine;
use App\Filament\Resources\RoutineResource;
use App\Models\Client;
use App\Models\Routine;
use DomainException;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewRoutine extends ViewRecord
{
    protected static string $resource = RoutineResource::class;

    /**
     * Header actions on the detail view (SPEC-010 FR-003, FR-004, FR-006,
     * FR-007, FR-009, FR-010).
     *
     * Edit is auto-hidden on `archived` versions via RoutineResource::canEdit
     * (ERR-006). Activate is offered for `draft` versions only (FR-007); the
     * model method enforces the content rules (ERR-003, ERR-004), whose
     * DomainException is surfaced to the user as a notification. Assign to
     * clients is offered for `active` versions only (ERR-008): a modal
     * multi-select of clients delegates to App\Actions\AssignRoutine, which
     * handles supersession transactionally (AF-002, AC-11) and reassignment
     * to another version (FR-010, D-12 option 3). Both lifecycle actions are
     * authorized through the resource's update policy, enforced server-side
     * (AGENTS.md §17).
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('activate')
                ->label('Activate')
                ->icon('heroicon-o-arrow-up-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (Routine $record): bool => $record->status === Routine::STATUS_DRAFT)
                ->authorize(fn (Routine $record): bool => auth()->user()->can('update', $record))
                ->action(function (Routine $record): void {
                    try {
                        $record->activate();
                    } catch (DomainException $exception) {
                        Notification::make()
                            ->danger()
                            ->title($exception->getMessage())
                            ->send();
                    }
                }),
            Actions\Action::make('assign')
                ->label('Assign to clients')
                ->icon('heroicon-o-user-plus')
                ->color('primary')
                ->visible(fn (Routine $record): bool => $record->status === Routine::STATUS_ACTIVE)
                ->authorize(fn (Routine $record): bool => auth()->user()->can('update', $record))
                ->form([
                    Forms\Components\Select::make('client_ids')
                        ->label('Clients')
                        ->options(fn (): array => Client::query()->pluck('full_name', 'id')->all())
                        ->multiple()
                        ->searchable()
                        ->required(),
                ])
                ->action(function (Routine $record, array $data): void {
                    $clients = Client::query()->whereIn('id', $data['client_ids'])->get();

                    app(AssignRoutine::class)->handle($record, $clients);
                }),
        ];
    }
}
