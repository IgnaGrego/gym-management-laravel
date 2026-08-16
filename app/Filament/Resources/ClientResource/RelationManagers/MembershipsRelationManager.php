<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use App\Models\Membership;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MembershipsRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    protected static ?string $title = 'Membresías';

    /**
     * Client membership history (SPEC-004 FR-004, C-08).
     *
     * Shows ALL memberships of the selected client, including past states, in
     * chronological order by start_date. Read-only: membership creation
     * happens from MembershipResource (FR-001); no create/attach actions are
     * offered. Access is gated by the ClientPolicy (the parent resource) and
     * by MembershipPolicy::viewAny (Filament checks the related model's
     * policy), both ADMIN-only (BR-015).
     */
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('plan.name')
                    ->label('Plan'),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Fecha de inicio')
                    ->date('Y-m-d'),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Fecha de fin')
                    ->date('Y-m-d'),
                Tables\Columns\TextColumn::make('duration_days')
                    ->label('Duración (días)'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Membership::STATUS_PENDING => 'warning',
                        Membership::STATUS_ACTIVE => 'success',
                        Membership::STATUS_EXPIRED => 'gray',
                        Membership::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => Membership::statusLabels()[$state] ?? $state),
            ])
            ->defaultSort('start_date')
            ->actions([])
            ->bulkActions([]);
    }
}
