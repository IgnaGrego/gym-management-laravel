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

    protected static ?string $navigationLabel = 'Routines';

    protected static ?string $navigationGroup = 'Training';

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
            Forms\Components\Section::make('Routine')
                ->schema([
                    Forms\Components\TextInput::make('name')
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
            ->label('Sets')
            ->relationship('exercises')
            ->schema([
                Forms\Components\Select::make('exercise_id')
                    ->label('Exercise')
                    ->options(fn (): array => static::exerciseOptions($routine))
                    ->searchable()
                    ->required()
                    ->rule('exists:exercises,id')
                    ->rule(function (Get $get, ?string $state): array {
                        $item = $get('');
                        $isNewRow = blank($item['id'] ?? null);

                        if ($isNewRow && filled($state) && ! Exercise::active()->whereKey($state)->exists()) {
                            return [static function (string $attribute, mixed $value, \Closure $fail): void {
                                $fail('A new set row can only reference an active exercise.');
                            }];
                        }

                        return [];
                    }),
                Forms\Components\TextInput::make('set_number')
                    ->label('Set number')
                    ->required()
                    ->integer()
                    ->minValue(1)
                    ->distinct(),
                Forms\Components\TextInput::make('target_reps')
                    ->label('Target reps')
                    ->required()
                    ->integer()
                    ->minValue(1),
                Forms\Components\TextInput::make('target_weight')
                    ->label('Target weight (kg)')
                    ->numeric()
                    ->minValue(0),
                Forms\Components\TextInput::make('rest_seconds')
                    ->label('Rest (seconds)')
                    ->integer()
                    ->minValue(0),
                Forms\Components\Textarea::make('notes'),
            ])
            ->columns(3)
            ->defaultItems(0);

        $daysRepeater = Forms\Components\Repeater::make('days')
            ->label('Days')
            ->relationship('days')
            ->schema([
                Forms\Components\TextInput::make('day_number')
                    ->label('Day number')
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

        return Forms\Components\Section::make('Days and sets')
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
                Infolists\Components\Section::make('Routine')
                    ->schema([
                        Infolists\Components\TextEntry::make('name'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (?string $state): string => static::statusColor($state))
                            ->formatStateUsing(fn (?string $state): ?string => $state === null ? null : ucfirst($state)),
                        Infolists\Components\TextEntry::make('version_number')
                            ->label('Version')
                            ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : 'v'.$state),
                        Infolists\Components\TextEntry::make('creator.name')
                            ->label('Created by')
                            ->placeholder('—'),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Days')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('days')
                            ->label('Days')
                            ->schema([
                                Infolists\Components\TextEntry::make('day_number')
                                    ->label('Day'),
                                Infolists\Components\RepeatableEntry::make('exercises')
                                    ->label('Sets')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('exercise.name')
                                            ->label('Exercise'),
                                        Infolists\Components\TextEntry::make('set_number')
                                            ->label('Set'),
                                        Infolists\Components\TextEntry::make('target_reps')
                                            ->label('Target reps'),
                                        Infolists\Components\TextEntry::make('target_weight')
                                            ->label('Target weight (kg)')
                                            ->placeholder('—'),
                                        Infolists\Components\TextEntry::make('rest_seconds')
                                            ->label('Rest (s)')
                                            ->placeholder('—'),
                                        Infolists\Components\TextEntry::make('notes')
                                            ->placeholder('—'),
                                    ])
                                    ->columns(3),
                            ])
                            ->columns(2),
                    ]),
                Infolists\Components\Section::make('Version history')
                    ->schema([
                        Infolists\Components\TextEntry::make('version_history')
                            ->label('Version history')
                            ->state(fn (Routine $record): array => $record->lineage()
                                ->map(fn (Routine $version): string => 'v'.$version->version_number.' — '.ucfirst($version->status).' — '.($version->creator?->name ?? '—'))
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
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => static::statusColor($state))
                    ->formatStateUsing(fn (?string $state): ?string => $state === null ? null : ucfirst($state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('version_number')
                    ->label('Version')
                    ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : 'v'.$state)
                    ->sortable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Created by'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        Routine::STATUS_DRAFT => 'Draft',
                        Routine::STATUS_ACTIVE => 'Active',
                        Routine::STATUS_ARCHIVED => 'Archived',
                    ]),
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
