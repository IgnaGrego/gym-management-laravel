<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'price',
        'enrollment_fee',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * Monetary amounts are decimal(10,2) and cast to two-decimal strings
     * (ADR-003); the lifecycle status is a boolean (SPEC-003 AP-02, BR-005).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'enrollment_fee' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The memberships that use this plan (SPEC-004, consumer relationship
     * anticipated by SPEC-003 §3).
     *
     * Plan edits or deactivation never modify existing memberships (SPEC-004
     * BR-013); this relationship is navigational only and is not displayed in
     * PlanResource in this Specification.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }
}
