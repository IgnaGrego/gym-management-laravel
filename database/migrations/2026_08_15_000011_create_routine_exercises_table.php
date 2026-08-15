<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SPEC-010 (Routines). Set-level prescription rows (gate D-11 option 2,
     * pre-approved; BR-004): one row per set, prescribing an exercise
     * reference, a set number, target repetitions, an optional target weight,
     * optional rest seconds and optional notes. routine_day_id is a required
     * FK with cascadeOnDelete — the single deliberate deviation from the
     * repo's restrictOnDelete default (see architecture SPEC-010 §10 #5):
     * removing a day from a draft is a required editing operation (FR-005,
     * FR-008, AC-6) and the day's set rows must go with it. The cascade can
     * only ever run during draft in-place editing — archived versions are
     * never edited or deleted, so historical prescription data is never
     * affected (BR-008). exercise_id is a required FK to exercises.id with
     * restrictOnDelete (BR-006, AC-18): the consuming-module reference
     * direction documented by SPEC-009 BR-011 / §10; a deactivated exercise's
     * prescription rows survive (exercises are never hard-deleted anyway,
     * SPEC-009 BR-008). set_number is an unsigned integer; the UNIQUE index
     * on (routine_day_id, set_number) enforces ERR-002 (duplicate set numbers
     * in a day) at the database level (spec-requested). target_reps is a
     * required unsigned integer (BR-010, AR-06); target_weight is an optional
     * decimal(6,2) (absent/zero = bodyweight, BR-010, AR-06; the ADR-003
     * decimal convention); rest_seconds is an optional unsigned integer
     * (AR-06); notes is optional free text. No DB CHECK constraints: positive
     * reps / non-negative weight / non-negative rest are enforced by
     * form/action validation (framework-validation-first convention, ADR-003).
     * The index on exercise_id supports FR-008 exercise usage and future
     * SPEC-011 lookups by exercise.
     */
    public function up(): void
    {
        Schema::create('routine_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routine_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('set_number');
            $table->unsignedInteger('target_reps');
            $table->decimal('target_weight', 6, 2)->nullable();
            $table->unsignedInteger('rest_seconds')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['routine_day_id', 'set_number']);
            $table->index('exercise_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routine_exercises');
    }
};
