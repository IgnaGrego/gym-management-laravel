<?php

namespace App\Filament\Resources\AttendanceResource\Pages;

use App\Filament\Resources\AttendanceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAttendance extends CreateRecord
{
    protected static string $resource = AttendanceResource::class;

    /**
     * Inject the recording staff User (SPEC-008 BR-011, FR-006, AT-08).
     *
     * recorded_by is never a form field: it is set server-side to the
     * authenticated staff User who performs the check-in.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['recorded_by'] = auth()->id();

        return $data;
    }
}
