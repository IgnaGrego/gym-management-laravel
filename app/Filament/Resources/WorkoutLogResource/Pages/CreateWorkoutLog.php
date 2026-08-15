<?php

namespace App\Filament\Resources\WorkoutLogResource\Pages;

use App\Filament\Resources\WorkoutLogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkoutLog extends CreateRecord
{
    protected static string $resource = WorkoutLogResource::class;

    /**
     * Inject the recording staff User and normalize the empty weight input
     * (SPEC-011 BR-009, WL-11, WL-06).
     *
     * recorded_by is never a form field: it is set server-side to the
     * authenticated staff User who records the log (the CreateAttendance
     * precedent). An empty actual_weight input is stored as null
     * (absent/zero = bodyweight).
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['recorded_by'] = auth()->id();

        if (array_key_exists('actual_weight', $data)) {
            $data['actual_weight'] = blank($data['actual_weight']) ? null : $data['actual_weight'];
        }

        return $data;
    }
}
