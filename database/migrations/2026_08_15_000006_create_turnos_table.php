<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SPEC-006 (Scheduling & Turnos). A turno is a bookable time slot for gym
     * access, capacity-limited (gate D-07 option 1); it is NOT a trainer-led
     * session and not a class/group session (BR-001, C-16). date is NOT NULL
     * and must be today or future on create/edit (BR-006), enforced by form
     * validation (framework-validation-first convention, ADR-003). start_time
     * and end_time are same-day `time` columns in the gym's local time
     * (BR-005, BR-011); the invariant end > start on the same date and the
     * no-cross-midnight rule (ERR-007) are enforced by Laravel's
     * `after:start_time` rule on the raw time strings (no DB CHECK, ADR-003).
     * capacity_limit is an unsigned integer, required and >= 1 (BR-007,
     * FR-009), enforced by form validation; capacity checking against bookings
     * is SPEC-007's concern (FR-009, D-07, C-16). status is a string column
     * (not a DB enum) defaulting to 'active' (BR-002, FR-001, AS-07) with the
     * model constants as the single source of truth (ADR-004 precedent).
     * label is optional free text (max 255 is a technical detail, SPEC-006
     * §10). No location column (single location, BR-010), no timezone column
     * (BR-011), no type/category column (no speculative fields, OQ-03), and no
     * foreign keys: the turno is standalone in this Specification (BR-013);
     * SPEC-007 will add bookings.turno_id from the consuming module. The index
     * on date supports the FR-002 date-range filter and future SPEC-007
     * lookups by day. No uniqueness/overlap constraint: overlapping (and
     * identical) turnos are allowed (BR-008, AC-12). No hard deletion
     * (BR-009): no delete operation exists; deactivation and cancellation are
     * used instead.
     */
    public function up(): void
    {
        Schema::create('turnos', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('capacity_limit');
            $table->string('status')->default('active');
            $table->string('label')->nullable();
            $table->timestamps();

            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('turnos');
    }
};
