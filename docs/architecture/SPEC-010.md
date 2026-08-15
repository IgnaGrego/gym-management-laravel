# Architecture — SPEC-010

## 1. Feature

Routines (personalized exercise plans) for the gym management system:

- a **Routine** is a versioned plan of exercises assigned to clients, organized
  in **ordinal days** within a repeating cycle (Day 1..N, gate **D-10 option 2**,
  pre-approved under NIGHT MODE), with **set-level prescriptions** — one
  `RoutineExercise` row per set (gate **D-11 option 2**, pre-approved);
- the entity chain is `Routine → RoutineDay → RoutineExercise → Exercise`
  (C-09): a version owns its days; a day owns its set rows; each set row
  references one catalogue exercise (SPEC-009);
- **versioning with reassignment** (gate **D-12 option 3**, pre-approved): each
  `Routine` record is one **version**; versions of the same plan form a
  **lineage** linked by a self-referential `replaces_id`; editing an `active`
  version creates a new version (copy-on-edit) and archives the previous one;
  clients stay on the old version until staff explicitly reassign them;
- **assignment**: a client is assigned to a specific routine **version**; a
  client has **at most one active assignment** at a time (assumption AR-03);
  assigning to a client who already has one supersedes it (history preserved);
- staff — **ADMIN and TRAINER** — can create, list/search/filter, view, edit,
  activate, version and assign routines from the admin panel (C-03, C-15, AR-08;
  the same role set as `TurnoPolicy` / `ExercisePolicy`);
- **prescription vs execution separation** (C-10): the Routine entities carry
  only the prescription; workout logs (SPEC-011) record execution separately and
  must never modify a prescription. This Specification creates no execution
  record of any kind;
- the model is **execution-friendly**: SPEC-011 will reference the prescribed
  `RoutineExercise` rows (and/or `Exercise`) when logging execution;
- client isolation is preserved: a CLIENT never accesses routines or
  prescription data through any path defined here (C-13, AR-08); client
  visibility of the assigned routine is deferred to SPEC-011 / SPEC-013.

This is the tenth Specification of the MVP. It builds on the SPEC-001/002/009
foundations already implemented (User / Role / Client / Exercise models,
`User::hasRole` / `hasAnyRole` helpers, policy pattern, Filament admin panel,
ADR-001/002/003/004) and follows the SPEC-003 (Plan), SPEC-006 (Turno) and
SPEC-009 (Exercise) conventions — the closest patterns for an ADMIN+TRAINER
managed catalogue and for model-level state transitions. Routines is a
greenfield module: no routine tables exist yet. SPEC-011 (Workout Logs &
Progress) will depend on this Specification.

---

## 2. Specification

Reference:

`docs/specs/SPEC-010.md`

Status note: SPEC-010 is approved for the architecture phase per
`docs/sdd/state.yaml` (status `spec_ready`, current phase `architecture`,
architect `in_progress`). The gates **D-10 option 2** (ordinal days), **D-11
option 2** (set-level rows) and **D-12 option 3** (versioning with
reassignment) are pre-approved under NIGHT MODE (`docs/sdd/state.yaml`
`project.po_decisions`). The Specification explicitly flags assumptions
**AR-01 to AR-09** as NOT confirmed business rules; they require Product Owner
confirmation before Implementation (SPEC-010 §14.1). This design is written
against the assumptions as stated and remains valid under the documented
alternatives unless the PO changes them (see §12 Pending PO Confirmations).

Boundary note: workout logs and progress (SPEC-011), client-facing routine
visibility (SPEC-011 / SPEC-013), days-of-the-week scheduling (D-10 option 2
fixes ordinal days), exercise-level prescription granularity (D-11 option 2
fixes set-level rows), live edit of assigned routines (D-12 option 3 fixes
versioning), the trainer–client assignment (SPEC-002 OQ-02), a manual
"archive" action, routine templates/shared libraries, program phases /
periodization, bulk import/export and notifications are all explicitly OUT of
scope (SPEC-010 §12). This design introduces no workout-log, client-portal,
template or notification concept of any kind.

---

## 3. Affected Modules

- **Routines** (new module): the four Routine entities (`routines`,
  `routine_days`, `routine_exercises`, `routine_assignments` tables), the
  draft/active/archived lifecycle (BR-002), the ordinal-day structure (BR-003),
  the set-level prescription rows (BR-004), the version lineage (BR-001,
  AR-02), the assignment semantics (BR-007, AR-03), ADMIN+TRAINER management,
  and the execution-friendly shape (prescription only, BR-005) that SPEC-011
  will consume.
- **Cross-cutting authorization foundation** (no new module): a new
  `RoutinePolicy` and a thin `RoutineAssignmentPolicy` extend the
  SPEC-001/002/003/004/006/009 pattern — granting management to **ADMIN and
  TRAINER** like `TurnoPolicy` / `ExercisePolicy` (BR-009, AR-08) — and consume
  the existing `User::hasAnyRole` helper (ADR-001). No delete ability is
  registered (BR-008).
- **Clients** (existing module, additive changes only): the `Client` model
  gains a `routineAssignments()` HasMany relationship and a `currentRoutine()`
  read helper (the assignment target per SPEC-010 §13 dependency SPEC-002); the
  `ClientResource` gains a read-only routine-assignment history relation
  manager and a "current routine" entry in the client detail view (FR-011). No
  table on the Clients module changes.
- **Exercises** (existing module, consumed, unchanged): the
  `routine_exercises.exercise_id` reference direction documented by SPEC-009
  BR-011 / §10 is created from the consuming module; `Exercise::scopeActive()`
  is consumed as the "currently offered" set for NEW prescription rows (BR-006,
  AR-04). The `exercises` table gains no column and no FK is added to it.

No changes are made to: auth scaffolding, `AdminPanelProvider`, `RoleSeeder`,
`AdminUserSeeder`, the `role_user` pivot, or the `users` / `roles` /
`clients` / `plans` / `memberships` / `turnos` / `attendances` / `exercises`
tables, or the Users, Plans, Memberships, Scheduling and Attendance modules.

The boundary with later Specifications is kept clean: workout logging that
references the prescribed rows (SPEC-011), client visibility of the assigned
routine (SPEC-011 / SPEC-013) and trainer–client assignment (SPEC-002 OQ-02)
are explicitly OUT of scope. This design creates no workout-log record of any
kind (AC-17).

---

## 4. Application Flow

```text
Presentation (web)
    ↓
Admin panel: /admin — Filament panel (ADMIN | TRAINER gate via canAccessPanel)
    ↓
RoutineResource (Filament): list (lineage heads) / create / view / edit /
                            activate / assign-to-clients / version history
    ↓
Application
    ↓
RoutinePolicy / RoutineAssignmentPolicy (ADMIN | TRAINER)
App\Actions\VersionRoutine          App\Actions\AssignRoutine
    ↓
Domain
    ↓
Routine model (name, status, version_number, replaces_id, created_by)
RoutineDay model (day_number)
RoutineExercise model (set_number, target_reps, target_weight, rest_seconds, notes, exercise_id)
RoutineAssignment model (client_id, routine_id, assigned_at, is_active)
    ↓
Persistence
    ↓
PostgreSQL: routines / routine_days / routine_exercises / routine_assignments
            (new); users / roles / role_user / clients / exercises (existing,
            untouched except additive Eloquent relationships on Client)
```

Concrete flows:

1. **Create routine (FR-001)**
   - ADMIN or TRAINER opens the Routines section of the admin panel
     (`RoutineResource`).
   - Create form: fills the required `name`; saves.
   - The page sets `created_by = auth()->id()`, `status = draft` (DB default),
     `version_number = 1` (DB default), `replaces_id = null`. A new routine is
     persisted as **version 1, draft, no days** (FR-001, BR-002, AR-01, AR-05).
   - Creating a routine creates NO day, set-row, assignment or workout-log
     record (AC-17).
2. **Add days and set rows to a draft (FR-005, FR-008)**
   - Staff edit the draft: the EditRoutine form presents the name plus a days
     Repeater, each day with a nested set-rows Repeater (exercise Select,
     set number, target reps, target weight, rest seconds, notes).
   - Saving edits the draft **in place**: day/set rows are added, removed and
     updated on the same version (FR-005, AF-001); no new version is created
     (AC-6). Duplicate day/set numbers are rejected (ERR-002); new set rows
     may only reference active exercises (BR-006, AC-5); invalid prescription
     values are rejected (ERR-005).
3. **Activate a draft (FR-007)**
   - Staff open the draft's detail page (`ViewRoutine`) and use the
     **Activate** header action.
   - `Routine::activate()` validates: at least one day (ERR-003) and at least
     one set row per day (ERR-004); it throws `DomainException` otherwise. The
     version becomes `active` (BR-002).
4. **Assign to clients (FR-009, FR-010, FR-011)**
   - Staff open an `active` version's detail page and use the **Assign to
     clients** header action: select one or more clients in a modal.
   - `App\Actions\AssignRoutine` (transactional): rejects `draft` / `archived`
     versions (ERR-008); for each client, deactivates any existing active
     assignment (supersession, AF-002, AC-11) and creates a new active
     `RoutineAssignment` with `assigned_at = now` (BR-007, AC-10). Assignment
     history is preserved (AC-13). No prescription row is touched (BR-007).
   - The **Assigned clients** relation manager tab on the view page lists who
     is on this version (FR-011) and offers **Unassign** per active row
     (FR-010, AF-006: the active row is deactivated; the client keeps the
     history and has no active routine until a new assignment).
   - The client detail view shows the client's current active routine and
     assignment history (FR-011).
5. **Edit an active routine — versioning (FR-006)**
   - Staff click **Edit** on an `active` version. The EditRoutine page loads
     the current version's name, days and set rows.
   - On save, `EditRoutine::handleRecordUpdate` is overridden: because the
     record is `active`, it delegates to `App\Actions\VersionRoutine` instead
     of the default in-place save.
   - `VersionRoutine` (transactional): validates the routine is `active`
     (archived is read-only, ERR-006; drafts are edited in place), re-validates
     the prescription invariants, creates a NEW Routine (name from the form,
     `version_number = previous + 1`, `replaces_id = previous.id`, status
     `active`, `created_by = editor`), copies the days and set rows from the
     form state (fresh rows, never shared — BR-001), and archives the previous
     version (status `archived`) (BR-002, D-12 option 3).
   - Assignments are untouched (AC-7): clients remain on the previous version
     until staff explicitly reassign them via **Assign to clients** on the new
     version (AC-8, AF-003).
6. **Version history (FR-004)**
   - Staff open any version's detail page; the **Version history** section
     lists every version of the lineage with its number, status and creator
     (FR-004, FR-012). Archived versions are fully readable (FR-003) but
     cannot be edited, versioned again, or assigned (ERR-006, AF-004) — the
     `Edit` page and the **Assign to clients** / **Activate** actions are
     hidden and server-blocked for archived versions.
7. **Search and filter (FR-002, FR-012)**
   - The routine list shows one row per **lineage** (the current/latest
     version), searchable by name and filterable by status; every list row and
     detail view shows the status and version number.

---

## 5. Components

### Controllers

None new.

Routine management lives entirely inside the Filament `RoutineResource` (the
admin-side controller, same convention as `ExerciseResource`, `TurnoResource`,
`PlanResource`). No web routes or HTTP controllers are added.

### Actions / Use Cases

Two explicit Actions are warranted. Both are multi-entity, transactional,
rule-bearing operations — the exact `ProvisionClientUser` / `RenewMembership`
precedent (AGENTS.md §9-10, ARCHITECTURE §7). `AssignRoutine` is named by the
spec's own dependency list (SPEC-010 §13, ARCHITECTURE §7).

**`App\Actions\VersionRoutine`** (new) — the copy-on-edit versioning operation
(FR-006, BR-001, BR-002, AR-02):

- `handle(Routine $routine, User $editor, array $data): Routine` where
  `$data` is the validated edit-form state: `['name' => string, 'days' => [
  ['day_number' => int, 'exercises' => [['exercise_id' => int, 'set_number'
  => int, 'target_reps' => int, 'target_weight' => ?float, 'rest_seconds' =>
  ?int, 'notes' => ?string, ...], ...]], ...]]`. Incoming row `id` keys are
  ignored: the copy creates FRESH `RoutineDay` / `RoutineExercise` rows so no
  row is ever shared or mutated across versions (BR-001).
- Guards (validate step): the routine must be `active` (drafts are edited in
  place, archived versions are read-only — ERR-006); duplicate day numbers
  within the version and duplicate set numbers within a day are rejected
  (ERR-002); prescription values are re-validated (ERR-005); rows with no
  source id (new rows added during the edit) must reference an active exercise
  (BR-006, AR-04), while rows copied from the previous version keep their
  exercise reference even if the exercise is now inactive (AR-04).
- Transactional body: create the new Routine (`version_number = $routine->
  version_number + 1`, `replaces_id = $routine->id`, status `active`,
  `created_by = $editor->id`), create the day and set rows from `$data`, then
  set the previous version's status to `archived`.
- Never touches assignments (FR-006, AC-7): existing `routine_assignments`
  rows keep pointing at the previous version.

**`App\Actions\AssignRoutine`** (new) — the assignment operation with
supersession (FR-009, FR-010, BR-007, AR-03):

- `handle(Routine $routine, Collection|array $clients, ?Carbon $assignedAt =
  null): void` where `$clients` is a collection/array of `Client` models (the
  Filament action resolves the selected client ids).
- Guards: the routine version must be `active` (draft/archived rejected,
  ERR-008); clients must exist (FK integrity, ERR-001 by analogy).
- Transactional body: for each client, deactivate every existing active
  assignment (`is_active = false`, history preserved) and create a new active
  `RoutineAssignment` (`client_id`, `routine_id`, `assigned_at = $assignedAt
  ?? now()`, `is_active = true`). This implements both the initial assignment
  (FR-009) and the reassignment after versioning (FR-010, AF-002, AC-11).
- Never creates, modifies or deletes any prescription row or workout log
  (BR-007, AC-17).

No other Action is needed: draft editing is plain Eloquent CRUD on the draft
plus its HasMany relations (handled by the Filament form / page); activation is
a single-record state transition with content validation, implemented as a
model method `Routine::activate()` (the `Turno` precedent); unassignment is a
single-record state transition implemented as a model method
`RoutineAssignment::deactivate()` (the `Turno` precedent).

### Models

**`App\Models\Routine`** (new)

- Table: `routines` (one row per version, D-12 option 3).
- Fillable: `name`, `status`, `version_number`, `replaces_id`, `created_by`.
- Constants — single source of truth for the three-state lifecycle (BR-002,
  AR-01; the ADR-004 string-with-constants convention, precedent
  `Turno::STATUS_*`):
  - `Routine::STATUS_DRAFT = 'draft'`
  - `Routine::STATUS_ACTIVE = 'active'`
  - `Routine::STATUS_ARCHIVED = 'archived'`
- Default attributes: `status` → `STATUS_DRAFT`, `version_number` → `1`
  (FR-001, AR-01); the DB columns carry the same defaults (the `Turno` /
  `Membership` precedent).
- Casts: `version_number` → `'integer'` (the `capacity_limit` precedent).
  `status` stays a plain string validated against the constants (ADR-004).
- Relationships:
  - `days(): HasMany` — `RoutineDay`, ordered by `day_number` (FR-003
    display; the same ordering discipline as `Client::memberships()`).
  - `replaces(): BelongsTo` — self-referential, `replaces_id` → `routines.id`,
    nullable (the version this one replaces; `null` for version 1) — the
    lineage chain (BR-001, AR-02).
  - `replacedBy(): HasMany` — self-referential inverse
    (`hasMany(Routine::class, 'replaces_id')`); used to find the lineage head
    and to walk the lineage forward.
  - `creator(): BelongsTo` — `User`, FK `created_by` (BR-011, AR-08);
    informational/audit only.
  - `assignments(): HasMany` — `RoutineAssignment` (FR-011; SPEC-011 will
    consume the active assignment).
- Simple domain behavior (ARCHITECTURE §8):
  - `isDraft(): bool`, `isActive(): bool`, `isArchived(): bool` — status
    checks (BR-002; the `Turno::isActive()` / `Exercise::isActive()` pattern).
  - `activate(): void` — `draft → active` (FR-007, BR-002). Throws
    `DomainException` when the version has zero days (ERR-003) or any day has
    zero set rows (ERR-004), or when the status is not `draft`. Sets
    `status = STATUS_ACTIVE` and saves. This is the model-method precedent of
    `Turno::deactivate()` / `Membership::cancel()`: the content invariants
    depend on the persisted days/rows, not on form state.
  - `lineageIds(): array` — walks the `replaces` chain backwards (ancestors)
    and the `replacedBy` chain forward (descendants), returning every version
    id of the lineage (BR-001, AR-02; FR-004 version history). Lineages are
    short chains; a PHP walk is the simplest correct mechanism (no recursive
    SQL; ADR-003 framework-first).
  - `lineage(): Collection` — the `Routine` models for `lineageIds()`, ordered
    by `version_number` (FR-004).
- Scopes:
  - `scopeActive(Builder): Builder` — `where('status', STATUS_ACTIVE)` (the
    "currently assignable" set for consumers; the `Turno::scopeActive` /
    `Exercise::scopeActive` pattern).
  - `scopeLineageHeads(Builder): Builder` — `whereDoesntHave('replacedBy')`:
    routines that no other version replaces = the current/latest version of
    each lineage; the FR-002 list query (one row per lineage).
- No delete scope/method: deletion is not offered anywhere (BR-008, ERR-009).

**`App\Models\RoutineDay`** (new)

- Table: `routine_days` (D-10 option 2).
- Fillable: `routine_id`, `day_number`.
- Casts: `day_number` → `'integer'`.
- Relationships:
  - `routine(): BelongsTo` — `Routine`.
  - `exercises(): HasMany` — `RoutineExercise`, ordered by `set_number`
    (FR-003 display; BR-004 ordering).
- No domain methods: the day is a plain ordinal container (BR-003). The
  per-version day-number uniqueness is enforced by the DB unique index
  (ERR-002) and by form/action validation.

**`App\Models\RoutineExercise`** (new) — the set-level prescription row
(D-11 option 2, BR-004)

- Table: `routine_exercises`.
- Fillable: `routine_day_id`, `exercise_id`, `set_number`, `target_reps`,
  `target_weight`, `rest_seconds`, `notes`.
- Casts: `set_number` / `target_reps` / `rest_seconds` → `'integer'`;
  `target_weight` → `'decimal:2'` (the ADR-003 decimal cast precedent — note
  Eloquent returns strings, see §9).
- Relationships:
  - `routineDay(): BelongsTo` — `RoutineDay`.
  - `exercise(): BelongsTo` — `Exercise` (BR-006; the consuming-module
    reference direction documented by SPEC-009 BR-011 / §10). Displaying a
    prescription reads the exercise's CURRENT catalogue attributes (AR-04: no
    per-prescription snapshot).
- No domain methods: the row is a plain prescription value object. Its
  invariants (positive reps, non-negative weight/rest, per-day set-number
  uniqueness, active-exercise rule for new rows) are enforced by form
  validation and by the `VersionRoutine` action validation.

**`App\Models\RoutineAssignment`** (new)

- Table: `routine_assignments` (BR-007, AR-03, AR-09).
- Fillable: `client_id`, `routine_id`, `assigned_at`, `is_active`.
- Casts: `assigned_at` → `'datetime'`; `is_active` → `'boolean'`.
- Relationships:
  - `client(): BelongsTo` — `Client`.
  - `routine(): BelongsTo` — `Routine` (the assigned VERSION).
- Simple domain behavior:
  - `deactivate(): void` — ends the assignment (FR-010, AF-006): throws
    `DomainException` unless `is_active === true`, then sets
    `is_active = false` and saves (the `Turno` transition-method precedent;
    history preserved, BR-008). Unassignment never touches prescription rows
    (BR-007).
- No delete scope/method: assignment history is never hard-deleted (BR-008,
  ERR-009).

**`App\Models\Client`** (existing — additive changes only)

- New relationship: `routineAssignments(): HasMany` — `RoutineAssignment`,
  ordered by `assigned_at` (the `memberships()` / `attendances()` pattern;
  FR-011 history display).
- New read helper: `currentRoutine(): ?Routine` — returns the `Routine` of the
  client's current active assignment (`routineAssignments()->where('is_active',
  true)->first()?->routine`), or `null` when the client has none (FR-011;
  AR-03). Used by the `ClientResource` detail view and by unit tests.

**`App\Models\User` / `App\Models\Exercise`** — unchanged. `User` is the FK
target of `routines.created_by`; `Exercise` is the FK target of
`routine_exercises.exercise_id` and the source of `scopeActive()`.

### Policies

**`App\Policies\RoutinePolicy`** (new) — extends the `TurnoPolicy` /
`ExercisePolicy` pattern, with the ADMIN+TRAINER management set required by
BR-009 / AR-08 (C-03; the same role set as `TurnoPolicy` / `ExercisePolicy`):

- `viewAny` / `view`: ADMIN **or** TRAINER (BR-009, FR-002, FR-003).
- `create`: ADMIN **or** TRAINER (BR-009, FR-001).
- `update`: ADMIN **or** TRAINER (BR-009) — covers draft in-place edits
  (FR-005), active versioning (FR-006), activation (FR-007) and assignment
  operations (FR-009..FR-011), the same way `TurnoPolicy::update` covers the
  turno lifecycle and `ExercisePolicy::update` covers activate/deactivate.
- No `delete` policy is registered: routine records are never hard-deleted
  (BR-008, ERR-009); there is no delete operation and no delete path in the
  UI.
- All rules use `$user->hasAnyRole([Role::ADMIN, Role::TRAINER])` (ADR-001).
- Role-based, not ownership-based: any ADMIN or TRAINER may operate on any
  routine regardless of `created_by` (BR-011, AR-08).

**`App\Policies\RoutineAssignmentPolicy`** (new) — a thin policy required by
the Filament relation managers, which check the RELATED model's policy (the
`MembershipsRelationManager` precedent: "Filament checks the related model's
policy"):

- `viewAny` / `view`: ADMIN **or** TRAINER (FR-011; the relation managers on
  `RoutineResource` and `ClientResource` render the assignment history).
- `update`: ADMIN **or** TRAINER (the **Unassign** row action on the assigned-
  clients relation manager calls `RoutineAssignment::deactivate()`, FR-010).
- No `create`: assignments are created only through `App\Actions\AssignRoutine`
  (authorized via `RoutinePolicy::update` on the routine), never through a
  relation-manager create action.
- No `delete`: assignment history is never hard-deleted (BR-008, ERR-009).

Authorization matrix (SPEC-010 §9):

| Operation | Anonymous | ADMIN | TRAINER | CLIENT |
| --- | --- | --- | --- | --- |
| Create routine | Denied | Allowed (BR-009) | Allowed (BR-009) | Denied |
| List / search / filter routines | Denied | Allowed (BR-009) | Allowed (BR-009) | Denied |
| View routine detail / version history | Denied | Allowed (BR-009) | Allowed (BR-009) | Denied |
| Edit draft routine (in place) | Denied | Allowed (BR-009) | Allowed (BR-009) | Denied |
| Edit active routine (create new version) | Denied | Allowed (BR-009) | Allowed (BR-009) | Denied |
| Activate / publish routine | Denied | Allowed (BR-009) | Allowed (BR-009) | Denied |
| Assign / reassign / unassign clients | Denied | Allowed (BR-009, C-03) | Allowed (BR-009, C-03) | Denied |
| Delete routine / day / set / assignment | Denied | Denied (no such operation — BR-008) | Denied (no such operation — BR-008) | Denied (no such operation — BR-008) |
| Client visibility of own assigned routine | Out of scope (SPEC-011, SPEC-013) | — | — | Out of scope at this stage (AR-08) |

A multi-role user receives the union of permissions (SPEC-001 BR-002): an
ADMIN or TRAINER who also holds CLIENT can manage routines in the admin panel
(AC-19). CLIENT-only users never reach the admin panel (`canAccessPanel`,
SPEC-001); anonymous visitors are redirected to `/login` (ERR-007).
Authorization is enforced server-side via the Policies; frontend hiding is
never the enforcement (AGENTS.md §17). The lifecycle/state rules (archived is
read-only, ERR-006; only active versions assignable, ERR-008; only drafts
activatable) are NOT authorization rules: they are enforced by the model
methods (`Routine::activate()`), the Actions (`VersionRoutine`,
`AssignRoutine`), and the resource guards (`RoutineResource::canEdit`,
action `visible` conditions), so an authorized user still cannot perform an
invalid transition.

### Filament

**`App\Filament\Resources\RoutineResource`** (new) with pages `ListRoutines`,
`CreateRoutine`, `ViewRoutine`, `EditRoutine` (the `ExerciseResource` /
`TurnoResource` folder convention:
`app/Filament/Resources/RoutineResource/Pages/*`).
`navigationIcon` (e.g., `heroicon-o-clipboard-document-check`) and
`navigationGroup = 'Training'` (the group created by SPEC-009 for the Training
modules).

- Form (create — FR-001):
  - `name` — `TextInput`, required, `maxLength(255)`. **Not unique** (AR-05,
    BR-010): versions of a lineage share the name and different lineages may
    reuse it.
  - No other fields: a new routine is created as draft v1 with no days
    (FR-001); days and set rows are added via the edit form (FR-005, AF-005).
  - The page sets `created_by = auth()->id()` via
    `mutateFormDataBeforeSave` (BR-011); `status` / `version_number` /
    `replaces_id` come from the DB defaults.
- Form (edit — FR-005, FR-006, FR-008):
  - `name` — as above.
  - `days` — `Repeater` of days (ordinal days, BR-003), each item with:
    - `day_number` — `TextInput` numeric, required, `integer`, `minValue(1)`,
      plus a closure rule rejecting a number already used by another day item
      in the same version (ERR-002, AC-3);
    - `exercises` — nested `Repeater` of set rows (set-level prescription,
      BR-004), each item with:
      - `exercise_id` — `Select` whose options are the active exercises
        (`Exercise::scopeActive()->pluck('name', 'id')`) merged with the
        exercise ids already referenced by this version's rows (so preserved
        rows referencing now-inactive exercises keep displaying, AR-04);
        required, `exists:exercises,id` (ERR-001); plus a closure rule
        rejecting an `inactive` exercise for a NEW row (no existing id)
        (BR-006, AR-04, AC-5);
      - `set_number` — `TextInput` numeric, required, `integer`, `minValue(1)`,
        plus a closure rule rejecting a number already used by another set-row
        item in the same day (ERR-002, AC-3);
      - `target_reps` — `TextInput` numeric, required, `integer`, `minValue(1)`
        (BR-010, ERR-005, AR-06);
      - `target_weight` — `TextInput` numeric, nullable, `numeric`, `minValue(0)`
        (BR-010, ERR-005, AR-06; absent/zero = bodyweight); an empty input is
        stored as `null`;
      - `rest_seconds` — `TextInput` numeric, nullable, `integer`, `minValue(0)`
        (BR-010, ERR-005, AR-06); an empty input is stored as `null`;
      - `notes` — `Textarea`, nullable (BR-010, AR-06).
  - `status` is NOT a form field: status changes exclusively through the
    lifecycle action / versioning (FR-007, FR-006; the `Turno` precedent where
    status is action-driven, not form-driven).
  - Persistence: for a `draft` record the form edits it **in place** (FR-005)
    — the days/sets Repeaters synchronize the draft's related rows (added,
    removed and updated rows; see §6 for the FK mechanism that makes day
    removal work). For an `active` record the page's `handleRecordUpdate` is
    overridden to delegate the whole save to `App\Actions\VersionRoutine`
    (FR-006) — the default in-place save must NOT run against an active
    version (BR-001); the new version's id is returned so Filament redirects
    to the new version.
- Table (FR-002, FR-012) — one row per LINEAGE (the current/latest version):
  - The table query applies `Routine::scopeLineageHeads()` (via
    `modifyQueryUsing` on the table or `getEloquentQuery` on the page).
  - Columns: `name` (searchable, sortable), `status` (badge column — FR-012;
    colors e.g. draft=gray, active=success, archived=danger; presentation
    choice), `version_number` (sortable, displayed as `v{n}` — FR-012),
    `creator.name` (Label "Created by").
  - Filters (FR-002): `SelectFilter` on `status` with the three constants.
  - Row actions: `View`, `Edit` (auto-hidden on archived versions via
    `canEdit`, ERR-006). No delete action; `bulkActions([])` (BR-008,
    ERR-009).
  - `canEdit(Routine $record): bool` — overridden to return
    `parent::canEdit($record) && $record->status !== Routine::STATUS_ARCHIVED`
    (BR-002, ERR-006): the single override gates both the `Edit` row/header
    actions (hidden on archived versions) and direct URL access to the
    `EditRoutine` page (abort/403) — the `TurnoResource::canEdit` precedent.
    Drafts AND active versions remain editable (draft in place; active via a
    new version).
- View page (`ViewRoutine`, FR-003, FR-004, FR-012):
  - Infolist: `name`, `status` (badge), `version_number`, `creator.name`
    (BR-011), and the days with their set rows (`RepeatableEntry::make('days')`
    with a nested entry for `exercises` showing `exercise.name`, `set_number`,
    `target_reps`, `target_weight`, `rest_seconds`, `notes`; presentation
    detail — if the installed Filament version cannot nest RepeatableEntries,
    a formatted text/table section is acceptable, the FR-003 content is what
    matters). Prescription display reads the exercise's current catalogue
    attributes (AR-04).
  - **Version history** section (FR-004): every version of the lineage with
    `version_number`, `status` and `creator.name`, implemented either as an
    infolist `RepeatableEntry` bound to a `lineageSummary` accessor (or
    `->state()` closure) on `Routine`, or as a read-only `VersionsRelationManager`
    table whose query is scoped to `lineageIds()` (presentation choice; the
    chain-walk data comes from `Routine::lineage()`, §5 Models). Archived
    versions are fully readable here (AF-004).
  - Header action **Activate** (FR-007): visible when
    `record->status === STATUS_DRAFT`, `requiresConfirmation()`, authorized via
    `auth()->user()->can('update', $record)`, calls `$record->activate()`; the
    `DomainException` from ERR-003/ERR-004 is surfaced to the user (the
    Developer converts it to a notification or a thrown `ValidationException`).
  - Header action **Assign to clients** (FR-009): visible when
    `record->status === STATUS_ACTIVE` (ERR-008; hidden for draft/archived),
    opens a modal with a multi-`Select` of clients (`Client::query()->pluck(
    'full_name', 'id')`), authorized via `auth()->user()->can('update',
    $record)`, on submit calls `App\Actions\AssignRoutine` with the resolved
    clients (supersession is handled inside the Action, AF-002). Reassignment
    to another version of the same lineage (or another routine) is the same
    action on the target version (FR-010, D-12 option 3).
  - Relation manager **Assigned clients** (FR-011): a tab showing this
    version's `assignments` — columns `client.full_name`, `assigned_at`
    (`->dateTime()`), `is_active` (badge/boolean); row action **Unassign**
    (visible when the row is active, `requiresConfirmation()`, authorized via
    `auth()->user()->can('update', $assignment)`, calls
    `$assignment->deactivate()`). No create/attach action: assignment creation
    goes through **Assign to clients** (the `MembershipsRelationManager`
    read-only precedent).
- Navigation: the Routines section appears in the `Training` navigation group
  (the Developer may adjust cosmetic placement).

**`App\Filament\Resources\ClientResource`** (existing — additive UI only):

- `getRelations()` gains a read-only `RoutineAssignmentsRelationManager`
  (tab "Routines"): the client's assignment history ordered by `assigned_at`,
  columns `routine.name` (with the version, e.g. `{name} — v{version_number}`),
  `assigned_at`, `is_active` (badge marking the current active assignment) —
  the exact `MembershipsRelationManager` pattern (FR-011 client side; BR-007
  history).
- The client detail infolist gains a **Current routine** entry
  (`->state(fn (Client $record): ?string => $record->currentRoutine()?->name
  . ' — v' . $record->currentRoutine()?->version_number)` or a simple
  "No routine assigned" placeholder) — FR-011 ("for a client, which routine
  version is currently active").
- Note: `ClientResource` remains ADMIN-only (ClientPolicy, SPEC-002); the
  FR-011 client-side read is therefore ADMIN-only in this placement — the
  routine-side **Assigned clients** relation manager covers the same data for
  TRAINERs (see §9).

### Events

None required.

No operation in SPEC-010 has a defined secondary effect that needs decoupling
(ARCHITECTURE §10). Versioning, activation and assignment are synchronous and
transactional; no notification is sent to clients (notifications depend on
SPEC-013 client-communication infrastructure and are out of scope, SPEC-010
§12). `RoutineVersioned` / `RoutineAssigned`-style events are not needed until
SPEC-011 / SPEC-013 define consumers.

### Jobs

None required.

No queued work exists in SPEC-010 (no notifications, email, or slow
operations); the versioning and assignment operations are synchronous
transactions (ARCHITECTURE §11).

### Routes

No new routes. Filament auto-registers `/admin/routines*` through the panel's
`discoverResources` (already configured in `AdminPanelProvider`).

### Seeders

None new. Routines are created by staff in the admin panel only (SPEC-010 §10:
"No seeder is required"). The existing `RoleSeeder` already provides the ADMIN
and TRAINER roles required by management.

---

## 6. Data Changes

### Migrations

Four new migrations in the existing timestamp sequence (next numbers after
`2026_08_15_000008_create_exercises_table.php`):

1. **`2026_08_15_000009_create_routines_table.php`**:

   | Column | Type | Constraints |
   | --- | --- | --- |
   | `id` | bigint | PK |
   | `name` | string(255) | NOT NULL (BR-010, AR-05; NOT unique) |
   | `status` | string | NOT NULL, default `'draft'` (BR-002, AR-01); string with model constants, NOT a DB enum (ADR-004) |
   | `version_number` | unsignedInteger | NOT NULL, default `1` (BR-001, AR-02) |
   | `replaces_id` | FK `routines.id` | nullable, `restrictOnDelete` (BR-001, AR-02; `null` for version 1) |
   | `created_by` | FK `users.id` | NOT NULL, `restrictOnDelete` (BR-011, AR-08) |
   | `created_at` / `updated_at` | timestamp | timestamps |

   - Index on `name` (FR-002 search) and index on `status` (FR-002 filter).
     No unique index on `name` (AR-05) and no unique index on the lineage
     `version_number` (the chain is not a column; the invariant is enforced by
     the `VersionRoutine` action, BR-001).
   - `replaces_id` forms the lineage chain: each version points at the version
     it replaces; the lineage head is the version no other version replaces
     (AR-02, `scopeLineageHeads`).
   - No DB CHECK constraints: status values and version-number positivity are
     enforced by form/action validation (framework-validation-first convention,
     ADR-003; same as SPEC-003/006/008/009).

2. **`2026_08_15_000010_create_routine_days_table.php`**:

   | Column | Type | Constraints |
   | --- | --- | --- |
   | `id` | bigint | PK |
   | `routine_id` | FK `routines.id` | NOT NULL, `restrictOnDelete` (BR-001, BR-008) |
   | `day_number` | unsignedInteger | NOT NULL (BR-003, BR-010) |
   | `created_at` / `updated_at` | timestamp | timestamps |

   - UNIQUE index on `(routine_id, day_number)` — enforces ERR-002 (duplicate
     day numbers in a version) at the database level (spec-requested, SPEC-010
     §10).
   - `restrictOnDelete` on `routine_id`: a routine version is never deleted
     (archiving instead, BR-008); the guard blocks a hypothetical delete from
     cascading into prescription data.

3. **`2026_08_15_000011_create_routine_exercises_table.php`**:

   | Column | Type | Constraints |
   | --- | --- | --- |
   | `id` | bigint | PK |
   | `routine_day_id` | FK `routine_days.id` | NOT NULL, `cascadeOnDelete` (see note) |
   | `exercise_id` | FK `exercises.id` | NOT NULL, `restrictOnDelete` (BR-006, AC-18) |
   | `set_number` | unsignedInteger | NOT NULL (BR-004, BR-010) |
   | `target_reps` | unsignedInteger | NOT NULL (BR-010, AR-06) |
   | `target_weight` | decimal(6,2) | nullable (BR-010, AR-06; absent/zero = bodyweight; precision is a technical detail with no business difference — the ADR-003 decimal convention) |
   | `rest_seconds` | unsignedInteger | nullable (BR-010, AR-06) |
   | `notes` | text | nullable (BR-010, AR-06) |
   | `created_at` / `updated_at` | timestamp | timestamps |

   - UNIQUE index on `(routine_day_id, set_number)` — enforces ERR-002
     (duplicate set numbers in a day) at the database level (spec-requested).
   - Index on `exercise_id` (FR-008 exercise usage; future SPEC-011 lookups by
     exercise).
   - **`cascadeOnDelete` on `routine_day_id` — the single deliberate deviation
     from the repo's `restrictOnDelete` default** (see §10): removing a day
     from a `draft` is a required editing operation (FR-005, FR-008, AC-6),
     and a day row's set rows must go with it. This cascade can only ever run
     during draft in-place editing — archived versions are never edited or
     deleted, so historical prescription data is never affected (BR-008).
     `exercise_id` keeps `restrictOnDelete` so a deactivated exercise's
     prescription rows survive (BR-006, AC-18; exercises are never hard-deleted
     anyway, SPEC-009 BR-008).
   - No DB CHECK constraints: positive reps / non-negative weight / non-
     negative rest are enforced by form/action validation (ADR-003).

4. **`2026_08_15_000012_create_routine_assignments_table.php`**:

   | Column | Type | Constraints |
   | --- | --- | --- |
   | `id` | bigint | PK |
   | `client_id` | FK `clients.id` | NOT NULL, `restrictOnDelete` (BR-007, BR-008) |
   | `routine_id` | FK `routines.id` | NOT NULL, `restrictOnDelete` (BR-007; the assigned VERSION) |
   | `assigned_at` | timestamp | NOT NULL (BR-007, AR-03; set by `AssignRoutine`, defaults to now) |
   | `is_active` | boolean | NOT NULL, default `true` (BR-007, AR-03) |
   | `created_at` / `updated_at` | timestamp | timestamps |

   - Index on `(client_id, is_active)` — the one-active-assignment check and
     per-client history (FR-011, AR-03; spec-requested index on
     `client_id`).
   - Index on `(routine_id, is_active)` — "which clients are currently assigned
     to this version" (FR-011; spec-requested index on `routine_id`).
   - The one-active-assignment invariant (AR-03) is enforced at the application
     level (in `AssignRoutine`, transactional deactivate + create) per the
     spec ("enforced at the application level", SPEC-010 §10). No partial
     unique index / raw SQL constraint is added (ADR-003 framework-first
     convention; a partial unique index on `(client_id) WHERE is_active` is
     the documented optional hardening if a non-validated write path ever
     appears).

No existing migration is modified. The `users`, `roles`, `role_user`,
`clients`, `plans`, `memberships`, `turnos`, `attendances` and `exercises`
tables are reused as-is. The `exercises` table gains no column; the only added
reference direction is `routine_exercises.exercise_id` → `exercises.id` (the
SPEC-009 BR-011 consumption point).

### Relationships

```text
users.id ← routines.created_by (audit only, BR-011)
routines.id ←→ routines.replaces_id (self, nullable; lineage chain, AR-02)
routines.id ← routine_days.routine_id (1:N)
routine_days.id ← routine_exercises.routine_day_id (1:N)
exercises.id ← routine_exercises.exercise_id (SPEC-009 BR-011 consumption)
clients.id ← routine_assignments.client_id (1:N)
routines.id ← routine_assignments.routine_id (1:N; the assigned VERSION)
```

No relationship is added to `Exercise` (the consuming module owns the
reference direction — the SPEC-009 §6 boundary discipline).

### Data lifecycle

- **Created:** routine versions (draft v1 on create; new active versions on
  copy-on-edit), routine days, set-level routine exercise rows, and active
  assignments. Creating/editing/activating/assigning a routine never creates,
  modifies or deletes any workout-log record (BR-005, AC-17).
- **Modified:** a `draft` version's name, days and set rows in place (FR-005);
  a version's status `draft → active` on activation (FR-007) and
  `active → archived` when superseded by a new version (FR-006, BR-002); an
  assignment's `is_active` on supersession (AF-002) and on unassignment
  (AF-006). An `active` version's content is NEVER mutated: the only edit path
  creates a new version (BR-001, D-12 option 3).
- **Deleted:** no standalone delete operation exists (BR-008, ERR-009). The
  only row deletions in the system are working-copy deletions that occur while
  editing a `draft` (a removed day's rows go with it via the day→row cascade;
  removed rows in kept days are deleted by the edit sync). Archived versions
  and their rows, and assignment history, are never deleted. No delete policy
  is registered for any Routine entity.

---

## 7. External Integrations

None.

SPEC-010 touches no external service. No notification/email is sent by any
routine operation (client notifications depend on SPEC-013 infrastructure and
are out of scope, SPEC-010 §12). Prescription display reads exercise attributes
live from the local catalogue (AR-04); no per-prescription snapshot or
external exercise data source exists.

---

## 8. Testing Strategy

Pest (PHPUnit) feature tests following the existing conventions
(`tests/Pest.php` helpers `role()`, `userWithRoles()`; `RefreshDatabase`;
Livewire component testing as used in `ExerciseManagementTest` /
`TurnoManagementTest`). The spec's own test plan (SPEC-010 §11) is mapped
below; new factories mirror the `ExerciseFactory` / `TurnoFactory` shape.

New factories (`database/factories/`):

- `RoutineFactory`: `name` → `fake()->words(3, true)`, `status` →
  `Routine::STATUS_DRAFT`, `version_number` → `1`, `replaces_id` → `null`,
  `created_by` → `User::factory()` (the `created_by` FK is required).
- `RoutineDayFactory`: `routine_id` → `Routine::factory()`, `day_number` → a
  per-version-unique number (the Developer may sequence per test).
- `RoutineExerciseFactory`: `routine_day_id` → `RoutineDay::factory()`,
  `exercise_id` → `Exercise::factory()`, `set_number` → `1`,
  `target_reps` → `10`, `target_weight` → `null`, `rest_seconds` → `null`,
  `notes` → `null`.
- `RoutineAssignmentFactory`: `client_id` → `Client::factory()`,
  `routine_id` → `Routine::factory()`, `assigned_at` → `now()`,
  `is_active` → `true`.

**Routine CRUD, lifecycle and versioning (AC-1..AC-9, AC-14, AC-16, AC-18;
FR-001..FR-008, FR-012)**
- `tests/Feature/Admin/RoutineManagementTest.php` (Livewire component tests
  against `CreateRoutine`, `ListRoutines`, `ViewRoutine`, `EditRoutine`):
  - ADMIN or TRAINER can create a routine with a name; version 1 is persisted
    as `draft` with `created_by` set and no days (AC-1, FR-001, BR-002,
    BR-011, AR-01, AR-05).
  - ADMIN or TRAINER can add ordinal days and set-level prescription rows
    referencing exercises; the rows persist with set number, target reps,
    target weight, rest seconds and notes (AC-2, FR-008, FR-003, BR-003,
    BR-004).
  - Duplicate day numbers in a version and duplicate set numbers in a day are
    rejected with validation errors (AC-3, ERR-002, BR-010).
  - Activating a draft with zero days is rejected; activating a draft where
    any day has zero set rows is rejected; activating a valid draft makes it
    `active` (AC-4, FR-007, ERR-003, ERR-004, BR-002).
  - A new set row referencing an `inactive` exercise is rejected; one
    referencing an `active` exercise is accepted (AC-5, BR-006, AR-04).
  - Invalid prescription values — missing/zero/negative target reps, negative
    target weight, negative rest seconds — are rejected (ERR-005, BR-010).
  - Editing a `draft` changes it in place and does not create a new version
    (AC-6, FR-005, BR-002).
  - Editing an `active` routine creates a new version with the changes applied,
    increments the version number, archives the previous version, and leaves
    assignments untouched (AC-7, FR-006, BR-001, BR-002, D-12 option 3).
  - After AC-7, clients assigned to the previous version remain assigned to it
    until staff explicitly reassign them (AC-8, FR-010, AF-003).
  - The version history shows every version with its number, status and
    creator; archived versions are fully readable but cannot be edited,
    versioned again, or assigned (AC-9, FR-004, AF-004, ERR-006 — asserted
    server-side via `RoutineResource::canEdit` and the action visibility).
  - Search by name and filter by status; lists and detail show status and
    version number (AC-14, FR-002, FR-012).
  - No delete action exists for routines, days, set rows or assignments
    (AC-16, ERR-009, BR-008 — assert no `delete` action and no delete ability).
  - Deactivating or editing an exercise never creates, modifies or deletes any
    routine, day or set row; existing set rows keep referencing the exercise
    (AC-18, BR-006, AR-04 — deactivate an exercise, assert the prescription
    rows are unchanged and still displayed).
  - Creating, editing, activating or versioning a routine touches only the
    routine tables — no workout-log / user / client / exercise record is
    created or modified (AC-17, BR-005; assert only the expected tables gained
    rows).

**Assignment (AC-10..AC-13, AC-8; FR-009..FR-011)**
- `tests/Feature/Admin/RoutineAssignmentTest.php` (Livewire + Action-level):
  - ADMIN or TRAINER can assign an `active` routine version to one or more
    clients; the assignment records client, version, date and active flag
    (AC-10, FR-009, BR-007).
  - Assigning to a client who already has an active assignment supersedes it:
    the previous active row is deactivated and a new active row is created; no
    assignment record is deleted (AC-11, AF-002, BR-007, AR-03).
  - Assigning a `draft` or `archived` version is rejected (AC-12, ERR-008,
    BR-007).
  - Reassigning a client to another version and unassigning without replacement
    preserve history (AC-13, FR-010, AF-006, BR-008).
  - Clients remain on the old version after an edit creates a new version until
    explicitly reassigned (AC-8).
  - Assignment operations never touch prescription rows (BR-007 — assert day
    and set-row counts are unchanged after assign/unassign).

**Authorization / Policy (AC-15, AC-16, AC-19; ERR-007)**
- `tests/Feature/Admin/RoutinePolicyTest.php`:
  - ADMIN and TRAINER can `viewAny`/`view`/`create`/`update` routines and
    assignments (AC-15, BR-009, AR-08 — the full management set).
  - CLIENT and anonymous users cannot create, list, view, edit, activate,
    version or assign routines — 403 on `/admin/routines` routes and no
    navigation; guests are redirected to `/login` (AC-15, ERR-007, BR-009;
    asserted server-side, AGENTS.md §17).
  - A multi-role ADMIN + CLIENT or TRAINER + CLIENT user can manage routines
    in the admin panel (AC-19, SPEC-001 BR-002).
  - `RoutinePolicy` and `RoutineAssignmentPolicy` have no `delete` ability for
    anyone (AC-16, ERR-009, BR-008).

**Unit**
- `tests/Unit/RoutineTest.php`:
  - Status constants and the default `draft` on creation (BR-002, AR-01).
  - Version-lineage invariants: `version_number` increments and `replaces_id`
    chains per lineage; `lineageIds()` / `lineage()` return the full lineage
    from any version; `scopeLineageHeads()` returns the latest version of each
    lineage (BR-001, AR-02, FR-002, FR-004).
  - `activate()` validation: throws `DomainException` on zero days (ERR-003),
    on an empty day (ERR-004), and when not `draft`; succeeds on a valid draft
    (FR-007, BR-002).
  - Day/set numbering uniqueness (DB unique indexes reject duplicates —
    ERR-002) and the one-active-assignment invariant (AR-03 — the
    `AssignRoutine` supersession leaves exactly one active row per client).
  - A copied version preserves set rows referencing now-inactive exercises
    (BR-006, AR-04 — `VersionRoutine` keeps the reference; only new rows must
    be active).
  - The boundary rule: creating, editing, activating or assigning a routine
    creates no workout-log record (BR-005, AC-17).
  - `RoutineAssignment::deactivate()` only from an active row (FR-010, AF-006);
    `Client::currentRoutine()` returns the active version's routine or `null`
    (FR-011, AR-03).

All authorization assertions are server-side (AGENTS.md §17); no test relies
on frontend visibility.

---

## 9. Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Assumptions AR-01..AR-09 are unconfirmed (SPEC-010 §14.1). | If the PO changes them, parts of the design change (e.g., AR-03 allows several active routines per client; AR-02 creates new versions as drafts; AR-08 restricts TRAINER). | Keep implementation isolated: `RoutinePolicy` / `RoutineAssignmentPolicy` rules, the model methods, the Actions, the four-table schema and the form rules are the only touch points. Block Implementation until the PO confirms the §12 items (per spec: "before Implementation (or at latest before Review)"). |
| OQ-01 (AR-03): at most one active routine per client vs several. | SPEC-011 depends on this. The supersession logic, the `currentRoutine()` helper and the "one active assignment" tests change. | Design assumes at most one (AR-03), consistent with the D-12 option 3 reassignment model; the invariant lives only in `AssignRoutine` + the relation-manager display, so a multi-active change touches one Action and the helpers. |
| OQ-02 (AR-02): new versions created `active` immediately vs draft-then-publish. | If draft-then-publish is chosen, `VersionRoutine` creates the copy as `draft` and the previous version stays `active` longer; the copy-on-edit flow gains a publish step. | Design assumes immediate `active` (AR-02); the change is confined to `VersionRoutine`'s status assignment and the flow tests. |
| `cascadeOnDelete` on `routine_exercises.routine_day_id` (first in the repo). | A future delete path could cascade into prescription rows; a code bug during draft editing could drop rows. | The cascade is reachable only through draft in-place editing (archived versions are never edited/deleted, BR-008); feature tests assert day removal drops exactly that day's rows; exercise_id and all other FKs keep `restrictOnDelete`. Documented in §10 as a deliberate deviation with rationale. |
| Draft editing removes days/rows (FR-005, FR-008) vs BR-008/ERR-009 "no hard deletion". | A strict reading of BR-008/ERR-009 could suggest draft rows must persist forever, contradicting FR-005/FR-008 which require removal. | Interpretation documented in §10/§11 and flagged for PO confirmation (§12): "no delete operation" means no standalone delete feature/policy/UI and no deletion of archived/historical data; removing a day/row while editing a working-copy `draft` is the mechanism of "remove", not a delete operation. |
| Filament nested Repeaters / RepeatableEntry API differences across versions. | Day+set-row editing and the version-history display depend on nested repeater/infolist capabilities. | The functional requirements (FR-003, FR-005, FR-008) are what the tests verify; the Developer chooses the exact component API (nested relationship Repeaters; if the installed version cannot nest, a manual sync in the page's `afterSave`/`handleRecordUpdate` is the documented fallback). |
| Version-history presentation (infolist RepeatableEntry vs RelationManager table). | FR-004 requires showing every lineage version; the component choice is presentation. | `Routine::lineage()` provides the data; either presentation satisfies the ACs; the Developer verifies the installed Filament API. |
| Eloquent `decimal:2` cast returns strings (ADR-003 precedent). | `target_weight` form/display handling could trip on string vs numeric. | Same trade-off as Plan price; the `numeric` form rules accept numeric strings; display formatting is a presentation detail. |
| FR-011 client-side read is ADMIN-only in this placement (ClientResource is ADMIN-only per SPEC-002 ClientPolicy). | A TRAINER cannot open ClientResource to see a client's current routine; the parenthetical of FR-011 is partially ADMIN-only. | The routine-side **Assigned clients** relation manager (TRAINER-accessible) covers the same data; flagged for PO confirmation (§12) rather than silently widening `ClientPolicy`. |
| No DB CHECK / partial unique index on the one-active-assignment invariant. | A non-validated write path could create two active assignments per client. | All MVP write paths go through `AssignRoutine` (transactional); unit tests assert the invariant; a partial unique index `(client_id) WHERE is_active` is the documented optional hardening (ADR-003 stance). |
| SPEC-011 depends on this model. | If SPEC-011 needs a different prescription shape, the schema is already execution-friendly by design (prescription-only rows, stable ids). | The four tables are standalone and version-stable; SPEC-011 adds the workout-log tables and the reference direction from the consuming module (BR-005, C-10). |

---

## 10. Alternatives Considered

1. **Versioning mechanism: copy-on-edit with `replaces_id` chain vs immutable
   version snapshots vs `routine_group_id` column** — The mechanism is
   pre-approved by the PO (D-12 option 3; AR-02 fixes "copy-on-edit with a
   version lineage"), so this is NOT an open Architect choice. The spec
   recommends `replaces_id` and permits an equivalent `routine_group_id`; the
   self-referential `replaces_id` chain is chosen because it states the
   lineage semantics directly (each version points at the version it replaces),
   needs no extra column or group seeding, and `lineageIds()` walks it in
   PHP (lineages are short). No ADR is required: the business decision is
   documented in SPEC-010 AR-02 / analyst-pass-001 §D-12.
2. **Versioning logic location: `App\Actions\VersionRoutine` vs a
   `Routine::createVersion()` model method** — Copy-on-edit is multi-entity
   and transactional (new Routine + new days + new rows + archiving the old
   version) and rule-bearing (status guards, active-exercise rule for new
   rows, lineage numbering). This matches the Actions precedent
   (`ProvisionClientUser`, `RenewMembership`) better than a model method —
   AGENTS.md §9 reserves Actions for "important business operations" and
   model methods for single-record transitions (the `Turno` / `Membership`
   precedent). **`App\Actions\VersionRoutine` chosen.**
3. **Assignment logic location: `App\Actions\AssignRoutine` (spec-named) vs a
   model method** — The spec's own dependency list names `AssignRoutine` as
   the example action (SPEC-010 §13, ARCHITECTURE §7); it is multi-client,
   transactional and rule-bearing (ERR-008 guard, supersession). **Action
   chosen.** Unassignment, by contrast, is a single-record transition and is a
   model method `RoutineAssignment::deactivate()` (the `Turno` precedent).
4. **Activation: model method `Routine::activate()` vs a Filament-only
   action** — The content invariants (≥1 day, ≥1 set per day, ERR-003/ERR-004)
   depend on the persisted days/rows and are re-checked on every activation
   path; a model method is the single enforcement point (the `Turno::cancel()`
   precedent for state rules with preconditions). **Chosen.**
5. **`cascadeOnDelete` vs `restrictOnDelete` on `routine_exercises.
   routine_day_id`** — The repo default is `restrictOnDelete` (memberships,
   attendances), but those modules have NO delete path at all. Routines DO
   have a legitimate row-deletion path: removing a day from a `draft` is a
   required editing operation (FR-005, FR-008, AC-6) and its set rows must go
   with it. With `restrictOnDelete`, the edit sync would have to delete child
   rows before the day on every removal (fragile ordering, incompatible with
   Filament's nested relationship Repeater auto-sync). The cascade is
   unreachable for archived data (archived versions are never edited or
   deleted, BR-008). **`cascadeOnDelete` chosen with the documented rationale;
   every other Routine FK keeps `restrictOnDelete`.**
6. **Draft editing: Filament nested relationship Repeaters vs manual sync in
   the page** — Repeaters with `->relationship('days')` / nested
   `->relationship('exercises')` persist the draft's in-place changes
   automatically (Laravel/Filament conventions first, AGENTS.md §10). If the
   installed Filament version cannot nest relationship Repeaters, the fallback
   is an explicit sync in `EditRoutine` (delete removed rows, update/create
   others) — same business behavior. **Relationship Repeaters preferred.**
7. **Active-version editing UX: override `EditRoutine::handleRecordUpdate` vs
   a separate "Create new version" form** — FR-006's UX is "staff edit the
   active routine"; overriding the save hook keeps the natural Edit flow while
   delegating to `VersionRoutine` (and prevents the default in-place save from
   mutating the active version, BR-001). A separate versioning form would add
   a second edit surface. **`handleRecordUpdate` override chosen.**
8. **Version history display: infolist `RepeatableEntry` bound to
   `Routine::lineage()` vs a `VersionsRelationManager` table** — The lineage
   is a computed chain, not a single Eloquent relationship, so a RelationManager
   would need a custom query; an infolist entry fed by `lineage()` is simpler
   and robust. Presentation choice; the ACs (FR-004: every version with
   number/status/creator) are what count. **RepeatableEntry preferred,
   RelationManager table acceptable.**
9. **Assignment UI: manage from `RoutineResource` (Assign to clients header
   action + Assigned clients relation manager) vs from `ClientResource`** —
   The natural flow after versioning is "open the new version, assign the
   clients still on the old one" (D-12 option 3, FR-010); one management
   surface on the routine side is minimal. The client side gets only read
   displays (current routine entry + read-only history relation manager,
   mirroring `MembershipsRelationManager`). **Routine-side management chosen.**
10. **`RoutineAssignmentPolicy` vs reusing `RoutinePolicy` for the relation
    managers** — Filament relation managers authorize against the RELATED
    model's policy (the `MembershipsRelationManager` precedent), so the
    Assigned-clients relation manager needs a policy on `RoutineAssignment`.
    A thin dedicated policy keeps the Filament convention working; no delete,
    no create (creation goes through the Action). **Chosen.**
11. **Status as string with model constants vs PHP enum / PostgreSQL enum** —
    The repo has no PHP enum anywhere; every fixed set is a class constant
    validated at the framework level (`Turno::STATUS_*`, `Role::ADMIN`,
    ADR-001/ADR-004). A native DB enum adds raw SQL and migration-heavy value
    changes. **String + constants chosen** (the ADR-004 convention).
12. **`target_weight` precision: `decimal(6,2)` vs `decimal(10,2)` vs
    integer grams** — The spec delegates the technical precision with no
    business difference (SPEC-010 §10); `decimal(6,2)` (0..9999.99 kg) covers
    any gym prescription; the ADR-003 `decimal` convention is followed. No
    integer-minor-units conversion layer (same reasoning as ADR-003).

**No new ADR is required for this Specification.** Every decision above is an
incremental application of the established ADRs — ADR-001 (role/authorization
foundation), ADR-002 (module boundary discipline), ADR-003 (validation-first
stored representation), ADR-004 (status-as-string / stored-state precedent) —
to a greenfield module, plus the PO-pre-approved gates D-10 / D-11 / D-12
whose mechanism (copy-on-edit with reassignment) is a business decision already
recorded in SPEC-010 AR-02, not an Architect choice. No genuinely new
architectural pattern is introduced.

---

## 11. Decision

Use the established SPEC-001/002/003/004/006/009 conventions throughout:

- **Persistence:** four new tables — `routines` (one row per version: `name`
  NOT unique, `status` string default `draft`, `version_number` default 1,
  nullable self-referential `replaces_id`, `created_by` FK; indexes on `name`
  and `status`), `routine_days` (`routine_id` FK restrict, `day_number`;
  UNIQUE `(routine_id, day_number)`), `routine_exercises` (set-level rows:
  `routine_day_id` FK cascade — the documented exception for draft editing,
  `exercise_id` FK restrict, `set_number`, `target_reps`, `target_weight`
  decimal(6,2) nullable, `rest_seconds` nullable, `notes` nullable; UNIQUE
  `(routine_day_id, set_number)`, index on `exercise_id`), and
  `routine_assignments` (`client_id` FK restrict, `routine_id` FK restrict,
  `assigned_at`, `is_active` default true; indexes on `(client_id, is_active)`
  and `(routine_id, is_active)`). No DB enums, no DB CHECK constraints, no
  partial unique index (application-level one-active-assignment invariant per
  the spec), no seeder. The existing schema is untouched; the only new
  reference direction into an existing table is
  `routine_exercises.exercise_id` → `exercises.id` (SPEC-009 BR-011
  consumption).
- **Versioning (D-12 option 3, AR-02):** copy-on-edit via
  `App\Actions\VersionRoutine` — a transactional deep copy of the version's
  days and set rows into a new Routine (`version_number + 1`, `replaces_id`
  chain, status `active`, `created_by`), archiving the previous version;
  assignments untouched until staff explicitly reassign via
  `App\Actions\AssignRoutine`. Draft editing is in-place (FR-005); archived
  versions are read-only (ERR-006).
- **Assignment (BR-007, AR-03):** `App\Actions\AssignRoutine` — only `active`
  versions assignable (ERR-008), transactional supersession of a client's
  previous active assignment (history preserved), new active row with
  `assigned_at`; unassignment via `RoutineAssignment::deactivate()`;
  prescription rows never touched.
- **Models:** `Routine` (status constants, `isDraft/isActive/isArchived`,
  `activate()`, `lineage()/lineageIds()`, `scopeActive`, `scopeLineageHeads`,
  relationships days/replaces/replacedBy/creator/assignments), `RoutineDay`
  (day_number, routine/exercises), `RoutineExercise` (set prescription,
  routineDay/exercise), `RoutineAssignment` (client/routine, `deactivate()`);
  `Client` gains `routineAssignments()` + `currentRoutine()` (additive).
- **Authorization:** `RoutinePolicy` and `RoutineAssignmentPolicy`
  (viewAny/view/create/update = ADMIN **or** TRAINER, no delete) on top of
  `User::hasAnyRole` (ADR-001) — the `TurnoPolicy` / `ExercisePolicy` pattern
  (BR-009, AR-08); state rules enforced by the model methods, the Actions and
  `RoutineResource::canEdit`, not by the Policies.
- **UI:** Filament `RoutineResource` with list (lineage heads) / create (name
  only) / view (detail + version history + Activate + Assign to clients +
  Assigned-clients relation manager) / edit (name + nested days/sets
  Repeaters; `handleRecordUpdate` delegates to `VersionRoutine` for active
  records; `canEdit` blocks archived); search by name, status filter, status
  badge and version number everywhere (FR-012); no delete actions (BR-008);
  `navigationGroup = 'Training'`. `ClientResource` gains the current-routine
  entry and a read-only routine-assignment history relation manager (FR-011).
- **Validation:** Filament form rules — `name` required/max 255 (not unique,
  AR-05); `day_number`/`set_number` integer min 1 with duplicate-closure rules
  (ERR-002); `exercise_id` required + `exists` + active-only-for-new-rows
  closure (ERR-001, BR-006, AC-5); `target_reps` required integer min 1
  (ERR-005); `target_weight` nullable numeric min 0 (ERR-005, AR-06);
  `rest_seconds` nullable integer min 0 (ERR-005, AR-06); `notes` nullable.
  Structural invariants re-checked in `VersionRoutine::validate()` (defense in
  depth); activation content rules in `Routine::activate()` (ERR-003,
  ERR-004); assignment rules in `AssignRoutine` (ERR-008, AR-03). No separate
  Form Requests: the repo convention is resource-level validation (no HTTP
  controllers exist for admin CRUD).
- **No events, no jobs, no new routes, no new seeders, no external
  integrations, no new ADR.**
- **Deferred (reserved for later Specifications):** workout logs referencing
  the prescription rows (SPEC-011, BR-005/C-10), client visibility of the
  assigned routine (SPEC-011/013, AR-08), and any consumer of the
  one-active-assignment invariant (SPEC-011, OQ-01).

---

## 12. Pending PO Confirmations

These items are carried from SPEC-010 and must be confirmed before
Implementation (or at latest before Review). This design does not silently
resolve them.

### Assumptions (SPEC-010 §14.1)

| ID | Assumption | Impact on this design |
| --- | --- | --- |
| AR-01 | Lifecycle `draft` → `active` → `archived`; new routines are created `draft` (empty allowed); archived is read-only and not assignable but existing assignments remain valid; no manual archive action. | `routines.status` string default `draft`; `Routine::activate()`; `VersionRoutine` archives the superseded version; `canEdit` + action visibility block archived edits/assignments. |
| AR-02 | Versioning = copy-on-edit with a version lineage (`replaces_id` chain); editing an `active` routine creates a new Routine, `version_number + 1`, status `active`, previous becomes `archived`. | `replaces_id` column; `VersionRoutine` action; `lineage()` / `scopeLineageHeads`; AC-7/AC-8 tests. OQ-02 open (draft-then-publish alternative). |
| AR-03 | A client has at most one active assignment; assigning supersedes; only `active` versions assignable; a version may be assigned to many clients. | `routine_assignments.is_active`; `AssignRoutine` supersession; `Client::currentRoutine()`; indexes. OQ-01 open (SPEC-011 depends). |
| AR-04 | New set rows may only reference `active` exercises; existing rows are never modified when an exercise is deactivated/edited; a version copy preserves rows referencing inactive exercises; display reads current catalogue attributes. | Form closure rule + `VersionRoutine` validation (new rows active-only); `exercise_id` FK restrict; AC-5/AC-18 tests. OQ-07 open. |
| AR-05 | Routine name is required free text, NOT unique. | No unique index on `routines.name`; form rule required/max 255. |
| AR-06 | Prescription value rules: reps required positive int; weight optional decimal ≥ 0 (absent/zero = bodyweight); rest optional int ≥ 0; notes optional. | Column types + form rules (ERR-005). OQ-05/OQ-06 open (max limits; bodyweight representation). |
| AR-07 | Day/set numbers positive ints, unique within parent, gaps allowed; ≥1 day and ≥1 set per day to activate; drafts may be empty. | Unique indexes `(routine_id, day_number)` / `(routine_day_id, set_number)`; `Routine::activate()` content rules (ERR-003/004). OQ-03 open (renumbering). |
| AR-08 | Routine management/assignment = ADMIN and TRAINER (no ownership filter); CLIENT has no direct routine access; `created_by` is audit-only. | `RoutinePolicy` / `RoutineAssignmentPolicy` ADMIN+TRAINER; `created_by` FK + display; ERR-007. OQ-08 open (client-visibility timing). |
| AR-09 | No hard deletion anywhere in the Routines module; versions archived, assignments deactivated; history preserved. | No delete policies/actions; draft day/row removal is the documented working-copy exception (see design note below). |

### Open questions (SPEC-010 §14.2) relevant to this design

| ID | Question | Impact on this design |
| --- | --- | --- |
| OQ-01 | May a client hold more than one active routine? | Design assumes at most one (AR-03); changing it touches `AssignRoutine` + `currentRoutine()` + tests. **SPEC-011 depends.** |
| OQ-02 | New version created `active` immediately or as a `draft` to publish later? | Design assumes immediate `active` (AR-02); draft-then-publish touches `VersionRoutine` status + flow tests. |
| OQ-03 | Renumber days/sets to be contiguous after removals, or allow gaps? | Design allows gaps (AR-07); renumbering would add a UI behavior on removal, no schema change. |
| OQ-04 | May a `draft` be assigned? | Design assumes NO (only `active`, AR-03/ERR-008). |
| OQ-05 | Maximum limits (days per routine, sets per day, reps, weight, rest)? | Design imposes none; additive validation only. |
| OQ-06 | Is `target_weight = 0`/absent the right bodyweight representation? | Design assumes absent/zero = bodyweight (AR-06); an explicit flag would be an additive column. |
| OQ-07 | New rows restricted to `active` exercises, and how existing prescriptions display a deactivated exercise? | Design assumes active-only for new rows and live display of the exercise (AR-04). |
| OQ-08 | When does a CLIENT see their assigned routine? | Deferred to SPEC-011/013 (AR-08); no client path here. |
| OQ-09 | Should staff views flag clients still on an archived version, or is the passive "view assigned clients" enough? | Design implements the passive view (FR-011, Assigned-clients relation manager). |
| OQ-10 | Is `created_by` wanted as a displayed audit field? | Design records it on each version and shows it in detail/history (FR-003, FR-004). |

### Additional design notes flagged for confirmation

- **Draft day/row removal deletes working-copy rows (interpretation of
  BR-008 / ERR-009 vs FR-005 / FR-008).** FR-005 and FR-008 explicitly require
  staff to be able to "add/remove days and set rows" when editing a draft, and
  AC-6 requires in-place editing; the only mechanism for "remove" is deleting
  the working-copy rows. BR-008/ERR-009 are therefore read as "no standalone
  delete operation/feature/policy and no deletion of archived or historical
  data", not as "draft rows are immutable". The design implements removal via
  `cascadeOnDelete` on `routine_exercises.routine_day_id` (§10 #5) and never
  deletes archived versions or assignment history. Flagged for confirmation
  because a stricter reading would make FR-005/FR-008 unimplementable.
- **FR-011 client-side read is ADMIN-only in this placement.** The client-side
  "which routine version is currently active" display lives in
  `ClientResource`, which is ADMIN-only per SPEC-002 `ClientPolicy`. TRAINERs
  obtain the same data through the routine-side **Assigned clients** relation
  manager. Flagged rather than silently widening `ClientPolicy`.
- Status is stored as a string column with model constants, not a PostgreSQL
  or PHP enum (ADR-004 precedent; same as `turnos.status`).
- The one-active-assignment invariant is enforced at the application level in
  `AssignRoutine` (per the spec's explicit "enforced at the application
  level"); a partial unique index is documented optional hardening only.
- `created_by` on `routines` and the assignment's `assigned_at` are set by the
  create page / Actions, never by user-entered form fields.

---

## 13. Related Documents

- Specification: `docs/specs/SPEC-010.md` (FR-001..FR-012, BR-001..BR-011,
  ERR-001..ERR-009, AC-1..AC-19, AR-01..AR-09, OQ-01..OQ-10)
- Architecture decisions: `docs/adr/ADR-001.md` (role/authorization
  foundation), `docs/adr/ADR-002.md` (module boundary discipline),
  `docs/adr/ADR-003.md` (validation-first representation / decimal
  convention), `docs/adr/ADR-004.md` (status-as-string / stored-state
  precedent)
- Architecture: `docs/architecture/SPEC-001.md`, `docs/architecture/SPEC-002.md`
  (Client model / ADMIN-only ClientPolicy), `docs/architecture/SPEC-003.md`
  (Plan lifecycle / boolean-flag precedent), `docs/architecture/SPEC-004.md`
  (Membership status machine / `restrictOnDelete` precedent),
  `docs/architecture/SPEC-006.md` (ADMIN+TRAINER `TurnoPolicy`, model state
  transitions, `scopeActive` precedents), `docs/architecture/SPEC-008.md`
  (FK / index conventions, `recorded_by` user-FK precedent),
  `docs/architecture/SPEC-009.md` (`Exercise` model, `scopeActive()`, the
  `routine_exercises.exercise_id` reference direction), `ARCHITECTURE.md`
  (§7 Actions — `AssignRoutine`, §8 Models, §10 Events, §12 Authorization,
  §16 Routines: prescription vs execution, §20 simplest correct architecture)
- Product: `docs/product/product-definition-v0.1.md` (Routines business area;
  "Are routines versioned over time?")
- Domain: `docs/domain/domain-model-v0.1.md` (§Routine, §RoutineDay,
  §RoutineExercise, §Exercise, §WorkoutLog; C-09, C-10, C-11)
- Requirements analysis: `docs/requirements/analyst-pass-001.md` (§5.12
  Routines, D-10, D-11, D-12, C-09, C-10, C-11, E-07)
- Workflow state: `docs/sdd/state.yaml` (SPEC-010 `spec_ready`, current phase
  `architecture`; NIGHT MODE pre-approval D-10/D-11/D-12; SPEC-001/002/009
  completed; SPEC-011 depends on SPEC-010)
- Development rules: `AGENTS.md`
