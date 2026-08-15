<?php

namespace App\Filament\Resources\ExerciseResource\Pages;

use App\Filament\Resources\ExerciseResource;
use App\Models\Exercise;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewExercise extends ViewRecord
{
    protected static string $resource = ExerciseResource::class;

    /**
     * Header actions on the detail view (SPEC-009 FR-003, FR-005).
     *
     * Same visibility/authorization rules as the list row actions: Edit
     * (allowed in both statuses, FR-004 / BR-010 — no terminal state),
     * Deactivate for active and Activate for inactive (FR-005, BR-007).
     * Each lifecycle action is a single-field update of is_active authorized
     * through the resource's update policy, enforced server-side (AGENTS.md
     * §17).
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('deactivate')
                ->label('Deactivate')
                ->icon('heroicon-o-arrow-down-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (Exercise $record): bool => (bool) $record->is_active)
                ->authorize(fn (Exercise $record): bool => auth()->user()->can('update', $record))
                ->action(fn (Exercise $record) => $record->update(['is_active' => false])),
            Actions\Action::make('activate')
                ->label('Activate')
                ->icon('heroicon-o-arrow-up-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (Exercise $record): bool => ! (bool) $record->is_active)
                ->authorize(fn (Exercise $record): bool => auth()->user()->can('update', $record))
                ->action(fn (Exercise $record) => $record->update(['is_active' => true])),
        ];
    }
}
