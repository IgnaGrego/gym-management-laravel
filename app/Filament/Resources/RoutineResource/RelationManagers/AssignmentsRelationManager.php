<?php

namespace App\Filament\Resources\RoutineResource\RelationManagers;

use App\Models\RoutineAssignment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    protected static ?string $title = 'Clientes asignados';

    /**
     * The clients assigned to this routine version (SPEC-010 FR-011) — for a
     * version, which clients are currently assigned, so staff can manage
     * reassignment after versioning (D-12 option 3).
     *
     * Read-only except for the per-row Unassign action (FR-010, AF-006): the
     * active row is deactivated via RoutineAssignment::deactivate() and the
     * history row is preserved (BR-008). Assignment creation goes through the
     * "Assign to clients" header action on the view page (via
     * App\Actions\AssignRoutine), never through a relation-manager create
     * action. Access is gated by RoutineAssignmentPolicy::viewAny / view /
     * update (Filament checks the related model's policy — the
     * MembershipsRelationManager precedent).
     */
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('client.full_name')
                    ->label('Cliente')
                    ->searchable(),
                Tables\Columns\TextColumn::make('assigned_at')
                    ->label('Asignado el')
                    ->dateTime(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->defaultSort('assigned_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('unassign')
                    ->label('Desasignar')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (RoutineAssignment $record): bool => (bool) $record->is_active)
                    ->authorize(fn (RoutineAssignment $record): bool => auth()->user()->can('update', $record))
                    ->action(fn (RoutineAssignment $record) => $record->deactivate()),
            ])
            ->bulkActions([]);
    }
}
