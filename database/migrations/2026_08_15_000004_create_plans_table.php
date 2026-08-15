<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * SPEC-003 (Plan Management). The plan catalog is standalone in this
     * Specification: a required unique name, an optional description, a
     * required price and an optional one-time enrollment fee (matricula)
     * stored as decimal(10,2) amounts in the MVP's single implicit currency
     * (BR-001, BR-002, AP-01, AP-05; ADR-003), and an active/inactive status
     * flag defaulting to active (FR-001, AP-02). The unique index on name
     * enforces BR-003 at the database level. There is no currency column
     * (AP-05), no period/duration column (AP-06), and no foreign keys:
     * memberships (SPEC-004) and payments (SPEC-005) consume plans later and
     * define the reference direction (BR-007, C-07). Positivity of amounts
     * (BR-002) is enforced by form validation, not DB check constraints
     * (ADR-003).
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('enrollment_fee', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
