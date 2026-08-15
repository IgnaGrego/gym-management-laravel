<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SPEC-010 (Routines). One row per routine VERSION (D-12 option 3,
     * AR-02): each Routine record is a version of a plan; versions of the
     * same plan form a lineage linked by the self-referential replaces_id
     * chain (null for version 1). name is required free text and NOT unique
     * (BR-010, AR-05): versions of a lineage share the name and different
     * lineages may reuse it, so no unique index is added (only a plain index
     * for the FR-002 name search). status is a string column (not a DB enum)
     * defaulting to 'draft' with the model constants as the single source of
     * truth (BR-002, AR-01; the ADR-004 string-with-constants convention,
     * precedent: turnos.status); the index on status supports the FR-002
     * filter. version_number is an unsigned integer defaulting to 1 (BR-001,
     * AR-02), incremented per lineage by the VersionRoutine action; the
     * per-lineage numbering invariant is enforced by the action, not by a
     * unique index (the chain is not a column). created_by is a required FK
     * to users.id, audit-only (BR-011, AR-08), with restrictOnDelete as a
     * defensive guard consistent with the preservation pattern (users are
     * never hard-deleted). No DB CHECK constraints: status values and
     * version-number positivity are enforced by form/action validation
     * (framework-validation-first convention, ADR-003). No hard deletion
     * (BR-008): archived status is reached by being superseded by a new
     * version; there is no delete operation.
     */
    public function up(): void
    {
        Schema::create('routines', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('status')->default('draft');
            $table->unsignedInteger('version_number')->default(1);
            $table->foreignId('replaces_id')->nullable()->constrained('routines')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('name');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routines');
    }
};
