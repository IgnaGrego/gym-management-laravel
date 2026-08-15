<?php

namespace App\Actions;

use App\Models\Exercise;
use App\Models\Routine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class VersionRoutine
{
    /**
     * The copy-on-edit versioning operation (SPEC-010 FR-006, BR-001, BR-002,
     * AR-02; D-12 option 3).
     *
     * Editing an `active` routine never mutates the version clients see: a NEW
     * Routine version is created (copy of the current version with the
     * requested changes applied), `version_number = previous + 1`, linked via
     * `replaces_id` to the replaced version, status `active`, `created_by` set
     * to the editor; the previous version becomes `archived`. Fresh
     * RoutineDay / RoutineExercise rows are always created from the validated
     * form state — incoming row `id` keys are ignored so no row is ever
     * shared or mutated across versions (BR-001). Assignments are untouched
     * (FR-006, AC-7): existing routine_assignments rows keep pointing at the
     * previous version until staff explicitly reassign.
     *
     * @param  array<string, mixed>  $data  validated edit-form state:
     *                                      ['name' => string, 'days' => [
     *                                      ['day_number' => int, 'exercises' =>
     *                                      [['exercise_id' => int, 'set_number'
     *                                      => int, 'target_reps' => int,
     *                                      'target_weight' => ?float,
     *                                      'rest_seconds' => ?int,
     *                                      'notes' => ?string, ...], ...]],
     *                                      ...]]
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException when the user
     *                                                         cannot update routines
     * @throws ValidationException when the routine is not active (ERR-006),
     *                             the prescription state is invalid (ERR-002,
     *                             ERR-005, BR-010) or a new row references an
     *                             inactive exercise (BR-006, AR-04)
     */
    public function handle(Routine $routine, User $editor, array $data): Routine
    {
        $this->authorize($routine);

        $this->validate($routine, $data);

        return DB::transaction(function () use ($routine, $editor, $data): Routine {
            $newVersion = Routine::create([
                'name' => $data['name'],
                'status' => Routine::STATUS_ACTIVE,
                'version_number' => $routine->version_number + 1,
                'replaces_id' => $routine->id,
                'created_by' => $editor->id,
            ]);

            foreach ($data['days'] as $dayData) {
                $day = $newVersion->days()->create([
                    'day_number' => $dayData['day_number'],
                ]);

                foreach ($dayData['exercises'] as $exerciseData) {
                    $day->exercises()->create([
                        'exercise_id' => $exerciseData['exercise_id'],
                        'set_number' => $exerciseData['set_number'],
                        'target_reps' => $exerciseData['target_reps'],
                        'target_weight' => $exerciseData['target_weight'] ?? null,
                        'rest_seconds' => $exerciseData['rest_seconds'] ?? null,
                        'notes' => $exerciseData['notes'] ?? null,
                    ]);
                }
            }

            $routine->status = Routine::STATUS_ARCHIVED;
            $routine->save();

            return $newVersion;
        });
    }

    /**
     * Server-side enforcement (SPEC-010 §9; AGENTS.md §17): versioning is an
     * update of the routine, authorized through RoutinePolicy::update
     * (ADMIN | TRAINER). The page gates already restrict the route; this
     * check is defense in depth so the Action stays safe outside the UI (the
     * RenewMembership / ProvisionClientUser precedent).
     */
    protected function authorize(Routine $routine): void
    {
        Gate::authorize('update', $routine);
    }

    /**
     * Validate versioning input (SPEC-010 ERR-006, ERR-002, ERR-005, BR-010;
     * BR-006, AR-04).
     *
     * Only an `active` routine can be versioned (drafts are edited in place;
     * archived versions are read-only — ERR-006). The field-level
     * prescription invariants are re-validated (ERR-005), duplicate day / set
     * numbers are rejected (ERR-002), and the active-version content rule is
     * enforced: a version is created `active`, so it must have at least one
     * day (BR-010, ERR-003) and each day at least one set row (BR-010,
     * ERR-004). Rows with no source `id` (new rows added during the edit)
     * must reference an active exercise (BR-006, AR-04); rows copied from the
     * previous version (with an `id`) keep their exercise reference even if
     * the exercise is now inactive (AR-04).
     *
     * @param  array<string, mixed>  $data
     */
    protected function validate(Routine $routine, array $data): void
    {
        if ($routine->status !== Routine::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'routine' => 'Only an active routine can be versioned.',
            ]);
        }

        Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'days' => ['nullable', 'array'],
            'days.*.day_number' => ['required', 'integer', 'min:1'],
            'days.*.exercises' => ['nullable', 'array'],
            'days.*.exercises.*.exercise_id' => ['required', 'integer', 'exists:exercises,id'],
            'days.*.exercises.*.set_number' => ['required', 'integer', 'min:1'],
            'days.*.exercises.*.target_reps' => ['required', 'integer', 'min:1'],
            'days.*.exercises.*.target_weight' => ['nullable', 'numeric', 'min:0'],
            'days.*.exercises.*.rest_seconds' => ['nullable', 'integer', 'min:0'],
            'days.*.exercises.*.notes' => ['nullable', 'string'],
        ])->validate();

        $days = $data['days'] ?? [];

        if (count($days) === 0) {
            throw ValidationException::withMessages([
                'days' => 'A routine must have at least one day.',
            ]);
        }

        $dayNumbers = [];

        foreach ($days as $dayIndex => $day) {
            $dayNumber = $day['day_number'] ?? null;

            if (in_array($dayNumber, $dayNumbers, true)) {
                throw ValidationException::withMessages([
                    "days.{$dayIndex}.day_number" => 'The day number is duplicated within this routine.',
                ]);
            }

            $dayNumbers[] = $dayNumber;

            $exercises = $day['exercises'] ?? [];

            if (count($exercises) === 0) {
                throw ValidationException::withMessages([
                    "days.{$dayIndex}.exercises" => 'Every routine day must have at least one set.',
                ]);
            }

            $setNumbers = [];

            foreach ($exercises as $exerciseIndex => $exerciseData) {
                $setNumber = $exerciseData['set_number'] ?? null;

                if (in_array($setNumber, $setNumbers, true)) {
                    throw ValidationException::withMessages([
                        "days.{$dayIndex}.exercises.{$exerciseIndex}.set_number" => 'The set number is duplicated within this day.',
                    ]);
                }

                $setNumbers[] = $setNumber;

                $isNewRow = blank($exerciseData['id'] ?? null);

                if ($isNewRow && filled($exerciseData['exercise_id'] ?? null) && ! Exercise::active()->whereKey($exerciseData['exercise_id'])->exists()) {
                    throw ValidationException::withMessages([
                        "days.{$dayIndex}.exercises.{$exerciseIndex}.exercise_id" => 'A new set row can only reference an active exercise.',
                    ]);
                }
            }
        }
    }
}
