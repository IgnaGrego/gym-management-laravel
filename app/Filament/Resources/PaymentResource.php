<?php

namespace App\Filament\Resources;

use App\Actions\RegisterPayment;
use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Cuota;
use App\Models\Payment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationLabel = 'Payments';

    protected static ?string $navigationGroup = 'Commercial';

    /**
     * Payment create form (SPEC-005 FR-004, BR-004, BR-007).
     *
     * cuota_id is a required select of PENDING cuotas only (BR-003, ERR-011)
     * with a server-side exists rule (ERR-001) and a reactive side effect that
     * pre-fills the amount with the selected cuota's amount. amount is
     * required, numeric and positive (ERR-002), pre-filled and editable so a
     * full-payment mismatch can be attempted; RegisterPayment re-validates the
     * equality server-side (ERR-010, NC-01 — defense in depth). method is
     * cash | transfer (ERR-003, BR-004). reference is required for transfers
     * (ERR-005, PY-04). payment_date is required, not in the future and
     * defaults to today (ERR-004, PY-03). notes is optional free text.
     * recorded_by is never a form field: RegisterPayment injects the
     * authenticated staff User (PY-06).
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Payment')
                    ->schema([
                        Forms\Components\Select::make('cuota_id')
                            ->label('Cuota')
                            ->options(fn (): Collection => Cuota::query()
                                ->where('status', Cuota::STATUS_PENDING)
                                ->with(['membership.client', 'membership.plan'])
                                ->get()
                                ->mapWithKeys(fn (Cuota $cuota): array => [
                                    $cuota->id => $cuota->membership->client->full_name.' — '.$cuota->membership->plan->name.' — $'.$cuota->amount,
                                ]))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->exists('cuotas', 'id')
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state): void {
                                $set('amount', $state ? Cuota::find($state)?->amount : null);
                            }),
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->required()
                            ->minValue(0.01),
                        Forms\Components\Select::make('method')
                            ->options([
                                Payment::METHOD_CASH => 'Cash',
                                Payment::METHOD_TRANSFER => 'Bank transfer',
                            ])
                            ->in([Payment::METHOD_CASH, Payment::METHOD_TRANSFER])
                            ->required()
                            ->live(),
                        Forms\Components\TextInput::make('reference')
                            ->label('Reference')
                            ->requiredIf('method', Payment::METHOD_TRANSFER)
                            ->hidden(fn (Get $get): bool => $get('method') !== Payment::METHOD_TRANSFER),
                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Payment date')
                            ->required()
                            ->default(now()->toDateString())
                            ->beforeOrEqual('today'),
                        Forms\Components\Textarea::make('notes'),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Payment detail view (SPEC-005 FR-005, FR-007).
     *
     * Shows the full context (client, plan, membership period via the cuota),
     * amount, method, payment date, reference, notes, status and the staff
     * User who recorded it. No edit: confirmed payments are immutable
     * (BR-006, PY-05).
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Payment')
                    ->schema([
                        Infolists\Components\TextEntry::make('cuota.membership.client.full_name')
                            ->label('Client'),
                        Infolists\Components\TextEntry::make('cuota.membership.client.dni')
                            ->label('DNI'),
                        Infolists\Components\TextEntry::make('cuota.membership.plan.name')
                            ->label('Plan'),
                        Infolists\Components\TextEntry::make('amount')
                            ->numeric(decimalPlaces: 2),
                        Infolists\Components\TextEntry::make('method')
                            ->badge()
                            ->color(fn (string $state): string => $state === Payment::METHOD_CASH ? 'success' : 'info'),
                        Infolists\Components\TextEntry::make('payment_date')
                            ->date('Y-m-d'),
                        Infolists\Components\TextEntry::make('reference')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('notes')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => $state === Payment::STATUS_CONFIRMED ? 'success' : 'gray')
                            ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                        Infolists\Components\TextEntry::make('recordedBy.name')
                            ->label('Recorded by'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime('Y-m-d H:i'),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Payment list (SPEC-005 FR-005, FR-007).
     *
     * Search by client name/DNI; filters by method, status and payment-date
     * range. Row actions: View only — no edit, no delete, no bulk actions
     * (BR-006, BR-009, AC-11).
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('cuota.membership.client.full_name')
                    ->label('Client')
                    ->searchable(),
                Tables\Columns\TextColumn::make('cuota.membership.client.dni')
                    ->label('DNI')
                    ->searchable(),
                Tables\Columns\TextColumn::make('cuota.membership.plan.name')
                    ->label('Plan'),
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
            ->filters([
                Tables\Filters\SelectFilter::make('method')
                    ->options([
                        Payment::METHOD_CASH => 'Cash',
                        Payment::METHOD_TRANSFER => 'Bank transfer',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        Payment::STATUS_PENDING => 'Pending',
                        Payment::STATUS_CONFIRMED => 'Confirmed',
                        Payment::STATUS_FAILED => 'Failed',
                    ]),
                Tables\Filters\Filter::make('payment_date')
                    ->form([
                        Forms\Components\DatePicker::make('payment_from'),
                        Forms\Components\DatePicker::make('payment_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['payment_from'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('payment_date', '>=', $date),
                            )
                            ->when(
                                $data['payment_until'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('payment_date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'view' => Pages\ViewPayment::route('/{record}'),
        ];
    }
}
