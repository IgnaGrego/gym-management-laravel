<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CuotaResource\Pages;
use App\Filament\Resources\CuotaResource\RelationManagers;
use App\Models\Cuota;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CuotaResource extends Resource
{
    protected static ?string $model = Cuota::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Cuotas';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $modelLabel = 'Cuota';

    protected static ?string $pluralModelLabel = 'Cuotas';

    /**
     * Cuota detail view (SPEC-005 FR-003, FR-007).
     *
     * Shows the cuota's membership context (client, DNI, plan, period), the
     * amount, the current status badge and paid_at when set. The payment
     * history is rendered by the PaymentsRelationManager (FR-003).
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Cuota')
                    ->schema([
                        Infolists\Components\TextEntry::make('membership.client.full_name')
                            ->label('Cliente'),
                        Infolists\Components\TextEntry::make('membership.client.dni')
                            ->label('DNI'),
                        Infolists\Components\TextEntry::make('membership.plan.name')
                            ->label('Plan'),
                        Infolists\Components\TextEntry::make('membership.start_date')
                            ->label('Inicio del período')
                            ->date('Y-m-d'),
                        Infolists\Components\TextEntry::make('membership.end_date')
                            ->label('Fin del período')
                            ->date('Y-m-d'),
                        Infolists\Components\TextEntry::make('amount')
                            ->label('Monto')
                            ->numeric(decimalPlaces: 2),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn (string $state): string => static::statusColor($state))
                            ->formatStateUsing(fn (string $state): string => Cuota::statusLabels()[$state] ?? $state),
                        Infolists\Components\TextEntry::make('paid_at')
                            ->label('Pagada el')
                            ->dateTime('Y-m-d H:i')
                            ->placeholder('—'),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Cuota list (SPEC-005 FR-003, FR-007).
     *
     * Search by client name/DNI and plan name; filters by status and amount
     * range. Status is always shown as a badge. Row actions: View and Edit
     * amount (pending only). No create, no edit page, no delete and no bulk
     * actions (BR-009, BR-012).
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('membership.client.full_name')
                    ->label('Cliente')
                    ->searchable(),
                Tables\Columns\TextColumn::make('membership.client.dni')
                    ->label('DNI')
                    ->searchable(),
                Tables\Columns\TextColumn::make('membership.plan.name')
                    ->label('Plan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('membership.start_date')
                    ->label('Inicio del período')
                    ->date('Y-m-d'),
                Tables\Columns\TextColumn::make('membership.end_date')
                    ->label('Fin del período')
                    ->date('Y-m-d'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Monto')
                    ->numeric(decimalPlaces: 2),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => static::statusColor($state))
                    ->formatStateUsing(fn (string $state): string => Cuota::statusLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Pagada el')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options(Cuota::statusLabels()),
                Tables\Filters\Filter::make('amount')
                    ->form([
                        Forms\Components\TextInput::make('amount_from')
                            ->label('Desde')
                            ->numeric(),
                        Forms\Components\TextInput::make('amount_until')
                            ->label('Hasta')
                            ->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['amount_from'] ?? null),
                                fn (Builder $query, string $amount): Builder => $query->where('amount', '>=', $amount),
                            )
                            ->when(
                                filled($data['amount_until'] ?? null),
                                fn (Builder $query, string $amount): Builder => $query->where('amount', '<=', $amount),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('editAmount')
                    ->label('Editar monto')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->visible(fn (Cuota $record): bool => $record->status === Cuota::STATUS_PENDING)
                    ->authorize(fn (Cuota $record): bool => auth()->user()->can('update', $record))
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('Monto')
                            ->numeric()
                            ->required()
                            ->minValue(0.01),
                    ])
                    ->action(function (Cuota $record, array $data): void {
                        $record->updateAmount((string) $data['amount']);
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCuotas::route('/'),
            'view' => Pages\ViewCuota::route('/{record}'),
        ];
    }

    /**
     * Badge color per status (presentation choice; FR-007).
     */
    protected static function statusColor(string $state): string
    {
        return match ($state) {
            Cuota::STATUS_PENDING => 'warning',
            Cuota::STATUS_PAID => 'success',
            Cuota::STATUS_CANCELLED => 'danger',
            default => 'gray',
        };
    }
}
