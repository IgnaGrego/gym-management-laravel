<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Technical safeguard (not a business rule, architecture SPEC-001 §5 and
     * §12): an ADMIN cannot deactivate their own account, preventing an
     * accidental lockout. Flagged for Product Owner confirmation.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = $this->getRecord();

        if ($user->is(auth()->user()) && ! ($data['is_active'] ?? true)) {
            throw ValidationException::withMessages([
                'is_active' => 'You cannot deactivate your own account.',
            ]);
        }

        return $data;
    }
}
