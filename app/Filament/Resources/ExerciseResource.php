<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExerciseResource\Pages;
use App\Models\Exercise;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExerciseResource extends Resource
{
    protected static ?string $model = Exercise::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Exercises';

    protected static ?string $navigationGroup = 'Training';

    /**
     * Exercise create/edit form (SPEC-009 FR-001, FR-004).
     *
     * name is required, max 255 and unique among ALL exercises regardless of
     * status (BR-003, ERR-002): the current record's own name is ignored on
     * edit (unique ignoreRecord) and a deactivated exercise's name stays
     * occupied (AF-005). muscle_group is a required Select from the fixed
     * model-constant set (BR-004, ERR-003) with an explicit server-side
     * `in:` rule: the option list is UX, the `in:` rule is the enforcement
     * (AGENTS.md §17). equipment is optional free text (EX-05, BR-002).
     * difficulty is an optional Select from the fixed set (BR-005, ERR-004);
     * the `in:` rule skips empty values so an omitted difficulty is accepted.
     * instructions is optional plain long text (EX-10, BR-002). video_url is
     * an optional external http/https URL (BR-006, ERR-005): ->url() gives
     * the input type/icon and the `url:http,https` rule is the server-side
     * enforcement. is_active is a Toggle defaulting to true so a new exercise
     * is active by default (FR-001, BR-007, EX-07) and the status can also be
     * changed during an edit (FR-005 path). Absent optional fields are stored
     * as null, not as empty strings (BR-002, ADR-003 convention).
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Exercise')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\Select::make('muscle_group')
                            ->label('Muscle group')
                            ->options(Exercise::muscleGroupLabels())
                            ->required()
                            ->rule('in:'.implode(',', Exercise::muscleGroups())),
                        Forms\Components\TextInput::make('equipment')
                            ->maxLength(255),
                        Forms\Components\Select::make('difficulty')
                            ->options(Exercise::difficultyLabels())
                            ->nullable()
                            ->rule('in:'.implode(',', Exercise::difficulties())),
                        Forms\Components\Textarea::make('instructions'),
                        Forms\Components\TextInput::make('video_url')
                            ->label('Video URL')
                            ->url()
                            ->rule('url:http,https'),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Exercise detail view (SPEC-009 FR-003, FR-006, FR-007).
     *
     * Shows the full record: name, muscle group, equipment, difficulty,
     * instructions, video URL (as a link) and the current status
     * (active/inactive). Muscle group and difficulty display via the label
     * helpers (BR-004, BR-005); absent optional fields show a placeholder
     * (BR-002, AF-004).
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Exercise')
                    ->schema([
                        Infolists\Components\TextEntry::make('name'),
                        Infolists\Components\TextEntry::make('muscle_group')
                            ->label('Muscle group')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): ?string => static::muscleGroupLabel($state)),
                        Infolists\Components\TextEntry::make('equipment')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('difficulty')
                            ->badge()
                            ->placeholder('—')
                            ->formatStateUsing(fn (?string $state): ?string => static::difficultyLabel($state)),
                        Infolists\Components\TextEntry::make('instructions')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('video_url')
                            ->label('Video URL')
                            ->placeholder('—')
                            ->url(fn (?string $state): ?string => filled($state) ? $state : null, true),
                        Infolists\Components\TextEntry::make('is_active')
                            ->label('Status')
                            ->badge()
                            ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive'),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Exercise list (SPEC-009 FR-002, FR-006, FR-007).
     *
     * Search is by name and equipment (FR-002). Columns show the catalogue
     * attributes (muscle group and difficulty via the label helpers, with a
     * placeholder for the absent optional fields) and the status via a
     * boolean icon column (FR-006). Filters: muscle group, difficulty and
     * status (FR-002). Row actions: View, Edit, Deactivate (active only,
     * confirmation) and Activate (inactive only, confirmation). Each
     * lifecycle action is a single-field update of is_active authorized
     * through the resource's update policy, enforced server-side (AGENTS.md
     * §17) — the exact Plan pattern (SPEC-003 FR-005). No delete action and
     * no bulk actions exist (BR-008, ERR-007).
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('muscle_group')
                    ->label('Muscle group')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (?string $state): ?string => static::muscleGroupLabel($state)),
                Tables\Columns\TextColumn::make('equipment')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('difficulty')
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state): ?string => static::difficultyLabel($state)),
                Tables\Columns\TextColumn::make('video_url')
                    ->label('Video URL')
                    ->placeholder('—')
                    ->limit(30)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('instructions')
                    ->placeholder('—')
                    ->limit(50)
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('muscle_group')
                    ->label('Muscle group')
                    ->options(Exercise::muscleGroupLabels()),
                Tables\Filters\SelectFilter::make('difficulty')
                    ->options(Exercise::difficultyLabels()),
                Tables\Filters\SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        true => 'Active',
                        false => 'Inactive',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        return $query->where('is_active', (bool) $data['value']);
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
                    ->visible(fn (Exercise $record): bool => (bool) $record->is_active)
                    ->authorize(fn (Exercise $record): bool => auth()->user()->can('update', $record))
                    ->action(fn (Exercise $record) => $record->update(['is_active' => false])),
                Tables\Actions\Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-arrow-up-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Exercise $record): bool => ! (bool) $record->is_active)
                    ->authorize(fn (Exercise $record): bool => auth()->user()->can('update', $record))
                    ->action(fn (Exercise $record) => $record->update(['is_active' => true])),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExercises::route('/'),
            'create' => Pages\CreateExercise::route('/create'),
            'view' => Pages\ViewExercise::route('/{record}'),
            'edit' => Pages\EditExercise::route('/{record}/edit'),
        ];
    }

    /**
     * Display label for a stored muscle-group identifier (presentation;
     * BR-004). Unknown values fall back to the identifier itself.
     */
    protected static function muscleGroupLabel(?string $state): ?string
    {
        if ($state === null) {
            return null;
        }

        return Exercise::muscleGroupLabels()[$state] ?? $state;
    }

    /**
     * Display label for a stored difficulty identifier (presentation;
     * BR-005). Unknown values fall back to the identifier itself.
     */
    protected static function difficultyLabel(?string $state): ?string
    {
        if ($state === null) {
            return null;
        }

        return Exercise::difficultyLabels()[$state] ?? $state;
    }
}
