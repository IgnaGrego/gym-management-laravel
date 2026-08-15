# Architecture — SPEC-009

## 1. Feature

Exercise Catalogue for the gym management system:

- an **exercise** is a single exercise that can be included in routines
  (domain-model §Exercise; confirmed decision C-09), with the attributes
  approved by gate **D-20 option 2** (pre-approved under NIGHT MODE): **name,
  muscle group, equipment, plus instructions/video and difficulty**
  (SPEC-009 §1, EX-01);
- staff — **ADMIN and TRAINER** — can create, list/search/filter, view, edit,
  activate and deactivate exercises from the admin panel (D-20 option 2,
  BR-009, EX-08);
- each exercise records: `name` (unique among all exercises regardless of
  status), required `muscle_group` (fixed set, BR-004), optional `equipment`,
  optional `difficulty` (fixed set, BR-005), optional `instructions` (plain
  text, EX-10), optional `video_url` (external http/https URL, BR-006), and an
  `is_active` active/inactive status defaulting to active (BR-007, EX-07 —
  mirroring the Plan lifecycle, SPEC-003 AP-02);
- exercise records are never hard-deleted; deactivation is used instead
  (BR-008, the preservation pattern of AGENTS.md §12 / SPEC-003 BR-004 /
  SPEC-006 BR-009);
- the catalogue is **routine-friendly**: it is a standalone catalogue (no
  foreign keys, no dependency on clients/memberships), it exposes the active
  set via `Exercise::scopeActive()` for future routine prescription (SPEC-009
  §10), and the routine-side consequences of deactivation/editing are
  explicitly deferred to SPEC-010 (BR-011, the same boundary discipline as
  SPEC-006 BR-013 for bookings);
- client isolation is preserved: a CLIENT never accesses the catalogue through
  any path defined here (C-13, EX-08); client visibility of exercise names is
  a SPEC-010 / SPEC-011 / SPEC-013 concern.

This is the ninth Specification of the MVP. It builds on the SPEC-001/002
foundations already implemented (User / Role / Client models, `role_user`
pivot, `User::hasRole` / `hasAnyRole` helpers, `Role::ADMIN` / `Role::TRAINER`
constants, policy pattern, Filament admin panel; ADR-001/002/003/004) and
follows the SPEC-003 (Plan) and SPEC-006 (Turno) conventions — the closest
patterns for a standalone, active/inactive, ADMIN+TRAINER-managed catalogue.
Exercises is a greenfield module: no exercise tables exist yet. SPEC-010
(Routines) will depend on this Specification.

---

## 2. Specification

Reference:

`docs/specs/SPEC-009.md`

Status note: SPEC-009 is approved for the architecture phase per
`docs/sdd/state.yaml` (status `spec_ready`, current phase `architecture`,
architect `in_progress`). The gate **D-20 option 2** (exercise catalogue
attributes = name, muscle group, equipment, instructions/video, difficulty;
management by ADMIN and TRAINER) is pre-approved under NIGHT MODE
(`docs/sdd/state.yaml` `project.po_decisions`). The Specification explicitly
flags assumptions **EX-01 to EX-10** as NOT confirmed business rules; they
require Product Owner confirmation before Implementation (SPEC-009 §14.1).
This design is written against the assumptions as stated and remains valid
under the documented alternatives unless the PO changes them (see §12 Pending
PO Confirmations).

Boundary note: routines and prescriptions (Routine, RoutineDay,
RoutineExercise, sets/reps/weight), the effect of deactivating/editing an
exercise on existing routine prescriptions, workout logs, client-facing
exercise visibility, the public website, video upload/embedding, exercise
images, an equipment entity, versioning/history, bulk import/export and
per-trainer ownership are all explicitly OUT of scope (SPEC-009 §12). This
design introduces no routine, workout or prescription concept of any kind.

---

## 3. Affected Modules

- **Exercises** (new module): the exercise entity (`exercises` table) with its
  fields (`name`, `muscle_group`, `equipment`, `difficulty`, `instructions`,
  `video_url`, `is_active`), the active/inactive lifecycle (BR-007), the
  fixed-set invariants for `muscle_group` and `difficulty` (BR-004, BR-005),
  the unique-name invariant (BR-003), ADMIN+TRAINER management, and the
  routine-friendly shape (`scopeActive()`) that SPEC-010 will consume.
- **Cross-cutting authorization foundation** (no new module): a new
  `ExercisePolicy` extends the SPEC-001/002/003/004/006 pattern — granting
  management to **ADMIN and TRAINER** like `TurnoPolicy` (BR-012 precedent;
  D-20 option 2, BR-009) — and consumes the existing `User::hasAnyRole`
  helper (ADR-001). No delete ability is registered (BR-008).

No changes are made to: auth scaffolding (login/logout/redirect), the
`EnsureUserHasRole` middleware, `AdminPanelProvider`, `RoleSeeder`,
`AdminUserSeeder`, the `role_user` pivot, the `users`/`roles`/`clients`/
`plans`/`memberships`/`turnos`/`attendances` tables, or the Users, Clients,
Plans, Memberships, Scheduling and Attendance modules. Exercises reference
nothing: no relationship to Client, Membership, Plan, Turno, Attendance or
User exists in this Specification (SPEC-009 §10, BR-011).

The boundary with later Specifications is kept clean: whether new routine
prescriptions may only use `active` exercises, and what happens to existing
prescriptions when an exercise is deactivated or edited (name/muscle-group
change), are SPEC-010 concerns (BR-010, BR-011, D-12) and impose no
restriction or mechanism here. This design introduces no routine or
prescription concept of any kind.

---

## 4. Application Flow

```text
Presentation (web)
    ↓
Admin panel: /admin — Filament panel (ADMIN | TRAINER gate via canAccessPanel)
    ↓
ExerciseResource (Filament): list / create / view / edit /
                             deactivate / reactivate exercises
    ↓
Application
    ↓
ExercisePolicy (ADMIN | TRAINER)
    ↓
Domain
    ↓
Exercise model (name, muscle_group, equipment, difficulty,
                instructions, video_url, is_active)
    ↓
Persistence
    ↓
PostgreSQL: exercises (new); users / roles / role_user / clients / plans /
            memberships / turnos / attendances (existing, untouched)
```

Concrete flows:

1. **Create exercise (FR-001)**
   - ADMIN or TRAINER opens the Exercises section of the admin panel
     (`ExerciseResource`).
   - Create form: fills required `name` and `muscle_group`, plus optional
     `equipment`, `difficulty`, `instructions` and `video_url`. Saves.
   - Validation: required fields present (ERR-001, BR-002), name unique
     (ERR-002, BR-003), muscle group in the fixed set (ERR-003, BR-004),
     difficulty in the fixed set when present (ERR-004, BR-005), video a
     valid http/https URL when present (ERR-005, BR-006).
   - The record is persisted with `is_active = true` by default (FR-001,
     BR-007, EX-07) and appears in the list (FR-002), where its status and
     catalogue attributes are shown (FR-006, FR-007).
   - Creating an exercise does NOT create any routine, routine day, routine
     exercise or workout record (AC-13, BR-011; C-09 — the Routine entities
     are SPEC-010).
2. **List / search / filter (FR-002, FR-006, FR-007)**
   - ADMIN or TRAINER lists exercises; search by `name` and optionally
     `equipment`; filters by status (active/inactive), `muscle_group` and
     `difficulty`. Status is always displayed.
3. **View detail (FR-003, FR-006, FR-007)**
   - ADMIN or TRAINER opens the detail view: name, muscle group, equipment,
     difficulty, instructions, video URL and current status.
4. **Edit (FR-004, BR-010, AF-003)**
   - ADMIN or TRAINER edits `name`, `muscle_group`, `equipment`,
     `difficulty`, `instructions` and `video_url` while the exercise is
     `active` or `inactive`. Editing re-applies the same validations as
     creation (ERR-001..ERR-005). The effect of an edit on existing routine
     prescriptions is NOT defined by this Specification (BR-010; deferred to
     SPEC-010, D-12).
5. **Deactivate (FR-005, AF-001, BR-007)**
   - ADMIN or TRAINER deactivates an `active` exercise (row action or detail
     header action); `is_active` becomes false. The exercise remains in the
     system and in the list, marked inactive (FR-006); it is no longer
     offered for new routine prescriptions (the concrete effect is consumed
     and enforced by SPEC-010, BR-011). The transition is a single-field
     `update` of `is_active`, authorized by the `ExercisePolicy::update`
     ability (the Plan precedent, SPEC-003 FR-005).
6. **Reactivate (FR-005, AF-002, BR-007)**
   - ADMIN or TRAINER reactivates an `inactive` exercise; `is_active` becomes
     true and it may be offered for new routine prescriptions again (BR-007).
7. **No side effects (AC-13, BR-009, BR-011)**
   - Creating, editing, activating or deactivating an exercise never creates,
     modifies or deletes any routine, routine day, routine exercise or
     workout record. No other table gains or loses rows in any exercise
     operation (the Routine entities are SPEC-010).

---

## 5. Components

### Controllers

None new.

Exercise management lives entirely inside the Filament `ExerciseResource`
(the admin-side controller, same convention as `UserResource`, `ClientResource`,
`PlanResource`, `TurnoResource`, `AttendanceResource`). No web routes or HTTP
controllers are added.

### Actions / Use Cases

None required.

Exercise create/edit is plain Eloquent CRUD handled by the Filament resource
with form validation, matching the SPEC-001/002/003/004/006 precedent. The
activate/deactivate transitions are single-field `update`s of the boolean
`is_active`, covered by the `update` policy ability — the exact Plan pattern
(SPEC-003 FR-005), where the row action performs
`$record->update(['is_active' => ...])` directly. No model lifecycle methods
and no Action class are warranted: the lifecycle is a two-state boolean
(not a multi-state string machine like Turno's `deactivate()`/`reactivate()`/
`cancel()`, and not a multi-entity transactional operation —
`ProvisionClientUser` / `RenewMembership` precedent, AGENTS.md §9-10,
ARCHITECTURE §7).

### Models

**`App\Models\Exercise`** (new)

- Table: `exercises`.
- Fillable: `name`, `muscle_group`, `equipment`, `difficulty`,
  `instructions`, `video_url`, `is_active`.
- Casts:
  - `is_active` → `'boolean'` (BR-007, EX-07 — the same representation as
    Plan SPEC-003 AP-02).
  - All other attributes stay plain strings/text (no cast). The fixed-set
    attributes (`muscle_group`, `difficulty`) are stored as strings and
    validated against the model constants (BR-004, BR-005) — the same
    string-with-constants convention as `Turno::status` (ADR-004 precedent)
    and `Role::ADMIN` (ADR-001). No PHP enums exist anywhere in the repo
    (see §10).
- Constants — single source of truth for the fixed sets (BR-004, BR-005,
  EX-03, EX-04):
  - Muscle group identifiers (12): `MUSCLE_GROUP_CHEST = 'chest'`,
    `MUSCLE_GROUP_BACK = 'back'`, `MUSCLE_GROUP_SHOULDERS = 'shoulders'`,
    `MUSCLE_GROUP_BICEPS = 'biceps'`, `MUSCLE_GROUP_TRICEPS = 'triceps'`,
    `MUSCLE_GROUP_FOREARMS = 'forearms'`, `MUSCLE_GROUP_ABS = 'abs'`,
    `MUSCLE_GROUP_QUADRICEPS = 'quadriceps'`,
    `MUSCLE_GROUP_HAMSTRINGS = 'hamstrings'`, `MUSCLE_GROUP_GLUTES = 'glutes'`,
    `MUSCLE_GROUP_CALVES = 'calves'`, `MUSCLE_GROUP_FULL_BODY = 'full_body'`.
  - Difficulty identifiers (3): `DIFFICULTY_BEGINNER = 'beginner'`,
    `DIFFICULTY_INTERMEDIATE = 'intermediate'`,
    `DIFFICULTY_ADVANCED = 'advanced'`.
  - Static list helpers so the form options and the validation rules share
    one source of truth:
    - `muscleGroups(): array` — the flat list of the 12 identifiers (for the
      `in:` validation rule, ERR-003);
    - `muscleGroupLabels(): array` — `[identifier => display label]`
      (presentation only, BR-004: "Stored values are fixed identifiers; the
      display labels are a presentation concern");
    - `difficulties(): array` and `difficultyLabels(): array` — the same pair
      for the 3 difficulty values (ERR-004, BR-005).
- Default attributes: `is_active` defaults to `true` (FR-001, BR-007). The DB
  column carries the same default; the Developer may add
  `protected $attributes = ['is_active' => true]` for in-memory default
  parity (the `Turno` precedent) or rely on the DB default + form Toggle
  default (the `Plan` precedent) — no business difference.
- Relationships: **none**. The exercise catalogue is standalone in this
  Specification (BR-011, SPEC-009 §10). No `routineExercises()` relationship
  is introduced: SPEC-010 defines the Routine entities and the reference
  direction (`routine_exercises.exercise_id`), the same boundary discipline
  as `plans` waiting for its consumers (SPEC-003 §6) and `turnos` waiting for
  SPEC-007 (SPEC-006 §6).
- Simple domain behavior (ARCHITECTURE §8):
  - `isActive(): bool` — `$this->is_active === true` (BR-007; the "currently
    offered for new prescriptions" notion that SPEC-010 will consume; mirrors
    `Turno::isActive()`).
  - No `deactivate()` / `reactivate()` model methods: the lifecycle is a
    two-state boolean updated directly by the Filament actions under the
    `update` policy (the Plan precedent, SPEC-003 FR-005). No delete
    scope/method: deletion is not offered anywhere (BR-008).
- Scopes:
  - `scopeActive(Builder $query): Builder` — `where('is_active', true)`
    (FR-006 display; the "currently offered" set directly reusable by
    SPEC-010 when building prescriptions — the same role as
    `Turno::scopeActive` in SPEC-006, SPEC-009 §10).

**No existing model is modified.** `User`, `Role`, `Client`, `Plan`,
`Membership`, `Turno` and `Attendance` are untouched (exercises reference none
of them).

### Policies

**`App\Policies\ExercisePolicy`** (new) — extends the `TurnoPolicy` /
`PlanPolicy` / `UserPolicy` pattern, with the ADMIN+TRAINER management set
required by D-20 option 2 / BR-009 / EX-08 (the same role set as
`TurnoPolicy`, SPEC-006 BR-012):

- `viewAny` / `view`: ADMIN **or** TRAINER (BR-009, FR-002, FR-003).
- `create`: ADMIN **or** TRAINER (BR-009, FR-001).
- `update`: ADMIN **or** TRAINER (BR-009) — covers field edits (FR-004) AND
  the activate/deactivate transitions (FR-005), the same way
  `PlanPolicy::update` covers activate/deactivate and
  `TurnoPolicy::update` covers the turno lifecycle.
- No `delete` policy is registered: exercise records are never hard-deleted
  (BR-008); there is no delete operation and no delete path in the UI
  (ERR-007).
- All rules use `$user->hasAnyRole([Role::ADMIN, Role::TRAINER])` (ADR-001).

Authorization matrix (SPEC-009 §9):

| Operation | Anonymous | ADMIN | TRAINER | CLIENT |
| --- | --- | --- | --- | --- |
| Create exercise | Denied | Allowed (BR-009, D-20 option 2) | Allowed (BR-009, D-20 option 2) | Denied |
| List / search / filter exercises | Denied | Allowed (BR-009) | Allowed (BR-009) | Denied |
| View exercise detail | Denied | Allowed (BR-009) | Allowed (BR-009) | Denied |
| Edit exercise | Denied | Allowed (BR-009) | Allowed (BR-009) | Denied |
| Activate / deactivate exercise | Denied | Allowed (BR-009, BR-007) | Allowed (BR-009, BR-007) | Denied |
| Delete exercise | Denied | Denied (no such operation — BR-008) | Denied (no such operation — BR-008) | Denied (no such operation — BR-008) |
| Client visibility of exercise names (routines / workout logs / portal) | Out of scope (SPEC-010, SPEC-011, SPEC-013) | — | — | Out of scope at this stage (EX-08) |

A multi-role user receives the union of permissions (SPEC-001 BR-002): an
ADMIN or TRAINER who also holds CLIENT can manage the catalogue in the admin
panel (AC-14). CLIENT-only users never reach the admin panel
(`canAccessPanel`, SPEC-001); anonymous visitors are redirected to `/login`
(ERR-006). Authorization is enforced server-side via the Policy; frontend
hiding is never the enforcement (AGENTS.md §17).

### Filament

**`App\Filament\Resources\ExerciseResource`** (new) with pages
`ListExercises`, `CreateExercise`, `ViewExercise`, `EditExercise` (following
the `PlanResource` / `TurnoResource` folder convention:
`app/Filament/Resources/ExerciseResource/Pages/*`).

- Form (create/edit — FR-001, FR-004):
  - `name` — `TextInput`, required (ERR-001, BR-002), `maxLength(255)`,
    `unique(ignoreRecord: true)` (ERR-002, BR-003 — the current record's own
    name is ignored on edit, and inactive exercises' names stay occupied,
    AF-005).
  - `muscle_group` — `Select` with options from `Exercise::muscleGroupLabels()`
    (or constants with `ucfirst` display), required (ERR-001, BR-002), PLUS
    an explicit server-side `in:` rule listing `Exercise::muscleGroups()`
    (ERR-003, BR-004; see §6 — a Select's option list is UX, the `in:` rule
    is the enforcement).
  - `equipment` — `TextInput`, nullable, `maxLength(255)` (BR-002; free
    text, EX-05 — no equipment entity).
  - `difficulty` — `Select` with options from `Exercise::difficultyLabels()`,
    nullable (BR-002), PLUS an explicit server-side `in:` rule listing
    `Exercise::difficulties()` applied only when a value is present (ERR-004,
    BR-005).
  - `instructions` — `Textarea`, nullable (BR-002). Plain text only; no
    rich-text editor, no HTML sanitization (EX-10).
  - `video_url` — `TextInput`, nullable, `->url()` (UX icon), server-side
    rule `url:http,https` (ERR-005, BR-006 — a valid absolute http/https URL;
    no file upload, no embedding, no external-service validation). An empty
    input is stored as `null`.
  - `is_active` — `Toggle`, `default(true)` (FR-001, BR-007, EX-07); visible
    on create and edit so the status can also be changed during an edit
    (FR-005 path) — the exact Plan pattern.
  - Absent optional fields are stored as `null`, not empty strings or
    placeholders (BR-002, ADR-003 convention).
- Table (FR-002, FR-006, FR-007):
  - Columns: `name` (searchable, sortable), `muscle_group` (TextColumn
    badge; display via `Exercise::muscleGroupLabels()`), `equipment`
    (searchable, placeholder '—'), `difficulty` (TextColumn badge,
    placeholder '—', display via `Exercise::difficultyLabels()`),
    `video_url` (placeholder '—'; may be toggleable/limited — presentation
    choice), `instructions` (placeholder '—', toggleable/truncated —
    presentation choice), `is_active` (`IconColumn` boolean — status
    display, FR-006).
  - Global search covers FR-002 "search by name and, optionally, equipment"
    (searchable columns).
  - Filters (FR-002): `SelectFilter` on `muscle_group` (options from the
    constants), `SelectFilter` on `difficulty` (options from the constants),
    `SelectFilter` on `is_active` (Active / Inactive — status filter).
  - Row actions: `View`, `Edit`, `Deactivate` (visible when
    `record->is_active` is true, `requiresConfirmation()`), `Activate`
    (visible when false, `requiresConfirmation()`). Each lifecycle action is
    authorized via `auth()->user()->can('update', $record)` and performs
    `$record->update(['is_active' => ...])` — the Plan row-action pattern
    (SPEC-003 FR-005). No delete action; `bulkActions([])` (BR-008, ERR-007).
  - `canEdit` does NOT need an override: editing is allowed in both states
    (FR-004, BR-010) and there is no terminal state (unlike the turno
    `cancelled` rule, SPEC-006 ERR-006).
- View page (`ViewExercise`, FR-003, FR-006, FR-007): infolist showing `name`,
  `muscle_group`, `equipment`, `difficulty`, `instructions`, `video_url`
  (link entry) and status (boolean/text entry; optional badges via the label
  helpers). Header actions: `Edit`, `Deactivate`, `Activate` with the same
  visibility/authorization rules as the row actions (the `ViewTurno` header
  pattern).
- Navigation: `navigationIcon` (e.g., `heroicon-o-clipboard-document-list`)
  and `navigationGroup = 'Training'` (a new group for the Training modules —
  Exercises now, Routines in SPEC-010; the Developer may adjust the cosmetic
  placement).

### Events

None required.

No operation in SPEC-009 has a defined secondary effect that needs decoupling
(ARCHITECTURE §10). Activating/deactivating an exercise never touches a
routine or prescription (BR-011) and sends no notification. `ExerciseDeactivated`-
style events are not needed until SPEC-010 defines consumers.

### Jobs

None required.

No queued work exists in SPEC-009 (no notifications, email, or slow
operations); lifecycle transitions are synchronous single-field updates
(ARCHITECTURE §11).

### Routes

No new routes. Filament auto-registers `/admin/exercises*` through the
panel's `discoverResources` (already configured in `AdminPanelProvider`).

### Seeders

None new. Exercises are created by staff in the admin panel only (EX-09,
SPEC-009 §10: "No seeder is required"). The existing `RoleSeeder` already
provides the ADMIN and TRAINER roles required by management.

---

## 6. Data Changes

### Migrations

1. **`create_exercises_table`** (new; next migration in the existing
   timestamp sequence: `2026_08_15_000008_create_exercises_table.php`):

   | Column | Type | Constraints |
   | --- | --- | --- |
   | `id` | bigint | PK |
   | `name` | string(255) | NOT NULL, UNIQUE (BR-003, ERR-002) |
   | `muscle_group` | string(50) | NOT NULL (BR-002, BR-004); stored as string with model constants, NOT a DB enum (Architect decision, §10) |
   | `equipment` | string(255) | nullable (BR-002, EX-05) |
   | `difficulty` | string(20) | nullable (BR-005); string with model constants, NOT a DB enum |
   | `instructions` | text | nullable (BR-002, EX-10) |
   | `video_url` | string(2048) | nullable (BR-006, EX-06) |
   | `is_active` | boolean | NOT NULL, default `true` (BR-007, EX-07) |
   | `created_at` / `updated_at` | timestamp | timestamps |

   - The UNIQUE index on `name` enforces BR-003 at the database level,
     including inactive exercises (AF-005) — the same as `plans.name`
     (SPEC-003) and per SPEC-009 §10 ("A unique index on `name` enforcing
     BR-003 at the database level").
   - Index on `muscle_group` and index on `is_active` to support the FR-002
     filters (SPEC-009 §10 explicitly requests both).
   - `muscle_group` and `difficulty` are plain string columns validated
     against the model constants, not PostgreSQL enums and not PHP enums:
     the spec delegates the storage with no business difference (SPEC-009
     §10), and the repo convention is validation-first string-with-constants
     (ADR-003, ADR-004; precedent: `turnos.status`, `Role::ADMIN`). No DB
     CHECK constraints (framework-validation-first, ADR-003; same as
     SPEC-003/006/008).
   - No foreign keys: the exercise catalogue is standalone in this
     Specification (BR-011, SPEC-009 §10). SPEC-010 will add
     `routine_exercises.exercise_id` → `exercises.id` from the consuming
     module (same boundary discipline as `plans` in SPEC-003 §6 and `turnos`
     in SPEC-006 §6). **No FK and no constraint rule is added now for the
     SPEC-010 deactivation restriction** (BR-011): whether new prescriptions
     may only use `active` exercises is a SPEC-010 rule, documented here only
     as the intended consumption of `Exercise::scopeActive()`.
   - No seeder (EX-09).
   - No `created_by` / trainer-ownership column: any exercise belongs to the
     shared catalogue; per-trainer ownership is out of scope (SPEC-009 §12).
   - No `category`/`type`, image, or extra attribute columns: D-20 option 2
     fixes the attribute set exactly (SPEC-009 §12).

No existing migration is modified. The `users`, `roles`, `role_user`,
`clients`, `plans`, `memberships`, `turnos` and `attendances` tables are
reused as-is.

### Relationships

```text
exercises (standalone in this Specification)
    ↑ referenced later by SPEC-010 (routine_exercises.exercise_id)
```

No Eloquent relationship is defined in this Specification (BR-011).

### Data lifecycle

- **Created:** exercise records, active by default (FR-001, BR-007).
  Creating an exercise creates no other record (AC-13, BR-011).
- **Modified:** `name`, `muscle_group`, `equipment`, `difficulty`,
  `instructions`, `video_url` via edit (FR-004, BR-010 — allowed in both
  statuses); `is_active` via activate/deactivate (FR-005, BR-007).
- **Deleted:** none in the MVP. No delete operation (BR-008) and no hard
  deletion of any kind; historical catalogue data is preserved (AGENTS.md
  §12).

---

## 7. External Integrations

None.

SPEC-009 touches no external service. The video is a plain external URL
string; no video hosting, embedding, or external-service validation is
performed (BR-006, EX-06). No notification/email is sent by any exercise
operation.

---

## 8. Testing Strategy

Pest (PHPUnit) feature tests following the existing conventions
(`tests/Pest.php` helpers `role()`, `userWithRoles()`; `RefreshDatabase`;
Livewire component testing as used in `TurnoManagementTest` /
`PlanManagementTest`). A new `ExerciseFactory` (`database/factories/`) is
added, mirroring the `PlanFactory` / `TurnoFactory` shape: `name` →
`fake()->unique()->words(3, true)` (unique per BR-003), `muscle_group` → one
of the constants (e.g. `Exercise::MUSCLE_GROUP_CHEST`), `equipment` →
`null`, `difficulty` → `null`, `instructions` → `null`, `video_url` →
`null`, `is_active` → `true`.

**Exercise CRUD and lifecycle (AC-1..AC-10, AC-12, AC-13; FR-001..FR-007)**
- `tests/Feature/Admin/ExerciseManagementTest.php` (Livewire component tests
  against `CreateExercise`, `ListExercises`, `ViewExercise`, `EditExercise`):
  - ADMIN or TRAINER can create an exercise with required name + muscle group
    plus optional equipment, difficulty, instructions and video; the record
    is persisted with `is_active = true` and listed (AC-1, FR-001, FR-002,
    BR-002, BR-007).
  - A minimal exercise with only name + muscle group is valid; absent
    optional fields are stored as `null` (AC-1, AF-004, BR-002).
  - Missing required fields (name / muscle group) are rejected (ERR-001).
  - Creating or editing an exercise with a name already used by another
    exercise — including an `inactive` one — is rejected; editing keeps the
    record's own name (AC-2, ERR-002, BR-003, AF-005).
  - A muscle group outside the fixed set is rejected (AC-3, ERR-003, BR-004).
  - A difficulty outside `beginner`/`intermediate`/`advanced` is rejected;
    omitting difficulty is accepted (AC-4, ERR-004, BR-005).
  - An invalid video URL (not an absolute http/https URL) is rejected;
    omitting the video is accepted (AC-5, ERR-005, BR-006).
  - ADMIN or TRAINER can search by name and equipment and filter by status,
    muscle group and difficulty (AC-6, FR-002).
  - ADMIN or TRAINER can view the full detail including status and all
    catalogue attributes (AC-7, FR-003, FR-006, FR-007).
  - ADMIN or TRAINER can edit an `active` and an `inactive` exercise; changes
    persist (AC-8, FR-004, BR-010).
  - ADMIN or TRAINER can deactivate an `active` exercise via the list action;
    it remains in the system and is displayed as inactive (AC-9, FR-005,
    FR-006, BR-007).
  - ADMIN or TRAINER can reactivate an `inactive` exercise (AC-10, FR-005,
    AF-002, BR-007).
  - No delete action exists; a created exercise record persists (AC-12,
    ERR-007, BR-008).
  - Creating, editing, activating or deactivating an exercise touches only
    the `exercises` table — no routine/routine-day/routine-exercise/workout
    record is created, modified or deleted (AC-13, BR-009, BR-011; assert
    only the `exercises` table gained/changed rows).

**Authorization / Policy (AC-11, AC-12, AC-14; ERR-006, ERR-007)**
- `tests/Feature/Admin/ExercisePolicyTest.php`:
  - ADMIN and TRAINER can `viewAny`/`view`/`create`/`update` exercises
    (AC-11, BR-009, EX-08 — the full management set per D-20 option 2).
  - CLIENT and anonymous users cannot create, list, search, filter, view,
    edit, activate or deactivate exercises — 403 on `/admin/exercises` routes
    and no navigation; guests are redirected to `/login` (AC-11, ERR-006,
    BR-009; asserted server-side, AGENTS.md §17).
  - A multi-role ADMIN + CLIENT or TRAINER + CLIENT user can manage the
    catalogue in the admin panel (AC-14, SPEC-001 BR-002).
  - `ExercisePolicy` has no `delete` ability for anyone (AC-12, ERR-007,
    BR-008).

**Unit**
- `tests/Unit/ExerciseTest.php`:
  - Constants / fixed-set helpers: the 12 muscle-group identifiers and the 3
    difficulty identifiers match the spec's sets; `muscleGroups()` /
    `difficulties()` return exactly those (BR-004, BR-005, EX-03, EX-04).
  - Factory / model defaults: `is_active` is `true` by default; absent
    optional fields are `null` (FR-001, BR-002, BR-007).
  - Casts: `is_active` is a boolean (BR-007).
  - The DB unique constraint on `exercises.name` rejects duplicates
    (BR-003), including against an `inactive` record (AF-005).
  - The DB default on `is_active` is `true` even for a raw write path; the
    expected columns and the `name` (unique), `muscle_group` and `is_active`
    indexes exist (FR-001, FR-002, BR-003, BR-007).
  - `scopeActive()` returns only active exercises (FR-006; the routine-
    friendly set for SPEC-010).
  - The boundary rule: creating an exercise creates no other record
    (BR-011, AC-13).

All authorization assertions are server-side (AGENTS.md §17); no test relies
on frontend visibility.

---

## 9. Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Assumptions EX-01..EX-10 are unconfirmed (SPEC-009 §14.1). | If the PO changes them, parts of the design change (e.g., EX-01 demotes muscle group to optional; EX-03 changes the muscle-group set; EX-08 restricts TRAINER to a subset; EX-07 changes the lifecycle). | Keep implementation isolated: `ExercisePolicy` rules, the `exercises` schema, the model constants and the form rules are the only touch points. Block Implementation until the PO confirms the §12 items (per spec: "before Implementation (or at latest before Review)"). |
| OQ-01: exact muscle-group value set (EX-03). | If the PO changes the set (add/rename values, e.g. separate `abs`/`core`, add `cardio`), the constants, form options and `in:` rule change. | The set is implemented as model constants + list helpers, the single place to edit; stored identifiers are English per repo convention, display labels are a presentation concern (BR-004). |
| OQ-03: name uniqueness case sensitivity (Postgres default vs case-insensitive). | If the PO wants case-insensitive uniqueness ("Press" vs "press" rejected), the DB unique index alone is insufficient (Postgres is case-sensitive by default). | Design assumes the DB unique constraint as-is (the Plan SPEC-003 precedent); a case-insensitive rule would be an additional validation (e.g. a unique index on `lower(name)` or a closure rule). Documented as pending confirmation, not silently resolved. |
| OQ-04: TRAINER full management vs restricted subset (EX-08). | If the PO restricts TRAINER (e.g., no deactivate), `ExercisePolicy` and/or the resource actions change. | The policy is the single enforcement point; granting/restricting TRAINER touches policy + action visibility only. |
| Fixed-set values stored as strings (no DB enum, no PHP enum). | A typo could store an invalid `muscle_group`/`difficulty` via a non-validated write path. | All MVP write paths go through the Filament form (validated with `in:` rules); the constants are the single source of truth; no raw SQL enum (ADR-004 §10, ADR-003 validation-first). |
| No DB CHECK on video URL / fixed sets / name length. | A raw write path could store invalid data. | All MVP write paths go through the Filament form (validated); same trade-off as ADR-003/SPEC-004/006. |
| SPEC-010 depends on this model. | If SPEC-010 needs a different exercise shape (e.g., prescription references), the schema is already routine-friendly by design. | The exercise is standalone with a stable shape and `scopeActive()`; SPEC-010 adds the `routine_exercises` table and the reference direction from the consuming module (BR-011). |
| Deactivation semantics are consumed later (BR-011). | SPEC-010 might need to enforce "new prescriptions use only active exercises" — a rule this Specification deliberately does not implement. | No restriction is pre-imposed; `scopeActive()` is the documented consumption point. If SPEC-010 adds restrictions, they live in SPEC-010's module, not here. |

---

## 10. Alternatives Considered

1. **PHP enums for muscle group / difficulty vs. string constants** — The
   repo has no PHP enum anywhere: every fixed set is a class constant on the
   model (`Role::ADMIN`, `Turno::STATUS_*`, membership statuses), validated
   at the framework level against those constants (ADR-001, ADR-004). PHP 8
   enums would add a new pattern for no functional gain and would force
   Filament option wiring through `->options(enum::class)` — a different
   convention from every existing resource. **String constants + static list
   helpers chosen** for consistency.
2. **`muscle_group` / `difficulty` as PostgreSQL native enums** — A native
   enum adds raw SQL and makes future value-set changes migration-heavy; the
   project avoids raw SQL constraints (ADR-003) and stores status values as
   strings with model constants (`turnos.status`, ADR-004 §10). The spec
   explicitly delegates the storage with no business difference (SPEC-009
   §10). **Plain string columns chosen.**
3. **`difficulty`/`muscle_group` as booleans or separate tables** — A
   separate `muscle_groups` table (or equipment entity) is explicitly
   rejected by the spec: the fixed sets are small, closed and filterable;
   equipment is an attribute, not a first-class entity (EX-05, SPEC-009 §12).
   Not modeled.
4. **Model lifecycle methods `deactivate()` / `reactivate()` (Turno style)
   vs. direct `is_active` updates (Plan style)** — Turno needs model methods
   because its status is a three-state string machine with a terminal state.
   Exercise is a two-state boolean with no terminal state (BR-007); the Plan
   precedent (single-field `update(['is_active' => ...])` under the `update`
   policy) is the simplest correct representation (AGENTS.md §9, ARCHITECTURE
   §20). **Plan style chosen.**
5. **`scopeActive()` vs. no scope (relying on `where('is_active', true)` in
   SPEC-010)** — The spec explicitly expects the model to expose the active
   set for future routine prescription, citing `Turno::scopeActive` as the
   pattern (SPEC-009 §10). The scope gives SPEC-010 a single predicate. **Chosen.**
6. **`video` column name vs. `video_url`** — The spec delegates the exact
   column name ("e.g., `video_url`", SPEC-009 §10). `video_url` chosen: it
   states the URL semantics explicitly, and `is_active` / `video_url` /
   `muscle_group` match Laravel's snake_case naming like every other column
   in the repo. Not significant enough for an ADR.
7. **`instructions` as rich text / HTML** — EX-10 assumes plain long text;
   no rich-text editor or sanitization requirement is documented. A `text`
   column with a plain `Textarea` is the simplest correct representation
   (ARCHITECTURE §20). If the PO approves rich text later (OQ-05), the field
   and sanitization policy change in place.
8. **Lifecycle status as a string column (`active`/`inactive`) instead of a
   boolean `is_active`** — The spec fixes the lifecycle as active/inactive
   with no other state (BR-007, EX-07), and `is_active` boolean is the
   established precedent (`users.is_active` SPEC-001, `plans.is_active`
   SPEC-003 AP-02). A string column would be more extensible but overkill for
   a two-state flag. **Boolean `is_active` chosen.**
9. **Explicit `CreateExercise` / `UpdateExercise` / `DeactivateExercise`
   Actions** — Plain CRUD plus a single-field boolean toggle needs no
   explicit use case, matching the SPEC-001/002/003/004/006 precedent
   (Actions are reserved for genuinely multi-entity, transactional,
   rule-bearing operations — `ProvisionClientUser`, `RenewMembership`).
   **Not added.**
10. **`ExerciseResource` with separate deactivate/reactivate abilities in
    the policy** — The lifecycle is covered by the `update` ability (the
    Plan/Turno precedent: `PlanPolicy::update` covers activate/deactivate);
    no dedicated abilities are registered. **Chosen.**

No new ADR is required for this Specification: every decision above is an
incremental application of the established ADRs (ADR-001 role/authorization
foundation, ADR-002 module boundary discipline, ADR-003 validation-first
stored representation, ADR-004 status-as-string / stored-state precedent) to a
greenfield module. No genuinely new architectural pattern is introduced.

---

## 11. Decision

Use the established SPEC-001/002/003/004/006 conventions throughout:

- **Persistence:** a new `exercises` table with required unique `name`,
  required `muscle_group` (string, fixed set via model constants), nullable
  `equipment`, nullable `difficulty` (string, fixed set via model constants),
  nullable `instructions` (text), nullable `video_url` (external http/https
  URL), boolean `is_active` default `true`, timestamps; UNIQUE index on
  `name`, indexes on `muscle_group` and `is_active`. No foreign keys, no DB
  enums, no DB CHECK constraints, no seeder (EX-09). The existing schema is
  untouched.
- **Fixed sets (BR-004, BR-005):** string constants on `Exercise`
  (12 muscle groups, 3 difficulties) + static list helpers
  (`muscleGroups()`/`muscleGroupLabels()`, `difficulties()`/`difficultyLabels()`)
  as the single source of truth for form options, display and `in:`
  validation — the ADR-004 string-with-constants convention (no PHP enums,
  no DB enums).
- **Lifecycle (BR-007, BR-008):** boolean `is_active` default `true`;
  activate/deactivate are single-field updates under the `update` policy (the
  Plan pattern); `Exercise::scopeActive()` and `Exercise::isActive()` expose
  the "currently offered" set for SPEC-010; no delete policy, no delete
  action, no lifecycle model methods.
- **Authorization:** `ExercisePolicy` (viewAny/view/create/update = ADMIN
  **or** TRAINER, no delete) on top of the existing `User::hasAnyRole` helper
  (ADR-001) — the `TurnoPolicy` pattern (D-20 option 2, BR-009, EX-08).
- **UI:** Filament `ExerciseResource` with list/create/view/edit pages;
  search by name and equipment; status/muscle-group/difficulty filters; status
  shown via a boolean column; `Deactivate` / `Activate` row and header actions
  (confirmation, `update`-authorized); a `Toggle` in the form with default
  `true`; no delete action (BR-008); `navigationGroup = 'Training'`.
- **Validation:** Filament form rules — `name` required/unique/max 255
  (ERR-001, ERR-002), `muscle_group` required + `in:` fixed set (ERR-001,
  ERR-003), `difficulty` nullable + `in:` fixed set when present (ERR-004),
  `video_url` nullable + `url:http,https` (ERR-005), `equipment`
  nullable/max 255, `instructions` nullable plain text, `is_active` Toggle
  default `true`. No separate Form Request: the repo convention is
  resource-level validation (no HTTP controllers exist for admin CRUD).
- **No Actions, no events, no jobs, no new routes, no new seeders, no
  external integrations, no ADR.**
- **Deferred (reserved for later Specifications):** the `routine_exercises`
  reference and the "active-only prescriptions" rule (SPEC-010, BR-011 —
  documented here as the intended consumption of `scopeActive()`, not
  implemented); client-facing exercise visibility (SPEC-010/011/013).

---

## 12. Pending PO Confirmations

These items are carried from SPEC-009 and must be confirmed before
Implementation (or at latest before Review). This design does not silently
resolve them.

### Assumptions (SPEC-009 §14.1)

| ID | Assumption | Impact on this design |
| --- | --- | --- |
| EX-01 | Field set and optionality per D-20 option 2: name and muscle group REQUIRED; equipment, difficulty, instructions and video OPTIONAL. | `exercises` columns (name, muscle_group NOT NULL; others nullable) + form rules (ERR-001). |
| EX-02 | Name unique among ALL exercises regardless of status; a deactivated exercise's name stays occupied. | UNIQUE index on `name` + `unique(ignoreRecord: true)` (ERR-002, AF-005). |
| EX-03 | Muscle group is a fixed enumeration of 12 identifiers (chest, back, shoulders, biceps, triceps, forearms, abs, quadriceps, hamstrings, glutes, calves, full_body); display labels are presentation. | String column + constants + `in:` rule + Select options (BR-004, ERR-003). OQ-01 open: exact set. |
| EX-04 | Difficulty is optional and a fixed enumeration: beginner / intermediate / advanced. | Nullable string column + constants + `in:` rule when present (BR-005, ERR-004). |
| EX-05 | Equipment is optional free text; no equipment entity/table. | Nullable `string(255)` column; TextInput (BR-002). |
| EX-06 | Video is an external URL string (http/https), framework-validated; no upload/embedding/external validation. | Nullable `video_url` column + `url:http,https` rule (BR-006, ERR-005). |
| EX-07 | Lifecycle = active/inactive toggle, created active, reversible, no hard deletion; both states editable. | `is_active` boolean default `true`; Toggle + Deactivate/Activate actions; no delete policy/action (BR-007, BR-008). |
| EX-08 | Catalogue management = ADMIN and TRAINER (full set); CLIENT has no direct catalogue access; anonymous none. | `ExercisePolicy` grants ADMIN and TRAINER; CLIENT denied; guest redirect (BR-009, ERR-006). OQ-04 open: TRAINER subset. |
| EX-09 | No seeder / no starter exercise set; exercises are created by staff in the admin panel only. | No seeder added (§6). |
| EX-10 | Instructions is plain long text (no rich text / markup). | `text` column + plain Textarea (BR-002). |

### Open questions (SPEC-009 §14.2)

| ID | Question | Impact on this design |
| --- | --- | --- |
| OQ-01 | Exact muscle-group value set (12 recommended; extend/rename possible). | Constants + list helpers are the single place to edit; display labels are presentation (BR-004). |
| OQ-02 | Is the muscle group required at creation? | Design assumes required (EX-01); demoting it touches the schema + form + unit tests. |
| OQ-03 | Name uniqueness case-sensitive (Postgres default) or case-insensitive? | Design assumes the DB unique constraint as-is; a case-insensitive rule would be an additive validation (e.g., `lower(name)` index or closure rule). |
| OQ-04 | TRAINER full management set vs. restricted subset (e.g., no deactivate)? | Design assumes full (EX-08), consistent with SPEC-006 AS-01 / SPEC-008 AT-01; restricting touches `ExercisePolicy` + action visibility only. |
| OQ-05 | Rich text for instructions and a max length? | Design assumes plain text (EX-10) with no documented max beyond the `text` column; a max length is a single form-rule change. |
| OQ-06 | Pre-populated starter catalogue (seeder) wanted? | Design assumes empty (EX-09); a seeder would be additive. |
| OQ-07 | SPEC-010 restriction of new prescriptions to `active` exercises, and behavior of existing prescriptions on deactivation/edit? | Deferred to SPEC-010 by design (BR-011, D-12); must be answered when SPEC-010 is specified. |
| OQ-08 | Equipment as a closed enumeration or separate entity later? | Design assumes optional free text for the MVP (EX-05); additive later without restructuring. |

### Additional design notes flagged for confirmation

- `muscle_group` and `difficulty` are stored as plain string columns
  validated against model constants — not PostgreSQL enums and not PHP
  enums — per the ADR-004 / ADR-003 validation-first convention (Architect
  decision delegated by SPEC-009 §10 with no business difference).
- The activate/deactivate transitions are modeled as `is_active` updates
  covered by the `update` policy ability (the Plan precedent, SPEC-003
  FR-005); no separate "deactivate" ability is registered.
- `scopeActive()` is provided as the routine-friendly active set for
  SPEC-010; whether SPEC-010 restricts new prescriptions to it is a SPEC-010
  business rule (BR-011), not implemented here.
- No FK or DB constraint is added for the SPEC-010 deactivation restriction
  (BR-011); the constraint rule is documented for SPEC-010, not implemented.
- `video_url` is the chosen column name for the video attribute (spec
  delegates the name; "e.g., `video_url`", SPEC-009 §10).

---

## 13. Related Documents

- Specification: `docs/specs/SPEC-009.md`
- Architecture decisions: `docs/adr/ADR-001.md` (role/authorization
  foundation), `docs/adr/ADR-002.md` (module boundary discipline),
  `docs/adr/ADR-003.md` (validation-first representation),
  `docs/adr/ADR-004.md` (status-as-string / stored-state precedent)
- Architecture: `docs/architecture/SPEC-001.md`,
  `docs/architecture/SPEC-002.md`, `docs/architecture/SPEC-003.md` (Plan
  lifecycle / unique-name / boolean `is_active` precedents),
  `docs/architecture/SPEC-004.md`, `docs/architecture/SPEC-006.md`
  (ADMIN+TRAINER `TurnoPolicy` and `scopeActive` precedents),
  `docs/architecture/SPEC-008.md`, `ARCHITECTURE.md` (§7 Actions, §8 Models,
  §10 Events, §12 Authorization, §16 Routines, §20 simplest correct
  architecture)
- Product: `docs/product/product-definition-v0.1.md`
- Domain: `docs/domain/domain-model-v0.1.md` (§Exercise;
  §Routine → RoutineDay → RoutineExercise → Exercise; C-09)
- Requirements analysis: `docs/requirements/analyst-pass-001.md` (§5.11
  Exercises, D-20, C-09, R-07)
- Workflow state: `docs/sdd/state.yaml` (SPEC-009 `spec_ready`, current phase
  `architecture`; NIGHT MODE pre-approval D-20 option 2; SPEC-001/002/003/
  004/006/008 completed; SPEC-010 depends on SPEC-009)
- Development rules: `AGENTS.md`
