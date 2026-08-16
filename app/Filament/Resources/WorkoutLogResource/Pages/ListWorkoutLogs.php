<?php

namespace App\Filament\Resources\WorkoutLogResource\Pages;

use App\Filament\Resources\WorkoutLogResource;
use App\Models\Client;
use App\Models\WorkoutLog;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;

class ListWorkoutLogs extends ListRecords
{
    protected static string $resource = WorkoutLogResource::class;

    /**
     * Header actions (SPEC-011 FR-001, FR-002, FR-003, FR-004; BR-007).
     *
     * Create records a new log (authorized via WorkoutLogPolicy::create).
     * "View client progress" opens the per-client progress page (a modal
     * client select; authorized via WorkoutLogPolicy::viewAny, BR-007,
     * WL-09) — the FR-003 history + FR-004 comparison surface. The progress
     * page belongs to this standalone ADMIN|TRAINER resource so TRAINER can
     * reach it (the UI-placement constraint of SPEC-011 §2/§9).
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('viewProgress')
                ->label('Ver progreso del cliente')
                ->icon('heroicon-o-chart-bar')
                ->color('primary')
                ->authorize(fn (): bool => auth()->user()->can('viewAny', WorkoutLog::class))
                ->form([
                    Forms\Components\Select::make('client_id')
                        ->label('Cliente')
                        ->options(fn (): array => Client::query()->pluck('full_name', 'id')->all())
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->redirect(WorkoutLogResource::getUrl('progress', ['client' => $data['client_id']]));
                }),
        ];
    }
}
