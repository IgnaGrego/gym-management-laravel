<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SPEC-004 (Membership Management). A Membership is a client's enrollment
     * in a Plan for a specific fixed-duration period (BR-002, BR-003, C-05).
     * client_id and plan_id are required FKs (BR-002) with restrictOnDelete as
     * a defensive guard consistent with the preservation pattern: clients
     * (SPEC-002 BR-006) and plans (SPEC-003 BR-004) are never hard-deleted,
     * and a deletion attempt should be blocked rather than cascade into
     * historical membership data (BR-014). end_date is NOT NULL and is
     * computed by the model as start_date + duration_days - 1 (BR-003, AM-07);
     * it is stored so the period is explicit for every consumer. duration_days
     * is a positive integer enforced by form/action validation, not by a DB
     * CHECK constraint (framework-validation-first convention, ADR-003).
     * status is a string column (not a DB enum) defaulting to 'pending'
     * (BR-004, BR-005) with the model constants as the single source of truth.
     * No monetary column exists: amounts belong to cuotas/payments (SPEC-005)
     * using the ADR-003 decimal(10,2) convention (BR-013, AM-09). The index on
     * (client_id, start_date) supports the client membership history query
     * ordered by start_date (FR-004, C-08).
     */
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('duration_days');
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index(['client_id', 'start_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
