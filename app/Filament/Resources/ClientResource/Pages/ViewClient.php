<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Actions\ProvisionClientUser;
use App\Filament\Resources\ClientResource;
use App\Models\Client;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;

class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Explicit provisioning action (SPEC-002 FR-005): thin UI glue over
            // App\Actions\ProvisionClientUser. The action is hidden when the
            // client already has a linked account (ERR-004 UX); the rule is
            // still enforced server-side inside the Action (BR-003).
            Actions\Action::make('provision')
                ->label('Crear cuenta de usuario')
                ->icon('heroicon-o-user-plus')
                ->modalHeading('Crear cuenta de usuario')
                ->modalSubmitActionLabel('Crear')
                ->visible(fn (Client $record): bool => $record->user_id === null)
                ->form([
                    Forms\Components\TextInput::make('login_email')
                        ->label('Email de acceso')
                        ->email()
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('password')
                        ->label('Contraseña')
                        ->password()
                        ->required()
                        ->minLength(8)
                        ->maxLength(255),
                ])
                ->action(function (Client $record, array $data): void {
                    app(ProvisionClientUser::class)->handle(
                        $record,
                        $data['login_email'],
                        $data['password'],
                    );
                }),
        ];
    }
}
