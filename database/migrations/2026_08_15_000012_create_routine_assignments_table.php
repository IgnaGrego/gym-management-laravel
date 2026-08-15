<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SPEC-010 (Routines). A client's assignment to a specific routine VERSION
     * (BR-007, AR-03, AR-09). client_id and routine_id are required FKs with
     * restrictOnDelete as a defensive guard consistent with the preservation
     * pattern: clients and routine versions are never hard-deleted (BR-008)
     * and a deletion attempt should be blocked rather than cascade into
     * assignment history. assigned_at is a NOT NULL timestamp set by the
     * AssignRoutine action (defaults to now). is_active is a boolean
     * defaulting to true: the current-assignment flag; at most one active row
     * per client (AR-03), enforced at the application level in AssignRoutine
     * (transactional deactivate + create, per SPEC-010 §10); no partial unique
     * index / raw SQL constraint is added (framework-first convention,
     * ADR-003). The index on (client_id, is_active) supports the
     * one-active-assignment check and per-client history (FR-011); the index
     * on (routine_id, is_active) supports "which clients are currently
     * assigned to this version" (FR-011). Assignment history is never
     * hard-deleted: unassignment deactivates the active row (BR-008).
     */
    public function up(): void
    {
        Schema::create('routine_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('routine_id')->constrained()->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['client_id', 'is_active']);
            $table->index(['routine_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routine_assignments');
    }
};
