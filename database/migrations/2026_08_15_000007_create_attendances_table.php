<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SPEC-008 (Attendance). An Attendance record is an immutable event-log
     * entry: a Client accessed the gym (BR-001, AT-07; D-09 option 3). It has
     * NO status column (an event, not a stateful entity — BR-001) and NO
     * booking_id column (deferred to SPEC-007, AT-04 / BR-006 / AC-14).
     * client_id and recorded_by are required FKs (BR-002, BR-011) with
     * restrictOnDelete as a defensive guard consistent with the preservation
     * pattern: clients, users and turnos are never hard-deleted, and a
     * deletion attempt should be blocked rather than cascade into historical
     * attendance data (BR-008). attended_at is a NOT NULL `timestamp` column
     * recording the gym-local access time (BR-007, AT-05); the "not in the
     * future" rule and FK existence are enforced by framework validation, not
     * by DB CHECK constraints (framework-validation-first convention,
     * ADR-003). turno_id is an optional FK (AT-06) referencing the gym-access
     * slot the client used; no booking/capacity semantics apply (BR-012).
     * notes is optional free text (max 500 is a technical detail, SPEC-008
     * §10). No uniqueness constraint on (client_id, day): multiple check-ins
     * per day are each independent records (AT-03, AF-004). Indexes on
     * attended_at (FR-002 date-range filter) and (client_id, attended_at)
     * (FR-004 per-client history ordered by attended_at); the FK columns
     * receive their own indexes automatically via constrained().
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->timestamp('attended_at');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('turno_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index('attended_at');
            $table->index(['client_id', 'attended_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
