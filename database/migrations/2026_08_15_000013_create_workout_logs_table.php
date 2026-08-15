<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SPEC-011 (Workout Logs). A WorkoutLog is an immutable per-set execution
     * record (D-11 option 2, BR-001): one row per performed set, matching the
     * set-level prescription rows of SPEC-010. It has NO status column (an
     * event record, not a stateful entity — BR-006) and NO snapshot columns
     * (WL-08: display reads the exercise's current catalogue attributes and
     * the prescription from the immutable set row).
     *
     * client_id and recorded_by are required FKs (BR-008, BR-009/WL-11) with
     * restrictOnDelete as a defensive guard consistent with the preservation
     * pattern: clients, users, exercises and routine versions are never
     * hard-deleted, and a deletion attempt should be blocked rather than
     * cascade into historical execution data (BR-006).
     *
     * routine_exercise_id and exercise_id are BOTH nullable at the database
     * level; the exactly-one-reference invariant (BR-002, ERR-001) is enforced
     * by application validation via the shared WorkoutLog::referenceRules()
     * (required_without + prohibits) — the repo's validation-first convention
     * (ADR-003); no DB CHECK constraint is added, consistent with every
     * existing migration. routine_exercise_id is the version-stable
     * prescription reference (D-12 option 3, BR-004); exercise_id is the
     * free-log catalogue reference (C-11, BR-005).
     *
     * performed_at is a NOT NULL `timestamp` recording the gym-local time the
     * set was performed (BR-008, WL-05; the attendances.attended_at
     * convention); the value is always supplied (form default now, backdating
     * allowed). actual_reps is unsigned NOT NULL; positivity and
     * not-in-the-future are enforced by framework validation, not by DB CHECK
     * constraints (framework-validation-first convention, ADR-003). actual_weight
     * is a nullable decimal(6,2) matching the prescription's target_weight
     * (absent/zero = bodyweight, WL-06). notes is optional free text.
     *
     * Indexes: (client_id, performed_at) for the history/progress list
     * (FR-003) and the client filter; routine_exercise_id for the comparison
     * lookups (FR-004); exercise_id for free-log lookups (FR-002); recorded_by
     * for audit queries (BR-009). No uniqueness constraint on (client_id,
     * performed_at): every performed set is an independent log row (BR-001).
     */
    public function up(): void
    {
        Schema::create('workout_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->timestamp('performed_at');
            $table->foreignId('routine_exercise_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('exercise_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('actual_weight', 6, 2)->nullable();
            $table->unsignedInteger('actual_reps');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'performed_at']);
            $table->index('routine_exercise_id');
            $table->index('exercise_id');
            $table->index('recorded_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workout_logs');
    }
};
