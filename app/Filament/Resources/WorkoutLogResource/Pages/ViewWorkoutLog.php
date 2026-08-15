<?php

namespace App\Filament\Resources\WorkoutLogResource\Pages;

use App\Filament\Resources\WorkoutLogResource;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkoutLog extends ViewRecord
{
    protected static string $resource = WorkoutLogResource::class;

    /**
     * No header actions on purpose: workout logs are an immutable event log —
     * no edit and no delete operation exists (SPEC-011 BR-006, ERR-007,
     * WL-04).
     */
}
