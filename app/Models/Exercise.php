<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exercise extends Model
{
    use HasFactory;

    /**
     * The 12 fixed muscle-group identifiers (SPEC-009 BR-004, EX-03).
     *
     * These constants are the single source of truth for the muscle-group
     * set. Stored values are fixed English identifiers; the display labels
     * are a presentation concern (muscleGroupLabels(), BR-004).
     */
    public const MUSCLE_GROUP_CHEST = 'chest';

    public const MUSCLE_GROUP_BACK = 'back';

    public const MUSCLE_GROUP_SHOULDERS = 'shoulders';

    public const MUSCLE_GROUP_BICEPS = 'biceps';

    public const MUSCLE_GROUP_TRICEPS = 'triceps';

    public const MUSCLE_GROUP_FOREARMS = 'forearms';

    public const MUSCLE_GROUP_ABS = 'abs';

    public const MUSCLE_GROUP_QUADRICEPS = 'quadriceps';

    public const MUSCLE_GROUP_HAMSTRINGS = 'hamstrings';

    public const MUSCLE_GROUP_GLUTES = 'glutes';

    public const MUSCLE_GROUP_CALVES = 'calves';

    public const MUSCLE_GROUP_FULL_BODY = 'full_body';

    /**
     * The 3 fixed difficulty identifiers (SPEC-009 BR-005, EX-04).
     */
    public const DIFFICULTY_BEGINNER = 'beginner';

    public const DIFFICULTY_INTERMEDIATE = 'intermediate';

    public const DIFFICULTY_ADVANCED = 'advanced';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'muscle_group',
        'equipment',
        'difficulty',
        'instructions',
        'video_url',
        'is_active',
    ];

    /**
     * Default attribute values.
     *
     * A new exercise is always created with status active (FR-001, BR-007,
     * EX-07). The DB column carries the same default; the model default keeps
     * the in-memory record correct for every write path.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * is_active is a boolean (BR-007, EX-07), the same representation as
     * Plan (SPEC-003 AP-02). All other attributes stay plain strings/text (no
     * cast); the fixed-set attributes (muscle_group, difficulty) are stored
     * as strings and validated against the model constants (BR-004, BR-005),
     * the string-with-constants convention (ADR-004).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * The flat list of the 12 muscle-group identifiers (BR-004, EX-03).
     *
     * Shared by the form options, the display labels and the `in:`
     * validation rule so the fixed set has a single source of truth.
     *
     * @return array<int, string>
     */
    public static function muscleGroups(): array
    {
        return [
            static::MUSCLE_GROUP_CHEST,
            static::MUSCLE_GROUP_BACK,
            static::MUSCLE_GROUP_SHOULDERS,
            static::MUSCLE_GROUP_BICEPS,
            static::MUSCLE_GROUP_TRICEPS,
            static::MUSCLE_GROUP_FOREARMS,
            static::MUSCLE_GROUP_ABS,
            static::MUSCLE_GROUP_QUADRICEPS,
            static::MUSCLE_GROUP_HAMSTRINGS,
            static::MUSCLE_GROUP_GLUTES,
            static::MUSCLE_GROUP_CALVES,
            static::MUSCLE_GROUP_FULL_BODY,
        ];
    }

    /**
     * Muscle-group display labels (presentation only; BR-004: "Stored values
     * are fixed identifiers; the display labels are a presentation concern").
     *
     * @return array<string, string>
     */
    public static function muscleGroupLabels(): array
    {
        return [
            static::MUSCLE_GROUP_CHEST => 'Chest',
            static::MUSCLE_GROUP_BACK => 'Back',
            static::MUSCLE_GROUP_SHOULDERS => 'Shoulders',
            static::MUSCLE_GROUP_BICEPS => 'Biceps',
            static::MUSCLE_GROUP_TRICEPS => 'Triceps',
            static::MUSCLE_GROUP_FOREARMS => 'Forearms',
            static::MUSCLE_GROUP_ABS => 'Abs',
            static::MUSCLE_GROUP_QUADRICEPS => 'Quadriceps',
            static::MUSCLE_GROUP_HAMSTRINGS => 'Hamstrings',
            static::MUSCLE_GROUP_GLUTES => 'Glutes',
            static::MUSCLE_GROUP_CALVES => 'Calves',
            static::MUSCLE_GROUP_FULL_BODY => 'Full body',
        ];
    }

    /**
     * The flat list of the 3 difficulty identifiers (BR-005, EX-04).
     *
     * @return array<int, string>
     */
    public static function difficulties(): array
    {
        return [
            static::DIFFICULTY_BEGINNER,
            static::DIFFICULTY_INTERMEDIATE,
            static::DIFFICULTY_ADVANCED,
        ];
    }

    /**
     * Difficulty display labels (presentation only; BR-005).
     *
     * @return array<string, string>
     */
    public static function difficultyLabels(): array
    {
        return [
            static::DIFFICULTY_BEGINNER => 'Beginner',
            static::DIFFICULTY_INTERMEDIATE => 'Intermediate',
            static::DIFFICULTY_ADVANCED => 'Advanced',
        ];
    }

    /**
     * Whether the exercise is currently offered for new routine prescriptions
     * (BR-007; the "currently offered" notion that SPEC-010 will consume).
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    /**
     * Scope the query to active (currently offered) exercises (FR-006).
     *
     * Directly reusable by SPEC-010 when building routine prescriptions that
     * may only use active exercises (SPEC-009 §10, BR-011).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
