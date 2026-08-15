<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TurnoResource\Pages;
use App\Models\Turno;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TurnoResource extends Resource
{
    protected static ?string $model = Turno::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Turnos';

    protected static ?string $navigationGroup = 'Scheduling';

    /**
     * Turno create/edit form (SPEC-006 FR-001, FR-004).
     *
     * date is required and must be today or future (BR-006, ERR-003); the
     * minDate gives the picker a floor (UX) and the `after_or_equal:today`
     * rule is the server-side enforcement (AGENTS.md §17). start_time and
     * end_time are required 24-hour times (ERR-001, ERR-002); end_time must
     * be strictly after start_time on the same date (BR-005, ERR-002) — the
     * `after:data.start_time` rule (the full state path, as Livewire
     * validation data is nested under `data`) also rejects a cross-midnight
     * interval such as 23:00–01:00 (ERR-007). capacity_limit is a required
     * positive integer (BR-007, ERR-004); capacity is only stored here,
     * checking it against bookings is SPEC-007's concern (FR-009). label is
     * optional free text (max 255, no business rules). status is NOT a form
     * field: status changes exclusively through the lifecycle actions
     * (FR-005..FR-007).
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Turno')
                    ->schema([
                        Forms\Components\DatePicker::make('date')
                            ->required()
                            ->minDate(now()->toDateString())
                            ->rule('after_or_equal:today'),
                        Forms\Components\TimePicker::make('start_time')
                            ->label('Start time')
                            ->seconds(false)
                            ->required()
                            ->rule('date_format:H:i'),
                        Forms\Components\TimePicker::make('end_time')
                            ->label('End time')
                            ->seconds(false)
                            ->required()
                            ->rule('date_format:H:i')
                            ->rule('after:data.start_time'),
                        Forms\Components\TextInput::make('capacity_limit')
                            ->label('Capacity limit')
                            ->numeric()
                            ->required()
                            ->integer()
                            ->minValue(1),
                        Forms\Components\TextInput::make('label')
                            ->maxLength(255),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Turno detail view (SPEC-006 FR-003, FR-008).
     *
     * Shows the full record: date, start time, end time, capacity limit,
     * status (badge) and label.
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Turno')
                    ->schema([
                        Infolists\Components\TextEntry::make('date')
                            ->date('Y-m-d'),
                        Infolists\Components\TextEntry::make('start_time')
                            ->label('Start time')
                            ->time('H:i'),
                        Infolists\Components\TextEntry::make('end_time')
                            ->label('End time')
                            ->time('H:i'),
                        Infolists\Components\TextEntry::make('capacity_limit')
                            ->label('Capacity limit'),
                        Infolists\Components\TextEntry::make('occupancy')
                            ->label('Occupancy')
                            ->state(fn (Turno $record): string => $record->confirmedBookingsCount().' / '.$record->capacity_limit),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => static::statusColor($state))
                            ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                        Infolists\Components\TextEntry::make('label')
                            ->placeholder('—'),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Turno list (SPEC-006 FR-002, FR-008).
     *
     * Columns show date, start/end times (H:i), capacity limit, optional
     * label and the status badge. Filters: status (active / inactive /
     * cancelled) and a date range (FR-002). Row actions: View, Edit
     * (auto-hidden on cancelled via canEdit, BR-004), Deactivate (active
     * only, confirmation), Reactivate (inactive only, confirmation) and
     * Cancel (active/inactive only, confirmation). Each lifecycle action is
     * authorized through the resource's update policy and calls the model
     * method, which is the final state-rule enforcement (BR-003, ERR-006).
     * No delete action and no bulk actions exist (BR-009).
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->date('Y-m-d')
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Start time')
                    ->time('H:i'),
                Tables\Columns\TextColumn::make('end_time')
                    ->label('End time')
                    ->time('H:i'),
                Tables\Columns\TextColumn::make('capacity_limit')
                    ->label('Capacity limit'),
                Tables\Columns\TextColumn::make('label')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => static::statusColor($state))
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        Turno::STATUS_ACTIVE => 'Active',
                        Turno::STATUS_INACTIVE => 'Inactive',
                        Turno::STATUS_CANCELLED => 'Cancelled',
                    ]),
                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('date_from'),
                        Forms\Components\DatePicker::make('date_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['date_until'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('deactivate')
                    ->label('Deactivate')
                    ->icon('heroicon-o-arrow-down-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Turno $record): bool => $record->status === Turno::STATUS_ACTIVE)
                    ->authorize(fn (Turno $record): bool => auth()->user()->can('update', $record))
                    ->action(fn (Turno $record) => $record->deactivate()),
                Tables\Actions\Action::make('reactivate')
                    ->label('Reactivate')
                    ->icon('heroicon-o-arrow-up-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Turno $record): bool => $record->status === Turno::STATUS_INACTIVE)
                    ->authorize(fn (Turno $record): bool => auth()->user()->can('update', $record))
                    ->action(fn (Turno $record) => $record->reactivate()),
                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Turno $record): bool => in_array($record->status, [Turno::STATUS_ACTIVE, Turno::STATUS_INACTIVE], true))
                    ->authorize(fn (Turno $record): bool => auth()->user()->can('update', $record))
                    ->action(fn (Turno $record) => $record->cancel()),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTurnos::route('/'),
            'create' => Pages\CreateTurno::route('/create'),
            'view' => Pages\ViewTurno::route('/{record}'),
            'edit' => Pages\EditTurno::route('/{record}/edit'),
        ];
    }

    /**
     * A `cancelled` turno cannot be edited (FR-004, ERR-006, BR-004).
     *
     * This single override gates both the Edit row/header actions (hidden on
     * cancelled turnos) and direct URL access to the EditTurno page
     * (abort/403) — server-side enforcement consistent with Filament's own
     * authorization hook. Note: the signature must match Filament's
     * `Resource::canEdit(Model $record)`.
     */
    public static function canEdit(Model $record): bool
    {
        return parent::canEdit($record) && $record->status !== Turno::STATUS_CANCELLED;
    }

    /**
     * Badge color per status (presentation choice; FR-008).
     */
    protected static function statusColor(string $state): string
    {
        return match ($state) {
            Turno::STATUS_ACTIVE => 'success',
            Turno::STATUS_INACTIVE => 'warning',
            Turno::STATUS_CANCELLED => 'danger',
            default => 'gray',
        };
    }
}
