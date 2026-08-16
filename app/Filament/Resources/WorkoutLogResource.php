<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WorkoutLogResource\Pages;
use App\Models\Client;
use App\Models\Exercise;
use App\Models\RoutineExercise;
use App\Models\WorkoutLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WorkoutLogResource extends Resource
{
    protected static ?string $model = WorkoutLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Registros de entrenamiento';

    protected static ?string $navigationGroup = 'Entrenamiento';

    protected static ?string $modelLabel = 'Registro de entrenamiento';

    protected static ?string $pluralModelLabel = 'Registros de entrenamiento';

    /**
     * Workout-log create form (SPEC-011 FR-001, FR-002, FR-005; BR-001,
     * BR-002, BR-004, BR-005, BR-008, BR-009; ERR-001..ERR-005, ERR-008;
     * WL-05, WL-06, WL-11).
     *
     * client_id is a required searchable select of existing clients (BR-008,
     * ERR-008) with a server-side exists rule. NO membership/access gate rule
     * is applied (BR-010 — unlike AttendanceResource, logging has no
     * membership precondition).
     *
     * performed_at is a required DateTimePicker defaulting to now (WL-05)
     * with a not-in-the-future server-side rule (ERR-004); backdating is
     * allowed without an explicit limit.
     *
     * The reference selection (BR-002, ERR-001) uses a transient
     * `reference_type` toggle (routine/free, dehydrated(false) — never
     * persisted) that conditionally shows exactly one reference field:
     * routine_exercise_id lists the set-level rows of every routine version
     * the client has been assigned to — active OR historical (FR-001, AF-002;
     * drafts are never assignable so never listed, ERR-003) — and prefills
     * actual_weight / actual_reps from the row's target values on selection
     * (FR-001; staff may override); exercise_id lists the active catalogue
     * exercises only (FR-002, BR-005, ERR-005). Switching the toggle clears
     * the other reference so the UI never submits a both-set payload; the
     * shared WorkoutLog::referenceRules() (required_without + prohibits,
     * ERR-001/ERR-002) plus the ERR-003 closure rule (the row's routine
     * version must satisfy Client::hasRoutineAssignmentTo()) and the ERR-005
     * closure rule (active exercise only) remain the server-side enforcement
     * regardless of UI behavior (AGENTS.md §17).
     *
     * actual_weight is nullable numeric >= 0 (absent/zero = bodyweight,
     * WL-06); actual_reps is required positive integer (WL-06); notes is
     * optional free text. recorded_by is NOT a form field (BR-009, WL-11):
     * the CreateWorkoutLog page sets it to the authenticated staff User in
     * mutateFormDataBeforeCreate.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Registro de entrenamiento')
                    ->schema([
                        Forms\Components\Select::make('client_id')
                            ->label('Cliente')
                            ->relationship('client', 'full_name')
                            ->searchable(['full_name', 'dni'])
                            ->preload()
                            ->required()
                            ->reactive()
                            ->exists('clients', 'id'),
                        Forms\Components\DateTimePicker::make('performed_at')
                            ->label('Realizado el')
                            ->seconds(false)
                            ->displayFormat('Y-m-d H:i')
                            ->required()
                            ->default(now())
                            ->beforeOrEqual('now'),
                        Forms\Components\Select::make('reference_type')
                            ->label('Tipo de referencia')
                            ->options(WorkoutLog::referenceTypeLabels())
                            ->default('routine')
                            ->dehydrated(false)
                            ->reactive()
                            ->afterStateUpdated(function (Forms\Set $set): void {
                                $set('routine_exercise_id', null);
                                $set('exercise_id', null);
                            }),
                        Forms\Components\Select::make('routine_exercise_id')
                            ->label('Serie prescrita')
                            ->options(fn (Get $get): array => static::routineExerciseOptions($get('client_id')))
                            ->helperText(fn (Get $get): ?string => static::routineExerciseHint($get('client_id')))
                            ->searchable()
                            ->nullable()
                            ->reactive()
                            ->visible(fn (Get $get): bool => $get('reference_type') === 'routine')
                            ->rules(WorkoutLog::referenceRules()['routine_exercise_id'])
                            ->rule(static fn (Get $get): array => WorkoutLog::assignedVersionRule(blank($get('client_id')) ? null : (int) $get('client_id')))
                            ->afterStateUpdated(function (Forms\Set $set, ?string $state): void {
                                $routineExercise = RoutineExercise::find($state);

                                if (! $routineExercise) {
                                    return;
                                }

                                $set('actual_weight', $routineExercise->target_weight);
                                $set('actual_reps', $routineExercise->target_reps);
                            }),
                        Forms\Components\Select::make('exercise_id')
                            ->label('Ejercicio')
                            ->options(fn (): array => Exercise::active()->pluck('name', 'id')->all())
                            ->searchable()
                            ->nullable()
                            ->reactive()
                            ->visible(fn (Get $get): bool => $get('reference_type') === 'free')
                            ->rules(WorkoutLog::referenceRules()['exercise_id'])
                            ->rule(static fn (): array => WorkoutLog::activeExerciseRule()),
                        Forms\Components\TextInput::make('actual_weight')
                            ->label('Peso real (kg)')
                            ->numeric()
                            ->minValue(0),
                        Forms\Components\TextInput::make('actual_reps')
                            ->label('Repeticiones reales')
                            ->required()
                            ->integer()
                            ->minValue(1),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notas'),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Workout-log detail view (SPEC-011 FR-005, FR-003).
     *
     * Shows the client (name/DNI), the performed timestamp, the exercise
     * (whether the reference is a prescribed row or a free exercise), the
     * prescription target when the log references a prescribed row (read live
     * from the immutable set row, WL-08), the actual weight/reps, the notes,
     * the staff User who recorded the log and logged_at (created_at). No
     * header actions: logs are immutable (BR-006, ERR-007).
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Registro de entrenamiento')
                    ->schema([
                        Infolists\Components\TextEntry::make('client.full_name')
                            ->label('Cliente'),
                        Infolists\Components\TextEntry::make('client.dni')
                            ->label('DNI'),
                        Infolists\Components\TextEntry::make('performed_at')
                            ->label('Realizado el')
                            ->dateTime('Y-m-d H:i'),
                        Infolists\Components\TextEntry::make('exercise')
                            ->label('Ejercicio')
                            ->state(fn (WorkoutLog $record): ?string => $record->exerciseName())
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('routineExercise.target_weight')
                            ->label('Peso objetivo (kg)')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('routineExercise.target_reps')
                            ->label('Repeticiones objetivo')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('actual_weight')
                            ->label('Peso real (kg)')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('actual_reps')
                            ->label('Repeticiones reales'),
                        Infolists\Components\TextEntry::make('notes')
                            ->label('Notas')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('recordedBy.name')
                            ->label('Registrado por'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Registrado el')
                            ->dateTime('Y-m-d H:i'),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Workout-log list (SPEC-011 FR-005; supports FR-003/FR-004 via the
     * client filter).
     *
     * Columns show the client (name/DNI, searchable), the performed timestamp
     * (sortable — default chronological order, the history ordering), the
     * exercise (same display for both reference kinds), the prescription
     * target when referenced (the FR-004 comparison columns), the actual
     * weight/reps, the recording staff User, logged_at (created_at) and the
     * optional notes. Filters: client, date range on performed_at, recorded_by
     * and reference type (FR-003). Row actions: View only — no EditAction, no
     * DeleteAction, no bulk actions (BR-006, ERR-007).
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
                Tables\Columns\TextColumn::make('performed_at')
                    ->label('Realizado el')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('exercise_name')
                    ->label('Ejercicio')
                    ->state(fn (WorkoutLog $record): ?string => $record->exerciseName())
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('routineExercise.target_weight')
                    ->label('Peso objetivo (kg)')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('routineExercise.target_reps')
                    ->label('Repeticiones objetivo')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('actual_weight')
                    ->label('Peso real (kg)')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('actual_reps')
                    ->label('Repeticiones reales'),
                Tables\Columns\TextColumn::make('recordedBy.name')
                    ->label('Registrado por'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registrado el')
                    ->dateTime('Y-m-d H:i'),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Notas')
                    ->placeholder('—')
                    ->limit(50)
                    ->toggleable(),
            ])
            ->defaultSort('performed_at')
            ->filters([
                Tables\Filters\SelectFilter::make('client_id')
                    ->label('Cliente')
                    ->relationship('client', 'full_name')
                    ->searchable(['full_name', 'dni'])
                    ->preload(),
                Tables\Filters\Filter::make('performed_at')
                    ->form([
                        Forms\Components\DatePicker::make('performed_from')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('performed_until')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['performed_from'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('performed_at', '>=', $date),
                            )
                            ->when(
                                $data['performed_until'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('performed_at', '<=', $date),
                            );
                    }),
                Tables\Filters\SelectFilter::make('recorded_by')
                    ->label('Registrado por')
                    ->relationship('recordedBy', 'name'),
                Tables\Filters\SelectFilter::make('reference_type')
                    ->label('Tipo de referencia')
                    ->options(WorkoutLog::referenceTypeLabels())
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $query, string $value): Builder => $value === 'routine'
                                ? $query->whereNotNull('routine_exercise_id')
                                : $query->whereNull('routine_exercise_id'),
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
            'index' => Pages\ListWorkoutLogs::route('/'),
            'create' => Pages\CreateWorkoutLog::route('/create'),
            'view' => Pages\ViewWorkoutLog::route('/{record}'),
            'progress' => Pages\ClientProgress::route('/progress/{client}'),
        ];
    }

    /**
     * The Select options for the assigned-routine reference (SPEC-011 FR-001,
     * AF-002, BR-004, ERR-003): the set-level prescription rows of every
     * routine version the client has been assigned to — active OR historical
     * (SPEC-010 BR-008/AR-09: the assignment history is preserved). Drafts
     * are never assignable, so their rows are never listed.
     *
     * @return array<int, string>
     */
    protected static function routineExerciseOptions(mixed $clientId): array
    {
        if (blank($clientId)) {
            return [];
        }

        $client = Client::find($clientId);

        if (! $client) {
            return [];
        }

        $routineIds = $client->routineAssignments()->pluck('routine_id')->all();

        if (count($routineIds) === 0) {
            return [];
        }

        $rows = RoutineExercise::query()
            ->whereHas('routineDay', fn (Builder $query): Builder => $query->whereIn('routine_id', $routineIds))
            ->with(['routineDay', 'exercise'])
            ->get()
            ->sortBy(fn (RoutineExercise $row): string => $row->routineDay->day_number.'.'.sprintf('%02d', $row->set_number));

        $options = [];

        foreach ($rows as $row) {
            $options[$row->id] = static::routineExerciseLabel($row);
        }

        return $options;
    }

    /**
     * The option label for a prescribed set row (presentation detail; FR-001).
     */
    protected static function routineExerciseLabel(RoutineExercise $row): string
    {
        return trim(sprintf(
            'Día %d · %s — %s × %d (Serie %d)',
            $row->routineDay->day_number,
            $row->exercise?->name ?? '—',
            $row->target_weight === null ? 'Peso corporal' : $row->target_weight.' kg',
            $row->target_reps,
            $row->set_number,
        ));
    }

    /**
     * A helper hint for the assigned-routine reference (AF-001): a client with
     * no assigned routine is directed to the free-exercise path.
     */
    protected static function routineExerciseHint(mixed $clientId): ?string
    {
        if (blank($clientId)) {
            return null;
        }

        $client = Client::find($clientId);

        if (! $client || $client->routineAssignments()->exists()) {
            return null;
        }

        return 'Este cliente no tiene rutina asignada — usa la referencia de ejercicio libre.';
    }
}
