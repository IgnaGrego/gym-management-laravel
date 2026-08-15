<?php

namespace App\Http\Requests\Portal;

use App\Models\WorkoutLog;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkoutLogRequest extends FormRequest
{
    /**
     * The portal route gate (auth + role:CLIENT) plus the derived client_id is
     * the authorization (SPEC-013 FR-009, BR-006; ADR-007): the acting CLIENT
     * may log only their OWN workout, so no per-record policy check is needed.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The SPEC-011 workout-log rules (BR-008, WL-05, WL-06; ERR-001..ERR-005),
     * shared with the Filament form via WorkoutLog::referenceRules() plus the
     * extracted assigned-version / active-exercise closure rules.
     *
     * client_id and recorded_by are NOT request fields: the controller injects
     * them server-side (C-13). No membership gate applies (SPEC-011 BR-010).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $clientId = $this->user()?->clientId();
        $referenceRules = WorkoutLog::referenceRules();

        return [
            'performed_at' => ['required', 'date', 'before_or_equal:now'],
            'routine_exercise_id' => array_merge(
                $referenceRules['routine_exercise_id'],
                WorkoutLog::assignedVersionRule($clientId),
            ),
            'exercise_id' => array_merge(
                $referenceRules['exercise_id'],
                WorkoutLog::activeExerciseRule(),
            ),
            'actual_weight' => ['nullable', 'numeric', 'min:0'],
            'actual_reps' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
