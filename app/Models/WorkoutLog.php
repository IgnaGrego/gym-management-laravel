<?php

namespace App\Models;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkoutLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * recorded_by is fillable so the create path (mutateFormDataBeforeCreate),
     * the factory and direct writes work, but it is never a form field: it is
     * set to the authenticated staff User at creation (BR-009, WL-11 — the
     * Attendance precedent).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'client_id',
        'performed_at',
        'routine_exercise_id',
        'exercise_id',
        'actual_weight',
        'actual_reps',
        'notes',
        'recorded_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * performed_at is cast to Carbon (BR-008, WL-05 — the gym-local performed
     * timestamp; no timezone column, same local-time convention as
     * attendances.attended_at). actual_weight is cast to decimal:2 (the
     * ADR-003 decimal cast precedent — note Eloquent returns strings for
     * decimal casts; precision decimal(6,2) matches the prescription's
     * target_weight). actual_reps is cast to integer (WL-06). The FK columns
     * stay plain integers and notes a plain string.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
            'actual_weight' => 'decimal:2',
            'actual_reps' => 'integer',
        ];
    }

    /**
     * The client this log records (BR-001, BR-002).
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * The prescribed set-level row this log references (BR-002, D-12 option
     * 3, BR-004), or null for a free log. Version-stable by construction: the
     * log keeps this reference permanently; routine versioning never rewrites,
     * re-points or deletes logs (AF-004).
     */
    public function routineExercise(): BelongsTo
    {
        return $this->belongsTo(RoutineExercise::class);
    }

    /**
     * The free-log catalogue exercise this log references (BR-002, BR-005),
     * or null for an assigned-routine log. Live catalogue reference: display
     * reads the exercise's CURRENT attributes (WL-08; no per-log snapshot).
     */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    /**
     * The staff User who recorded the log (BR-009, FR-005, WL-11 — the
     * Attendance::recordedBy() precedent).
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Scope the query to one client's workout logs (FR-003).
     *
     * Ordering by performed_at is applied by the consuming UI (chronological
     * history, AC-8).
     */
    public function scopeForClient(Builder $query, int $clientId): Builder
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * The exercise display name (FR-003, WL-08).
     *
     * The exercise shown in lists and detail is the same whether the log
     * references a prescribed set row or a free catalogue exercise; it reads
     * the exercise's CURRENT catalogue attributes live (no per-log snapshot).
     */
    public function exerciseName(): ?string
    {
        return $this->routineExercise?->exercise?->name
            ?? $this->exercise?->name;
    }

    /**
     * The shared validation rules for the exactly-one-reference invariant plus
     * reference existence (BR-002, ERR-001, ERR-002).
     *
     * This is the single source of truth used by the Filament form (via
     * ->rules(...)) and exercised directly by the unit tests
     * (Validator::make($data, WorkoutLog::referenceRules())).
     *
     * @return array<string, array<int, string>>
     */
    public static function referenceRules(): array
    {
        return [
            'routine_exercise_id' => ['nullable', 'required_without:exercise_id', 'prohibits:exercise_id', 'exists:routine_exercises,id'],
            'exercise_id' => ['nullable', 'required_without:routine_exercise_id', 'prohibits:routine_exercise_id', 'exists:exercises,id'],
        ];
    }

    /**
     * The ERR-003 closure rule for routine_exercise_id (SPEC-011 BR-004,
     * BR-008, WL-07): when a value is present, the row's routine version must
     * satisfy Client::hasRoutineAssignmentTo() (active or historical
     * assignment; drafts are never assignable so they fail automatically).
     *
     * Shared by the Filament create form (SPEC-011) and the client portal
     * self-log endpoint (SPEC-013 FR-009, BR-006) — the single source of truth
     * so the two surfaces cannot drift (AGENTS.md §9).
     *
     * @return array<int, Closure>
     */
    public static function assignedVersionRule(?int $clientId): array
    {
        if ($clientId === null) {
            return [];
        }

        return [static function (string $attribute, mixed $value, Closure $fail) use ($clientId): void {
            if (blank($value)) {
                return;
            }

            $routineExercise = RoutineExercise::with('routineDay.routine')->find($value);

            if (! $routineExercise || ! $routineExercise->routineDay?->routine) {
                return;
            }

            $client = Client::find($clientId);

            if (! $client || ! $client->hasRoutineAssignmentTo($routineExercise->routineDay->routine->id)) {
                $fail('This set belongs to a routine version the client has never been assigned to.');
            }
        }];
    }

    /**
     * The ERR-005 closure rule for exercise_id (SPEC-011 BR-005, BR-008,
     * WL-02): a present value must be an `active` catalogue exercise.
     *
     * Shared by the Filament create form (SPEC-011) and the client portal
     * self-log endpoint (SPEC-013 FR-009, BR-006) — see assignedVersionRule().
     *
     * @return array<int, Closure>
     */
    public static function activeExerciseRule(): array
    {
        return [static function (string $attribute, mixed $value, Closure $fail): void {
            if (blank($value)) {
                return;
            }

            if (! Exercise::active()->whereKey($value)->exists()) {
                $fail('A free log can only reference an active exercise.');
            }
        }];
    }
}
