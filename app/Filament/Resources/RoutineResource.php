<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoutineResource\Pages;
use App\Filament\Resources\RoutineResource\RelationManagers;
use App\Models\Exercise;
use App\Models\Routine;
use App\Models\RoutineDay;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RoutineResource extends Resource
{
    protected static ?string $model = Routine::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Rutinas';

    protected static ?string $navigationGroup = 'Entrenamiento';

    protected static ?string $modelLabel = 'Rutina';

    protected static ?string $pluralModelLabel = 'Rutinas';

    /**
     * Routine create/edit form (SPEC-010 FR-001, FR-005, FR-006, FR-008).
     *
     * Create (FR-001): only `name` is present — a new routine is created as
     * draft version 1 with no days; status / version_number / replaces_id come
     * from the DB defaults and `created_by` is set by the CreateRoutine page
     * (BR-011). name is required free text, max 255 and NOT unique (AR-05):
     * versions of a lineage share the name and different lineages may reuse
     * it.
     *
     * Edit (FR-005, FR-006, FR-008): the name plus a days Repeater (ordinal
     * days, D-10 option 2 / BR-003), each day with a nested set-rows Repeater
     * (set-level prescription, D-11 option 2 / BR-004). day_number / set_number
     * are positive integers with Filament's native `distinct` rule rejecting
     * numbers already used by another item in the same repeater (ERR-002,
     * AC-3). exercise_id is a required Select from the active exercises merged
     * with the ids already referenced by this version's rows (so preserved
     * rows referencing now-inactive exercises keep displaying, AR-04); the
     * `exists:exercises,id` rule enforces ERR-001 and a closure rule rejects
     * an inactive exercise for a NEW row (no existing `id`) — BR-006, AR-04,
     * AC-5. target_reps / target_weight / rest_seconds / notes follow the
     * AR-06 value rules (ERR-005). status is NOT a form field: status changes
     * exclusively through the lifecycle action / versioning (FR-006, FR-007;
     * the Turno precedent).
     *
     * Persistence: for a `draft` record the relationship Repeaters edit it in
     * place (FR-005, AC-6). For an `active` record the save callbacks of both
     * Repeaters are neutralized (BR-001: an active version's content is never
     * mutated in place) and EditRoutine::handleRecordUpdate delegates the
     * whole save to App\Actions\VersionRoutine (FR-006).
     */
    public static function form(Form $form): Form
    {
        $schema = [
            Forms\Components\Section::make('Rutina')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255),
                ]),
        ];

        if ($form->getOperation() === 'edit' && $form->getRecord() instanceof Routine) {
            $schema[] = static::daysAndSetsSection($form->getRecord());
        }

        return $form->schema($schema);
    }

    /**
     * The days + nested sets editing section (SPEC-010 FR-008; D-10 option 2,
     * D-11 option 2).
     *
     * When the edited record is an `active` version, the relationship save
     * callbacks are neutralized: an active version is never mutated in place
     * (BR-001); the whole save is delegated to App\Actions\VersionRoutine via
     * EditRoutine::handleRecordUpdate (FR-006).
     */
    protected static function daysAndSetsSection(Routine $routine): Forms\Components\Section
    {
        $isActiveRecord = $routine->status === Routine::STATUS_ACTIVE;

        $exercisesRepeater = Forms\Components\Repeater::make('exercises')
            ->label('Series')
            ->relationship('exercises')
            ->schema([
                Forms\Components\Select::make('exercise_id')
                    ->label('Ejercicio')
                    ->options(fn (): array => static::exerciseOptions($routine))
                    ->searchable()
                    ->required()
                    ->rule('exists:exercises,id')
                    ->rule(function (Get $get, ?string $state): array {
                        $item = $get('');
                        $isNewRow = blank($item['id'] ?? null);

                        if ($isNewRow && filled($state) && ! Exercise::active()->whereKey($state)->exists()) {
                            return [static function (string $attribute, mixed $value, \Closure $fail): void {
                                $fail('Una nueva fila de serie solo puede referenciar un ejercicio activo.');
                            }];
                        }

                        return [];
                    }),
                Forms\Components\TextInput::make('set_number')
                    ->label('Número de serie')
                    ->required()
                    ->integer()
                    ->minValue(1)
                    ->distinct(),
                Forms\Components\TextInput::make('target_reps')
                    ->label('Repeticiones objetivo')
                    ->required()
                    ->integer()
                    ->minValue(1),
                Forms\Components\TextInput::make('target_weight')
                    ->label('Peso objetivo (kg)')
                    ->numeric()
                    ->minValue(0),
                Forms\Components\TextInput::make('rest_seconds')
                    ->label('Descanso (segundos)')
                    ->integer()
                    ->minValue(0),
                Forms\Components\Textarea::make('notes')
                    ->label('Notas'),
            ])
            ->columns(3)
            ->defaultItems(0);

        $daysRepeater = Forms\Components\Repeater::make('days')
            ->label('Días')
            ->relationship('days')
            ->schema([
                Forms\Components\TextInput::make('day_number')
                    ->label('Número de día')
                    ->required()
                    ->integer()
                    ->minValue(1)
                    ->distinct(),
                $exercisesRepeater,
            ])
            ->columns(1)
            ->defaultItems(0);

        if ($isActiveRecord) {
            $daysRepeater->saveRelationshipsUsing(static fn () => null);
            $exercisesRepeater->saveRelationshipsUsing(static fn () => null);
        }

        return Forms\Components\Section::make('Días y series')
            ->schema([$daysRepeater]);
    }

    /**
     * Routine detail view (SPEC-010 FR-003, FR-004, FR-012).
     *
     * Shows the full version: name, status badge, version number, creator
     * (BR-011), the ordinal days with their set-level prescription rows
     * (reading each exercise's current catalogue attributes, AR-04) and the
     * version history of the lineage — every version with its number, status
     * and creator (FR-004, FR-012); archived versions stay fully readable
     * here (AF-004).
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Rutina')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label('Nombre'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn (?string $state): string => static::statusColor($state))
                            ->formatStateUsing(fn (?string $state): ?string => $state === null ? null : (Routine::statusLabels()[$state] ?? $state)),
                        Infolists\Components\TextEntry::make('version_number')
                            ->label('Versión')
                            ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : 'v'.$state),
                        Infolists\Components\TextEntry::make('creator.name')
                            ->label('Creado por')
                            ->placeholder('—'),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Días')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('days')
                            ->label('Días')
                            ->schema([
                                Infolists\Components\TextEntry::make('day_number')
                                    ->label('Día'),
                                Infolists\Components\RepeatableEntry::make('exercises')
                                    ->label('Series')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('exercise.name')
                                            ->label('Ejercicio'),
                                        Infolists\Components\TextEntry::make('set_number')
                                            ->label('Serie'),
                                        Infolists\Components\TextEntry::make('target_reps')
                                            ->label('Repeticiones objetivo'),
                                        Infolists\Components\TextEntry::make('target_weight')
                                            ->label('Peso objetivo (kg)')
                                            ->placeholder('—'),
                                        Infolists\Components\TextEntry::make('rest_seconds')
                                            ->label('Descanso (s)')
                                            ->placeholder('—'),
                                        Infolists\Components\TextEntry::make('notes')
                                            ->label('Notas')
                                            ->placeholder('—'),
                                    ])
                                    ->columns(3),
                            ])
                            ->columns(2),
                    ]),
                Infolists\Components\Section::make('Historial de versiones')
                    ->schema([
                        Infolists\Components\TextEntry::make('version_history')
                            ->label('Historial de versiones')
                            ->state(fn (Routine $record): array => $record->lineage()
                                ->map(fn (Routine $version): string => 'v'.$version->version_number.' — '.(Routine::statusLabels()[$version->status] ?? $version->status).' — '.($version->creator?->name ?? '—'))
                                ->values()
                                ->all())
                            ->listWithLineBreaks(),
                    ]),
            ]);
    }

    /**
     * Routine list (SPEC-010 FR-002, FR-012) — one row per LINEAGE.
     *
     * The query applies Routine::scopeLineageHeads() so each list entry
     * represents the current/latest version of a lineage. Columns show the
     * name (searchable, sortable), the status badge (draft / active /
     * archived), the version number (displayed as v{n}) and the creator. A
     * SelectFilter on status supports FR-002 filtering. Row actions: View and
     * Edit (auto-hidden on archived versions via canEdit, ERR-006). No delete
     * action and no bulk actions exist (BR-008, ERR-009).
     */
    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->lineageHeads())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (?string $state): string => static::statusColor($state))
                    ->formatStateUsing(fn (?string $state): ?string => $state === null ? null : (Routine::statusLabels()[$state] ?? $state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('version_number')
                    ->label('Versión')
                    ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : 'v'.$state)
                    ->sortable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Creado por'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options(Routine::statusLabels()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AssignmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoutines::route('/'),
            'create' => Pages\CreateRoutine::route('/create'),
            'view' => Pages\ViewRoutine::route('/{record}'),
            'edit' => Pages\EditRoutine::route('/{record}/edit'),
        ];
    }

    /**
     * An `archived` routine version cannot be edited (FR-006, ERR-006,
     * BR-002); drafts are edited in place and active versions via a new
     * version.
     *
     * This single override gates both the Edit row/header actions (hidden on
     * archived versions) and direct URL access to the EditRoutine page
     * (abort/403) — server-side enforcement consistent with Filament's own
     * authorization hook (the TurnoResource::canEdit precedent). Note: the
     * signature must match Filament's `Resource::canEdit(Model $record)`.
     */
    public static function canEdit(Model $record): bool
    {
        return parent::canEdit($record) && $record->status !== Routine::STATUS_ARCHIVED;
    }

    /**
     * The Select options for a set row's exercise reference (BR-006, AR-04):
     * the active exercises, merged with the exercise ids already referenced
     * by this version's rows, so preserved rows referencing now-inactive
     * exercises keep displaying. New rows are still restricted to active
     * exercises by the closure rule.
     *
     * @return array<int, string>
     */
    protected static function exerciseOptions(Routine $record): array
    {
        $options = Exercise::active()->pluck('name', 'id')->all();

        $referencedIds = $record->days()
            ->with('exercises')
            ->get()
            ->flatMap(fn (RoutineDay $day) => $day->exercises->pluck('exercise_id'))
            ->unique();

        foreach ($referencedIds as $exerciseId) {
            if (isset($options[$exerciseId])) {
                continue;
            }

            if ($exercise = Exercise::find($exerciseId)) {
                $options[$exerciseId] = $exercise->name;
            }
        }

        return $options;
    }

    /**
     * Badge color per status (presentation choice; FR-012).
     */
    protected static function statusColor(?string $state): string
    {
        return match ($state) {
            Routine::STATUS_DRAFT => 'gray',
            Routine::STATUS_ACTIVE => 'success',
            Routine::STATUS_ARCHIVED => 'danger',
            default => 'gray',
        };
    }
}
