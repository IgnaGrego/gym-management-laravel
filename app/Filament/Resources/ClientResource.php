<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientResource\Pages;
use App\Filament\Resources\ClientResource\RelationManagers;
use App\Models\Client;
use Illuminate\Support\Facades\Gate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Clients';

    /**
     * Client create/edit form (SPEC-002 FR-001, FR-004, FR-007).
     *
     * full_name and dni are required (ERR-002); dni is unique (ERR-001, BR-005)
     * with no format regex (no DNI format is specified). email is validated as
     * an email and phone by length only (ERR-006; no phone format is
     * specified). Health fields are optional and clearly separated (FR-007,
     * ADR-002); they appear only here and in the detail view, never in lists
     * or search.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identity')
                    ->schema([
                        Forms\Components\TextInput::make('full_name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('dni')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Contact')
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->maxLength(255),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Health notes')
                    ->schema([
                        Forms\Components\TextInput::make('emergency_contact')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('injuries_notes'),
                        Forms\Components\Textarea::make('medical_conditions_notes'),
                    ]),
            ]);
    }

    /**
     * Client detail view (SPEC-002 FR-003, FR-006, FR-007).
     *
     * Shows the full record including health notes and the linked-account
     * status ("No account" when no User is linked, otherwise the login email
     * and Active/Inactive state - AC-14).
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Identity')
                    ->schema([
                        Infolists\Components\TextEntry::make('full_name'),
                        Infolists\Components\TextEntry::make('dni'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                Client::STATUS_PENDING => 'warning',
                                Client::STATUS_REJECTED => 'danger',
                                default => 'success',
                            }),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Contact')
                    ->schema([
                        Infolists\Components\TextEntry::make('email')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('phone')
                            ->placeholder('—'),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Health notes')
                    ->schema([
                        Infolists\Components\TextEntry::make('emergency_contact')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('injuries_notes')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('medical_conditions_notes')
                            ->placeholder('—'),
                    ]),
                Infolists\Components\Section::make('Linked account')
                    ->schema([
                        Infolists\Components\TextEntry::make('user.email')
                            ->label('Login email')
                            ->placeholder('No account'),
                        Infolists\Components\TextEntry::make('user.is_active')
                            ->label('Account status')
                            ->formatStateUsing(fn (?bool $state): string => match ($state) {
                                true => 'Active',
                                false => 'Inactive',
                                null => '—',
                            }),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Current routine')
                    ->schema([
                        Infolists\Components\TextEntry::make('current_routine')
                            ->label('Current routine')
                            ->state(fn (Client $record): string => $record->currentRoutine() !== null
                                ? $record->currentRoutine()->name.' — v'.$record->currentRoutine()->version_number
                                : 'No routine assigned'),
                    ]),
            ]);
    }

    /**
     * Client list (SPEC-002 FR-002, FR-006, FR-007).
     *
     * Search is limited to full_name, dni and email. Health columns are never
     * shown in the list. Link status is displayed via the linked account email
     * ("No account") and its active state. No delete action exists (BR-006).
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('dni')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('phone')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Linked account')
                    ->placeholder('No account'),
                Tables\Columns\IconColumn::make('user.is_active')
                    ->label('Account active')
                    ->boolean()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Client::STATUS_PENDING => 'warning',
                        Client::STATUS_REJECTED => 'danger',
                        default => 'success',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    Client::STATUS_PENDING => 'Pending',
                    Client::STATUS_ACTIVE => 'Active',
                    Client::STATUS_REJECTED => 'Rejected',
                ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Client $record): bool => $record->isPending())
                    ->action(function (Client $record): void {
                        Gate::authorize('approve', $record);
                        $record->approve();
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Client $record): bool => $record->isPending())
                    ->action(function (Client $record): void {
                        Gate::authorize('reject', $record);
                        $record->reject();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'view' => Pages\ViewClient::route('/{record}'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }

    /**
     * Relation managers shown on the client detail view (SPEC-004 FR-004;
     * SPEC-010 FR-011): the client's membership history and routine-assignment
     * history, both read-only.
     */
    public static function getRelations(): array
    {
        return [
            RelationManagers\MembershipsRelationManager::class,
            RelationManagers\RoutineAssignmentsRelationManager::class,
        ];
    }
}
