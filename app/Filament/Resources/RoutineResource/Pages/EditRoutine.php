<?php

namespace App\Filament\Resources\RoutineResource\Pages;

use App\Actions\VersionRoutine;
use App\Filament\Resources\RoutineResource;
use App\Models\Routine;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditRoutine extends EditRecord
{
    protected static string $resource = RoutineResource::class;

    /**
     * The new version created when an `active` routine is edited (FR-006);
     * used by getRedirectUrl() to send the user to the new version.
     */
    protected ?Routine $createdVersion = null;

    /**
     * The days/sets repeater state as edited by the user, captured before the
     * form's getState() re-hydrates it from the active version's relationship
     * rows (loadStateFromRelationships).
     *
     * @var array<int, array<string, mixed>>|null
     */
    protected ?array $editedDaysState = null;

    /**
     * Capture the repeater state for `active` records (SPEC-010 FR-006).
     *
     * The relationship Repeaters are not dehydrated, so the edited days never
     * reach handleRecordUpdate's $data; and getState() re-loads the state from
     * the active version's rows after validation. The user's edited days are
     * therefore captured here, before any re-hydration, and handed to
     * App\Actions\VersionRoutine.
     */
    protected function beforeValidate(): void
    {
        if ($this->getRecord()->status === Routine::STATUS_ACTIVE) {
            $this->editedDaysState = $this->data['days'] ?? [];
        }
    }

    /**
     * Persist an edit (SPEC-010 FR-005, FR-006; BR-001, BR-002).
     *
     * A `draft` record is edited in place by the default flow (the days/sets
     * relationship Repeaters sync the draft's rows, FR-005, AC-6). An
     * `active` record is NEVER mutated in place: the whole save is delegated
     * to App\Actions\VersionRoutine, which creates a new version (copy with
     * the requested changes applied), increments the version number, archives
     * the previous version and leaves assignments untouched (AC-7, D-12
     * option 3). The Action validates the captured days state and creates
     * fresh rows, ignoring incoming row ids (BR-001).
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if ($record->status === Routine::STATUS_ACTIVE) {
            $this->createdVersion = app(VersionRoutine::class)->handle($record, auth()->user(), [
                'name' => $data['name'],
                'days' => $this->editedDaysState ?? [],
            ]);

            return $this->createdVersion;
        }

        return parent::handleRecordUpdate($record, $data);
    }

    /**
     * After versioning an active routine, redirect to the new version's edit
     * page (FR-006; the previous version is now archived and read-only).
     */
    protected function getRedirectUrl(): ?string
    {
        if ($this->createdVersion instanceof Routine) {
            return static::getResource()::getUrl('edit', ['record' => $this->createdVersion]);
        }

        return parent::getRedirectUrl();
    }
}
