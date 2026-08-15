<?php

namespace App\Filament\Resources\CuotaResource\RelationManagers;

use App\Models\Payment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payments';

    /**
     * The cuota's payment history (SPEC-005 FR-003).
     *
     * Read-only: payments are registered from PaymentResource (FR-004), never
     * from this relation manager. Access is gated by PaymentPolicy::viewAny
     * (Filament checks the related model's policy — the
     * MembershipsRelationManager precedent), ADMIN|TRAINER.
     */
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('amount')
                    ->numeric(decimalPlaces: 2),
                Tables\Columns\TextColumn::make('method')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Payment::METHOD_CASH => 'success',
                        Payment::METHOD_TRANSFER => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('payment_date')
                    ->date('Y-m-d'),
                Tables\Columns\TextColumn::make('reference')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === Payment::STATUS_CONFIRMED ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('recordedBy.name')
                    ->label('Recorded by'),
            ])
            ->defaultSort('payment_date')
            ->actions([])
            ->bulkActions([]);
    }
}
