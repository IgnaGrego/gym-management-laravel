<?php

use App\Filament\Resources\ExerciseResource\Pages\CreateExercise;
use App\Filament\Resources\ExerciseResource\Pages\EditExercise;
use App\Filament\Resources\ExerciseResource\Pages\ListExercises;
use App\Filament\Resources\ExerciseResource\Pages\ViewExercise;
use App\Models\Exercise;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
 * Exercise CRUD and lifecycle feature tests (SPEC-009 FR-001..FR-007; BR-002,
 * BR-003, BR-004, BR-005, BR-006, BR-007, BR-008, BR-010, BR-011; ERR-001,
 * ERR-002, ERR-003, ERR-004, ERR-005, ERR-007; AC-1..AC-10, AC-12, AC-13).
 * Authorization is enforced server-side (AGENTS.md §17).
 */

it('allows ADMIN to create an exercise with required and optional fields stored active', function () {
    // AC-1 (FR-001, FR-002, BR-002, BR-007).
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreateExercise::class)
        ->fillForm([
            'name' => 'Bench Press',
            'muscle_group' => Exercise::MUSCLE_GROUP_CHEST,
            'equipment' => 'Barbell',
            'difficulty' => Exercise::DIFFICULTY_INTERMEDIATE,
            'instructions' => 'Lower the bar to the chest and press up.',
            'video_url' => 'https://www.youtube.com/watch?v=example',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $exercise = Exercise::where('name', 'Bench Press')->firstOrFail();

    expect($exercise->muscle_group)->toBe(Exercise::MUSCLE_GROUP_CHEST);
    expect($exercise->equipment)->toBe('Barbell');
    expect($exercise->difficulty)->toBe(Exercise::DIFFICULTY_INTERMEDIATE);
    expect($exercise->instructions)->toBe('Lower the bar to the chest and press up.');
    expect($exercise->video_url)->toBe('https://www.youtube.com/watch?v=example');
    expect($exercise->is_active)->toBeTrue();
});

it('allows TRAINER to create an exercise', function () {
    // AC-1 (BR-009, EX-08; D-20 option 2): TRAINER receives the full
    // management set.
    $trainer = userWithRoles([Role::TRAINER]);

    Livewire::actingAs($trainer)
        ->test(CreateExercise::class)
        ->fillForm([
            'name' => 'Squat',
            'muscle_group' => Exercise::MUSCLE_GROUP_QUADRICEPS,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Exercise::count())->toBe(1);
    expect(Exercise::first()->is_active)->toBeTrue();
});

it('allows creating a minimal exercise with only name and muscle group', function () {
    // AC-1 (FR-001, AF-004, BR-002): a valid exercise needs only name +
    // muscle group; absent optional fields are stored as null.
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreateExercise::class)
        ->fillForm([
            'name' => 'Minimal Exercise',
            'muscle_group' => Exercise::MUSCLE_GROUP_FULL_BODY,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $exercise = Exercise::where('name', 'Minimal Exercise')->firstOrFail();

    expect($exercise->equipment)->toBeNull();
    expect($exercise->difficulty)->toBeNull();
    expect($exercise->instructions)->toBeNull();
    expect($exercise->video_url)->toBeNull();
    expect($exercise->is_active)->toBeTrue();
});

it('does not create or modify any other record when creating an exercise', function () {
    // AC-13 (BR-009, BR-011): creating an exercise touches only the
    // exercises table; no user/client/plan/membership/turno/attendance
    // record is created or modified.
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreateExercise::class)
        ->fillForm([
            'name' => 'Standalone Exercise',
            'muscle_group' => Exercise::MUSCLE_GROUP_BACK,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Exercise::count())->toBe(1);
    expect(DB::table('users')->count())->toBe(1); // only the acting admin
    expect(DB::table('role_user')->count())->toBe(1); // only the admin's role
    expect(DB::table('clients')->count())->toBe(0);
    expect(DB::table('plans')->count())->toBe(0);
    expect(DB::table('memberships')->count())->toBe(0);
    expect(DB::table('turnos')->count())->toBe(0);
    expect(DB::table('attendances')->count())->toBe(0);
});

it('rejects creating an exercise with a duplicate name', function () {
    // AC-2 (ERR-002, BR-003).
    Exercise::factory()->create(['name' => 'Bench Press']);
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreateExercise::class)
        ->fillForm([
            'name' => 'Bench Press',
            'muscle_group' => Exercise::MUSCLE_GROUP_CHEST,
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique']);

    expect(Exercise::count())->toBe(1);
});

it('rejects creating an exercise whose name is used by an inactive exercise', function () {
    // AC-2 (ERR-002, BR-003, AF-005): a deactivated exercise's name stays
    // occupied.
    Exercise::factory()->create(['name' => 'Deadlift', 'is_active' => false]);
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreateExercise::class)
        ->fillForm([
            'name' => 'Deadlift',
            'muscle_group' => Exercise::MUSCLE_GROUP_BACK,
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique']);

    expect(Exercise::count())->toBe(1);
});

it('rejects editing an exercise onto another exercise name, keeping its own name', function () {
    // AC-2 (ERR-002, BR-003) on update: the current record's own name is
    // ignored, but another exercise's name collides.
    $other = Exercise::factory()->create(['name' => 'Squat']);
    $exercise = Exercise::factory()->create(['name' => 'Lunge']);
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(EditExercise::class, ['record' => $exercise->getRouteKey()])
        ->fillForm(['name' => 'Squat'])
        ->call('save')
        ->assertHasFormErrors(['name' => 'unique']);

    expect($exercise->fresh()->name)->toBe('Lunge');
    expect($other->fresh()->name)->toBe('Squat');
});

it('rejects creating an exercise without the required fields', function () {
    // AC-1 (ERR-001, BR-002): name and muscle group are required.
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreateExercise::class)
        ->call('create')
        ->assertHasFormErrors([
            'name' => 'required',
            'muscle_group' => 'required',
        ]);

    expect(Exercise::count())->toBe(0);
});

it('rejects a muscle group outside the fixed set', function () {
    // AC-3 (ERR-003, BR-004): a value outside the fixed set is rejected even
    // though the Select option list would not offer it (server-side `in:`
    // rule, AGENTS.md §17).
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreateExercise::class)
        ->fillForm([
            'name' => 'Invalid Muscle Group',
            'muscle_group' => 'neck',
        ])
        ->call('create')
        ->assertHasFormErrors(['muscle_group' => 'in']);

    expect(Exercise::count())->toBe(0);
});

it('rejects a difficulty outside the fixed set and accepts an omitted difficulty', function () {
    // AC-4 (ERR-004, BR-005): difficulty must be one of the fixed set when
    // present; omitting it is accepted.
    $admin = userWithRoles([Role::ADMIN]);

    Livewire::actingAs($admin)
        ->test(CreateExercise::class)
        ->fillForm([
            'name' => 'Invalid Difficulty',
            'muscle_group' => Exercise::MUSCLE_GROUP_ABS,
            'difficulty' => 'expert',
        ])
        ->call('create')
        ->assertHasFormErrors(['difficulty' => 'in']);

    expect(Exercise::count())->toBe(0);

    Livewire::actingAs($admin)
        ->test(CreateExercise::class)
        ->fillForm([
            'name' => 'No Difficulty',
            'muscle_group' => Exercise::MUSCLE_GROUP_ABS,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Exercise::where('name', 'No Difficulty')->firstOrFail()->difficulty)->toBeNull();
});

it('rejects an invalid video URL and accepts an omitted video', function () {
    // AC-5 (ERR-005, BR-006): the video, when present, must be an absolute
    // http/https URL; omitting it is accepted.
    $admin = userWithRoles([Role::ADMIN]);

    foreach (['not-a-url', 'ftp://example.com/video.mp4', 'www.example.com/video'] as $videoUrl) {
        Livewire::actingAs($admin)
            ->test(CreateExercise::class)
            ->fillForm([
                'name' => 'Bad Video '.$videoUrl,
                'muscle_group' => Exercise::MUSCLE_GROUP_BICEPS,
                'video_url' => $videoUrl,
            ])
            ->call('create')
            ->assertHasFormErrors(['video_url' => 'url']);
    }

    expect(Exercise::count())->toBe(0);

    Livewire::actingAs($admin)
        ->test(CreateExercise::class)
        ->fillForm([
            'name' => 'No Video',
            'muscle_group' => Exercise::MUSCLE_GROUP_TRICEPS,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Exercise::where('name', 'No Video')->firstOrFail()->video_url)->toBeNull();
});

it('lets ADMIN search exercises by name and equipment', function () {
    // AC-6 (FR-002): search by name and, optionally, equipment.
    $admin = userWithRoles([Role::ADMIN]);

    $byName = Exercise::factory()->create(['name' => 'Searchable Press']);
    $byEquipment = Exercise::factory()->create([
        'name' => 'Cable Fly',
        'equipment' => 'Cable Machine',
    ]);
    $other = Exercise::factory()->create(['name' => 'Another Exercise']);

    Livewire::actingAs($admin)
        ->test(ListExercises::class)
        ->searchTable('Searchable')
        ->assertCanSeeTableRecords([$byName])
        ->assertCanNotSeeTableRecords([$byEquipment, $other]);

    Livewire::actingAs($admin)
        ->test(ListExercises::class)
        ->searchTable('Cable Machine')
        ->assertCanSeeTableRecords([$byEquipment])
        ->assertCanNotSeeTableRecords([$byName, $other]);
});

it('lets ADMIN filter exercises by status, muscle group and difficulty', function () {
    // AC-6 (FR-002).
    $admin = userWithRoles([Role::ADMIN]);

    $activeChest = Exercise::factory()->create([
        'name' => 'Push Up',
        'muscle_group' => Exercise::MUSCLE_GROUP_CHEST,
        'difficulty' => Exercise::DIFFICULTY_BEGINNER,
        'is_active' => true,
    ]);
    $inactiveChest = Exercise::factory()->create([
        'name' => 'Dips',
        'muscle_group' => Exercise::MUSCLE_GROUP_CHEST,
        'difficulty' => Exercise::DIFFICULTY_ADVANCED,
        'is_active' => false,
    ]);
    $activeBack = Exercise::factory()->create([
        'name' => 'Pull Up',
        'muscle_group' => Exercise::MUSCLE_GROUP_BACK,
        'difficulty' => Exercise::DIFFICULTY_INTERMEDIATE,
        'is_active' => true,
    ]);

    Livewire::actingAs($admin)
        ->test(ListExercises::class)
        ->filterTable('muscle_group', Exercise::MUSCLE_GROUP_CHEST)
        ->assertCanSeeTableRecords([$activeChest, $inactiveChest])
        ->assertCanNotSeeTableRecords([$activeBack]);

    Livewire::actingAs($admin)
        ->test(ListExercises::class)
        ->filterTable('difficulty', Exercise::DIFFICULTY_BEGINNER)
        ->assertCanSeeTableRecords([$activeChest])
        ->assertCanNotSeeTableRecords([$inactiveChest, $activeBack]);

    Livewire::actingAs($admin)
        ->test(ListExercises::class)
        ->filterTable('is_active', '0')
        ->assertCanSeeTableRecords([$inactiveChest])
        ->assertCanNotSeeTableRecords([$activeChest, $activeBack]);
});

it('lets ADMIN view the full exercise detail including status and attributes', function () {
    // AC-7 (FR-003, FR-006, FR-007).
    $admin = userWithRoles([Role::ADMIN]);
    $exercise = Exercise::factory()->create([
        'name' => 'Detail Exercise',
        'muscle_group' => Exercise::MUSCLE_GROUP_SHOULDERS,
        'equipment' => 'Dumbbells',
        'difficulty' => Exercise::DIFFICULTY_BEGINNER,
        'instructions' => 'Press the dumbbells overhead.',
        'video_url' => 'https://example.com/video',
        'is_active' => true,
    ]);

    Livewire::actingAs($admin)
        ->test(ViewExercise::class, ['record' => $exercise->getRouteKey()])
        ->assertSee('Detail Exercise')
        ->assertSee('Hombros')
        ->assertSee('Dumbbells')
        ->assertSee('Principiante')
        ->assertSee('Press the dumbbells overhead.')
        ->assertSee('https://example.com/video')
        ->assertSee('Activo');
});

it('lets ADMIN edit an active exercise and persist the changes', function () {
    // AC-8 (FR-004, BR-010).
    $admin = userWithRoles([Role::ADMIN]);
    $exercise = Exercise::factory()->create(['name' => 'Original Exercise']);

    Livewire::actingAs($admin)
        ->test(EditExercise::class, ['record' => $exercise->getRouteKey()])
        ->fillForm([
            'name' => 'Updated Exercise',
            'muscle_group' => Exercise::MUSCLE_GROUP_HAMSTRINGS,
            'equipment' => 'Leg Curl Machine',
            'difficulty' => Exercise::DIFFICULTY_INTERMEDIATE,
            'instructions' => 'Curl the legs against resistance.',
            'video_url' => 'https://example.com/hamstrings',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $exercise->refresh();

    expect($exercise->name)->toBe('Updated Exercise');
    expect($exercise->muscle_group)->toBe(Exercise::MUSCLE_GROUP_HAMSTRINGS);
    expect($exercise->equipment)->toBe('Leg Curl Machine');
    expect($exercise->difficulty)->toBe(Exercise::DIFFICULTY_INTERMEDIATE);
    expect($exercise->instructions)->toBe('Curl the legs against resistance.');
    expect($exercise->video_url)->toBe('https://example.com/hamstrings');
});

it('lets ADMIN edit an inactive exercise and persist the changes', function () {
    // AC-8 (FR-004, BR-010): editing is allowed regardless of status.
    $admin = userWithRoles([Role::ADMIN]);
    $exercise = Exercise::factory()->create(['name' => 'Inactive Editable', 'is_active' => false]);

    Livewire::actingAs($admin)
        ->test(EditExercise::class, ['record' => $exercise->getRouteKey()])
        ->fillForm(['name' => 'Inactive Edited'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($exercise->fresh()->name)->toBe('Inactive Edited');
    expect($exercise->fresh()->is_active)->toBeFalse();
});

it('lets ADMIN deactivate an active exercise via the list action; it remains in the system', function () {
    // AC-9 (FR-005, FR-006, BR-007).
    $admin = userWithRoles([Role::ADMIN]);
    $exercise = Exercise::factory()->create(['name' => 'Deactivatable']);

    Livewire::actingAs($admin)
        ->test(ListExercises::class)
        ->callTableAction('deactivate', $exercise);

    expect($exercise->fresh()->is_active)->toBeFalse();
    expect(Exercise::find($exercise->id))->not->toBeNull();
});

it('lets ADMIN reactivate an inactive exercise via the list action', function () {
    // AC-10 (FR-005, AF-002, BR-007).
    $admin = userWithRoles([Role::ADMIN]);
    $exercise = Exercise::factory()->create(['name' => 'Reactivatible', 'is_active' => false]);

    Livewire::actingAs($admin)
        ->test(ListExercises::class)
        ->callTableAction('activate', $exercise);

    expect($exercise->fresh()->is_active)->toBeTrue();
});

it('shows the lifecycle actions according to the exercise status', function () {
    // AC-9, AC-10 (FR-005): Deactivate is only offered for active exercises
    // and Activate only for inactive exercises; the rules are still enforced
    // server-side (AGENTS.md §17).
    $admin = userWithRoles([Role::ADMIN]);
    $active = Exercise::factory()->create(['name' => 'Active Exercise', 'is_active' => true]);
    $inactive = Exercise::factory()->create(['name' => 'Inactive Exercise', 'is_active' => false]);

    Livewire::actingAs($admin)
        ->test(ListExercises::class)
        ->assertTableActionVisible('deactivate', $active)
        ->assertTableActionHidden('deactivate', $inactive)
        ->assertTableActionVisible('activate', $inactive)
        ->assertTableActionHidden('activate', $active);
});

it('lets ADMIN toggle the status during an edit', function () {
    // FR-005 path: the status can also be changed from the edit form.
    $admin = userWithRoles([Role::ADMIN]);
    $exercise = Exercise::factory()->create(['name' => 'Toggle Me', 'is_active' => true]);

    Livewire::actingAs($admin)
        ->test(EditExercise::class, ['record' => $exercise->getRouteKey()])
        ->fillForm(['is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($exercise->fresh()->is_active)->toBeFalse();
});

it('lets ADMIN deactivate and reactivate from the detail view header actions', function () {
    // FR-005 paths on the detail page (ViewExercise header).
    $admin = userWithRoles([Role::ADMIN]);
    $exercise = Exercise::factory()->create(['name' => 'Header Lifecycle']);

    Livewire::actingAs($admin)
        ->test(ViewExercise::class, ['record' => $exercise->getRouteKey()])
        ->callAction('deactivate');

    expect($exercise->fresh()->is_active)->toBeFalse();

    Livewire::actingAs($admin)
        ->test(ViewExercise::class, ['record' => $exercise->getRouteKey()])
        ->callAction('activate');

    expect($exercise->fresh()->is_active)->toBeTrue();
});

it('does not expose a delete operation and preserves created exercise records', function () {
    // AC-12 (ERR-007, BR-008): no delete policy is registered and no delete
    // action exists; a created exercise record persists.
    $admin = userWithRoles([Role::ADMIN]);
    $exercise = Exercise::factory()->create(['name' => 'Eternal Exercise']);

    expect($admin->can('delete', $exercise))->toBeFalse();

    Livewire::actingAs($admin)
        ->test(ListExercises::class)
        ->assertTableActionDoesNotExist('delete');

    $this->actingAs($admin)->get('/admin/exercises')->assertOk();

    expect(Exercise::find($exercise->id))->not->toBeNull();
});

it('leaves every other table untouched when editing, deactivating or reactivating an exercise', function () {
    // AC-13 (BR-009, BR-011): editing, activating or deactivating an
    // exercise never creates, modifies or deletes any other record.
    $admin = userWithRoles([Role::ADMIN]);
    $exercise = Exercise::factory()->create(['name' => 'No Side Effects']);

    Livewire::actingAs($admin)
        ->test(EditExercise::class, ['record' => $exercise->getRouteKey()])
        ->fillForm(['name' => 'No Side Effects Edited'])
        ->call('save')
        ->assertHasNoFormErrors();

    Livewire::actingAs($admin)
        ->test(ListExercises::class)
        ->callTableAction('deactivate', $exercise);

    $exercise->refresh();

    Livewire::actingAs($admin)
        ->test(ListExercises::class)
        ->callTableAction('activate', $exercise);

    expect(Exercise::count())->toBe(1);
    expect(DB::table('users')->count())->toBe(1); // only the acting admin
    expect(DB::table('role_user')->count())->toBe(1); // only the admin's role
    expect(DB::table('clients')->count())->toBe(0);
    expect(DB::table('plans')->count())->toBe(0);
    expect(DB::table('memberships')->count())->toBe(0);
    expect(DB::table('turnos')->count())->toBe(0);
    expect(DB::table('attendances')->count())->toBe(0);
});
