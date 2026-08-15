<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutineExercise extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'routine_day_id',
        'exercise_id',
        'set_number',
        'target_reps',
        'target_weight',
        'rest_seconds',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * set_number / target_reps / rest_seconds are cast to integer (BR-004,
     * BR-010, AR-06). target_weight is cast to decimal:2 (the ADR-003 decimal
     * cast precedent — note Eloquent returns strings for decimal casts).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'set_number' => 'integer',
            'target_reps' => 'integer',
            'target_weight' => 'decimal:2',
            'rest_seconds' => 'integer',
        ];
    }

    /**
     * The routine day this set-level prescription row belongs to (BR-004).
     */
    public function routineDay(): BelongsTo
    {
        return $this->belongsTo(RoutineDay::class);
    }

    /**
     * The catalogue exercise this set row references (BR-006; the
     * consuming-module reference direction documented by SPEC-009 BR-011 /
     * §10). Displaying a prescription reads the exercise's CURRENT catalogue
     * attributes (AR-04: no per-prescription snapshot).
     */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
