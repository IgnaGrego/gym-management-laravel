<?php

use App\Models\Exercise;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Exercise model unit tests (SPEC-009 BR-002, BR-003, BR-004, BR-005,
 * BR-007, BR-011; FR-001, FR-006; EX-03, EX-04; AC-1, AC-12, AC-13).
 */

it('exposes exactly the 12 muscle-group identifiers', function () {
    // BR-004, EX-03: the fixed set is exactly the spec's 12 identifiers.
    expect(Exercise::MUSCLE_GROUP_CHEST)->toBe('chest');
    expect(Exercise::MUSCLE_GROUP_BACK)->toBe('back');
    expect(Exercise::MUSCLE_GROUP_SHOULDERS)->toBe('shoulders');
    expect(Exercise::MUSCLE_GROUP_BICEPS)->toBe('biceps');
    expect(Exercise::MUSCLE_GROUP_TRICEPS)->toBe('triceps');
    expect(Exercise::MUSCLE_GROUP_FOREARMS)->toBe('forearms');
    expect(Exercise::MUSCLE_GROUP_ABS)->toBe('abs');
    expect(Exercise::MUSCLE_GROUP_QUADRICEPS)->toBe('quadriceps');
    expect(Exercise::MUSCLE_GROUP_HAMSTRINGS)->toBe('hamstrings');
    expect(Exercise::MUSCLE_GROUP_GLUTES)->toBe('glutes');
    expect(Exercise::MUSCLE_GROUP_CALVES)->toBe('calves');
    expect(Exercise::MUSCLE_GROUP_FULL_BODY)->toBe('full_body');

    expect(Exercise::muscleGroups())->toBe([
        'chest',
        'back',
        'shoulders',
        'biceps',
        'triceps',
        'forearms',
        'abs',
        'quadriceps',
        'hamstrings',
        'glutes',
        'calves',
        'full_body',
    ]);
});

it('exposes exactly the 3 difficulty identifiers', function () {
    // BR-005, EX-04.
    expect(Exercise::DIFFICULTY_BEGINNER)->toBe('beginner');
    expect(Exercise::DIFFICULTY_INTERMEDIATE)->toBe('intermediate');
    expect(Exercise::DIFFICULTY_ADVANCED)->toBe('advanced');

    expect(Exercise::difficulties())->toBe(['beginner', 'intermediate', 'advanced']);
});

it('maps every fixed-set identifier to a display label', function () {
    // BR-004, BR-005: display labels are a presentation concern; every
    // stored identifier has a label.
    expect(Exercise::muscleGroupLabels())->toHaveCount(12);
    expect(Exercise::difficultyLabels())->toHaveCount(3);

    foreach (Exercise::muscleGroups() as $muscleGroup) {
        expect(Exercise::muscleGroupLabels())->toHaveKey($muscleGroup);
    }

    foreach (Exercise::difficulties() as $difficulty) {
        expect(Exercise::difficultyLabels())->toHaveKey($difficulty);
    }
});

it('creates an exercise as active by default with absent optional fields null', function () {
    // FR-001, BR-002, BR-007, AC-1.
    $exercise = Exercise::factory()->create();

    expect($exercise->is_active)->toBeTrue();
    expect($exercise->isActive())->toBeTrue();
    expect($exercise->equipment)->toBeNull();
    expect($exercise->difficulty)->toBeNull();
    expect($exercise->instructions)->toBeNull();
    expect($exercise->video_url)->toBeNull();
});

it('casts is_active to boolean', function () {
    // BR-007.
    $active = Exercise::factory()->create(['is_active' => true]);
    $inactive = Exercise::factory()->create(['is_active' => false]);

    expect($active->is_active)->toBeTrue();
    expect($active->is_active)->toBeBool();
    expect($inactive->is_active)->toBeFalse();
});

it('rejects a duplicate name via the unique database constraint, including against an inactive record', function () {
    // BR-003, ERR-002, AF-005: the unique index on exercises.name enforces
    // uniqueness at the database level regardless of status.
    Exercise::factory()->create(['name' => 'Bench Press']);
    Exercise::factory()->create(['name' => 'Deadlift', 'is_active' => false]);

    expect(fn () => Exercise::factory()->create(['name' => 'Bench Press']))
        ->toThrow(QueryException::class);
    expect(fn () => Exercise::factory()->create(['name' => 'Deadlift']))
        ->toThrow(QueryException::class);
});

it('defaults is_active to true even for a raw write path and creates the expected columns and indexes', function () {
    // FR-001, FR-002, BR-003, BR-007: the DB default on is_active is true
    // even for a raw insert; the expected columns and the name (unique),
    // muscle_group and is_active indexes exist.
    DB::table('exercises')->insert([
        'name' => 'Raw Insert Exercise',
        'muscle_group' => Exercise::MUSCLE_GROUP_BACK,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('exercises')->first()->is_active)->toBe(1);

    expect(Schema::hasColumns('exercises', [
        'id',
        'name',
        'muscle_group',
        'equipment',
        'difficulty',
        'instructions',
        'video_url',
        'is_active',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
    expect(Schema::hasIndex('exercises', 'exercises_name_unique'))->toBeTrue();
    expect(Schema::hasIndex('exercises', 'exercises_muscle_group_index'))->toBeTrue();
    expect(Schema::hasIndex('exercises', 'exercises_is_active_index'))->toBeTrue();
});

it('scopes active exercises only', function () {
    // FR-006: scopeActive returns only the currently offered exercises (the
    // routine-friendly set for SPEC-010, SPEC-009 §10).
    $active = Exercise::factory()->create();
    Exercise::factory()->create(['is_active' => false]);

    expect(Exercise::active()->pluck('id'))->toHaveCount(1);
    expect(Exercise::active()->pluck('id'))->toContain($active->id);
});

it('creates no other record when creating an exercise', function () {
    // BR-011, AC-13: creating an exercise touches only the exercises table;
    // no user, client, plan, membership, turno or attendance record is
    // created.
    Exercise::factory()->create();

    expect(Exercise::count())->toBe(1);
    expect(DB::table('users')->count())->toBe(0);
    expect(DB::table('role_user')->count())->toBe(0);
    expect(DB::table('clients')->count())->toBe(0);
    expect(DB::table('plans')->count())->toBe(0);
    expect(DB::table('memberships')->count())->toBe(0);
    expect(DB::table('turnos')->count())->toBe(0);
    expect(DB::table('attendances')->count())->toBe(0);
});
