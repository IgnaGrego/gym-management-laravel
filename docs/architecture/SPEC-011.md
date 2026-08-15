# Architecture — SPEC-011

## 1. Feature

Workout Logs & Progress for the gym management system:

- a **WorkoutLog** is a **per-set execution record** (gate **D-11 option 2**,
  pre-approved under NIGHT MODE): one row per performed set, matching the
  set-level prescription rows of SPEC-010 (BR-001, WL-01). It records what the
  client **actually performed** — client, performed timestamp, actual weight,
  actual reps, optional notes, and the staff User who recorded it (`recorded_by`
  audit, WL-11) — and never modifies the prescribed routine (C-10, BR-003);
- each log references **either** a prescribed `RoutineExercise` (the set-level
  prescription row from the routine version the client was on when the log was
  recorded) **or** a free catalogue `Exercise` (C-11 — both cases exist, BR-002);
  exactly one of `routine_exercise_id` / `exercise_id` is set (ERR-001);
- the log's `routine_exercise_id` reference is **version-stable** (gate **D-12
  option 3**, pre-approved): it keeps pointing at the specific version's set
  row; versioning a routine later never rewrites, re-points or deletes a log
  (BR-004, AF-004);
- staff — **ADMIN and TRAINER** — can record workout logs on behalf of clients
  and review a client's workout history and minimal prescription-vs-actual
  comparison from the admin panel (C-03 "a Trainer may review workout progress";
  C-15; WL-03). Client self-logging and client visibility of their OWN logs are
  deferred to SPEC-013 (D-18 option 3 pre-approved there);
- logs are **immutable in the MVP**: no edit, no delete, no status transitions
  (BR-006, WL-04 — the preservation pattern of AGENTS.md §12, same as
  `Attendance` SPEC-008);
- client isolation is preserved: a CLIENT never accesses another client's logs
  through any path defined here (C-13); client visibility of their own logs is
  SPEC-013 scope (BR-007);
- logging has **no membership/access precondition** (BR-010): unlike the
  Attendance check-in gate (SPEC-008 BR-003), no membership or access rule is
  applied to workout logs.

This is the eleventh Specification of the MVP. It builds on the SPEC-001/002
foundations (User / Role / Client models, `User::hasAnyRole`, policy pattern,
Filament admin panel; ADR-001/002/003/004), consumes SPEC-009 (`Exercise`
catalogue, `Exercise::scopeActive()` — the free-log reference direction) and
SPEC-010 (Routine → RoutineDay → RoutineExercise → Exercise, set-level rows,
versioning with reassignment — the prescribed-set reference surface), and
follows the SPEC-008 (Attendance) conventions — the closest pattern for an
immutable, ADMIN+TRAINER-managed event-log module with a server-injected
`recorded_by` audit field. Workout Logs is a greenfield module: no workout-log
tables exist yet. SPEC-013 (Client Portal) will depend on this Specification
for client-side log visibility.

---

## 2. Specification

Reference:

`docs/specs/SPEC-011.md`

Status note: SPEC-011 is approved for the architecture phase per
`docs/sdd/state.yaml` (status `spec_ready`, current phase `architecture`,
architect `in_progress`). The gates **D-11 option 2** (set-level prescription →
per-set log rows) and **D-12 option 3** (versioning with reassignment →
version-stable log references) are pre-approved under NIGHT MODE
(`docs/sdd/state.yaml` `project.po_decisions`). The confirmed decisions C-10 /
C-11 (prescription/execution separation; the log references RoutineExercise or
Exercise — both cases exist) and C-03 / C-13 / C-15 (trainer reviews progress;
client isolation; admin-panel context) apply. The Specification explicitly
flags assumptions **WL-01 to WL-11** as NOT confirmed business rules; they
require Product Owner confirmation before Implementation (SPEC-011 §14.1). This
design is written against the assumptions as stated and remains valid under the
documented alternatives unless the PO changes them (see §12 Pending PO
Confirmations).

Boundary note: client self-logging and client visibility of own logs
(SPEC-013), notifications, advanced progress analytics (volume totals, PRs,
charts), editing/deleting logs, a separate workout-session entity, RPE/rest
tracking, bulk import/export, membership-gated logging (BR-010) and the
trainer–client assignment (SPEC-002 OQ-02) are all explicitly OUT of scope
(SPEC-011 §12). This design introduces no session, notification, analytics or
client-portal concept of any kind.

---

## 3. Affected Modules

- **Workout Logs** (new module): the workout-log entity (`workout_logs` table)
  with its fields (`client_id`, `performed_at`, `routine_exercise_id`,
  `exercise_id`, `actual_weight`, `actual_reps`, `notes`, `recorded_by`), the
  per-set granularity (D-11 option 2, BR-001), the exactly-one-reference
  invariant (BR-002), the version-stable prescription reference (D-12 option 3,
  BR-004), the live catalogue reference for free logs (BR-005), the immutable
  event-log semantics (no edit/delete, BR-006), the ADMIN+TRAINER logging and
  review UI (BR-007), and the minimal progress view (FR-003, FR-004, WL-10).
  Greenfield on the SPEC-001/002/009/010 foundations.
- **Clients** (existing module, additive changes only): the `Client` model
  gains a `workoutLogs(): HasMany` relationship (the log target per SPEC-011
  §3; domain-model "Client ├── WorkoutLog") and a `hasRoutineAssignmentTo(int
  $routineId): bool` predicate — the ERR-003 assigned-version validation helper
  (reads the client's preserved assignment history, SPEC-010 BR-008/AR-09). No
  schema change to the `clients` table.
- **Routines** (existing module, consumed, unchanged): the set-level
  `routine_exercises` rows are the prescribed-reference surface (D-11 option 2,
  BR-004). `workout_logs.routine_exercise_id` → `routine_exercises.id` is a new
  reference direction created from THIS consuming module (the same boundary
  discipline as `routine_exercises.exercise_id` in SPEC-010). No Routine model,
  table or behavior changes: version-stability is a property of the existing
  SPEC-010 model (archived versions and their set rows are never deleted;
  draft rows are never assignable, so logs can never reference them — ERR-003).
- **Exercises** (existing module, consumed, unchanged): the `exercises` table
  is the free-log reference surface (C-11, FR-002). `workout_logs.exercise_id`
  → `exercises.id` is a new reference direction from this consuming module
  (SPEC-009 BR-011 / §10). `Exercise::scopeActive()` / `Exercise::isActive()`
  is consumed as the "currently offered" set for NEW free logs (BR-005, WL-02).
- **Cross-cutting authorization foundation** (no new module): a new
  `WorkoutLogPolicy` extends the SPEC-008 `AttendancePolicy` pattern — granting
  `viewAny` / `view` / `create` to **ADMIN and TRAINER** (BR-007, WL-03, WL-09),
  with **no `update` and no `delete`** ability registered (BR-006) — and
  consumes the existing `User::hasAnyRole` helper (ADR-001).

No changes are made to: auth scaffolding, `AdminPanelProvider`, `RoleSeeder`,
`AdminUserSeeder`, the `role_user` pivot, the `users` / `roles` / `clients` /
`plans` / `memberships` / `turnos` / `attendances` / `exercises` / `routines` /
`routine_days` / `routine_exercises` / `routine_assignments` tables, or the
Users, Plans, Memberships, Scheduling, Attendance, Exercises and Routines
modules.

The boundary with later Specifications is kept clean: client self-logging and
client visibility of own logs (SPEC-013), a session entity (OQ-03), log
correction (OQ-01) and analytics (OQ-06) are explicitly OUT of scope. This
design creates no session, notification or client-portal concept of any kind.

---

## 4. Application Flow

```text
Presentation (web)
    ↓
Admin panel: /admin — Filament panel (ADMIN | TRAINER gate via canAccessPanel)
    ↓
WorkoutLogResource (Filament): list / create / view / client-progress
    ↓
Application
    ↓
WorkoutLogPolicy (ADMIN | TRAINER; no update, no delete)
    ↓
Domain
    ↓
WorkoutLog model (client_id, performed_at, routine_exercise_id?, exercise_id?,
                 actual_weight?, actual_reps, notes?, recorded_by)
Client::workoutLogs() / Client::hasRoutineAssignmentTo()   (BR-004, ERR-003)
Exercise::scopeActive() / isActive()                       (BR-005, ERR-005)
    ↓
Persistence
    ↓
PostgreSQL: workout_logs (new); clients / users / exercises / routines /
            routine_days / routine_exercises / routine_assignments
            (existing, untouched except additive Eloquent helpers on Client)
```

Concrete flows:

1. **Record an assigned-routine log (FR-001, BR-001, BR-004)**
   - ADMIN or TRAINER opens the Workout Logs section of the admin panel
     (`WorkoutLogResource`) — a standalone staff-facing surface in the
     `Training` navigation group (the UI-placement constraint of SPEC-011 §2/§9:
     `ClientResource` is ADMIN-only, so the logging surface must be reachable
     by TRAINER through its own resource).
   - Create form: staff select the client (searchable by name/DNI), the
     performed timestamp (default now, never future, backdating allowed —
     WL-05), and a **reference type** toggle (`From assigned routine` /
     `Free exercise`, transient form control, not persisted).
   - Assigned-routine case: the `routine_exercise_id` Select lists the
     set-level prescription rows of every routine version the client has been
     assigned to — active OR historical (AF-002; drafts are never assignable so
     never listed — ERR-003). Picking a row prefills `actual_weight` /
     `actual_reps` from the row's `target_weight` / `target_reps` (FR-001
     prefill; staff may override). Staff enter the actual weight/reps/notes.
   - Free-log case: the `exercise_id` Select lists the `active` catalogue
     exercises only (FR-002, BR-005, ERR-005). Clients with no assigned routine
     use this path (AF-001).
   - The server validates (BR-008): exactly one reference (ERR-001), reference
     existence (ERR-002), the assigned-version rule for `routine_exercise_id`
     (ERR-003), the active-exercise rule for `exercise_id` (ERR-005), value
     invariants and not-future `performed_at` (ERR-004), client existence
     (ERR-008). No membership/access gate is applied (BR-010).
   - The log is persisted with `recorded_by = auth()->id()` (injected in
     `mutateFormDataBeforeCreate`, BR-009/WL-11) and `logged_at = created_at`
     (FR-005); the prescribed routine is untouched (BR-003, AC-2).
2. **List / filter logs (FR-005, FR-002 support)**
   - ADMIN or TRAINER lists logs; search by client name/DNI; filters by client,
     date range on `performed_at`, `recorded_by` and reference type. Columns
     show the exercise (whether the reference is a prescription row or a free
     exercise), the performed timestamp, actual weight/reps, the prescription
     target when referenced, the recording staff member and `logged_at`
     (FR-005, BR-009).
3. **View a log (FR-005)**
   - ADMIN or TRAINER opens the detail view: client, performed_at, exercise,
     actual weight/reps, notes, the prescription target when referenced
     (read live from the immutable set row, WL-08), `recordedBy.name` and
     `logged_at`. No header actions: logs are immutable (BR-006, ERR-007).
4. **Review a client's progress (FR-003, FR-004, WL-10)**
   - ADMIN or TRAINER opens the per-client **Client progress** page (entry: a
     "View client progress" header action on the list page with a client select,
     or direct URL `/admin/workout-logs/progress/{client}`): a read-only table
     of the client's logs, default-ordered chronologically by `performed_at`
     (optionally row-grouped by performed date via Filament table grouping),
     with per-set rows showing the exercise, the target weight/reps when the log
     references a `RoutineExercise` row, the actual weight/reps, notes, the
     recording staff member and `logged_at`. This single table satisfies both
     the grouped history (FR-003, AC-8) and the minimal prescription-vs-actual
     comparison (FR-004, AC-9 — target vs actual per logged set; free logs show
     the target columns as '—').
5. **Later routine versioning (AF-004, BR-004, D-12 option 3)**
   - Staff edit the client's routine (SPEC-010 FR-006): a new version is
     created, the previous archived, assignments untouched. Existing logs keep
     referencing the old version's set rows (which are preserved — archived
     versions and their rows are never deleted) and display the same
     prescription as at log time (AC-10). The versioning operation creates,
     modifies or deletes no log (BR-003).
6. **Exercise deactivation after a free log (AF-003, BR-005)**
   - An exercise referenced by existing free logs is deactivated (SPEC-009
     FR-005). Existing logs are unchanged and still display, reading the
     exercise's current catalogue attributes live (WL-08). New free logs cannot
     reference the now-inactive exercise (ERR-005, AC-11).

---

## 5. Components

### Controllers

None new.

Workout-log management lives entirely inside the Filament `WorkoutLogResource`
(the admin-side controller, same convention as `AttendanceResource`,
`RoutineResource`, `ExerciseResource`). No web routes or HTTP controllers are
added.

### Actions / Use Cases

None required.

Recording a workout log is a **single-record insert** with framework validation
(client exists, exactly one reference, reference validity, value invariants,
not-future `performed_at`) handled by the Filament resource — the exact
`Attendance` precedent (SPEC-008 §5 Actions: "Recording a check-in is a
single-record insert with framework validation ... An explicit `RecordCheckIn`
Action would be an unnecessary abstraction"). The reference rules validate
against existing rows (`routine_assignments`, `exercises`) but write only one
row; there is no multi-entity write or transactional orchestration, so the
`AssignRoutine` / `VersionRoutine` Action precedent does not apply (AGENTS.md
§9-10, ARCHITECTURE §7 — the `RecordWorkout` example in ARCHITECTURE §7 is
aspirational and, per the spec's own "if an Action is warranted" phrasing
(SPEC-011 §13), not warranted for a single-row validated insert; see §10
Alternative 1). The rule-bearing predicates live on the models
(`Client::hasRoutineAssignmentTo()`, `Exercise::scopeActive()` /
`isActive()`) and are invoked by form closure rules — the same simple-domain-
behavior convention as SPEC-008.

### Models

**`App\Models\WorkoutLog`** (new)

- Table: `workout_logs` (one row per performed set, D-11 option 2 / BR-001).
- Fillable: `client_id`, `performed_at`, `routine_exercise_id`, `exercise_id`,
  `actual_weight`, `actual_reps`, `notes`, `recorded_by`. (`recorded_by` is
  fillable so the create path, the factory and direct writes work, but it is
  never a form field — it is set to the authenticated staff User at creation,
  BR-009/WL-11, the `Attendance` precedent.)
- Casts:
  - `performed_at` → `'datetime'` (Carbon; BR-008/WL-05 — the gym-local
    performed timestamp; no timezone column, same local-time convention as
    SPEC-006 BR-011 / SPEC-008 AT-05).
  - `actual_weight` → `'decimal:2'` (the ADR-003 decimal cast precedent — note
    Eloquent returns strings, see §9; precision `decimal(6,2)` matches the
    prescription's `target_weight`).
  - `actual_reps` → `'integer'`.
- No `$attributes` defaults: every required field is always supplied (the form
  prefills `performed_at` with `now()`, WL-05). No status attribute exists
  (BR-006: an event record, not a stateful entity).
- Relationships:
  - `client(): BelongsTo` → `Client` (FK `client_id`, BR-002).
  - `routineExercise(): BelongsTo` → `RoutineExercise` (FK
    `routine_exercise_id`, nullable — BR-002, D-12 option 3: the prescribed
    set row the client was on; version-stable by construction, BR-004).
  - `exercise(): BelongsTo` → `Exercise` (FK `exercise_id`, nullable — BR-002,
    BR-005: the free-log catalogue reference).
  - `recordedBy(): BelongsTo` → `User` (FK `recorded_by`, BR-009, FR-005,
    WL-11 — the `Attendance::recordedBy()` precedent).
- Scopes:
  - `scopeForClient(Builder $query, int $clientId): Builder` —
    `where('client_id', $clientId)` (FR-003: a client's workout history; used
    by the progress page and the client filter; ordering by `performed_at` is
    applied by the consuming UI).
- Simple domain behavior (ARCHITECTURE §8):
  - `exerciseName(): ?string` — `$this->routineExercise?->exercise?->name ??
    $this->exercise?->name` (display helper: the exercise shown in lists and
    detail is the same whether the reference is a prescription row or a free
    exercise — FR-003; reads the exercise's CURRENT catalogue attributes live,
    WL-08). The prescription target display reads `routineExercise->
    target_weight` / `routineExercise->target_reps` directly in the UI (no
    snapshot needed — set rows never change once their version is
    active/archived, WL-08).
  - `referenceRules(): array` — the shared validation rules for the
    exactly-one-reference invariant + existence (BR-002, ERR-001, ERR-002):
    `routine_exercise_id` → `['nullable', 'required_without:exercise_id',
    'prohibits:exercise_id', 'exists:routine_exercises,id']` and `exercise_id`
    → `['nullable', 'required_without:routine_exercise_id',
    'prohibits:routine_exercise_id', 'exists:exercises,id']`. This is the
    single source of truth used by the Filament form (via `->rule(...)`) and
    exercised directly by the unit tests (`Validator::make($data,
    WorkoutLog::referenceRules())`).
- No domain validation methods for the reference predicates themselves: the
  ERR-003 predicate lives on `Client::hasRoutineAssignmentTo()` (the entity
  that owns the assignment history) and the ERR-005 check reuses
  `Exercise::isActive()` — the same helper-composition pattern as SPEC-008
  (`Client::hasQualifyingMembership()` + form closure rules).
- No `update` / `delete` behavior of any kind: no status transitions, no edit
  path (BR-006, ERR-007). There is intentionally no `scopeForRoutine` /
  `scopeForExercise` in the MVP (no spec requirement to list logs per
  prescription row or per exercise).

**`App\Models\Client`** (modified additively)

- New relationship (domain-model "Client ├── WorkoutLog", C-02):
  - `workoutLogs(): HasMany` → `WorkoutLog` (FR-003 navigation and the
    progress-page query; display ordering by `performed_at` is applied by the
    consuming UI — the `attendances()` pattern).
- New read helper (the ERR-003 predicate, BR-004/BR-008/WL-07):
  - `hasRoutineAssignmentTo(int $routineId): bool` —
    `$this->routineAssignments()->where('routine_id', $routineId)->exists()`
    (the client has an assignment to this routine VERSION — active or
    historical; assignment history is preserved per SPEC-010 BR-008/AR-09).
    Because `AssignRoutine` only ever assigns `active` versions (SPEC-010
    ERR-008), a version with any assignment row is never a `draft` — so this
    predicate automatically excludes draft rows (ERR-003: "drafts are never
    valid targets because drafts are never assignable").
- No new columns, no change to `$fillable`, casts, `user()` or the existing
  helpers.

**No other model is modified.** `User`, `Role`, `Exercise`, `Routine`,
`RoutineDay`, `RoutineExercise`, `RoutineAssignment`, `Membership`, `Plan`,
`Turno` and `Attendance` are untouched. `RoutineExercise` already exposes
`routineDay()` → `routine()` (SPEC-010) and `exercise()`, which the ERR-003
validation and the display need; `Exercise` already exposes `scopeActive()` /
`isActive()` (SPEC-009). No relationship is added to `Exercise` or `Routine`:
the reference directions are defined by the consuming module (`WorkoutLog`), the
same boundary discipline as SPEC-009 §6 / SPEC-010 §6.

### Policies

**`App\Policies\WorkoutLogPolicy`** (new) — extends the `AttendancePolicy`
pattern (SPEC-008), with the ADMIN+TRAINER management set required by BR-007 /
WL-03 / WL-09 (C-03; the same role set as `TurnoPolicy` / `ExercisePolicy` /
`AttendancePolicy`):

- `viewAny` / `view`: ADMIN **or** TRAINER (BR-007, FR-003, FR-005).
- `create`: ADMIN **or** TRAINER (BR-007, FR-001, FR-002).
- **No `update` and no `delete` policy is registered on purpose**: logs are
  immutable event-log entries; no edit or delete operation exists (BR-006,
  ERR-007) — the exact stance of `AttendancePolicy` (no update, no delete).
- All rules use `$user->hasAnyRole([Role::ADMIN, Role::TRAINER])` (ADR-001).
- Role-based, not ownership-based (BR-009, WL-09): any ADMIN or TRAINER may log
  for / review any client regardless of `recorded_by` — the same stance as
  SPEC-010 BR-011 / AR-08 and SPEC-008 AT-10.

Authorization matrix (SPEC-011 §9):

| Operation | Anonymous | ADMIN | TRAINER | CLIENT |
| --- | --- | --- | --- | --- |
| Record workout log (assigned routine) | Denied | Allowed (BR-007, C-03) | Allowed (BR-007, C-03) | Denied (deferred to SPEC-013) |
| Record free workout log (catalogue exercise) | Denied | Allowed (BR-007, C-03) | Allowed (BR-007, C-03) | Denied (deferred to SPEC-013) |
| View client workout history / progress | Denied | Allowed (BR-007, C-03) | Allowed (BR-007, C-03) | Denied (own logs: SPEC-013) |
| Edit / delete log | Denied | Denied (no such operation — BR-006) | Denied (no such operation — BR-006) | Denied (no such operation — BR-006) |
| Access another client's logs | Denied | Per rules (role-based, BR-009) | Per rules (role-based, BR-009) | Denied always (C-13) |

A multi-role user receives the union of permissions (SPEC-001 BR-002): an ADMIN
or TRAINER who also holds CLIENT can log and review in the admin panel (AC-14).
CLIENT-only users never reach the admin panel (`canAccessPanel`, SPEC-001);
anonymous visitors are redirected to `/login` (ERR-006). Authorization is
enforced server-side via the Policy; frontend hiding is never the enforcement
(AGENTS.md §17).

**UI-placement constraint (requirement-level, affects the requirement not the
policy):** because `ClientResource` is ADMIN-only (SPEC-002 `ClientPolicy`),
the TRAINER column of the matrix requires the logging AND progress surfaces to
live in a standalone staff-facing resource (`WorkoutLogResource`, §5 Filament),
never only inside ADMIN-only screens (SPEC-011 §2/§9).

The state/validation rules (ERR-001..ERR-005) are NOT authorization rules: they
are enforced by form validation (and the shared `referenceRules()`), so an
authorized user still cannot record an invalid log (the same stance as SPEC-010
§9 and SPEC-008 §9).

### Filament

**`App\Filament\Resources\WorkoutLogResource`** (new) with pages
`ListWorkoutLogs`, `CreateWorkoutLog`, `ViewWorkoutLog` plus a custom page
`ClientProgress` (the `AttendanceResource` folder convention:
`app/Filament/Resources/WorkoutLogResource/Pages/*`). `navigationIcon` (e.g.,
`heroicon-o-clipboard-document-list`) and `navigationGroup = 'Training'` (the
group created by SPEC-009, shared by Exercises and Routines).

There is NO `EditWorkoutLog` page: the Specification defines no edit operation
and logs are immutable (BR-006, ERR-007) — the same stance as
`AttendanceResource` (no edit page).

- Form (create only — FR-001, FR-002, FR-005):
  - `client_id` — `Select` with `->relationship('client', 'full_name')`
    (searchable/preload, name or DNI per FR-001), required (ERR-008, BR-008),
    server-side rule `exists:clients,id` (ERR-008). **No membership/access
    gate rule** (BR-010 — unlike `AttendanceResource`, which applies the
    access-gate closure rule; logging has no membership precondition).
  - `performed_at` — `DateTimePicker` (24-hour, seconds off), required
    (BR-008), `->default(now())` (WL-05), server-side rule not in the future —
    `->beforeOrEqual('now')` (ERR-004, the `AttendanceResource` precedent).
    No minimum date: backdating is allowed without an explicit limit (WL-05,
    OQ-04 open).
  - Reference selection (BR-002, ERR-001) — conditional UX:
    - A transient `reference_type` `Select` (options `routine` "From assigned
      routine" / `free` "Free exercise"), `->default('routine')`,
      `->dehydrated(false)` (never persisted — it drives which reference field
      is shown/cleared).
    - `routine_exercise_id` — `Select`, `->visible(fn (Get $get): bool =>
      $get('reference_type') === 'routine')`, options computed from the
      selected client (reactive on `client_id` and `reference_type`): the
      set-level rows of every routine version the client has been assigned to
      — active OR historical (`$client->routineAssignments()->pluck
      ('routine_id')` → `RoutineExercise` rows of those versions, eager-loaded
      with `exercise` and `routineDay`; the label shows e.g. "Day 1 · Bench
      Press — 60 kg × 10 (Set 1)", presentation detail). When the client has
      no assigned routine, the options are empty and a hint directs staff to
      the free-exercise path (AF-001). Server-side rules (from
      `WorkoutLog::referenceRules()`, ERR-001/ERR-002) PLUS the ERR-003 closure
      rule: when a value is present, the row's routine version must satisfy
      `$client->hasRoutineAssignmentTo($routine->id)` (resolve via
      `RoutineExercise::find($value)?->routineDay?->routine`); otherwise
      validation fails with "This set belongs to a routine version the client
      has never been assigned to." (drafts are never assignable, so they fail
      this rule automatically — ERR-003). On selection,
      `->afterStateUpdated` prefills `actual_weight` / `actual_reps` from the
      row's `target_weight` / `target_reps` (FR-001 prefill; staff may
      override).
    - `exercise_id` — `Select`, `->visible(fn (Get $get): bool =>
      $get('reference_type') === 'free')`, options = the active catalogue
      exercises (`Exercise::scopeActive()->pluck('name', 'id')`, FR-002,
      BR-005). Server-side rules (from `WorkoutLog::referenceRules()`) PLUS the
      ERR-005 closure rule: a present value must be an `active` exercise
      (`Exercise::active()->whereKey($value)->exists()` — the SPEC-010
      `VersionRoutine` pattern; ERR-005, AC-6).
    - Cleanup on switch: `->afterStateUpdated` on `reference_type` (or on the
      visible field) clears the other reference so the UI never submits a
      both-set payload; the `prohibits` rules remain the server-side
      enforcement regardless of UI behavior (AGENTS.md §17).
  - `actual_weight` — `TextInput` numeric, nullable, `numeric`, `minValue(0)`
    (BR-008, ERR-004, WL-06; absent/zero = bodyweight, the SPEC-010 AR-06
    convention); an empty input is stored as `null`.
  - `actual_reps` — `TextInput` numeric, required, `integer`, `minValue(1)`
    (BR-008, ERR-004, WL-06).
  - `notes` — `Textarea`, nullable (BR-008, WL-06).
  - `recorded_by` is NOT a form field (BR-009, WL-11): the `CreateWorkoutLog`
    page sets `data['recorded_by'] = auth()->id()` in
    `mutateFormDataBeforeCreate` — the staff User who recorded the log (the
    `CreateAttendance` / `CreateRoutine` injection pattern).
- Table (FR-005; supports FR-003/FR-004 via the client filter):
  - Columns: `client.full_name` (searchable), `client.dni` (searchable),
    `performed_at` (sortable, datetime — default sort chronological, satisfying
    the history ordering), `exercise` (a `TextColumn::make('exercise_name')`
    with `->state(fn (WorkoutLog $record): ?string =>
    $record->exerciseName())` — same exercise display for both reference
    kinds, FR-003), `routineExercise.target_weight` / `routineExercise.target_reps`
    (Label "Target", placeholder '—' — the prescription target when the log
    references a prescribed row; the FR-004 comparison column), `actual_weight`,
    `actual_reps`, `recordedBy.name` (Label "Recorded by" — FR-005, BR-009),
    `created_at` (Label "Logged at", datetime — FR-005, BR-009), `notes`
    (placeholder '—', toggleable/truncated).
  - Filters (support FR-003): a client `SelectFilter` on `client_id`
    (searchable, name/DNI — the FR-003 client-history entry point in the list);
    a date-range `Filter` on `performed_at` (two `DatePicker`s, the
    `AttendanceResource` pattern); a `recorded_by` `SelectFilter` (staff
    Users); a `reference_type` `SelectFilter` (routine/free).
  - Row actions: `View` only. No `EditAction`, no `DeleteAction`, no
    `bulkActions([])` (BR-006, ERR-007).
- View page (`ViewWorkoutLog`, FR-005): infolist showing `client.full_name`,
  `client.dni`, `performed_at` (datetime), `exercise` (via the
  `exerciseName()` state helper), `routineExercise.target_weight` /
  `routineExercise.target_reps` (Label "Target", placeholder '—' — FR-003
  prescription display), `actual_weight`, `actual_reps`, `notes`,
  `recordedBy.name` (Label "Recorded by"), `created_at` (Label "Logged at").
  No header actions (immutable records, BR-006).
- Progress page (`ClientProgress`, FR-003 + FR-004, WL-10): a custom page in
  `WorkoutLogResource` at route `progress/{client}` — NOT inside `ClientResource`
  (which is ADMIN-only; the UI-placement constraint of SPEC-011 §2/§9). It
  extends `Filament\Resources\Pages\Page` (not record-bound), overrides
  `mount(int $client)` to load the client (`Client::findOrFail($clientId)`,
  abort/403 if not found or not viewable), and renders a **read-only Filament
  table** (the `InteractsWithTable` trait) whose query is
  `WorkoutLog::query()->forClient($clientId)->with(['routineExercise.exercise',
  'exercise', 'recordedBy'])`, default-sorted by `performed_at` ascending.
  Columns: `performed_at` (datetime — the "grouped by date" key, WL-01; the
  table may apply Filament row grouping via `->groups(['performed_at'])` when
  the installed version supports it — fallback: the date column + default sort
  satisfies the grouping requirement, AC-8), `exercise` (`exerciseName()`),
  `routineExercise.target_weight` / `routineExercise.target_reps` (Label
  "Target", placeholder '—' — the prescription-vs-actual comparison, FR-004,
  AC-9), `actual_weight`, `actual_reps`, `notes`, `recordedBy.name`,
  `created_at` (Label "Logged at"). No row actions. Entry point: a
  "View client progress" header action on the `ListWorkoutLogs` page (modal
  client select → `WorkoutLogResource::getUrl('progress', ['client' =>
  $clientId])`), authorized via the `WorkoutLogPolicy` `viewAny` ability.
  TRAINER reachable by construction (the page belongs to the standalone
  ADMIN|TRAINER resource).
- Navigation: the Workout Logs section appears in the `Training` navigation
  group (the Developer may adjust cosmetic placement).

**No `WorkoutLogsRelationManager` on `ClientResource` is added in this
design** — `ClientResource` is ADMIN-only (SPEC-002 `ClientPolicy`), so any
log surface placed there would violate the TRAINER reachability constraint
(SPEC-011 §2/§9). The standalone `WorkoutLogResource` (list + client filter +
`ClientProgress` page) is the single staff-facing surface.

### Events

None required.

Recording a log has no defined secondary effect that needs decoupling
(ARCHITECTURE §10): it never creates, modifies or deletes a Client, Routine,
RoutineDay, RoutineExercise, RoutineAssignment, Exercise or User record
(BR-003, AC-2, AC-10, AC-11); no notification is sent (SPEC-011 §12;
notifications depend on SPEC-013 client-communication infrastructure). The
versioning (SPEC-010 FR-006) and exercise deactivation (SPEC-009 FR-005)
operations likewise never touch logs (BR-003 consumed symmetrically — the
SPEC-010 AC-17 boundary). `WorkoutLogged`-style events are not needed until
SPEC-013 defines consumers.

### Jobs

None required.

No queued work exists in SPEC-011 (no notifications, email, or slow
operations); recording a log is a synchronous single-row insert (ARCHITECTURE
§11).

### Routes

No new routes. Filament auto-registers `/admin/workout-logs*` (including the
`progress/{client}` custom page) through the panel's `discoverResources`
(already configured in `AdminPanelProvider`).

### Seeders

None new. Workout logs are created by staff in the admin panel only (SPEC-011
§10: "No seeder is required"). The existing `RoleSeeder` already provides the
ADMIN and TRAINER roles required by management.

---

## 6. Data Changes

### Migrations

1. **`create_workout_logs_table`** (new; next migration in the existing
   timestamp sequence: `2026_08_15_000013_create_workout_logs_table.php`):

   | Column | Type | Constraints |
   | --- | --- | --- |
   | `id` | bigint | PK |
   | `client_id` | foreignId | NOT NULL, FK → `clients.id`, `restrictOnDelete` (BR-002, ERR-008) |
   | `performed_at` | timestamp | NOT NULL (BR-008, WL-05); gym-local performed time, no timezone column; no DB default — the value is always supplied (form default `now()`; the `attendances.attended_at` convention) |
   | `routine_exercise_id` | foreignId | nullable, FK → `routine_exercises.id`, `restrictOnDelete` (BR-002, D-12 option 3, BR-004) |
   | `exercise_id` | foreignId | nullable, FK → `exercises.id`, `restrictOnDelete` (BR-002, BR-005) |
   | `actual_weight` | decimal(6,2) | nullable (BR-008, WL-06; absent/zero = bodyweight; the ADR-003 decimal convention, same precision as the prescription's `target_weight`) |
   | `actual_reps` | unsignedInteger | NOT NULL (BR-008, WL-06; positivity enforced by validation, ERR-004) |
   | `notes` | text | nullable (BR-008, WL-06) |
   | `recorded_by` | foreignId | NOT NULL, FK → `users.id`, `restrictOnDelete` (BR-009, WL-11 — the `attendances.recorded_by` precedent) |
   | `created_at` / `updated_at` | timestamp | timestamps; `logged_at` is `created_at` (BR-009, FR-005) |

   - `performed_at` as a `timestamp` column: it records a point in time —
     "when the set was performed" — in the gym's local time, so a `timestamp`
     (Laravel `timestamp()` maps to Postgres `timestamp(0) without time zone`;
     `dateTime()` is an alias for the same type) is the natural fit, the same
     choice as `attendances.attended_at` (SPEC-008 §6). No timezone column
     (single location, local time — C-14, ARCHITECTURE §17).
   - **Exactly-one-reference invariant (BR-002, ERR-001):** both
     `routine_exercise_id` and `exercise_id` are nullable at the database
     level; the invariant (never both-null, never both-set) is enforced by the
     create path / form validation via the shared `WorkoutLog::referenceRules()`
     (`required_without` + `prohibits`, ERR-001) — the repo's
     validation-first convention (ADR-003). **No DB CHECK constraint is
     added**, consistent with every existing migration; the optional hardening
     `CHECK ((routine_exercise_id IS NULL) != (exercise_id IS NULL))` is
     documented (no business difference, SPEC-011 §10) and can be added by an
     additive migration only if a non-validated write path ever appears.
   - `restrictOnDelete` on all four FKs is a defensive guard consistent with
     the preservation pattern: clients (SPEC-002 BR-006), users (SPEC-001
     BR-007, ADR-001), exercises (SPEC-009 BR-008) and routine versions /
     set rows (SPEC-010 BR-008) are never hard-deleted, and a deletion attempt
     should be blocked rather than cascade into historical execution data
     (BR-006). Note on the `routine_exercise_id` guard: SPEC-010 draft-editing
     deletes working-copy `routine_exercises` rows (day removal cascades),
     but logs can never reference draft rows (ERR-003), so no log is ever
     blocked by or lost in that cascade — the restrict guard only fires on a
     hypothetical (and business-blocked) deletion of a referenced historical
     row.
   - Indexes (SPEC-011 §10 suggested, Architect decision):
     - `(client_id, performed_at)` — the FR-003 history list / progress page
       and the client filter;
     - `routine_exercise_id` — the FR-004 comparison lookups and "which logs
       reference this prescription row" (BR-004);
     - `exercise_id` — free-log lookups (FR-002);
     - `recorded_by` — audit queries (BR-009).
     The FK columns receive their own indexes automatically via
     `constrained()`; the composite `(client_id, performed_at)` index also
     serves the single-column `client_id` filter needs.
   - No uniqueness constraint on `(client_id, performed_at)`: every performed
     set is an independent log row; multiple sets at the same timestamp are
     allowed (BR-001, WL-01).
   - No DB CHECK constraints for positivity / not-future: `actual_reps >= 1`,
     `actual_weight >= 0` and `performed_at` not in the future are enforced by
     framework validation (framework-validation-first convention, ADR-003;
     same as SPEC-003/004/006/008/009/010).
   - No seeder (SPEC-011 §10).

No existing migration is modified. The `users`, `roles`, `role_user`,
`clients`, `plans`, `memberships`, `turnos`, `attendances`, `exercises`,
`routines`, `routine_days`, `routine_exercises` and `routine_assignments`
tables are reused as-is. The only added reference directions are
`workout_logs.routine_exercise_id` → `routine_exercises.id` and
`workout_logs.exercise_id` → `exercises.id` (the consuming-module reference
direction documented by SPEC-009 BR-011 / §10, already exercised by SPEC-010).

### Relationships

```text
clients 1 ──── * workout_logs * ──── 0..1 routine_exercises (prescribed set row)
                        *
                        1
                      users (recorded_by)
                        *
                        1
exercises 1 ──── * workout_logs * ──── 0..1 exercises (free-log reference)
```

```text
workout_logs.client_id             → clients.id          (required, restrictOnDelete)
workout_logs.routine_exercise_id   → routine_exercises.id (optional, restrictOnDelete — the version-stable prescription reference)
workout_logs.exercise_id           → exercises.id         (optional, restrictOnDelete — the free-log catalogue reference)
workout_logs.recorded_by           → users.id             (required, restrictOnDelete)
```

Eloquent relationships: `WorkoutLog::client()`, `WorkoutLog::routineExercise()`,
`WorkoutLog::exercise()`, `WorkoutLog::recordedBy()` (new);
`Client::workoutLogs()` (new, additive); `Client::hasRoutineAssignmentTo()`
(new, additive — read helper, no relationship). `Exercise`, `Routine`,
`RoutineDay`, `RoutineExercise` and `RoutineAssignment` gain no relationship in
this Specification (the consuming module owns the reference directions — the
same boundary discipline as SPEC-009 §6 / SPEC-010 §6).

### Data lifecycle

- **Created:** workout-log rows on staff recording, one per performed set
  (D-11 option 2, BR-001), with `performed_at` (default now or backdated,
  WL-05), exactly one exercise reference (BR-002), the performed values
  (BR-008) and `recorded_by` = the authenticated staff User (BR-009, WL-11).
  Creating a log creates no other record (BR-003, AC-2) and applies no
  membership/access gate (BR-010).
- **Modified:** none. Workout logs are immutable: no field is modified by any
  operation (BR-006, WL-04). Routine versioning (SPEC-010 FR-006) and exercise
  deactivation/editing (SPEC-009) never modify, re-point or delete logs
  (BR-003, BR-004, BR-005, AC-10, AC-11).
- **Deleted:** none in the MVP. No delete operation (BR-006, ERR-007) and no
  hard deletion of workout-log records; the execution history is preserved
  (AGENTS.md §12).

---

## 7. External Integrations

None.

SPEC-011 touches no external service. No notification/email is sent by any
workout-log operation (SPEC-011 §12; notifications depend on SPEC-013 client-
communication infrastructure). Log display reads exercise and prescription
attributes live from the local catalogue / immutable set rows (WL-08, the
SPEC-010 AR-04 stance); no per-log snapshot or external exercise data source
exists.

---

## 8. Testing Strategy

Pest (PHPUnit) feature tests following the existing conventions
(`tests/Pest.php` helpers `role()`, `userWithRoles()`; `RefreshDatabase`;
Livewire component testing as used in `AttendanceManagementTest` /
`RoutineManagementTest`). The spec's own test plan (SPEC-011 §11) is mapped
below; a new `WorkoutLogFactory` mirrors the `AttendanceFactory` shape.

New factory (`database/factories/WorkoutLogFactory.php`):
`client_id` → `Client::factory()`, `performed_at` → `now()` (WL-05 default),
`routine_exercise_id` → `null`, `exercise_id` → `null` (callers set exactly one
reference per BR-002 — the factory intentionally does not pick a default
reference so each test states the case explicitly), `actual_weight` → `null`,
`actual_reps` → `10` (WL-06), `notes` → `null`, `recorded_by` → `User::factory()`
(BR-009 — required).

**Logging through the UI (AC-1..AC-11, AC-15; FR-001, FR-002, FR-005, FR-003,
FR-004)**
- `tests/Feature/Admin/WorkoutLogManagementTest.php` (Livewire component tests
  against `CreateWorkoutLog`, `ListWorkoutLogs`, `ViewWorkoutLog`,
  `ClientProgress`):
  - ADMIN or TRAINER can record an assigned-routine log for a client
    referencing a set-level `RoutineExercise` of the client's assigned routine
    version; the log persists with actual weight, actual reps, performed_at,
    notes, `recorded_by` (the current staff User) and `logged_at` (`created_at`)
    (AC-1, FR-001, BR-001, BR-008, BR-009, WL-11).
  - Recording a log never creates, modifies or deletes any routine / day /
    set-row / assignment record — assert only the `workout_logs` table gained
    a row and the prescription rows and assignment rows are byte-for-byte
    unchanged (AC-2, BR-003, C-10).
  - A log with both `routine_exercise_id` and `exercise_id`, or with neither,
    is rejected with validation errors (AC-3, ERR-001, BR-002 — via the shared
    `referenceRules()`).
  - A log referencing a `RoutineExercise` from a routine version the client was
    never assigned to — including a draft version — is rejected (AC-4, ERR-003,
    BR-004, BR-008); one from a version with a historical (inactive) assignment
    is accepted (AF-002).
  - Free logging works: a log referencing an `active` catalogue `Exercise` is
    accepted, including for a client with no assigned routine (AC-5, FR-002,
    AF-001, BR-002).
  - A new free log referencing an `inactive` exercise is rejected (AC-6,
    ERR-005, BR-005).
  - Invalid performed values — missing/zero/negative `actual_reps`, negative
    `actual_weight`, future `performed_at` — are rejected (AC-7, ERR-004,
    BR-008, WL-06); a backdated `performed_at` is accepted (WL-05).
  - A log for a nonexistent client is rejected (AC-15, ERR-008).
  - The list shows `recorded_by` / `logged_at` and supports filtering by client,
    date range, `recorded_by` and reference type; the client's history is
    chronological by `performed_at` (FR-005, FR-003).
  - The `ClientProgress` page shows a client's logs grouped by performed date
    with per-set rows showing exercise, actual weight/reps and, when
    referenced, the prescription target (AC-8, FR-003), and the target vs
    actual comparison per logged set (AC-9, FR-004); free-log rows show the
    target columns as '—'.
  - After a routine is versioned (SPEC-010 FR-006 via `VersionRoutine`),
    existing logs keep referencing the old version's rows and are unchanged;
    the versioning operation creates/modifies/deletes no log (AC-10, BR-004,
    AF-004).
  - Deactivating an exercise never creates, modifies or deletes any log; a log
    referencing the now-inactive exercise still displays (AC-11, AF-003,
    BR-005).
  - No edit/delete path exists: a created log persists unchanged and no
    edit/delete action, page or route is available (AC-12, ERR-007, BR-006).

**Authorization / Policy (AC-12, AC-13, AC-14; ERR-006, ERR-007)**
- `tests/Feature/Admin/WorkoutLogPolicyTest.php`:
  - ADMIN and TRAINER can `viewAny`/`view`/`create` workout logs (AC-13,
    BR-007, WL-03, WL-09).
  - CLIENT and anonymous users cannot record, list, filter or view logs or the
    progress page — 403 on `/admin/workout-logs` routes (including
    `progress/{client}`) and no navigation; guests are redirected to `/login`
    (AC-13, ERR-006, BR-007, C-13; asserted server-side, AGENTS.md §17).
  - A multi-role ADMIN + CLIENT or TRAINER + CLIENT user can log and review in
    the admin panel (AC-14, SPEC-001 BR-002).
  - `WorkoutLogPolicy` has no `update` and no `delete` ability for anyone
    (AC-12, ERR-007, BR-006).

**Unit**
- `tests/Unit/WorkoutLogTest.php`:
  - The exactly-one-reference invariant (BR-002, ERR-001): the shared
    `WorkoutLog::referenceRules()` rejects both-set and neither-set payloads via
    `Validator::make` (and accepts each single-reference case).
  - Value conventions (WL-06) and `recorded_by` audit (BR-009): a
    factory/user-created record without `recorded_by` fails the DB NOT NULL
    constraint; casts (`performed_at` Carbon, `actual_weight` decimal:2,
    `actual_reps` integer).
  - The boundary rule: creating a log creates no routine / assignment /
    exercise / client record (BR-003, AC-2 — assert only the `workout_logs`
    table gained a row).
  - The version-stability rule: a log's `routine_exercise_id` belongs to the
    client's assigned version at log time (`Client::hasRoutineAssignmentTo()`
    is true for the active and later the historical assignment) and survives a
    later versioning operation — after `VersionRoutine`, the log still points
    at the old version's row and displays the same prescription (BR-004, AC-10;
    the predicate is true because the assignment history is preserved).
  - The free-log active-exercise rule: the ERR-005 check (`Exercise::isActive()`
    / `Exercise::scopeActive()`) rejects inactive references (BR-005, AC-6).
  - `Client::workoutLogs()` ordering by `performed_at` (FR-003) and
    `WorkoutLog::scopeForClient()` returning only the client's logs.
  - The immutability shape: no status attribute/column; no update/delete
    methods; no membership-gating logic (BR-006, BR-010).
  - The DB-level shape: expected columns and the `(client_id, performed_at)`,
    `routine_exercise_id`, `exercise_id`, `recorded_by` indexes exist.

The `ClientProgress` page tests may alternatively be split into a dedicated
`tests/Feature/Admin/WorkoutProgressTest.php` if the Developer prefers
separation, but the spec's own test plan places AC-8/AC-9 in
`WorkoutLogManagementTest.php`; the file split is a presentation choice.

All authorization assertions are server-side (AGENTS.md §17); no test relies
on frontend visibility.

---

## 9. Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Assumptions WL-01..WL-11 are unconfirmed (SPEC-011 §14.1). | If the PO changes them, parts of the design change (e.g., WL-03 restricts TRAINER; WL-04 allows correction-with-audit; WL-05 adds a backdating limit; WL-09 introduces ownership-based rules; WL-10 expands analytics). | Keep implementation isolated: `WorkoutLogPolicy` rules, the `workout_logs` schema, the form rules and the `Client` helpers are the only touch points. Block Implementation until the PO confirms the §12 items (per spec: "before Implementation (or at latest before Review)"). |
| OQ-01 (WL-04): log-correction capability later. | If the PO approves edit/delete-with-audit, an `update`/`delete` ability, an edit page and correction flow are added. | Design assumes immutability (BR-006); the change touches the policy, one page and the tests only. |
| The ERR-003 rule is enforced by a form closure rule (not by the Policy or the DB). | A misplaced or bypassed rule could record a log referencing a row from a version the client was never assigned to. | The rule is server-side framework validation (ADR-003 validation-first), backed by the shared `referenceRules()` and the `Client::hasRoutineAssignmentTo()` predicate; `WorkoutLogManagementTest` covers the rejection paths (AC-4, AF-002) and no other write path creates logs. |
| The exactly-one reference has no DB CHECK (ADR-003 trade-off). | A raw write path could store both/neither reference. | All MVP write paths go through the Filament form (validated via `referenceRules()`); the documented optional `CHECK ((routine_exercise_id IS NULL) != (exercise_id IS NULL))` is the hardening if a non-validated write path ever appears (same trade-off as ADR-003 / SPEC-004/006/008). |
| Version-stability depends on SPEC-010's preservation of archived versions and their rows. | If a future SPEC-010 change deletes archived rows, historical logs lose their prescription reference. | The FK `routine_exercise_id` → `routine_exercises.id` with `restrictOnDelete` blocks such a deletion (safety net); SPEC-010 BR-008 already preserves archived versions and rows. Covered by AC-10. |
| The `reference_type` UI toggle vs. the `prohibits` rules. | If the UI submits a stale both-set payload, validation must still reject it. | The `prohibits`/`required_without` rules are the server-side enforcement independent of UI behavior (AGENTS.md §17); the Developer verifies the installed Filament/Livewire behavior and clears the hidden field on switch (AC-3). |
| `performed_at` "not in the future" rule and local time. | A log recorded exactly at the current minute could be rejected if the rule compares strictly after now with seconds precision. | Gym-local time convention (WL-05); the Developer uses `->beforeOrEqual('now')` (the `AttendanceResource` precedent) and verifies the installed Filament/Livewire behavior; covered by AC-7. |
| `prohibits` / `required_without` interplay with Filament's `visible` conditional fields. | A hidden field's stale value could reach validation. | The form clears the hidden reference on switch; the shared rules reject both-set regardless. Covered by AC-3. |
| TRAINER reachability of the progress surface depends on the standalone resource placement. | If the progress view were placed inside `ClientResource` (ADMIN-only), TRAINER would lose AC-8/AC-9 — a requirement violation. | The `ClientProgress` page lives on `WorkoutLogResource` (ADMIN|TRAINER), not `ClientResource`; asserted server-side in `WorkoutLogPolicyTest` (403 for CLIENT, allowed for TRAINER). |
| The prefill of actual from target is UX-only. | A wrong prefill could mislead staff; the stored values are what staff submit. | Prefill is a convenience (FR-001 "the form prefills"); staff may override; the stored `actual_*` values are the submitted ones. |
| No membership gate (BR-010). | A reviewer might assume the Attendance gate applies. | Explicitly documented: logging has no membership/access precondition; no gate rule exists on `client_id` (unlike `AttendanceResource`). |

---

## 10. Alternatives Considered

1. **Explicit `RecordWorkout` Action (ARCHITECTURE §7 example) vs. no Action** —
   Recording a log is a single-record insert with framework validation
   (reference existence/validity + value invariants); there is no multi-entity
   write or transactional orchestration beyond the insert itself. The
   `AssignRoutine` / `VersionRoutine` precedent shows Actions are reserved for
   genuinely multi-entity, transactional, rule-bearing operations; the spec
   itself phrases it "if an Action is warranted" (SPEC-011 §13). The `Attendance`
   precedent (an equally rule-bearing single-row insert with a validation gate)
   chose no Action. **No Action chosen** — the predicates live on the models
   (`Client::hasRoutineAssignmentTo()`, `Exercise::scopeActive()`) and are
   invoked by form closure rules. If SPEC-013 later introduces client
   self-logging through a non-Filament path, the create-time rules can be
   extracted into a shared validator or Action then (additive, no restructure).
2. **DB CHECK constraint for the exactly-one reference vs. application
   validation** — The spec explicitly permits both with no business difference
   (SPEC-011 §10). The repo convention is framework-validation-first with no
   DB CHECK constraints in any migration (ADR-003; SPEC-003/004/006/008/009/010).
   **Application validation chosen** (`WorkoutLog::referenceRules()` used by the
   form and the unit tests); the optional
   `CHECK ((routine_exercise_id IS NULL) != (exercise_id IS NULL))` is
   documented as hardening for a future non-validated write path.
3. **Conditional reference UI: transient `reference_type` toggle vs. two always-
   visible selects** — A single always-visible form with two nullable selects
   invites both-set / neither-set mistakes and complicates the label semantics.
   The transient `reference_type` Select (`->dehydrated(false)`) shows exactly
   the matching reference field and clears the other on switch, while the
   `prohibits`/`required_without` rules stay as the server-side enforcement.
   **Toggle chosen.**
4. **Progress view placement: `ClientProgress` page on `WorkoutLogResource` vs.
   a relation manager / infolist section on `ClientResource` vs. list-only** —
   The `ClientResource` placement is excluded by the UI-placement constraint
   (ADMIN-only, SPEC-011 §2/§9): TRAINER must reach the history/comparison, so
   the surface must be on the standalone ADMIN|TRAINER resource. A list-only
   design (the `AttendanceResource` AC-11 precedent: client filter +
   chronological sort) satisfies FR-003 but weakens FR-004/AC-9 ("comparison
   view shows target vs actual") as a first-class view. **A dedicated read-only
   `ClientProgress` page on `WorkoutLogResource` chosen** — a single
   date-ordered table with Target and Actual columns satisfies both FR-003 and
   FR-004 (WL-10 minimal scope); the list page remains the FR-005 global log
   list. The grouping requirement (AC-8) is satisfied by the `performed_at`
   default sort plus optional Filament row grouping (`->groups()`) when the
   installed version supports it.
5. **Model-level `creating` guard for the invariants vs. form-only validation**
   — A `booted()`/`saving` guard would enforce the reference rules on every
   write path but is a new pattern not used anywhere in the repo, and ADR-003
   accepts the form-validated-path trade-off. **Form validation + shared rules
   chosen**; the shared `referenceRules()` and the `Client` predicate keep the
   rules unit-testable without a model guard.
6. **`performed_at` as `timestamp` vs. `dateTime`** — In Laravel/Postgres both
   map to the same `timestamp(0) without time zone` type; the spec delegates
   the choice to the Architect (SPEC-011 §10). **`timestamp` chosen**, the
   `attendances.attended_at` precedent (SPEC-008 §10 #6). Not significant
   enough for an ADR.
7. **Per-log snapshot of exercise/prescription attributes vs. live reads** —
   WL-08 fixes live reads: exercise display reads the catalogue's current
   attributes; the prescription display reads the immutable set row (set rows
   never change once their version is active/archived, SPEC-010 BR-001).
   **No snapshot columns** (the SPEC-010 AR-04 stance).
8. **A separate "workout session" entity vs. the flat per-set log grouped by
   `performed_at`** — WL-01 / OQ-03 defer the session entity; a workout for
   display is the group of log rows sharing `performed_at`. **No session
   entity** (BR-001).
9. **`update` policy ability returning false (explicit) vs. not registering
   it** — Matching the repo convention (`AttendancePolicy` omits both `update`
   and `delete`), neither ability is registered; Filament then offers no
   edit/delete UI and direct attempts fall back to denied. **Not registered.**
10. **Form Request vs. Filament form rules** — The repo has no HTTP controllers
    for admin CRUD; validation lives in the resource (SPEC-006 §11, SPEC-008
    §10 #10). **Resource-level rules + the shared `referenceRules()` chosen.**

No new ADR is required for this Specification: every decision above is an
incremental application of the established ADRs (ADR-001 role/authorization
foundation, ADR-002 module boundary discipline, ADR-003 validation-first stored
representation, ADR-004 status-as-string / stored-state precedent) to a
greenfield module. The version-stability requirement (D-12 option 3) is
consumed, not introduced: SPEC-010 already preserves archived versions and
their set rows, and the `restrictOnDelete` FK guards the reference. No
genuinely new architectural pattern is introduced.

---

## 11. Decision

Use the established SPEC-001/002/003/004/006/008/009/010 conventions
throughout:

- **Persistence:** a new `workout_logs` table — one row per performed set
  (D-11 option 2, BR-001) — with required FKs to `clients` (`client_id`) and
  `users` (`recorded_by`), a NOT NULL `timestamp` `performed_at` (gym-local,
  WL-05), two nullable FKs `routine_exercise_id` → `routine_exercises.id`
  (version-stable prescription reference, D-12 option 3, BR-004) and
  `exercise_id` → `exercises.id` (free-log catalogue reference, BR-005),
  nullable `actual_weight` (decimal(6,2), bodyweight convention),
  NOT NULL `actual_reps`, nullable `notes`, timestamps (`logged_at` =
  `created_at`). Indexes on `(client_id, performed_at)`, `routine_exercise_id`,
  `exercise_id`, `recorded_by`. No DB CHECK constraints (ADR-003); the
  exactly-one-reference invariant is enforced by application validation with
  the optional CHECK documented as hardening. The existing schema is untouched.
- **Exactly-one reference (BR-002, ERR-001):** shared `WorkoutLog::
  referenceRules()` — `required_without` + `prohibits` + `exists` on each of
  the two reference fields — used by the Filament form and exercised directly
  by the unit tests; no DB CHECK (ADR-003).
- **Validation (BR-008):** Filament form rules — `client_id` required +
  `exists` (no membership gate, BR-010); `performed_at` required, default now,
  `beforeOrEqual('now')` (backdating allowed, WL-05); `actual_reps` required
  positive integer; `actual_weight` nullable numeric ≥ 0; `notes` nullable;
  the reference rules plus the ERR-003 closure rule
  (`Client::hasRoutineAssignmentTo()` — the assigned-version predicate, active
  or historical, drafts excluded by construction) and the ERR-005 closure rule
  (`Exercise::scopeActive()`, the SPEC-010 `VersionRoutine` pattern). No
  separate Form Request: the repo convention is resource-level validation.
- **Authorization:** `WorkoutLogPolicy` (viewAny/view/create = ADMIN **or**
  TRAINER, no `update`, no `delete`) on top of the existing `User::hasAnyRole`
  helper (ADR-001) — the `AttendancePolicy` pattern (BR-007, WL-03, WL-09).
  Role-based, not ownership-based (BR-009).
- **UI:** Filament `WorkoutLogResource` with list/create/view pages plus a
  custom `ClientProgress` page, all in the `Training` navigation group — the
  standalone staff-facing surface required by the UI-placement constraint
  (TRAINER reachable; never ADMIN-only). Create form with a searchable client
  select, `performed_at` DateTimePicker default now, a transient
  `reference_type` toggle (routine/free) that conditionally shows the
  `routine_exercise_id` Select (assigned-version rows, prefilling target
  weight/reps) or the `exercise_id` Select (active exercises), the actual
  weight/reps/notes fields, and `recorded_by` injected in
  `mutateFormDataBeforeCreate`. Table with client/date-range/recorded_by/
  reference-type filters, chronological default order, `View` row action only,
  no bulk actions. `ClientProgress` page: a read-only per-client table
  (chronological, optionally grouped by date) with Target and Actual columns —
  the FR-003 history + FR-004 comparison in one surface (WL-10). No edit page,
  no edit/delete actions (BR-006).
- **Models:** `WorkoutLog` (fillable, casts, `client()` / `routineExercise()` /
  `exercise()` / `recordedBy()` relationships, `scopeForClient()`,
  `exerciseName()` display helper, `referenceRules()`); `Client` gains
  `workoutLogs(): HasMany` and `hasRoutineAssignmentTo(int): bool` (additive,
  no schema change).
- **No Actions, no events, no jobs, no new routes, no new seeders, no
  external integrations, no ADR.**
- **Deferred (reserved for later Specifications):** client self-logging and
  client visibility of own logs/progress (SPEC-013, D-18 option 3
  pre-approved); log correction with audit (OQ-01); a workout-session entity
  (OQ-03); analytics / grouping by routine version (OQ-06).

---

## 12. Pending PO Confirmations

These items are carried from SPEC-011 and must be confirmed before
Implementation (or at latest before Review). This design does not silently
resolve them.

### Assumptions (SPEC-011 §14.1)

| ID | Assumption | Impact on this design |
| --- | --- | --- |
| WL-01 | Logging granularity = one WorkoutLog row per performed set (D-11 option 2); no `actual_sets` count; a "workout" is the group of rows sharing `performed_at`; no session entity. | `workout_logs` flat table; the history/progress views group by `performed_at` (BR-001, FR-003/FR-004). If the PO later requires a session entity (OQ-03), a header table is additive. |
| WL-02 | Free logging is allowed in the MVP (C-11); new free logs may only reference `active` exercises. | The `reference_type` toggle offers the free path (FR-002); the ERR-005 closure rule restricts new free logs to `Exercise::scopeActive()` (BR-005, AC-6). |
| WL-03 | Who records: ADMIN/TRAINER on behalf of the client in the admin panel; client self-logging deferred to SPEC-013. | `WorkoutLogPolicy` grants create/view to ADMIN and TRAINER; CLIENT denied (BR-007, AC-13). If the PO restricts TRAINER, policy + navigation change only. |
| WL-04 | Logs are immutable in the MVP: no edit, no delete, no status transition; correction is a staff procedure. | No `update`/`delete` policy ability, no edit page, no edit/delete actions (BR-006, ERR-007, AC-12). If the PO approves correction-with-audit (OQ-01), an edit/delete-with-audit path is additive. |
| WL-05 | `performed_at` required, defaults to now, never future, backdating allowed without an explicit limit. | `timestamp` column, form default `now()`, `beforeOrEqual('now')` rule, no minimum-date rule (BR-008, ERR-004, AF-001). OQ-04 open: a backdating limit is a single form-rule change. |
| WL-06 | Performed-value conventions: `actual_reps` required positive integer; `actual_weight` optional decimal ≥ 0 (absent/zero = bodyweight); `notes` optional free text. | `actual_reps` unsigned NOT NULL, `actual_weight` decimal(6,2) nullable, `notes` text nullable; form rules (BR-008, ERR-004, AC-7). |
| WL-07 | A `routine_exercise_id` reference is valid only when the row belongs to a routine version the client has been assigned to (active or historical); drafts are never valid; no per-timestamp version check in the MVP. | `Client::hasRoutineAssignmentTo()` predicate + ERR-003 closure rule; the option list covers active and historical assignment versions (AF-002). OQ-05 open: a per-timestamp check would require assignment deactivation timestamps (additive). |
| WL-08 | Log display reads the exercise's current catalogue attributes and the prescription from the immutable set row (no snapshots). | No snapshot columns; `exerciseName()` / target columns read live (BR-004, BR-005, AF-003). |
| WL-09 | Logging and review are role-based, not ownership-based: any ADMIN or TRAINER may log/review any client regardless of `recorded_by`. | `WorkoutLogPolicy` rules ignore `recorded_by`; `recorded_by` is audit-only (BR-009, FR-005). |
| WL-10 | Minimal progress-review scope: history grouped by date + simple prescription-vs-actual comparison per logged set; no analytics. | The `ClientProgress` page with Target/Actual columns; no charts, totals, PRs or trends (FR-003, FR-004). |
| WL-11 | `recorded_by` is a required audit field set from the authenticated staff User at creation (never a form field); `logged_at` = `created_at`. | NOT NULL FK to `users.id`; injected in `mutateFormDataBeforeCreate`; `created_at` displayed as "Logged at" (BR-009, FR-005, AC-1). |

### Open questions (SPEC-011 §14.2)

| ID | Question | Impact on this design |
| --- | --- | --- |
| OQ-01 (WL-04) | Should a log-correction capability (edit/delete with audit trail) be added later? | Design assumes immutable logs (BR-006); a correction path is additive (policy ability + page + tests) and tracked for a later iteration. |
| OQ-02 | Should an RPE / perceived-exertion field be added later? | Omitted from the schema (no speculative fields); additive nullable column later if approved. |
| OQ-03 (WL-01) | Does SPEC-013 self-logging need a first-class "workout session" entity? | MVP assumes the flat per-set log grouped by `performed_at`; a session header is an additive table if SPEC-013 requires it. |
| OQ-04 (WL-05) | Is a maximum backdate limit needed for `performed_at`? | None imposed; a limit is a single form-rule change (the `Attendance` OQ-07 stance). |
| OQ-05 (WL-07) | Should the "which version was the client on at performed_at" check be introduced later? | Not in the MVP (requires assignment deactivation timestamps); the design validates against the full assignment history. |
| OQ-06 (WL-10) | Should the progress view be grouped/filtered by routine version or day? | MVP groups by performed date only; the `ClientProgress` table can add filters additively. |
| OQ-07 (WL-03) | When SPEC-013 implements client self-logging, must CLIENT self-recorded logs be editable by client/staff? | Deferred by design to SPEC-013; the immutability rules apply to staff-recorded logs here (BR-006). |

### Additional design notes flagged for confirmation

- The exactly-one-reference invariant is enforced by application validation
  (shared `referenceRules()`), not by a DB CHECK — the ADR-003 validation-first
  convention; the optional CHECK is documented hardening, no business
  difference (SPEC-011 §10).
- Version-stability (BR-004, D-12 option 3) is a property of the existing
  SPEC-010 model (archived versions and their set rows are preserved; drafts
  are never assignable): this design consumes it and guards the reference with
  a `restrictOnDelete` FK, and introduces no snapshot or re-pointing mechanism.
- `recorded_by` is set server-side from the authenticated staff User; it is
  never a form field and is never modified (BR-009, WL-11).
- Logging and review are role-based, not ownership-based (BR-009, WL-09): any
  ADMIN or TRAINER may log for / review any client regardless of `recorded_by`.
- No membership/access precondition applies to logging (BR-010): the create
  form has no access-gate rule (unlike `AttendanceResource`).
- The logging and progress surfaces live in the standalone `WorkoutLogResource`
  (ADMIN|TRAINER) — never only inside the ADMIN-only `ClientResource` — to
  satisfy the TRAINER reachability constraint (SPEC-011 §2/§9, AC-13/AC-9).
- Logs are immutable: this design registers no `update`/`delete` policy
  ability, exposes no edit/delete page/action, and stores no `status`
  (BR-001, BR-006, ERR-007).

---

## 13. Related Documents

- Specification: `docs/specs/SPEC-011.md`
- Architecture decisions: `docs/adr/ADR-001.md` (role/authorization
  foundation), `docs/adr/ADR-002.md` (module boundary discipline),
  `docs/adr/ADR-003.md` (validation-first representation / decimal
  convention), `docs/adr/ADR-004.md` (status-as-string / stored-state
  precedent)
- Architecture: `docs/architecture/SPEC-008.md` (the immutable event-log /
  `recorded_by` / no-update-no-delete policy precedents),
  `docs/architecture/SPEC-009.md` (`Exercise`, `scopeActive()`, the
  consuming-module reference direction), `docs/architecture/SPEC-010.md`
  (Routine module: set-level rows, versioning with reassignment, the
  execution-friendly shape, AC-17 boundary), `docs/architecture/SPEC-001.md`,
  `docs/architecture/SPEC-002.md`, `docs/architecture/SPEC-003.md`,
  `docs/architecture/SPEC-004.md`, `docs/architecture/SPEC-006.md`,
  `ARCHITECTURE.md` (§7 Actions, §8 Models, §10 Events, §12 Authorization,
  §16 Routines: prescription vs execution, §20 simplest correct architecture)
- Product: `docs/product/product-definition-v0.1.md`
- Domain: `docs/domain/domain-model-v0.1.md` (§WorkoutLog, §Routine,
  §RoutineDay, §RoutineExercise, §Exercise; C-10, C-11)
- Requirements analysis: `docs/requirements/analyst-pass-001.md` (§5.13
  Workout Tracking, D-11, D-12, C-10, C-11, C-03, C-13, D-18)
- Specifications: `docs/specs/SPEC-001.md` (roles, C-13/C-15),
  `docs/specs/SPEC-002.md` (Client records; ADMIN-only `ClientResource` — the
  UI-placement constraint), `docs/specs/SPEC-008.md` (`recorded_by` precedent:
  AT-01, AT-08, BR-011), `docs/specs/SPEC-009.md` (Exercise catalogue; BR-007
  active set, BR-010/BR-011 consumption), `docs/specs/SPEC-010.md` (Routines;
  BR-001..BR-011, AR-01..AR-09, D-11/D-12, AC-17 boundary)
- Workflow state: `docs/sdd/state.yaml` (SPEC-011 `spec_ready`, current phase
  `architecture`; NIGHT MODE pre-approvals D-11/D-12; SPEC-001/002/003/004/
  006/008/009/010 completed; SPEC-013 depends on SPEC-011)
- Development rules: `AGENTS.md`
