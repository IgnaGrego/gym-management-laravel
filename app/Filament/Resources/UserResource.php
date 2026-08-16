<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\Role;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Usuarios';

    protected static ?string $navigationGroup = 'Administración';

    protected static ?string $modelLabel = 'Usuario';

    protected static ?string $pluralModelLabel = 'Usuarios';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('password')
                    ->label('Contraseña')
                    ->password()
                    ->required()
                    ->minLength(8)
                    ->maxLength(255)
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->hiddenOn('edit'),
                Forms\Components\CheckboxList::make('roles')
                    ->label('Roles')
                    ->relationship('roles', 'name')
                    ->options(fn (?User $record) => static::roleOptions($record))
                    ->columns(2),
                Forms\Components\Toggle::make('is_active')
                    ->label('Cuenta activa')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Cuenta activa')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Cuenta activa'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    /**
     * Options for the roles checkbox list.
     *
     * Only ADMIN and TRAINER are offered because client account provisioning
     * is deferred to SPEC-002 (assumption A-01). When editing a user that
     * already holds CLIENT, the option is shown so an existing assignment is
     * not silently detached by a staff-role edit.
     */
    protected static function roleOptions(?User $record): array
    {
        $options = [
            Role::ADMIN => 'Administrador',
            Role::TRAINER => 'Entrenador',
        ];

        if ($record?->hasRole(Role::CLIENT)) {
            $options[Role::CLIENT] = 'Cliente';
        }

        return $options;
    }
}
