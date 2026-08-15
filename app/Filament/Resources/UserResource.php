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

    protected static ?string $navigationLabel = 'Users';

    protected static ?string $navigationGroup = 'Administration';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->required()
                    ->minLength(8)
                    ->maxLength(255)
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->hiddenOn('edit'),
                Forms\Components\CheckboxList::make('roles')
                    ->relationship('roles', 'name')
                    ->options(fn (?User $record) => static::roleOptions($record))
                    ->columns(2),
                Forms\Components\Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->badge(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
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
            Role::ADMIN => 'Admin',
            Role::TRAINER => 'Trainer',
        ];

        if ($record?->hasRole(Role::CLIENT)) {
            $options[Role::CLIENT] = 'Client';
        }

        return $options;
    }
}
