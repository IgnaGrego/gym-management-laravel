<?php

namespace App\Filament\Resources;

use App\Actions\RenewMembership;
use App\Filament\Resources\MembershipResource\Pages;
use App\Models\Membership;
use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class MembershipResource extends Resource
{
    protected static ?string $model = Membership::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Membresías';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $modelLabel = 'Membresía';

    protected static ?string $pluralModelLabel = 'Membresías';

    /**
     * Membership create form (SPEC-004 FR-001).
     *
     * client_id is a required existing client (ERR-002, BR-002) validated
     * server-side with an exists rule (ERR-007). plan_id is a required select
     * of ACTIVE plans only (BR-012, AM-09); the option list is the UX
     * restriction, the exists-where-active rule is the server-side enforcement
     * (ERR-001, ERR-007; AGENTS.md §17). start_date is required (BR-003) and
     * duration_days is a required positive integer (ERR-003, BR-003). end_date
     * is never in the form: it is computed by the model's creating hook
     * (BR-003, AM-07). There is no edit page: the Specification defines no
     * edit operation; renewal creates a new record (BR-011) and cancellation
     * is terminal (BR-008).
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Membresía')
                    ->schema([
                        Forms\Components\Select::make('client_id')
                            ->label('Cliente')
                            ->relationship('client', 'full_name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->exists('clients', 'id'),
                        Forms\Components\Select::make('plan_id')
                            ->label('Plan')
                            ->options(fn (): Collection => Plan::query()
                                ->where('is_active', true)
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->rule(fn (): Exists => Rule::exists('plans', 'id')
                                ->where('is_active', true)),
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Fecha de inicio')
                            ->required(),
                        Forms\Components\TextInput::make('duration_days')
                            ->label('Duración (días)')
                            ->numeric()
                            ->required()
                            ->integer()
                            ->minValue(1),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Membership detail view (SPEC-004 FR-003, FR-007).
     *
     * Shows the full record: client, plan, period (start/end dates and
     * duration) and the current status (badge). The pending -> active
     * transition is NOT offered anywhere in the UI (FR-008, BR-006).
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Membresía')
                    ->schema([
                        Infolists\Components\TextEntry::make('client.full_name')
                            ->label('Cliente'),
                        Infolists\Components\TextEntry::make('client.dni')
                            ->label('DNI'),
                        Infolists\Components\TextEntry::make('plan.name')
                            ->label('Plan'),
                        Infolists\Components\TextEntry::make('start_date')
                            ->label('Fecha de inicio')
                            ->date('Y-m-d'),
                        Infolists\Components\TextEntry::make('end_date')
                            ->label('Fecha de fin')
                            ->date('Y-m-d'),
                        Infolists\Components\TextEntry::make('duration_days')
                            ->label('Duración (días)'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn (string $state): string => static::statusColor($state))
                            ->formatStateUsing(fn (string $state): string => Membership::statusLabels()[$state] ?? $state),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Membership list (SPEC-004 FR-002, FR-007).
     *
     * Search by client (name/DNI) and plan name; filters by status and period
     * dates. Status is always shown as a badge. Row actions: View, Cancel
     * (pending/active only, confirmation, calls $record->cancel()) and Renew
     * (active/expired only, modal pre-filled with the AM-08/OQ-05 defaults).
     * No delete action and no bulk actions exist (BR-014).
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client.full_name')
                    ->label('Cliente')
                    ->searchable(),
                Tables\Columns\TextColumn::make('client.dni')
                    ->label('DNI')
                    ->searchable(),
                Tables\Columns\TextColumn::make('plan.name')
                    ->label('Plan')
                    ->searchable(),
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
                    ->color(fn (string $state): string => static::statusColor($state))
                    ->formatStateUsing(fn (string $state): string => Membership::statusLabels()[$state] ?? $state),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options(Membership::statusLabels()),
                Tables\Filters\Filter::make('start_date')
                    ->form([
                        Forms\Components\DatePicker::make('start_from')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('start_until')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['start_from'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('start_date', '>=', $date),
                            )
                            ->when(
                                $data['start_until'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('start_date', '<=', $date),
                            );
                    }),
                Tables\Filters\Filter::make('end_date')
                    ->form([
                        Forms\Components\DatePicker::make('end_from')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('end_until')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['end_from'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('end_date', '>=', $date),
                            )
                            ->when(
                                $data['end_until'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('end_date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('cancel')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Membership $record): bool => in_array($record->status, [Membership::STATUS_PENDING, Membership::STATUS_ACTIVE], true))
                    ->authorize(fn (Membership $record): bool => auth()->user()->can('update', $record))
                    ->action(fn (Membership $record) => $record->cancel()),
                Tables\Actions\Action::make('renew')
                    ->label('Renovar')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->visible(fn (Membership $record): bool => in_array($record->status, [Membership::STATUS_ACTIVE, Membership::STATUS_EXPIRED], true))
                    ->authorize(fn (): bool => auth()->user()->can('create', Membership::class))
                    ->form([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Fecha de inicio')
                            ->required()
                            ->default(fn (Membership $record): string => $record->end_date->copy()->addDay()->toDateString()),
                        Forms\Components\TextInput::make('duration_days')
                            ->label('Duración (días)')
                            ->numeric()
                            ->required()
                            ->integer()
                            ->minValue(1)
                            ->default(fn (Membership $record): int => $record->duration_days),
                    ])
                    ->action(function (Membership $record, array $data): void {
                        app(RenewMembership::class)->handle(
                            $record,
                            $data['start_date'] ?? null,
                            isset($data['duration_days']) && $data['duration_days'] !== '' ? (int) $data['duration_days'] : null,
                        );
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMemberships::route('/'),
            'create' => Pages\CreateMembership::route('/create'),
            'view' => Pages\ViewMembership::route('/{record}'),
        ];
    }

    /**
     * Badge color per status (presentation choice; FR-007).
     */
    protected static function statusColor(string $state): string
    {
        return match ($state) {
            Membership::STATUS_PENDING => 'warning',
            Membership::STATUS_ACTIVE => 'success',
            Membership::STATUS_EXPIRED => 'gray',
            Membership::STATUS_CANCELLED => 'danger',
            default => 'gray',
        };
    }
}
