<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SPEC-005 (Payments & Cuotas). A Cuota is the single due amount generated
     * for a membership period (BR-001, D-02 option 2). membership_id is a
     * required, UNIQUE FK with restrictOnDelete (BR-001, NC-02): exactly one
     * cuota per membership, and memberships are never hard-deleted so the
     * historical cuota is preserved (SPEC-004 BR-014). amount is decimal(10,2)
     * NOT NULL (BR-002, ADR-003); positivity is enforced by framework
     * validation, not a DB CHECK constraint (ADR-003). status is a string
     * column (not a DB enum) defaulting to 'pending' (BR-003) with the model
     * constants as the single source of truth. paid_at is a nullable timestamp
     * set when the cuota becomes paid (FR-006/FR-007, OQ-02). There is no
     * due_date column: the period semantics belong to the membership
     * (SPEC-004 BR-003; NC-04).
     */
    public function up(): void
    {
        Schema::create('cuotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cuotas');
    }
};
