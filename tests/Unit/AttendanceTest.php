<?php

use App\Models\Attendance;
use App\Models\Client;
use App\Models\Role;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Attendance model unit tests (SPEC-008 BR-001, BR-002, BR-007, BR-008,
 * BR-011, BR-012; AT-04, AT-05, AT-06, AT-07; C-02; AC-8, AC-12, AC-14).
 */

it('navigates the client, recordedBy and turno relationships', function () {
    // BR-002, BR-011, AT-06: an attendance record belongs to exactly one
    // client, records the staff User who performed the check-in and may link
    // an existing turno.
    $client = Client::factory()->create();
    $staff = userWithRoles([Role::ADMIN]);
    $turno = Turno::factory()->create();
    $attendance = Attendance::factory()->create([
        'client_id' => $client->id,
        'recorded_by' => $staff->id,
        'turno_id' => $turno->id,
    ]);

    expect($attendance->client->is($client))->toBeTrue();
    expect($attendance->recordedBy->is($staff))->toBeTrue();
    expect($attendance->turno->is($turno))->toBeTrue();
});

it('navigates a client to their attendance records', function () {
    // C-02: a client aggregates attendance records.
    $client = Client::factory()->create();
    $first = Attendance::factory()->create(['client_id' => $client->id]);
    $second = Attendance::factory()->create(['client_id' => $client->id]);
    $other = Attendance::factory()->create();

    expect($client->attendances()->pluck('attendances.id'))->toContain($first->id);
    expect($client->attendances()->pluck('attendances.id'))->toContain($second->id);
    expect($client->attendances()->pluck('attendances.id'))->not->toContain($other->id);
});

it('casts attended_at to a Carbon datetime', function () {
    // BR-007: attended_at is the gym-local access timestamp, cast to Carbon.
    $attendance = Attendance::factory()->create([
        'attended_at' => '2026-08-15 08:30:00',
    ]);

    expect($attendance->attended_at)->toBeInstanceOf(Carbon\Carbon::class);
    expect($attendance->attended_at->toDateTimeString())->toBe('2026-08-15 08:30:00');
});

it('requires recorded_by via the database not-null constraint', function () {
    // BR-011, AT-08: every attendance record stores the staff User who
    // performed the check-in; a record without it fails the NOT NULL
    // constraint.
    $client = Client::factory()->create();

    expect(fn () => Attendance::create([
        'client_id' => $client->id,
        'attended_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('requires attended_at via the database not-null constraint', function () {
    // BR-007: attended_at is required; a record without it fails the NOT NULL
    // constraint.
    $client = Client::factory()->create();

    expect(fn () => Attendance::create([
        'client_id' => $client->id,
        'recorded_by' => User::factory()->create()->id,
    ]))->toThrow(QueryException::class);
});

it('stores no status and no booking_id column', function () {
    // BR-001, AT-07 (no status: an event, not a stateful entity) and AT-04 /
    // AC-14 (no booking_id column in the MVP: SPEC-007 is blocked and the
    // booking link is deferred).
    expect(Schema::hasColumns('attendances', [
        'id',
        'client_id',
        'attended_at',
        'recorded_by',
        'turno_id',
        'notes',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
    expect(Schema::hasColumn('attendances', 'status'))->toBeFalse();
    expect(Schema::hasColumn('attendances', 'booking_id'))->toBeFalse();
});

it('creates no other record when creating an attendance record', function () {
    // BR-012, AC-12: recording a check-in creates ONLY the attendance record;
    // no client, membership, turno, plan or user record is created or
    // modified.
    $client = Client::factory()->create();
    $staff = userWithRoles([Role::ADMIN]);

    Attendance::factory()->create([
        'client_id' => $client->id,
        'recorded_by' => $staff->id,
    ]);

    expect(Attendance::count())->toBe(1);
    expect(Client::count())->toBe(1);
    expect(User::count())->toBe(1); // only the acting staff user
    expect(DB::table('memberships')->count())->toBe(0);
    expect(DB::table('turnos')->count())->toBe(0);
    expect(DB::table('plans')->count())->toBe(0);
});

it('defaults the factory state to now with null turno and notes', function () {
    // AT-05, AT-06 (FR-001): the factory defaults attended_at to now and the
    // optional turno link and notes to null.
    $attendance = Attendance::factory()->create();

    expect($attendance->attended_at)->toBeInstanceOf(Carbon\Carbon::class);
    expect($attendance->attended_at->isPast())->toBeTrue();
    expect($attendance->turno_id)->toBeNull();
    expect($attendance->notes)->toBeNull();
});

it('scopes attendance records to a single client', function () {
    // FR-004: scopeForClient returns only the client's records.
    $client = Client::factory()->create();
    $other = Client::factory()->create();
    $mine = Attendance::factory()->create(['client_id' => $client->id]);
    Attendance::factory()->create(['client_id' => $client->id]);
    Attendance::factory()->create(['client_id' => $other->id]);

    expect(Attendance::forClient($client->id)->pluck('id'))
        ->toHaveCount(2)
        ->toContain($mine->id);
});

it('blocks hard deletion of a client, user or turno referenced by an attendance record', function () {
    // BR-008 (preservation pattern): restrictOnDelete guards historical
    // attendance data; referenced clients, users and turnos cannot be
    // hard-deleted.
    $attendance = Attendance::factory()->create([
        'turno_id' => Turno::factory()->create()->id,
    ]);

    expect(fn () => $attendance->client->delete())->toThrow(QueryException::class);
    expect(fn () => $attendance->recordedBy->delete())->toThrow(QueryException::class);
    expect(fn () => $attendance->turno->delete())->toThrow(QueryException::class);

    expect(Client::find($attendance->client_id))->not->toBeNull();
    expect(User::find($attendance->recorded_by))->not->toBeNull();
    expect(Turno::find($attendance->turno_id))->not->toBeNull();
    expect(Attendance::find($attendance->id))->not->toBeNull();
});
