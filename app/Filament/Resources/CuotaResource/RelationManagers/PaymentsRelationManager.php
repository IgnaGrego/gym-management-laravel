<?php

namespace App\Filament\Resources\CuotaResource\RelationManagers;

use App\Models\Payment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Pagos';

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
                    ->label('Monto')
                    ->numeric(decimalPlaces: 2),
                Tables\Columns\TextColumn::make('method')
                    ->label('Método')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Payment::METHOD_CASH => 'success',
                        Payment::METHOD_TRANSFER => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => Payment::methodLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Fecha de pago')
                    ->date('Y-m-d'),
                Tables\Columns\TextColumn::make('reference')
                    ->label('Referencia')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => $state === Payment::STATUS_CONFIRMED ? 'success' : 'gray')
                    ->formatStateUsing(fn (string $state): string => Payment::statusLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('recordedBy.name')
                    ->label('Registrado por'),
            ])
            ->defaultSort('payment_date')
            ->actions([])
            ->bulkActions([]);
    }
}
