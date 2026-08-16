<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use App\Models\RoutineAssignment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RoutineAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'routineAssignments';

    protected static ?string $title = 'Rutinas';

    /**
     * The client's routine-assignment history (SPEC-010 FR-011 client side,
     * BR-007, BR-008), ordered by assigned_at.
     *
     * Read-only: routine assignment management happens on the routine side
     * (RoutineResource — Assign to clients / Unassign), the same way the
     * memberships history is read-only here. The routine column shows the
     * assigned version (`{name} — v{version_number}`); the active badge marks
     * the current assignment (a client has at most one, AR-03). Access is
     * gated by the ClientPolicy (the parent resource, ADMIN-only per
     * SPEC-002) and by RoutineAssignmentPolicy::viewAny (Filament checks the
     * related model's policy).
     */
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('routine.name')
                    ->label('Rutina')
                    ->formatStateUsing(fn (?string $state, RoutineAssignment $record): string => $state === null ? '—' : $state.' — v'.$record->routine?->version_number),
                Tables\Columns\TextColumn::make('assigned_at')
                    ->label('Asignado el')
                    ->dateTime(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->defaultSort('assigned_at', 'desc')
            ->actions([])
            ->bulkActions([]);
    }
}
