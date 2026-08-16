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

    protected static ?string $navigationLabel = 'Clientes';

    protected static ?string $modelLabel = 'Cliente';

    protected static ?string $pluralModelLabel = 'Clientes';

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
                Forms\Components\Section::make('Identidad')
                    ->schema([
                        Forms\Components\TextInput::make('full_name')
                            ->label('Nombre completo')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('dni')
                            ->label('DNI')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Contacto')
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->label('Teléfono')
                            ->maxLength(255),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Notas de salud')
                    ->schema([
                        Forms\Components\TextInput::make('emergency_contact')
                            ->label('Contacto de emergencia')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('injuries_notes')
                            ->label('Notas de lesiones'),
                        Forms\Components\Textarea::make('medical_conditions_notes')
                            ->label('Notas de condiciones médicas'),
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
                Infolists\Components\Section::make('Identidad')
                    ->schema([
                        Infolists\Components\TextEntry::make('full_name')
                            ->label('Nombre completo'),
                        Infolists\Components\TextEntry::make('dni')
                            ->label('DNI'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                Client::STATUS_PENDING => 'warning',
                                Client::STATUS_REJECTED => 'danger',
                                default => 'success',
                            })
                            ->formatStateUsing(fn (string $state): string => Client::statusLabels()[$state] ?? $state),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Contacto')
                    ->schema([
                        Infolists\Components\TextEntry::make('email')
                            ->label('Email')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('phone')
                            ->label('Teléfono')
                            ->placeholder('—'),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Notas de salud')
                    ->schema([
                        Infolists\Components\TextEntry::make('emergency_contact')
                            ->label('Contacto de emergencia')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('injuries_notes')
                            ->label('Notas de lesiones')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('medical_conditions_notes')
                            ->label('Notas de condiciones médicas')
                            ->placeholder('—'),
                    ]),
                Infolists\Components\Section::make('Cuenta vinculada')
                    ->schema([
                        Infolists\Components\TextEntry::make('user.email')
                            ->label('Email de acceso')
                            ->placeholder('Sin cuenta'),
                        Infolists\Components\TextEntry::make('user.is_active')
                            ->label('Estado de la cuenta')
                            ->formatStateUsing(fn (?bool $state): string => match ($state) {
                                true => 'Activo',
                                false => 'Inactivo',
                                null => '—',
                            }),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Rutina actual')
                    ->schema([
                        Infolists\Components\TextEntry::make('current_routine')
                            ->label('Rutina actual')
                            ->state(fn (Client $record): string => $record->currentRoutine() !== null
                                ? $record->currentRoutine()->name.' — v'.$record->currentRoutine()->version_number
                                : 'Sin rutina asignada'),
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
                    ->label('Nombre completo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('dni')
                    ->label('DNI')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Cuenta vinculada')
                    ->placeholder('Sin cuenta'),
                Tables\Columns\IconColumn::make('user.is_active')
                    ->label('Cuenta activa')
                    ->boolean()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Client::STATUS_PENDING => 'warning',
                        Client::STATUS_REJECTED => 'danger',
                        default => 'success',
                    })
                    ->formatStateUsing(fn (string $state): string => Client::statusLabels()[$state] ?? $state),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options(Client::statusLabels()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Aprobar')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Client $record): bool => $record->isPending())
                    ->action(function (Client $record): void {
                        Gate::authorize('approve', $record);
                        $record->approve();
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Rechazar')
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
