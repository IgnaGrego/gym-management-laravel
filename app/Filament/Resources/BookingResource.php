<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookingResource\Pages;
use App\Models\Booking;
use App\Models\Turno;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationLabel = 'Bookings';

    protected static ?string $navigationGroup = 'Bookings';

    /**
     * Booking create form (SPEC-007 FR-001).
     *
     * client_id is a required searchable select of existing clients (ERR-001,
     * BR-002) with a server-side exists rule (ERR-002); the access gate
     * (BR-005), turno state/time/lead-time (BR-006/BR-007), capacity (BR-008)
     * and duplicate (BR-009) rules are enforced by the CreateBooking Action,
     * never by the option list (AGENTS.md §17). turno_id is a required select
     * of existing turnos (ERR-001, ERR-002); the option list is restricted to
     * active turnos as a UX convenience only. notes is optional free text
     * (max 500). status and booked_by are NOT form fields: status is set by the
     * model default (BR-003) and booked_by is set by the Action (BK-12).
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Booking')
                    ->schema([
                        Forms\Components\Select::make('client_id')
                            ->label('Client')
                            ->relationship('client', 'full_name')
                            ->searchable(['full_name', 'dni'])
                            ->preload()
                            ->required()
                            ->exists('clients', 'id'),
                        Forms\Components\Select::make('turno_id')
                            ->label('Turno')
                            ->relationship('turno', 'label', modifyQueryUsing: fn (Builder $query): Builder => $query->active()->orderBy('date')->orderBy('start_time'))
                            ->getOptionLabelFromRecordUsing(fn (Turno $record): string => static::turnoLabel($record))
                            ->preload()
                            ->required()
                            ->exists('turnos', 'id'),
                        Forms\Components\Textarea::make('notes')
                            ->maxLength(500),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Booking detail view (SPEC-007 FR-003).
     *
     * Shows the client (name/DNI), the turno (date + start + end), the status
     * badge, booked_at, the staff User who created the booking (booked_by) and
     * the optional notes. The cancel header action lives on the ViewBooking
     * page.
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Booking')
                    ->schema([
                        Infolists\Components\TextEntry::make('client.full_name')
                            ->label('Client'),
                        Infolists\Components\TextEntry::make('client.dni')
                            ->label('DNI'),
                        Infolists\Components\TextEntry::make('turno_id')
                            ->label('Turno')
                            ->formatStateUsing(fn (mixed $state, Booking $record): string => $record->turno ? static::turnoLabel($record->turno) : '—'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => static::statusColor($state))
                            ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                        Infolists\Components\TextEntry::make('booked_at')
                            ->dateTime('Y-m-d H:i'),
                        Infolists\Components\TextEntry::make('bookedBy.name')
                            ->label('Booked by')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('notes')
                            ->placeholder('—'),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Booking list (SPEC-007 FR-002, FR-005).
     *
     * Columns show the client (name/DNI, searchable), the turno label, the
     * status badge, booked_at (sortable), the staff User who booked it and the
     * optional notes. Filters: status (confirmed / cancelled), client (name/DNI)
     * and a turno-date range (FR-002). Row actions: View and Cancel (confirmed
     * only). No EditAction, no DeleteAction, no bulk actions (BR-011).
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client.full_name')
                    ->label('Client')
                    ->searchable(),
                Tables\Columns\TextColumn::make('client.dni')
                    ->label('DNI')
                    ->searchable(),
                Tables\Columns\TextColumn::make('turno_id')
                    ->label('Turno')
                    ->formatStateUsing(fn (mixed $state, Booking $record): string => $record->turno ? static::turnoLabel($record->turno) : '—'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => static::statusColor($state))
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                Tables\Columns\TextColumn::make('booked_at')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('bookedBy.name')
                    ->label('Booked by')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('notes')
                    ->placeholder('—')
                    ->limit(50)
                    ->toggleable(),
            ])
            ->defaultSort('booked_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        Booking::STATUS_CONFIRMED => 'Confirmed',
                        Booking::STATUS_CANCELLED => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('client_id')
                    ->label('Client')
                    ->relationship('client', 'full_name')
                    ->searchable(['full_name', 'dni'])
                    ->preload(),
                Tables\Filters\Filter::make('turno_date')
                    ->form([
                        Forms\Components\DatePicker::make('date_from'),
                        Forms\Components\DatePicker::make('date_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereHas('turno', fn (Builder $q): Builder => $q->whereDate('date', '>=', $date)),
                            )
                            ->when(
                                $data['date_until'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereHas('turno', fn (Builder $q): Builder => $q->whereDate('date', '<=', $date)),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Booking $record): bool => $record->status === Booking::STATUS_CONFIRMED)
                    ->authorize(fn (Booking $record): bool => auth()->user()->can('update', $record))
                    ->action(fn (Booking $record) => $record->cancel()),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'create' => Pages\CreateBooking::route('/create'),
            'view' => Pages\ViewBooking::route('/{record}'),
        ];
    }

    /**
     * Badge color per status (presentation choice; FR-005).
     */
    protected static function statusColor(string $state): string
    {
        return match ($state) {
            Booking::STATUS_CONFIRMED => 'success',
            Booking::STATUS_CANCELLED => 'gray',
            default => 'gray',
        };
    }

    /**
     * Option label for a turno (presentation choice; the AttendanceResource
     * turnoLabel pattern).
     */
    protected static function turnoLabel(Turno $turno): string
    {
        return trim(sprintf(
            '%s %s-%s %s',
            $turno->date->toDateString(),
            $turno->start_time,
            $turno->end_time,
            trim((string) $turno->label),
        ));
    }
}
