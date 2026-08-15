<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlanResource\Pages;
use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Plans';

    protected static ?string $navigationGroup = 'Commercial';

    /**
     * Plan create/edit form (SPEC-003 FR-001, FR-004).
     *
     * name is required, max 255 and unique (BR-003, ERR-002); description is
     * optional. price is required, numeric and strictly positive (min:0.01;
     * BR-002, ERR-003); enrollment_fee is optional, numeric and zero-or-
     * positive when present (min:0; BR-002, ERR-003), and an empty input is
     * stored as null (absent fee, ADR-003). Amounts are entered without a
     * currency symbol: the MVP uses a single implicit currency (AP-05, OQ-07;
     * ADR-003). is_active is a Toggle defaulting to true so a new plan is
     * active by default (FR-001, AP-02) and the status can also be changed
     * during an edit (FR-005 path).
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Offer')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\Textarea::make('description'),
                        Forms\Components\TextInput::make('price')
                            ->numeric()
                            ->required()
                            ->minValue(0.01),
                        Forms\Components\TextInput::make('enrollment_fee')
                            ->numeric()
                            ->minValue(0),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Plan detail view (SPEC-003 FR-003, FR-006).
     *
     * Shows the full record including price, enrollment fee and the current
     * status (active/inactive).
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Offer')
                    ->schema([
                        Infolists\Components\TextEntry::make('name'),
                        Infolists\Components\TextEntry::make('description')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('price')
                            ->numeric(decimalPlaces: 2),
                        Infolists\Components\TextEntry::make('enrollment_fee')
                            ->numeric(decimalPlaces: 2)
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('is_active')
                            ->label('Status')
                            ->formatStateUsing(fn (?bool $state): string => match ($state) {
                                true => 'Active',
                                false => 'Inactive',
                                null => '—',
                            }),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Plan list (SPEC-003 FR-002, FR-006).
     *
     * Search is by name and description. Status is displayed via a boolean
     * icon column. The lifecycle transitions are exposed as Deactivate /
     * Activate row actions (FR-005, AF-001, AF-002): each is a single-field
     * update of is_active authorized through the resource's update policy
     * (ADMIN-only), enforced server-side. No delete action and no bulk
     * actions exist (BR-004).
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->searchable()
                    ->limit(50)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('price')
                    ->numeric(decimalPlaces: 2),
                Tables\Columns\TextColumn::make('enrollment_fee')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('deactivate')
                    ->label('Deactivate')
                    ->icon('heroicon-o-arrow-down-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Plan $record): bool => (bool) $record->is_active)
                    ->authorize(fn (Plan $record): bool => auth()->user()->can('update', $record))
                    ->action(fn (Plan $record) => $record->update(['is_active' => false])),
                Tables\Actions\Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-arrow-up-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Plan $record): bool => ! (bool) $record->is_active)
                    ->authorize(fn (Plan $record): bool => auth()->user()->can('update', $record))
                    ->action(fn (Plan $record) => $record->update(['is_active' => true])),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'view' => Pages\ViewPlan::route('/{record}'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
