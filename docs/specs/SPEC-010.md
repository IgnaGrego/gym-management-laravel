# SPEC-010 — Routines

## Status

Draft (analysis phase).

This is the tenth Specification of the MVP. It depends on SPEC-001 (Authentication
& Roles), SPEC-002 (Client Management) and SPEC-009 (Exercise Catalogue), all
COMPLETED and implemented in the repository (`docs/sdd/state.yaml`). SPEC-011
(Workout Logs & Progress) will depend on this Specification, so the Routine
prescription model is defined explicitly and kept execution-friendly: the Routine
entities carry only the **prescription** (C-09, C-10); execution data (what the
client actually performed) belongs to SPEC-011 and must not modify the
prescription.

**NIGHT MODE notice:** this Specification is produced under the PO pre-approval
("modo nocturno", `docs/sdd/state.yaml` `project.po_decisions`). The gates are
pre-approved as follows:

- **D-10 — option 2** (routine "days" semantics): a routine's days are **ordinal
  days within a repeating cycle** (Day 1..N), NOT days-of-the-week templates
  (Mon/Thu). `analyst-pass-001.md` §D-10.
- **D-11 — option 2** (prescription granularity): **set-level rows** — one
  `RoutineExercise` row per set. The documented domain example "60 kg × 10" twice
  = **two rows**, not one row with `sets = 2`. `analyst-pass-001.md` §D-11.
- **D-12 — option 3** (routine versioning): **versioning with reassignment** —
  editing creates a new version; old versions remain for history (archived);
  clients are reassigned explicitly. `analyst-pass-001.md` §D-12.

All business gates needed by this Specification are covered by the pre-approved
decisions D-10 / D-11 / D-12, the confirmed decisions (C-01, C-03, C-09, C-10,
C-13, C-15), and the NIGHT MODE task directive for this Specification (which
explicitly routes the lifecycle, assignment cardinality, deactivated-exercise
handling and client-visibility timing to documented assumptions below). **No NOT
COVERED blocking business decision was found for this Specification.** Items whose
behavior cannot be defined before later Specifications exist (client visibility of
the assigned routine, workout logging, progress review) are explicitly deferred to
SPEC-011 / SPEC-013 by design (the same boundary discipline as SPEC-006 BR-013 for
bookings and SPEC-009 BR-011 for exercise-deactivation consequences).

**Assumption notice:** this specification contains explicitly flagged assumptions
(AR-01 to AR-09, see §14.1) that fill gaps required to make the specification
implementable. They are operational defaults **consistent with** the pre-approved
decisions D-10 / D-11 / D-12 (the versioning-with-reassignment model requires a
single current assignment per client; the "archives §12" wording of D-12 option 3
requires the draft/active/archived lifecycle; SPEC-009 BR-011 requires SPEC-010 to
define the active-exercise rule for prescriptions). **None of them is a confirmed
business rule** unless stated otherwise. Each requires Product Owner confirmation
before Implementation (or at latest before Review).

---

## 1. Objective

Provide routine (personalized exercise plan) management in the gym management
system:

- define the **Routine** entity — a versioned plan of exercises assigned to
  clients, organized in ordinal days (C-09, D-10 option 2), with set-level
  prescriptions (D-11 option 2):
  `Routine → RoutineDay → RoutineExercise → Exercise`;
- staff — **ADMIN and TRAINER** — can create, view, search, edit, activate and
  version routines, and assign routines to clients, from the admin panel (C-03,
  C-15; the same role set as `TurnoPolicy` / `ExercisePolicy`);
- each routine version records: name, status (draft / active / archived), version
  number, creator, ordinal days with day numbers, and per-set prescription rows
  (exercise reference, set number, target reps, target weight, optional rest
  seconds, optional notes);
- **versioning with reassignment** (D-12 option 3): editing an active routine
  creates a new version; old versions are preserved (archived) for history;
  clients currently assigned remain on the old version until staff explicitly
  reassigns them to the new version;
- **assignment**: a client is assigned to a specific routine version; a client has
  at most one active routine assignment at a time (assumption AR-03; SPEC-011
  depends on this); assignment history is preserved;
- **prescription vs execution separation** (C-10): the Routine entities carry only
  the prescription. Workout logs (SPEC-011) record execution separately and must
  not modify the prescribed routine;
- the model is **execution-friendly**: SPEC-011 will reference the prescribed
  `RoutineExercise` rows (and/or `Exercise`) when logging what the client actually
  performed (C-11);
- client isolation is preserved: a CLIENT never accesses routines or prescription
  data through any path defined here (C-13, AR-08); client visibility of their
  assigned routine is deferred to SPEC-011 / SPEC-013.

This is the base for the Training execution modules: Workout Logs (SPEC-011) will
reference the prescriptions defined here, and the Client Portal (SPEC-013) will
later display the client's assigned routine.

---

## 2. Actors

Actors are defined by role, not by person (SPEC-001 §2). A single User may hold
one or more roles (confirmed decision C-01).

| Actor | Description |
| --- | --- |
| Anonymous visitor | No access to routines. Routine data is never exposed on public pages. |
| ADMIN | Staff who administer the gym. Can create, view, search, edit, activate, version and assign routines (assumption AR-08; C-03 read together with the `TurnoPolicy`/`ExercisePolicy` ADMIN+TRAINER precedent). |
| TRAINER | Staff who train clients. Can create, view, search, edit, activate, version and assign routines (C-03 "a Trainer may create routines / assign routines"; AR-08). |
| CLIENT | A gym member. No routine management and no direct routine access in this Specification (assumption AR-08); client visibility of their assigned routine and workout logging are deferred to SPEC-011 (Workout Logs) and SPEC-013 (Client Portal). Client isolation (C-13) always applies. |

Notes:

- A User holding ADMIN and/or TRAINER uses the admin panel; a User holding CLIENT
  uses the client portal (SPEC-001 FR-006, confirmed C-15).
- A User holding both a staff role and CLIENT (e.g., a trainer who also trains) is
  permitted by C-01; the mixed-role behavior is tracked as SPEC-001 OQ-04.
- "Staff" in this Specification means ADMIN and/or TRAINER; there is no
  RECEPTIONIST role (confirmed SPEC-001 A-04 / D-19 option 1; same convention as
  SPEC-005 PY-01, SPEC-007 BK-02, SPEC-008 AT-01, SPEC-009 EX-08).
- The trainer–client assignment is NOT implemented yet (SPEC-002 OQ-02); it is not
  needed here: routine assignment is to `Client` records directly (C-03 only says
  a Trainer "may be assigned clients" and "assigns routines"; the two are
  independent in this Specification).

---

## 3. Preconditions

1. SPEC-001 is implemented and completed: authentication, fixed role catalog
   (ADMIN, TRAINER, CLIENT), admin panel access (ADMIN or TRAINER), role helpers
   (`hasRole` / `hasAnyRole`), `Role::ADMIN` / `Role::TRAINER` constants, policy
   pattern, no hard deletion of User records (`docs/sdd/state.yaml`, ADR-001).
2. SPEC-002 is implemented and completed: the `Client` model exists and is
   management-ready (client records are the assignment target;
   `docs/sdd/state.yaml` records `depends_on` on SPEC-001 and SPEC-002).
3. SPEC-009 is implemented and completed: the `Exercise` model exists
   (`exercises` table: `name`, `muscle_group`, `equipment`, `difficulty`,
   `instructions`, `video_url`, `is_active`) and exposes `Exercise::scopeActive()`
   / `Exercise::isActive()` as the "currently offered" set for prescriptions
   (SPEC-009 BR-007, BR-011, §10).
4. An authenticated ADMIN or TRAINER exists and can access the admin panel
   (SPEC-001 FR-008, FR-006).
5. The role catalog stays at ADMIN / TRAINER / CLIENT; no RECEPTIONIST role
   (confirmed SPEC-001 A-04).
6. The gate decisions D-10 (option 2), D-11 (option 2) and D-12 (option 3) are
   pre-approved (NIGHT MODE, `docs/sdd/state.yaml`).
7. No routine tables exist yet; the Routines module is greenfield on top of the
   SPEC-001 / SPEC-002 / SPEC-009 foundations.
8. SPEC-011 (Workout Logs) does NOT exist yet; this Specification only prepares
   the prescription reference point (C-09, C-10) and defers execution behavior.

---

## 4. Functional Requirements

### FR-001 — Create routine

An ADMIN or TRAINER can create a routine. Required field: name. A new routine is
created as **version 1** with status `draft` (BR-002, AR-01), with no days (a
draft may be empty; BR-010, AR-07) and with `created_by` set to the authenticated
user (BR-011, AR-08). Creating a routine does NOT create any workout log or
assignment record.

### FR-002 — List and search routines

An ADMIN or TRAINER can list routines (each list entry represents a routine
lineage, showing the current/latest version's name, status and version number) and
search them by name and filter them by status (draft / active / archived), so the
routine library is browsable at a glance.

### FR-003 — View routine detail

An ADMIN or TRAINER can view a routine version's full detail: name, status, version
number, creator (`created_by`), ordinal days (day numbers), and per-day set-level
prescription rows (exercise name, set number, target reps, target weight, rest
seconds, notes).

### FR-004 — View version history

An ADMIN or TRAINER can view the version history of a routine lineage: every
version with its number, status and creator, so staff can consult archived
versions (D-12 option 3: old versions remain for history).

### FR-005 — Edit draft routine

An ADMIN or TRAINER can edit a `draft` routine **in place** (BR-002): rename it,
and add/remove days and set rows. Editing a draft does NOT create a new version.
Editing re-applies the same validations as creation/activation (ERR-001..ERR-005).

### FR-006 — Edit active routine (creates a new version)

An ADMIN or TRAINER can edit an `active` routine. Editing its content (days, set
rows, or name) creates a **new version** of the lineage (copy of the current
version with the requested changes applied), increments the version number, and
archives the previous version (BR-001, BR-002, D-12 option 3). Assignments are
untouched by the edit: clients currently assigned remain on the previous version
until staff explicitly reassign them (FR-010). The new version is created with
status `active` (assumption AR-02; alternative documented in OQ-02).

### FR-007 — Activate (publish) routine

An ADMIN or TRAINER can activate a `draft` routine: its status becomes `active`.
Activation requires the routine to have at least one day and each day at least one
set row (BR-010, AR-07; ERR-003, ERR-004). An `active` routine version is the only
status that can be assigned to clients (BR-007, AR-03).

### FR-008 — Manage days and set rows

An ADMIN or TRAINER can, within a routine version (draft in place, FR-005; active
via a new version, FR-006): add and remove days (ordinal day numbers, BR-003),
add and remove set rows per day, and edit each set row's target reps, target
weight, rest seconds and notes (BR-004, BR-010). Sets are ordered by their set
number; reordering is expressed by editing set numbers.

### FR-009 — Assign routine to clients

An ADMIN or TRAINER can assign an `active` routine version to one or more clients
(BR-007). Each assignment records the client, the routine version, the assignment
date and an active flag (AR-03). Assigning an active routine to a client who
already has an active assignment **supersedes** it: the previous active assignment
is deactivated (history preserved) and a new active assignment is created (AF-002,
BR-007, D-12 option 3 reassignment semantics).

### FR-010 — Reassign / unassign clients

An ADMIN or TRAINER can explicitly reassign a client from their current active
routine version to another version of the same lineage (e.g., after an edit created
a new version, D-12 option 3) or to a different routine, and can end a client's
active assignment (unassign) without assigning a replacement. Reassignment and
unassignment preserve assignment history (BR-007, BR-008, AR-09).

### FR-011 — View assigned clients

An ADMIN or TRAINER can view, for a routine version, which clients are currently
assigned to it (and, for a client, which routine version is currently active), so
staff can manage reassignment after versioning (D-12 option 3).

### FR-012 — Display status and version info

Routine lists, detail and history views always show the routine's status and
version number, so staff know which version is current and which are archived
(BR-002, FR-004).

---

## 5. Business Rules

### BR-001 — Routine is a versioned entity

A Routine is a plan of exercises assigned to a client, organized in days
(domain-model §Routine; confirmed decision C-09). In this Specification a Routine
is versioned (D-12 option 3): each **version** is a first-class Routine record;
versions of the same routine form a **lineage** (AR-02). A RoutineExercise row
always belongs to exactly one routine version; rows are never shared or mutated
across versions.

### BR-002 — Version lifecycle

Each routine version has a status: `draft`, `active` or `archived` (AR-01; the
string-with-constants convention, ADR-004). Transitions:

- `draft → active` (activate/publish, FR-007). Editing a `draft` edits it in place
  (FR-005).
- `active → archived` when a new version is created from it (FR-006, D-12 option
  3). Editing an `active` routine never mutates the version clients see; it
  creates a new version instead (BR-001, FR-006).
- `archived` is terminal and read-only: no edits, no new version created from it,
  no new assignments to it (BR-007; ERR-006). Existing assignments to an archived
  version remain valid and are still displayed until the client is reassigned
  (D-12 option 3: "clients currently assigned remain on the old version until
  explicitly reassigned").
- There is no manual "archive" action in the MVP: `archived` is reached only by
  being superseded by a new version (AR-01).

### BR-003 — Days are ordinal within a repeating cycle

A routine's days are **ordinal days** within a repeating cycle — Day 1..N (D-10
option 2, pre-approved). They are NOT days-of-the-week templates. A day is
identified by its day number within the routine version; day numbers are unique
within a version and gaps are permitted (AR-07; ERR-002).

### BR-004 — Set-level prescription granularity

The prescription is stored **per set**: one `RoutineExercise` row per set (D-11
option 2, pre-approved). A set row prescribes: an exercise reference, a set
number, target repetitions, target weight, optional rest seconds and optional
notes. The domain example "60 kg × 10, 60 kg × 10, 62.5 kg × 8, 62.5 kg × 8" is
stored as **four rows**. There is no `sets` count field on an exercise-level row.

### BR-005 — Prescription vs execution separation

The Routine entities carry only the **prescription** (what the trainer assigns);
they contain no execution data (what the client actually performed) (C-10,
ARCHITECTURE §16). Workout logs (SPEC-011) record execution separately and must
not modify the prescribed routine. This Specification creates no execution record
of any kind.

### BR-006 — Exercise reference and active-exercise rule

A set row references an exercise via `exercise_id` → `exercises.id` (C-09; the
reference direction documented by SPEC-009 BR-011 / §10). **New** set rows may
only reference exercises that are `active` at the time the row is created
(assumption AR-04, consistent with SPEC-009 BR-011 and the documented consumption
of `Exercise::scopeActive()`). Existing set rows are never modified, re-pointed or
deleted when an exercise is deactivated or edited (SPEC-009 BR-010/BR-011
consumed here): a version copy preserves set rows that reference now-inactive
exercises, and displaying a prescription reads the exercise's current attributes
from the catalogue (no per-prescription snapshot of exercise attributes; AR-04).

### BR-007 — Assignment

A client is assigned to a specific routine version via a `routine_assignment`
record (client, routine version, assignment date, active flag; AR-03, AR-09). Only
`active` routine versions can be assigned to clients (AR-03). A client has **at
most one active assignment** at a time; assigning an active routine to a client
with an existing active assignment supersedes it (the previous active row is
deactivated, history preserved) (AF-002, AR-03). A routine version may be assigned
to many clients. Assigning or unassigning never creates, modifies or deletes any
prescription row, day, or workout log.

### BR-008 — No hard deletion

Routine, routine day, routine exercise and assignment records are never
hard-deleted; historical data is preserved (AGENTS.md §12; same pattern as
SPEC-001 BR-007 / SPEC-002 BR-006 / SPEC-003 BR-004 / SPEC-009 BR-008; AR-09).
Archiving (versions) and deactivating assignments are used instead; no delete
operation is provided and no delete policy is registered.

### BR-009 — Routine management is ADMIN/TRAINER

Only ADMIN and TRAINER can create, list, search, view, edit, activate, version and
assign routines (C-03 "a Trainer may create routines / assign routines", read with
the ADMIN+TRAINER management pattern of `TurnoPolicy` / `ExercisePolicy`; AR-08).
CLIENT has no routine access in this Specification (deferred to SPEC-011 /
SPEC-013). Anonymous visitors have no access (ERR-007).

### BR-010 — Validation invariants

- Routine name: required (non-empty) free text; NOT unique across lineages (AR-05;
  versions of a lineage share the name).
- Day numbering: `day_number` is a positive integer, unique within the routine
  version (ERR-002); gaps allowed (AR-07).
- Set numbering: `set_number` is a positive integer, unique within the routine day
  (ERR-002); gaps allowed (AR-07). A day must have at least one set row before the
  routine version can be `active` (ERR-004, AR-07).
- A routine version must have at least one day before it can be `active`
  (ERR-003, AR-07). A `draft` may be empty.
- Target reps: required positive integer (AR-06; ERR-005).
- Target weight: optional decimal ≥ 0; absent or zero means bodyweight/no external
  load (AR-06).
- Rest seconds: optional integer ≥ 0 (AR-06).
- Notes: optional free text (AR-06).
- All referenced exercises must exist (foreign key; ERR-001).

### BR-011 — Creator tracking is audit-only

Each routine version records `created_by` (the User who created that version),
which is informational/audit (FR-003, FR-004). Authorization is role-based
(ADMIN/TRAINER), not ownership-based: any ADMIN or TRAINER can edit or version any
routine regardless of who created it (AR-08; same stance as the SPEC-009 shared
catalogue).

---

## 6. Main Flow

1. An authenticated ADMIN or TRAINER opens the Routines section of the admin panel
   (FR-002).
2. Staff create a routine: fill the required name and save (FR-001). The system
   creates **version 1**, status `draft`, with `created_by` set (BR-001, BR-002,
   BR-011).
3. Staff add ordinal days (day numbers) and, per day, set-level prescription rows
   referencing `active` exercises (FR-008, BR-003, BR-004, BR-006).
4. Staff activate the draft (FR-007). The system validates: at least one day
   (ERR-003) and at least one set row per day (ERR-004). The version becomes
   `active` (BR-002).
5. Staff assign the active routine version to one or more clients (FR-009). Each
   assignment records client, version, date and active flag (BR-007, AR-03).
6. Later, staff edit the active routine's content (FR-006). The system creates a
   **new version** (copy with the changes applied), increments the version number,
   and archives the previous version (BR-001, BR-002, D-12 option 3). Assignments
   are untouched.
7. Staff review which clients are still on the previous version (FR-011) and
   explicitly reassign them to the new version (FR-010, D-12 option 3).
8. The version history shows every version with its status and creator (FR-004);
   archived versions remain readable and are no longer assignable or editable
   (BR-002, ERR-006).

---

## 7. Alternative Flows

### AF-001 — Editing a draft

Staff edit a `draft` routine in place (rename, add/remove days and set rows);
no new version is created (FR-005, BR-002). Validation re-applies on activation
(ERR-003, ERR-004).

### AF-002 — Assigning when the client already has an active routine

Staff assign an `active` routine version to a client who already has an active
assignment. The system supersedes the previous assignment: the old active row is
deactivated (is_active = false, history preserved) and a new active row is created
(FR-009, BR-007, AR-03, D-12 option 3 reassignment semantics).

### AF-003 — Editing an active routine assigned to clients

An `active` routine currently assigned to clients is edited. The system creates a
new version and archives the old one; clients remain on the old version and keep
following it until staff explicitly reassign them (FR-006, FR-010, BR-002, D-12
option 3; edge case E-07 of `analyst-pass-001.md` §7).

### AF-004 — Viewing an archived version

Staff open an `archived` version from the history (FR-004). The detail is fully
readable (FR-003) but the version cannot be edited, cannot produce a new version,
and cannot receive new assignments (BR-002, ERR-006).

### AF-005 — Empty draft

A draft may be created with only a name and no days (FR-001, BR-010). Staff add
content before activation; activating an empty draft is rejected (ERR-003,
ERR-004).

### AF-006 — Unassigning without replacement

Staff end a client's active assignment without assigning a replacement (FR-010).
The active row is deactivated; the client has no active routine until a new
assignment is made (BR-007, AR-09).

---

## 8. Error Cases

### ERR-001 — Nonexistent exercise reference

Condition: a set row references an exercise that does not exist.

Expected behavior: rejected; the exercise reference is a foreign key to
`exercises.id` (BR-006).

### ERR-002 — Duplicate day or set numbers

Condition: adding a day with a day number already used in the same routine version,
or a set row with a set number already used in the same routine day.

Expected behavior: rejected with a validation error (BR-003, BR-010, AR-07).

### ERR-003 — Activating a routine with no days

Condition: activating a draft routine that has zero days.

Expected behavior: rejected with a validation error (FR-007, BR-010, AR-07).

### ERR-004 — Activating a routine with an empty day

Condition: activating a routine version where at least one day has zero set rows.

Expected behavior: rejected with a validation error (FR-007, BR-010, AR-07).

### ERR-005 — Invalid prescription values

Condition: a set row with a missing/zero/negative target reps, or a negative
target weight, or negative rest seconds.

Expected behavior: rejected with a validation error (BR-010, AR-06).

### ERR-006 — Editing or assigning an archived version

Condition: an attempt to edit an `archived` version, create a new version from it,
or assign it to a client.

Expected behavior: blocked; archived versions are read-only and not assignable
(BR-002, BR-007; AF-004).

### ERR-007 — Unauthorized access

Condition: an anonymous visitor or a CLIENT attempts to create, list, view, edit,
activate, version or assign routines.

Expected behavior: access denied (redirect for anonymous; 403 for CLIENT)
(BR-009, AR-08).

### ERR-008 — Assigning a non-active version

Condition: an attempt to assign a `draft` or `archived` routine version to a
client.

Expected behavior: rejected; only `active` versions can be assigned (BR-007,
AR-03).

### ERR-009 — Attempted deletion

Condition: an attempt is made to delete a routine, day, set row or assignment.

Expected behavior: no such operation exists; records persist (BR-008, AR-09).
There is no delete ability in the policies and no delete path in the UI (§9).

---

## 9. Authorization

| Operation | Anonymous | ADMIN | TRAINER | CLIENT |
| --- | --- | --- | --- | --- |
| Create routine | Denied | Allowed (BR-009, C-03, AR-08) | Allowed (BR-009, C-03, AR-08) | Denied |
| List / search / filter routines | Denied | Allowed (BR-009) | Allowed (BR-009) | Denied |
| View routine detail / version history | Denied | Allowed (BR-009) | Allowed (BR-009) | Denied |
| Edit draft routine (in place) | Denied | Allowed (BR-009) | Allowed (BR-009) | Denied |
| Edit active routine (create new version) | Denied | Allowed (BR-009) | Allowed (BR-009) | Denied |
| Activate / publish routine | Denied | Allowed (BR-009) | Allowed (BR-009) | Denied |
| Assign / reassign / unassign clients | Denied | Allowed (BR-009, C-03) | Allowed (BR-009, C-03) | Denied |
| Delete routine / day / set / assignment | Denied | Denied (no such operation — BR-008) | Denied (no such operation — BR-008) | Denied (no such operation — BR-008) |
| Client visibility of own assigned routine (portal / workout views) | Out of scope (SPEC-011, SPEC-013) | — | — | Out of scope at this stage (AR-08) |

Notes:

- A User holding several roles receives the union of the column permissions
  (SPEC-001 BR-002); an ADMIN or TRAINER who is also CLIENT can manage routines in
  the admin panel.
- Authorization is enforced server-side via Policies; frontend-only restrictions
  are never sufficient (AGENTS.md §17).
- Routine management and assignment are role-based, not ownership-based: any
  ADMIN or TRAINER may operate on any routine regardless of `created_by` (BR-011,
  AR-08).
- CLIENT read of the assigned routine is deferred to SPEC-011 / SPEC-013 (AR-08),
  consistent with SPEC-009 EX-08 (client isolation always applies, C-13).

---

## 10. Data Changes

This Specification describes the information that must exist; the exact
persistence schema, table names and model names are defined by the Architect
(following the repo's English naming convention, the same way
`exercises`/`Exercise`, `turnos`/`Turno` were chosen: expected `routines`,
`routine_days`, `routine_exercises`, `routine_assignments` with `Routine`,
`RoutineDay`, `RoutineExercise`, `RoutineAssignment` models).

Created:

- **Routine versions** (`routines` table; one row per version, D-12 option 3):
  - `name` — required free text, NOT unique across lineages (AR-05); technical max
    length is an Architect/Developer detail (e.g., 255);
  - `status` — `draft` / `active` / `archived`, default `draft` (BR-002, AR-01).
    Following the string-with-constants convention (ADR-004; precedent:
    `turnos.status`, `Exercise::muscle_group`), the recommended representation is
    a string column validated against model constants, not a DB enum;
  - `version_number` — positive integer, 1 for the first version, incremented per
    lineage (BR-001, AR-02);
  - lineage link — the recommended mechanism is a self-referential `replaces_id`
    (nullable FK to `routines.id`: the version this one replaces; `null` for
    version 1) forming a chain per lineage (AR-02; the Architect may implement an
    equivalent `routine_group_id`; the business invariant is that versions of a
    lineage are linked and numbered sequentially);
  - `created_by` — FK to `users.id`, the User who created this version (BR-011,
    AR-08); informational/audit only;
  - timestamps.
- **Routine days** (`routine_days` table; D-10 option 2):
  - `routine_id` — FK to `routines.id` (the version the day belongs to);
  - `day_number` — positive integer, unique within the routine version (BR-003,
    BR-010, ERR-002);
  - timestamps.
- **Set-level prescription rows** (`routine_exercises` table; D-11 option 2, C-09):
  - `routine_day_id` — FK to `routine_days.id`;
  - `exercise_id` — FK to `exercises.id` (BR-006; the consuming-module reference
    direction documented by SPEC-009 BR-011 / §10);
  - `set_number` — positive integer, unique within the routine day (BR-010,
    ERR-002);
  - `target_reps` — required positive integer (BR-010, AR-06);
  - `target_weight` — optional decimal ≥ 0, nullable; absent/zero = bodyweight
    (BR-010, AR-06);
  - `rest_seconds` — optional integer ≥ 0, nullable (BR-010, AR-06);
  - `notes` — optional free text, nullable (BR-010, AR-06);
  - timestamps.
- **Assignments** (`routine_assignments` table; BR-007, AR-03):
  - `client_id` — FK to `clients.id`;
  - `routine_id` — FK to `routines.id` (the assigned VERSION);
  - `assigned_at` — timestamp of the assignment;
  - `is_active` — boolean: the current assignment flag; at most one active row per
    client (AR-03), enforced at the application level;
  - timestamps.
- Suggested indexes (Architect decision): unique on `routine_days(routine_id,
  day_number)` and `routine_exercises(routine_day_id, set_number)` to enforce
  ERR-002 at the database level; index on `routine_assignments(client_id)` and on
  `routine_assignments(routine_id)` to support FR-011 and the one-active-assignment
  check.

Modified:

- Routine name, days and set rows: in place for `draft` versions (FR-005); only
  via a new version for `active` versions (FR-006, BR-002).
- Routine status: `draft → active` on activation (FR-007); `active → archived`
  when superseded by a new version (FR-006, BR-002).
- Assignment active flag: deactivated on supersession (AF-002) and on unassignment
  (AF-006); history rows are never hard-deleted (BR-008).

Deleted:

- No hard deletion of routine, day, set-row or assignment records in the MVP
  (BR-008, ERR-009); no delete operation. Archiving and assignment deactivation
  are used instead.

No seeder is required: routines are created by staff in the admin panel only (no
starter routine set is requested by any documentation).

No change to the `users`, `roles`, `clients`, `exercises` or any other existing
table is made by this Specification. The `exercises` table gains no new columns;
the only added reference direction is `routine_exercises.exercise_id` →
`exercises.id` (the consumption point documented by SPEC-009 BR-011 / §10).

---

## 11. Acceptance Criteria

- [ ] AC-1: ADMIN or TRAINER can create a routine with a name; version 1 is
  persisted as `draft` with `created_by` set, with no days (FR-001, BR-002,
  BR-011, AR-01, AR-05).
- [ ] AC-2: ADMIN or TRAINER can add ordinal days (day numbers) and set-level
  prescription rows per day referencing exercises; the rows persist with set
  number, target reps, target weight, rest seconds and notes (FR-008, FR-003,
  BR-003, BR-004).
- [ ] AC-3: Adding a day number already used in the same version, or a set number
  already used in the same day, is rejected with a validation error (ERR-002,
  BR-010).
- [ ] AC-4: Activating a draft with zero days is rejected; activating a draft
  where any day has zero set rows is rejected; activating a valid draft makes it
  `active` (FR-007, ERR-003, ERR-004, BR-002).
- [ ] AC-5: A new set row referencing an `inactive` exercise is rejected; a set
  row referencing an `active` exercise is accepted (BR-006, AR-04).
- [ ] AC-6: Editing a `draft` routine changes it in place and does not create a
  new version (FR-005, BR-002).
- [ ] AC-7: Editing an `active` routine creates a new version with the changes
  applied, increments the version number, and archives the previous version;
  assignments are untouched (FR-006, BR-001, BR-002, D-12 option 3).
- [ ] AC-8: After AC-7, clients assigned to the previous version remain assigned
  to it until staff explicitly reassign them (FR-010, AF-003, D-12 option 3).
- [ ] AC-9: ADMIN or TRAINER can view the version history of a lineage with each
  version's number, status and creator; archived versions are fully readable but
  cannot be edited, versioned again, or assigned (FR-004, FR-003, AF-004,
  ERR-006).
- [ ] AC-10: ADMIN or TRAINER can assign an `active` routine version to one or
  more clients; the assignment records client, version, date and active flag
  (FR-009, BR-007).
- [ ] AC-11: Assigning an active routine to a client who already has an active
  assignment supersedes it: the previous active row is deactivated and a new
  active row is created; no assignment record is deleted (AF-002, BR-007, AR-03).
- [ ] AC-12: Assigning a `draft` or `archived` routine version is rejected
  (ERR-008, BR-007).
- [ ] AC-13: ADMIN or TRAINER can reassign a client to another version and
  unassign a client without replacement; history is preserved (FR-010, AF-006,
  BR-007, BR-008).
- [ ] AC-14: ADMIN or TRAINER can search routines by name and filter by status;
  lists and detail show status and version number (FR-002, FR-012).
- [ ] AC-15: A CLIENT or anonymous visitor cannot create, list, view, edit,
  activate, version or assign routines (403 or redirect) (ERR-007, BR-009,
  AR-08).
- [ ] AC-16: No delete operation exists for routines, days, set rows or
  assignments; created records persist (ERR-009, BR-008).
- [ ] AC-17: Creating, editing, activating or assigning a routine never creates,
  modifies or deletes any workout log record (C-10, BR-005; Workout Logs are
  SPEC-011); creating a workout log is not possible in this Specification.
- [ ] AC-18: Deactivating or editing an exercise (SPEC-009) never creates,
  modifies or deletes any routine, day or set row; existing set rows keep
  referencing the exercise (BR-006, AR-04; SPEC-009 BR-010/BR-011 consumed here).
- [ ] AC-19: A multi-role ADMIN + CLIENT or TRAINER + CLIENT user can manage
  routines in the admin panel (SPEC-001 BR-002).

**Test plan (to be executed at Implementation; aligned with the existing Pest
layout under `tests/`, `tests/Pest.php` helpers `role()` / `userWithRoles()`,
`RefreshDatabase`, Livewire component tests as used in
`ExerciseManagementTest` / `PlanManagementTest`):**

- `tests/Feature/Admin/RoutineManagementTest.php` (Livewire): create a routine
  with a name persisted as draft v1 (AC-1); add days and set rows with
  active-exercise validation (AC-2, AC-5); duplicate day/set numbers rejected
  (AC-3); activation validation — no days / empty day / valid (AC-4); invalid
  prescription values rejected (ERR-005); edit draft in place (AC-6); edit active
  creates a new version and archives the old (AC-7, AC-8); version history view
  (AC-9); editing an archived version blocked (ERR-006); search and filters
  (AC-14); no delete actions (AC-16); exercise deactivation does not modify
  prescriptions (AC-18).
- `tests/Feature/Admin/RoutineAssignmentTest.php`: assign an active version to a
  client (AC-10); supersession when a client already has an active assignment
  (AC-11); assigning draft/archived versions rejected (AC-12); reassign and
  unassign preserve history (AC-13); clients remain on the old version after an
  edit creates a new version until explicitly reassigned (AC-8); assignment
  operations never touch prescription rows (BR-007).
- `tests/Feature/Admin/RoutinePolicyTest.php`: ADMIN and TRAINER can
  viewAny/view/create/update routines and assign; CLIENT and anonymous denied
  (403); a multi-role ADMIN + CLIENT or TRAINER + CLIENT user can manage routines;
  no delete ability exists for anyone (AC-15, AC-16, AC-19).
- `tests/Unit/RoutineTest.php`: status constants and default `draft` (BR-002,
  AR-01); version lineage invariants — `version_number` increments and
  `replaces_id` chains per lineage (BR-001, AR-02); day/set numbering uniqueness
  and the one-active-assignment invariant (BR-003, BR-010, AR-03); a copied
  version preserves set rows referencing now-inactive exercises (BR-006, AR-04);
  the boundary rule that creating/editing/assigning a routine creates no workout
  log record (BR-005, AC-17).

---

## 12. Out of Scope

The following are explicitly NOT part of this Specification:

- **Workout Logs and Progress** — recording what a client actually performed,
  progress comparison, and trainer review of execution (SPEC-011; C-10, C-11).
  This Specification only prepares the prescription reference point.
- **Client-facing routine visibility** — a CLIENT viewing their assigned routine,
  or exercise names in their routine view (SPEC-011, SPEC-013; C-13, AR-08).
- **Days-of-the-week scheduling** — a routine's days are ordinal (Day 1..N), not
  weekday templates (D-10 option 2, pre-approved; BR-003).
- **Exercise-level prescription granularity** — no `sets` count field; the
  prescription is stored per set (D-11 option 2, pre-approved; BR-004).
- **Live edit of assigned routines** — editing an active routine never mutates the
  version clients see; versioning with explicit reassignment applies (D-12 option
  3, pre-approved; BR-002).
- **The trainer–client assignment** — which trainer is assigned to which client is
  still undefined (SPEC-002 OQ-02) and is not needed here: routine assignment is
  to `Client` records directly.
- **A manual "archive" action** — `archived` is reached only by being superseded
  by a new version (AR-01); there is no staff-initiated archive of the current
  routine without a new version.
- **Routine templates / shared libraries** beyond ordinary assignment — any
  routine version may be assigned to many clients; no separate "template" entity
  is introduced.
- **Program phases / periodization / deload weeks** — not documented in the
  domain.
- **Execution-time behavior** — recommended vs actual sets/reps/weight, rest timer
  enforcement, notifications to clients (SPEC-011 / SPEC-013).
- **Bulk import/export** of routines or assignments.
- **Hard deletion of routine data** — no delete operation (BR-008, ERR-009).
- **Notifications** to clients when a routine is assigned, edited or versioned
  (depends on SPEC-013 client communication infrastructure).

---

## 13. Dependencies

- **SPEC-001 (Authentication & Roles) — COMPLETED** (`docs/sdd/state.yaml`):
  authentication, fixed role catalog with `Role::ADMIN` / `Role::TRAINER`;
  admin panel access (ADMIN | TRAINER); `User::hasRole` / `User::hasAnyRole`;
  policy pattern (`UserPolicy`, `ClientPolicy`, `PlanPolicy`, `TurnoPolicy`,
  `ExercisePolicy`); no RECEPTIONIST role (D-19 option 1 confirmed).
- **SPEC-002 (Client Management) — COMPLETED** (`docs/sdd/state.yaml`): the
  `Client` model is the assignment target (`routine_assignments.client_id`).
- **SPEC-009 (Exercise Catalogue) — COMPLETED** (`docs/sdd/state.yaml`): the
  `Exercise` model is the reference that `RoutineExercise.exercise_id` points to
  (C-09); `Exercise::scopeActive()` / `Exercise::isActive()` is the "currently
  offered" set for new prescriptions (BR-006, AR-04; the consumption point
  documented by SPEC-009 BR-011 / §10 and OQ-07).
- **SPEC-011 (Workout Logs & Progress) — FUTURE; depends on this
  Specification.** The prescription rows defined here (Routine → RoutineDay →
  RoutineExercise → Exercise) are the reference for execution logging (C-10,
  C-11). SPEC-011 MUST also consume the assumption that a client has at most one
  active routine assignment (AR-03) and the client-visibility deferral (AR-08).
- **SPEC-013 (Client Portal) — FUTURE**: client visibility of the assigned
  routine and exercise names in the portal (AR-08).
- Gate decisions: **D-10 option 2**, **D-11 option 2**, **D-12 option 3** —
  pre-approved (NIGHT MODE, `docs/sdd/state.yaml`).
- Confirmed decisions used: C-01 (roles, multi-role), C-03 (Trainer creates and
  assigns routines), C-09 (Routine → RoutineDay → RoutineExercise → Exercise;
  prescription = sets/reps/weight), C-10 (prescription/execution separation),
  C-13 (client isolation), C-15 (presentation contexts).
- Architecture constraints used: ARCHITECTURE §7 (Actions — `AssignRoutine` is an
  example action), §8 (Models with simple domain behavior), §12 (authorization via
  Policies), §16 (Routines: prescription vs execution), §20 (simplest correct
  architecture).
- Flagged assumptions AR-01 to AR-09 require Product Owner confirmation before
  Implementation (see §14.1).

---

## 14. Open Questions

### 14.1 Assumed decisions requiring PO confirmation

These are flagged assumptions. They are needed to make the Specification
implementable, but they are NOT confirmed business rules. They are operational
defaults **consistent with** the pre-approved decisions D-10 / D-11 / D-12 and the
NIGHT MODE task directive for this Specification. Prefix **AR** distinguishes this
Specification's assumptions from SPEC-001 (A-xx), SPEC-002 (AD-xx), SPEC-003
(AP-xx), SPEC-004 (AM-xx), SPEC-005 (PY-xx), SPEC-006 (AS-xx), SPEC-007 (BK-xx),
SPEC-008 (AT-xx) and SPEC-009 (EX-xx).

| ID | Assumption | Source | Blocks / Affects |
| --- | --- | --- | --- |
| AR-01 | Routine version lifecycle: `draft` → `active` → `archived`. New routines are created `draft` (empty allowed); activation moves a draft to `active`; an `active` version becomes `archived` only when superseded by a new version. Archived versions are read-only and no longer assignable, but existing assignments remain valid and displayed. There is NO manual archive action in the MVP. | Task directive "Lifecycle: draft → active → archived"; D-12 option 3 recommended text ("preserves history (archives §12) without breaking active clients"); `analyst-pass-001.md` §5.12 names the draft/active/archived lifecycle as undefined | BR-002, FR-005..FR-007, ERR-006, AF-004, OQ-02 |
| AR-02 | Versioning mechanism = **copy-on-edit with a version lineage**. Each Routine record is one version. Editing an `active` routine creates a new Routine record (copy of the current version's days and set rows with the requested changes applied), `version_number` = previous + 1, linked via `replaces_id` to the replaced version, status `active` (the edit is published immediately; a draft-then-publish step is the documented alternative, OQ-02). The previous version becomes `archived`. Editing a `draft` edits in place; `archived` is read-only. | D-12 option 3 (pre-approved); task directive "Define the mechanism simply (e.g., routines table with version/replaces_id, or copy-on-edit). Keep MVP-simple but faithful to op3." | BR-001, BR-002, FR-006, §10, AC-7, OQ-02 |
| AR-03 | A client has **at most one active routine assignment** at a time; assigning an `active` routine version to a client with an existing active assignment supersedes it (previous active row deactivated, history preserved). Only `active` versions are assignable. A routine version may be assigned to many clients. | Task directive "a client can have one active routine? or more? (document as assumption; SPEC-011 depends)"; D-12 option 3 reassignment semantics (a single current assignment moved explicitly) | BR-007, FR-009..FR-011, AF-002, ERR-008, §10, AC-10..AC-13, OQ-01 (SPEC-011 depends) |
| AR-04 | **New** set rows may only reference `active` exercises (SPEC-009 BR-011 / OQ-07 consumed here, via `Exercise::scopeActive()`). Existing set rows are never modified when an exercise is deactivated or edited; a version copy preserves rows referencing now-inactive exercises; displaying a prescription reads the exercise's current attributes live from the catalogue (no per-prescription snapshot of exercise attributes). | SPEC-009 BR-010/BR-011 ("whether new prescriptions may only use `active` exercises, and whether existing prescriptions keep displaying the deactivated exercise — MUST be defined by SPEC-010"); task directive "document as assumption consistent with SPEC-009 BR-011" | BR-006, FR-008, AC-5, AC-18, OQ-07 |
| AR-05 | Routine name is required free text and **NOT unique**: versions of a lineage share the name, and different lineages may use the same name (two trainers may both have a "Push Day"). No uniqueness constraint is introduced. | Task directive entity list ("name"); no documented uniqueness requirement for routines (unlike Plan SPEC-003 AP-04 and Exercise SPEC-009 BR-003); versioning already makes names repeat within a lineage | BR-010, FR-001, §10, AC-1 |
| AR-06 | Prescription value rules: `target_reps` is a required positive integer; `target_weight` is optional decimal ≥ 0 (absent or zero = bodyweight/no external load); `rest_seconds` is optional integer ≥ 0; `notes` is optional free text. | Task directive entity list ("target_reps, target_weight, optional rest seconds, notes"); domain example "60 kg × 10" (weight + reps present); bodyweight exercises need a no-weight representation | BR-010, FR-008, ERR-005, §10 |
| AR-07 | Day and set numbering: `day_number` and `set_number` are positive integers, **unique within their parent** (routine version / routine day); gaps are allowed (removing a day or set may leave a gap; renumbering is not required). A routine version must have at least one day and each day at least one set row to become `active`; a `draft` may be empty. | Task directive "Validation and edge cases: empty routine, no days, no exercises, day numbering gaps/duplicates, duplicate set numbers"; ordinal-cycle semantics (D-10 option 2) | BR-003, BR-010, FR-007, ERR-002..ERR-004, AF-005 |
| AR-08 | Routine management and assignment are performed by **ADMIN and TRAINER** (full set, no ownership filter by `created_by`); CLIENT has **no direct routine access** in this Specification — client visibility of the assigned routine is deferred to SPEC-011 / SPEC-013. `created_by` is audit-only. | Task directive "routines are created/assigned by ADMIN/TRAINER per C-03"; "Who assigns: ADMIN/TRAINER"; "CLIENT read? (client sees own assigned routine — deferred to SPEC-011/013; decide/document)"; C-03; the `TurnoPolicy`/`ExercisePolicy` ADMIN+TRAINER precedent | §2, FR-001..FR-012, BR-009, BR-011, §9, ERR-007, OQ-08 |
| AR-09 | No hard deletion anywhere in the Routines module: routine versions (archiving instead), days, set rows and assignment history (deactivation instead) are preserved per AGENTS.md §12. | AGENTS.md §12; the preservation pattern of SPEC-001 BR-007 / SPEC-002 BR-006 / SPEC-003 BR-004 / SPEC-009 BR-008; task directive "old versions preserved (archived)" | BR-008, FR-010, ERR-009, AF-006, AC-13, AC-16 |

### 14.2 Open questions to be answered before Implementation (or at latest before Review)

- OQ-01 (AR-03): May a client hold **more than one active routine** at a time
  (e.g., a strength routine AND a cardio routine), or exactly one? This
  Specification assumes **at most one** (consistent with the D-12 option 3
  reassignment model). **SPEC-011 depends on this answer.**
- OQ-02 (AR-01/AR-02): When editing an `active` routine, should the new version be
  created `active` immediately (assumed), or as a `draft` that staff publish
  later? A draft-then-publish step would add a workflow stage and keep the old
  version active longer.
- OQ-03 (AR-07): Should day/set numbering be renumbered to be contiguous
  (1..N without gaps) after removals, or are gaps acceptable? This Specification
  assumes gaps are acceptable.
- OQ-04 (AR-03): Should a `draft` routine be assignable (assumed NO — only
  `active` versions), or may a draft be assigned to clients while work continues?
- OQ-05 (AR-06): Are any maximum limits needed (max days per routine, max sets per
  day, max reps, max weight, max rest seconds)? This Specification imposes none.
- OQ-06 (AR-06): Is `target_weight = 0` the right representation for bodyweight
  exercises, or should bodyweight be explicit (e.g., a flag)? This Specification
  assumes absent/zero = bodyweight.
- OQ-07 (AR-04, SPEC-009 BR-011/OQ-07): Should new set rows be restricted to
  `active` exercises (assumed YES), and how should existing prescriptions display
  a deactivated exercise (assumed: the exercise is still shown with its current
  catalogue attributes)? This Specification defines both per AR-04.
- OQ-08 (AR-08): When does a CLIENT see their assigned routine — in SPEC-011
  (workout logging context) or SPEC-013 (client portal), or both? Deferred by
  design.
- OQ-09 (BR-002): When an archived version still has active assignments (clients
  not yet reassigned), should the staff-facing views flag those clients, or is a
  passive "view assigned clients" (FR-011) sufficient? This Specification assumes
  the passive view.
- OQ-10 (BR-011): Is `created_by` wanted at all as a displayed audit field, and
  should it be visible in lists? This Specification records it on each version and
  shows it in detail/history (FR-003, FR-004).

---

## 15. Related Documents

- Product documentation: `docs/product/product-definition-v0.1.md` (Business
  Areas: "Routines — Personalized exercise plans assigned to clients"; Open
  Questions: "Are routines versioned over time?")
- Domain documentation: `docs/domain/domain-model-v0.1.md` (§Routine,
  §RoutineDay, §RoutineExercise, §Exercise, §WorkoutLog; C-09, C-10, C-11)
- Requirements analysis: `docs/requirements/analyst-pass-001.md` (§5.12 Routines,
  §5.13 Workout Tracking, D-10, D-11, D-12, C-09, C-10, C-11, C-18, R-07, E-07)
- Specifications: `docs/specs/SPEC-001.md` (roles, C-13/C-15),
  `docs/specs/SPEC-002.md` (Client records; trainer–client assignment OQ-02),
  `docs/specs/SPEC-003.md` (Plan lifecycle / unique-name / ADMIN-management
  patterns: AP-02, AP-04), `docs/specs/SPEC-006.md` (ADMIN/TRAINER management
  pattern and assumption convention: AS-01, AS-xx),
  `docs/specs/SPEC-009.md` (Exercise catalogue; BR-007 active set, BR-010/BR-011
  routine-consequence deferral; EX-xx assumption convention)
- Architecture documentation: `docs/architecture/SPEC-001.md`,
  `docs/architecture/SPEC-002.md`, `docs/architecture/SPEC-009.md` (`Exercise`
  model, `scopeActive()`, the `routine_exercises.exercise_id` reference direction),
  `ARCHITECTURE.md` (§7 Actions — `AssignRoutine`, §12 Authorization, §16
  Routines: prescription vs execution, §20 simplest correct architecture)
- Architecture decisions: `docs/adr/ADR-001.md` (role/authorization foundation),
  `docs/adr/ADR-002.md` (module boundary discipline),
  `docs/adr/ADR-003.md` (validation-first representation),
  `docs/adr/ADR-004.md` (status-as-string / model constants)
- Workflow state: `docs/sdd/state.yaml` (NIGHT MODE pre-approval D-10/D-11/D-12
  for SPEC-010; SPEC-001/002/009 completed; SPEC-011 depends on SPEC-010)
- Development rules: `AGENTS.md`

---

*Analyst note: SPEC-010 is analysis-complete. No NOT COVERED blocking business
decision was found: the pre-approved gates D-10 (ordinal days), D-11 (set-level
rows) and D-12 (versioning with reassignment) plus the confirmed decisions (C-01,
C-03, C-09, C-10, C-13, C-15) and the NIGHT MODE task directive cover everything
this Specification requires. Decisions whose behavior is not documented in
`analyst-pass-001.md` (routine lifecycle details, the number of active routines
per client — which SPEC-011 depends on, the active-exercise rule for new
prescriptions — which SPEC-009 BR-011 deferred here, and client-visibility timing)
are routed by the directive to flagged assumptions AR-01..AR-09 (§14.1), each
consistent with D-10/D-11/D-12; they require Product Owner confirmation before
Implementation (or at latest before Review), the same as every prior
Specification.*


