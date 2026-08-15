<?php

namespace App\Filament\Resources\AttendanceResource\Pages;

use App\Filament\Resources\AttendanceResource;
use Filament\Resources\Pages\ViewRecord;

class ViewAttendance extends ViewRecord
{
    protected static string $resource = AttendanceResource::class;

    /**
     * No header actions on purpose: attendance records are an immutable event
     * log — no edit and no delete operation exists (SPEC-008 BR-008,
     * ERR-008, AT-07).
     */
}
