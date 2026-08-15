<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceResource\Pages;
use App\Models\Attendance;
use App\Models\Client;
use App\Models\Turno;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendanceResource extends Resource
{
    protected static ?string $model = Attendance::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Attendance';

    protected static ?string $navigationGroup = 'Attendance';

    /**
     * Check-in form (SPEC-008 FR-001, FR-005; BR-002, BR-003, BR-007,
     * BR-011; ERR-001..ERR-006).
     *
     * client_id is a required searchable select of existing clients (ERR-001,
     * BR-002) with a server-side exists rule (ERR-002) PLUS the access-gate
     * closure rule (BR-003): when the selected client's accessDenialReason()
     * is not null, validation fails with the denial reason (ERR-003/ERR-004)
     * and no record is created — the server-side enforcement of the gate
     * (AGENTS.md §17). The reactive access-decision placeholder displays the
     * gate decision live (FR-005; display only).
     *
     * attended_at is a required DateTimePicker defaulting to the current
     * gym-local time (AT-05) with a not-in-the-future server-side rule
     * (ERR-005, BR-007); backdating is allowed without an explicit limit
     * (AF-001, AT-05).
     *
     * turno_id is an optional select of existing turnos (AT-06, ERR-006); no
     * turno status, time or capacity validation applies to the link (AT-06),
     * so the option list includes all turnos.
     *
     * notes is optional free text (max 500, no business rules).
     *
     * recorded_by is NOT a form field (BR-011): the CreateAttendance page
     * sets it to the authenticated staff User in mutateFormDataBeforeCreate.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Check-in')
                    ->schema([
                        Forms\Components\Select::make('client_id')
                            ->label('Client')
                            ->relationship('client', 'full_name')
                            ->searchable(['full_name', 'dni'])
                            ->preload()
                            ->required()
                            ->reactive()
                            ->exists('clients', 'id')
                            ->rule(static fn (): Closure => static::accessGateRule()),
                        Forms\Components\Placeholder::make('access_decision')
                            ->label('Access decision')
                            ->content(fn (Get $get): string => static::accessDecisionText($get('client_id')))
                            ->hidden(fn (Get $get): bool => blank($get('client_id'))),
                        Forms\Components\DateTimePicker::make('attended_at')
                            ->label('Attended at')
                            ->seconds(false)
                            ->displayFormat('Y-m-d H:i')
                            ->required()
                            ->default(now())
                            ->beforeOrEqual('now'),
                        Forms\Components\Select::make('turno_id')
                            ->label('Turno')
                            ->relationship('turno', 'label', modifyQueryUsing: fn (Builder $query): Builder => $query->orderBy('date')->orderBy('start_time'))
                            ->getOptionLabelFromRecordUsing(fn (Turno $record): string => static::turnoLabel($record))
                            ->preload()
                            ->nullable()
                            ->exists('turnos', 'id'),
                        Forms\Components\Textarea::make('notes')
                            ->maxLength(500),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Attendance detail view (SPEC-008 FR-003, FR-006).
     *
     * Shows the client (name/DNI), the access timestamp, the staff User who
     * recorded the check-in (recorded_by), the optional turno link and the
     * optional notes. No header actions: records are immutable (BR-008).
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Attendance')
                    ->schema([
                        Infolists\Components\TextEntry::make('client.full_name')
                            ->label('Client'),
                        Infolists\Components\TextEntry::make('client.dni')
                            ->label('DNI'),
                        Infolists\Components\TextEntry::make('attended_at')
                            ->dateTime('Y-m-d H:i'),
                        Infolists\Components\TextEntry::make('recordedBy.name')
                            ->label('Recorded by'),
                        Infolists\Components\TextEntry::make('turno.label')
                            ->label('Turno')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('notes')
                            ->placeholder('—'),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Attendance list (SPEC-008 FR-002, FR-004, FR-006; AC-11).
     *
     * Columns show the client (name/DNI, searchable), the access timestamp
     * (sortable), the recording staff User (recorded_by), the optional turno
     * link and the optional notes. Default order is chronological by
     * attended_at (asc — satisfies the client's attendance history in
     * chronological order, FR-004/AC-11; the column is sortable for the
     * daily access log). Filters: client, date range on attended_at,
     * recorded_by and turno (FR-002). Row actions: View only — no
     * EditAction, no DeleteAction, no bulk actions (BR-008, ERR-008).
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
                Tables\Columns\TextColumn::make('attended_at')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('recordedBy.name')
                    ->label('Recorded by'),
                Tables\Columns\TextColumn::make('turno.label')
                    ->label('Turno')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('notes')
                    ->placeholder('—')
                    ->limit(50)
                    ->toggleable(),
            ])
            ->defaultSort('attended_at')
            ->filters([
                Tables\Filters\SelectFilter::make('client_id')
                    ->label('Client')
                    ->relationship('client', 'full_name')
                    ->searchable(['full_name', 'dni'])
                    ->preload(),
                Tables\Filters\Filter::make('attended_at')
                    ->form([
                        Forms\Components\DatePicker::make('attended_from'),
                        Forms\Components\DatePicker::make('attended_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['attended_from'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('attended_at', '>=', $date),
                            )
                            ->when(
                                $data['attended_until'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('attended_at', '<=', $date),
                            );
                    }),
                Tables\Filters\SelectFilter::make('recorded_by')
                    ->label('Recorded by')
                    ->relationship('recordedBy', 'name'),
                Tables\Filters\SelectFilter::make('turno_id')
                    ->label('Turno')
                    ->relationship('turno', 'label'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendances::route('/'),
            'create' => Pages\CreateAttendance::route('/create'),
            'view' => Pages\ViewAttendance::route('/{record}'),
        ];
    }

    /**
     * The access-gate validation rule for client_id (SPEC-008 BR-003,
     * D-05 option 1).
     *
     * When the selected client does not qualify (accessDenialReason() is not
     * null), validation fails with the denial reason (ERR-003/ERR-004) and no
     * record is created. The gate is evaluated at check-in time only
     * (BR-004). A blank or nonexistent client is left to the required /
     * exists rules.
     */
    protected static function accessGateRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (blank($value)) {
                return;
            }

            $client = Client::find($value);

            if (! $client) {
                return;
            }

            $reason = $client->accessDenialReason();

            if ($reason !== null) {
                $fail(static::denialMessage($reason));
            }
        };
    }

    /**
     * The FR-005 access decision text for the selected client (display only;
     * enforcement is the access-gate rule).
     */
    protected static function accessDecisionText(mixed $clientId): string
    {
        if (blank($clientId)) {
            return '—';
        }

        $client = Client::find($clientId);

        if (! $client) {
            return '—';
        }

        $reason = $client->accessDenialReason();

        return $reason === null
            ? 'Qualified — this client can be checked in.'
            : static::denialMessage($reason);
    }

    /**
     * Human-readable denial reason (ERR-003, ERR-004).
     */
    protected static function denialMessage(string $reason): string
    {
        return match ($reason) {
            Client::ACCESS_DENIED_NO_MEMBERSHIP => 'This client has no membership and cannot be checked in.',
            Client::ACCESS_DENIED_MEMBERSHIP_EXPIRED => 'This client\'s membership has expired and cannot be checked in.',
            Client::ACCESS_DENIED_NO_ACTIVE_MEMBERSHIP => 'This client has no active membership and cannot be checked in.',
            default => 'This client cannot be checked in.',
        };
    }

    /**
     * Option label for a turno (presentation choice; AT-06).
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
