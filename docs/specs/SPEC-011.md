# SPEC-011 — Workout Logs & Progress

## Status

Draft (analysis phase).

This is the eleventh Specification of the MVP. It depends on SPEC-001
(Authentication & Roles), SPEC-002 (Client Management) and SPEC-010 (Routines),
all COMPLETED and implemented in the repository (`docs/sdd/state.yaml`). It also
consumes SPEC-009 (Exercise Catalogue, COMPLETED) directly, because a Workout Log
references a catalogue `Exercise` in the free-log case (C-11). SPEC-011 is the
execution counterpart of the SPEC-010 prescription model: the Routine entities
carry only the **prescription** (C-10); this Specification records what the
client **actually performed** and must never modify the prescription.

**NIGHT MODE notice:** this Specification is produced under the PO pre-approval
("modo nocturno", `docs/sdd/state.yaml` `project.po_decisions`). The gates are
pre-approved as follows:

- **D-11 — option 2** (prescription granularity): **set-level rows** — one
  `RoutineExercise` row per set (implemented by SPEC-010, BR-004). Workout logs
  inherit this granularity: **execution is logged per performed set**, matching
  the set-level prescription row. `analyst-pass-001.md` §D-11.
- **D-12 — option 3** (routine versioning): **versioning with reassignment**
  (implemented by SPEC-010: copy-on-edit, `replaces_id` lineage, explicit
  reassignment). Workout logs reference the prescribed routine version the client
  was on when the log was recorded; **the log must not change when the routine is
  later versioned/edited**. `analyst-pass-001.md` §D-12.
- **C-10 / C-11** (confirmed): **Prescription and Execution are separated**. A
  Workout Log records what the client actually performed and must NOT modify the
  prescribed routine. A Workout Log references the performed `RoutineExercise` or
  `Exercise` — **both cases exist** (client with assigned routine vs free
  logging). `domain-model §WorkoutLog`, `ARCHITECTURE §16`.

All business gates needed by this Specification are covered by the pre-approved
decisions D-11 / D-12, the confirmed decisions (C-01, C-03, C-10, C-11, C-13,
C-15), and the NIGHT MODE task directive for this Specification (which explicitly
routes the log granularity, free-logging-in-MVP, who-records, log immutability,
timestamp and progress-view scope decisions to documented assumptions below —
the same boundary discipline as SPEC-010 AR-01..AR-09 and SPEC-006 AS-xx).
Client self-logging and client visibility of their own logs are explicitly
deferred to SPEC-013 (Client Portal) by design; D-18 option 3 (pre-approved for
SPEC-013) already covers "log workouts" as a portal feature, so no blocking
business decision is deferred that would make this Specification un-implementable.
**No NOT COVERED blocking business decision was found for this Specification.**

**Assumption notice:** this specification contains explicitly flagged assumptions
(WL-01 to WL-11, see §14.1) that fill gaps required to make the specification
implementable. They are operational/technical defaults **consistent with** the
pre-approved decisions D-11 / D-12 and the confirmed decisions C-10 / C-11 / C-03
(set-level log rows matching the set-level prescription; free logging because
C-11 says both cases exist; staff recording because the only implemented
presentation context for this feature is the admin panel, C-15; log immutability
per the preservation pattern of AGENTS.md §12; value conventions borrowed from
SPEC-010 AR-06). **None of them is a confirmed business rule** unless stated
otherwise. Each requires Product Owner confirmation before Implementation (or at
latest before Review).

---

## 1. Objective

Provide workout logging and progress review in the gym management system:

- define the **WorkoutLog** entity — a per-set execution record (D-11 option 2)
  of what a client actually performed (C-10, C-11): client, performed timestamp,
  actual weight, actual reps, optional notes, recorded-by audit;
- each log references **either** a prescribed `RoutineExercise` (the set-level
  prescription row from the routine version the client was on when the log was
  recorded, D-12 option 3) **or** a free catalogue `Exercise` (C-11 — both cases
  exist; clients with an assigned routine vs free logging);
- **prescription vs execution separation** (C-10): logging never modifies the
  prescribed routine; versioning a routine after logs exist must never change
  historical logs (the log keeps its reference to the prescription row/version at
  the time, D-12 option 3);
- staff — **ADMIN and TRAINER** — can log workouts on behalf of clients and
  review workout progress from the admin panel (C-03 "a Trainer may review
  workout progress"; C-15 admin-panel context); client self-logging is deferred
  to SPEC-013 (Client Portal);
- minimal progress review: a client's logged workout history grouped by date, and
  a simple prescription-vs-actual comparison per logged set (C-03; MVP scope
  documented as assumption WL-10);
- logs are immutable in the MVP (no edit, no delete): historical execution data
  is preserved (AGENTS.md §12; assumption WL-04);
- client isolation is preserved: a CLIENT never accesses another client's logs
  through any path defined here (C-13); a client's visibility of their OWN logs
  is deferred to SPEC-013.

This is the execution side of the Training modules: it consumes the
prescriptions defined by SPEC-010 (Routine → RoutineDay → RoutineExercise →
Exercise, set-level rows, versioning with reassignment) and the catalogue defined
by SPEC-009, and it prepares the data the Client Portal (SPEC-013) will later
display to clients.

---

## 2. Actors

Actors are defined by role, not by person (SPEC-001 §2). A single User may hold
one or more roles (confirmed decision C-01).

| Actor | Description |
| --- | --- |
| Anonymous visitor | No access to workout logs or progress views. Log data is never exposed on public pages. |
| ADMIN | Staff who administer the gym. Can record workout logs for any client and view any client's workout history and progress (assumption WL-03, WL-09; C-03 read with the `TurnoPolicy`/`ExercisePolicy`/`RoutinePolicy` ADMIN+TRAINER precedent). |
| TRAINER | Staff who train clients. Can record workout logs for any client and view any client's workout history and progress (C-03 "a Trainer may review workout progress"; assumption WL-03, WL-09). |
| CLIENT | A gym member. Cannot record or manage workout logs in this Specification; visibility of their OWN logs is deferred to SPEC-013 (Client Portal). Client isolation (C-13) always applies — a client never accesses another client's logs. |

Notes:

- A User holding ADMIN and/or TRAINER uses the admin panel; a User holding CLIENT
  uses the client portal (SPEC-001 FR-006, confirmed C-15).
- A User holding both a staff role and CLIENT (e.g., a trainer who also trains)
  is permitted by C-01; the mixed-role behavior is tracked as SPEC-001 OQ-04.
- "Staff" in this Specification means ADMIN and/or TRAINER; there is no
  RECEPTIONIST role (confirmed SPEC-001 A-04 / D-19 option 1; same convention as
  SPEC-005 PY-01, SPEC-007 BK-02, SPEC-008 AT-01, SPEC-009 EX-08, SPEC-010 §2).
- The trainer–client assignment is NOT implemented yet (SPEC-002 OQ-02); it is
  not needed here: workout logs target `Client` records directly, the same way
  SPEC-010 assigns routines to `Client` records (C-03, SPEC-010 §2 note).
- **UI placement constraint (affects the requirement, not the architecture):**
  the log-recording and history/comparison views must be reachable by TRAINER.
  `ClientResource` is ADMIN-only (SPEC-002 BR-004 / `ClientPolicy`), so the
  workout-log UI must NOT live exclusively inside `ClientResource`; a standalone
  staff-facing surface (e.g., a Workout Logs resource in the admin panel's
  Training group) is required so both ADMIN and TRAINER can perform every
  operation in §9 (same constraint the SPEC-010 architecture flagged for FR-011).

---

## 3. Preconditions

1. SPEC-001 is implemented and completed: authentication, fixed role catalog
   (ADMIN, TRAINER, CLIENT), admin panel access (ADMIN or TRAINER), role helpers
   (`hasRole` / `hasAnyRole`), `Role::ADMIN` / `Role::TRAINER` constants,
   policy pattern, no hard deletion of User records (`docs/sdd/state.yaml`,
   ADR-001).
2. SPEC-002 is implemented and completed: the `Client` model exists and is
   management-ready; workout logs target `clients.id` (C-02;
   `docs/sdd/state.yaml`).
3. SPEC-009 is implemented and completed: the `Exercise` model exists
   (`exercises` table) and exposes `Exercise::scopeActive()` / `Exercise::isActive()`
   as the "currently offered" set (SPEC-009 BR-007, BR-011, §10). The free-log
   exercise reference is the consuming-module direction into this table (the
   SPEC-009 BR-011 / §10 boundary discipline, already exercised by SPEC-010).
4. SPEC-010 is implemented and completed: the Routine module exists with
   `Routine` → `RoutineDay` → `RoutineExercise` → `Exercise` (C-09), set-level
   prescription rows (D-11 option 2, BR-004), versioning with reassignment
   (D-12 option 3, BR-001/BR-002), assignments with at most one active
   assignment per client (`Client::currentRoutine()`, `routine_assignments`,
   AR-03) and assignment-history preservation (BR-008, AR-09). This Specification
   consumes those structures as the reference point for execution logging.
5. An authenticated ADMIN or TRAINER exists and can access the admin panel
   (SPEC-001 FR-008, FR-006).
6. The role catalog stays at ADMIN / TRAINER / CLIENT; no RECEPTIONIST role
   (confirmed SPEC-001 A-04).
7. No workout-log tables exist yet; the Workout Logs module is greenfield on top
   of the SPEC-001 / SPEC-002 / SPEC-009 / SPEC-010 foundations.
8. The gate decisions D-11 (option 2) and D-12 (option 3) are pre-approved, and
   the confirmed decisions C-10 / C-11 (prescription/execution separation; log
   references RoutineExercise or Exercise) apply (NIGHT MODE,
   `docs/sdd/state.yaml`).

---

## 4. Functional Requirements

### FR-001 — Record a workout log against a prescribed set (assigned routine)

An ADMIN or TRAINER can record, for a client, what the client actually performed
for a **prescribed set**: select the client; select the `RoutineExercise` (a
set-level prescription row of the client's routine — the form prefills the target
weight/reps from the row); enter the performed timestamp, actual weight, actual
reps and optional notes; save. The log references the `routine_exercise_id`
(BR-004, D-12 option 3). Recording the log NEVER modifies the prescribed routine
(BR-003, C-10).

### FR-002 — Record a free workout log (catalogue exercise)

An ADMIN or TRAINER can record a performed set against any **active** catalogue
`Exercise` (free logging), including for a client with no assigned routine or for
an exercise that is not part of the client's routine (C-11 — both cases exist;
BR-002, BR-005). The log references the `exercise_id` instead of a
`routine_exercise_id` (BR-002). A client without an assigned routine can still be
logged through this path (AF-001).

### FR-003 — View a client's workout history

An ADMIN or TRAINER can view a client's logged workout history, grouped by
performed date (WL-10), showing per-set log rows: exercise, performed timestamp,
actual weight, actual reps, notes, and — when the log references a prescribed
row — the target weight/reps of that prescription. This is the minimal "Trainer
reviews workout progress" view (C-03; scope assumption WL-10).

### FR-004 — View prescription vs actual comparison (minimal)

An ADMIN or TRAINER can view, for a client, a simple comparison of prescription
vs actual for logs that reference `RoutineExercise` rows: target weight/reps vs
actual weight/reps per logged set, aligned to the same exercise/date grouping.
This is the minimal progress view of the MVP; analytics (volume totals, PRs,
charts) are explicitly out of scope (WL-10, §12).

### FR-005 — Display audit metadata

Log lists and detail show the recording staff member (`recorded_by`) and the
record creation timestamp (`logged_at` = `created_at`), so staff can trace who
recorded what and when (BR-010; the `Attendance` `recorded_by` precedent,
SPEC-008 AT-08).

---

## 5. Business Rules

### BR-001 — WorkoutLog is a per-set execution record (D-11 option 2)

A Workout Log is **one record per performed set** (assumption WL-01), matching
the set-level prescription granularity of SPEC-010 BR-004. There is no `sets`
count field on the log and no exercise-level log row: the domain example
"60 kg × 10, 60 kg × 10, 62.5 kg × 8, 62.5 kg × 8" is recorded as **four log
rows** when fully executed. A log records: client, performed timestamp, optional
`routine_exercise_id` (nullable), optional `exercise_id` (nullable), actual
weight, actual reps, optional notes, `recorded_by` (audit). A "workout" for
display purposes is the group of log rows for a client sharing the same
`performed_at` value (WL-01); no separate session entity is introduced in the MVP.

### BR-002 — Exactly one exercise reference (C-11)

A Workout Log references **either** a prescribed `RoutineExercise` (the
assigned-routine case) **or** a free catalogue `Exercise` (the free-log case);
**exactly one** of `routine_exercise_id` / `exercise_id` must be set (ERR-001).
Both cases exist (C-11, domain-model §WorkoutLog). The two columns are nullable
individually but never both-null and never both-set.

### BR-003 — Prescription vs execution separation (C-10)

The Workout Log records execution only. Creating, viewing or editing a log NEVER
creates, modifies or deletes any routine, routine day, routine exercise or
assignment record (C-10, ARCHITECTURE §16). Conversely, creating, editing,
activating, versioning or assigning a routine (SPEC-010) NEVER creates, modifies
or deletes any workout log (SPEC-010 BR-005 / AC-17 boundary, enforced here
symmetrically).

### BR-004 — Routine-exercise reference is version-stable (D-12 option 3)

A log referencing a `RoutineExercise` keeps that reference permanently: it points
to the **specific version's set-level row** that was prescribed when the log was
recorded. When an active routine is edited, SPEC-010 creates a new version with
FRESH `RoutineExercise` rows and archives the old version (SPEC-010 FR-006,
BR-001/BR-002); the log keeps referencing the old version's row and is never
rewritten, re-pointed or deleted (D-12 option 3; AF-004). Because set-level
prescription rows are immutable once their version is active/archived (SPEC-010
BR-001 — draft in-place editing is the only mutation path, and logs can never
reference draft rows, BR-008/ERR-003), the prescription shown next to a
historical log is read directly from the referenced row with no snapshot needed.
A log may only reference a `RoutineExercise` belonging to a routine version the
client **has been assigned to** (active or historical assignment, BR-008; the
assignment history is preserved per SPEC-010 BR-008/AR-09).

### BR-005 — Free exercise reference is a live catalogue reference

A free log references an `Exercise` (C-11, FR-002). **New** free logs may only
reference `active` exercises at the time the log is created (assumption WL-02,
consistent with SPEC-010 AR-04's active-exercise rule for new prescription rows).
Existing logs are never modified, re-pointed or deleted when an exercise is
deactivated or edited (SPEC-009 BR-010/BR-011 consumed here): a log referencing a
now-inactive exercise still displays, reading the exercise's current catalogue
attributes live (no per-log snapshot of exercise attributes; WL-08, the same
stance as SPEC-010 AR-04).

### BR-006 — No hard deletion; logs are immutable in the MVP

Workout log records are never hard-deleted and never edited in the MVP
(assumption WL-04; preservation pattern of AGENTS.md §12, same as SPEC-001 BR-007
/ SPEC-002 BR-006 / SPEC-009 BR-008 / SPEC-010 BR-008). No delete operation is
provided, no delete policy is registered, and no edit path exists. Correction of
an erroneous log is out of scope (see OQ-01); the log persists as recorded.

### BR-007 — Logging and review is ADMIN/TRAINER

Only ADMIN and TRAINER can record workout logs and view workout history/progress
(C-03 "a Trainer may review workout progress"; C-15 admin-panel context;
assumption WL-03). CLIENT has no log access in this Specification; a client's
visibility of their OWN logs is deferred to SPEC-013 (D-18 option 3 pre-approved
there). Anonymous visitors have no access (ERR-006). A CLIENT must never access
another client's logs (C-13).

### BR-008 — Validation invariants

- `client_id`: required, FK to `clients.id` (ERR-008).
- `performed_at`: required timestamp; **must not be in the future**; backdating
  is allowed so staff can record a session performed earlier; defaults to now
  (assumption WL-05).
- `actual_reps`: required positive integer (WL-06).
- `actual_weight`: optional decimal ≥ 0; absent or zero means bodyweight/no
  external load (WL-06, the SPEC-010 AR-06 convention).
- `notes`: optional free text (WL-06).
- Exercise reference (BR-002): exactly one of `routine_exercise_id` /
  `exercise_id` (ERR-001).
- `routine_exercise_id` reference: must exist (ERR-002) AND belong to a routine
  version the client has been assigned to — active or historical assignment
  (ERR-003; SPEC-010 BR-007/AR-03/AR-09). Draft versions are never valid targets
  because drafts are never assignable (SPEC-010 ERR-008), so logs can never
  reference working-copy rows that draft editing could mutate.
- `exercise_id` reference: must exist (ERR-002) and be `active` for a new free
  log (ERR-005, BR-005).
- `recorded_by`: required, set to the authenticated staff User; never a form
  field (BR-010; the `Attendance` `recorded_by` precedent, SPEC-008 AT-08).

### BR-009 — Audit tracking and role-based (not ownership-based) access

Each log records `recorded_by` (the staff User who entered it) and `logged_at`
(the record's `created_at`), which are informational/audit (FR-005). Authorization
is role-based, not ownership-based: any ADMIN or TRAINER can log and review any
client's logs regardless of who recorded them (assumption WL-09; the same stance
as SPEC-010 BR-011 / AR-08, applied to logs; the trainer–client assignment is not
implemented, SPEC-002 OQ-02).

### BR-010 — Logging has no membership/access precondition

Workout logging is not gated on an active membership or a qualifying access
decision (SPEC-008). Nothing in the product/domain documentation ties workout
logging to membership status; no such rule is introduced (no invented business
rule; the gym may log any client's workouts).

---

## 6. Main Flow

1. An authenticated ADMIN or TRAINER opens the Workout Logs section of the admin
   panel (FR-001; a standalone staff-facing surface, see §2 UI-placement note).
2. Staff select the client for whom a workout is being recorded.
3. Staff record a performed set (FR-001 or FR-002):
   - **Assigned-routine case**: the form shows the client's current routine's
     set-level prescription rows (from `Client::currentRoutine()`, SPEC-010
     AR-03) prefilled with the target weight/reps; staff pick the prescribed row
     and enter the actual weight/reps/notes.
   - **Free-log case**: staff pick any `active` catalogue `Exercise` (FR-002,
     C-11) and enter the actual weight/reps/notes.
   - Staff may adjust `performed_at` (default now, never future; backdating
     allowed, WL-05).
4. The system validates (BR-008): exactly one exercise reference (ERR-001),
     reference validity (ERR-002/ERR-003/ERR-005), value invariants (ERR-004).
5. The log is persisted with `recorded_by = auth()->id()` and `logged_at =
   created_at` (BR-009); the prescribed routine is untouched (BR-003).
6. Staff can view the client's workout history grouped by date (FR-003) and the
   minimal prescription-vs-actual comparison (FR-004).
7. Later, staff edit the client's routine (SPEC-010 FR-006 — a new version is
   created and the old archived; assignments untouched). The recorded logs keep
   referencing the old version's set rows and display unchanged (BR-004, D-12
   option 3).

---

## 7. Alternative Flows

### AF-001 — Client without an assigned routine

The client has no active assignment (`Client::currentRoutine()` returns `null`).
The assigned-routine selector shows an empty state; logging is still possible
through the free-log path (FR-002, BR-002) — the log references an `active`
catalogue `Exercise` (ERR-003 does not apply because no `routine_exercise_id` is
used).

### AF-002 — Logging a session performed under a previous routine version

Staff record a log whose `performed_at` predates a reassignment (D-12 option 3:
the client was reassigned after the session). The staff pick the
`RoutineExercise` from the previous version's rows; the validation accepts it
because the client has a **historical** assignment to that version (BR-004,
BR-008; assignment history is preserved per SPEC-010 BR-008/AR-09). The log's
prescription display reads the previous version's immutable set row.

### AF-003 — Exercise deactivated after a free log

An exercise referenced by existing free logs is deactivated (SPEC-009 FR-005).
Existing logs are unchanged and still display, reading the exercise's current
catalogue attributes (BR-005, WL-08). New free logs cannot reference the now
inactive exercise (ERR-005).

### AF-004 — Routine versioned after logs exist

An `active` routine with recorded logs is edited: SPEC-010 creates a new version
(copy-on-edit, D-12 option 3) and archives the previous version. No log is
created, modified, re-pointed or deleted by the versioning operation (BR-003,
BR-004); logs keep referencing the old version's rows and display the same
prescription as at log time. This is the direct consequence of the D-12
pre-approval: "the log must not change when the routine is later versioned".

### AF-005 — Erroneous log

Staff notice a recorded log is wrong (e.g., wrong weight). No edit or delete
operation exists in the MVP (BR-006, immutability); the log persists. The
operational workaround (record the correct set as a new log, optionally with a
note) is a staff procedure, not a system feature; an edit/delete-with-audit
capability is tracked as OQ-01.

---

## 8. Error Cases

### ERR-001 — Both or neither exercise reference

Condition: a log sets both `routine_exercise_id` and `exercise_id`, or neither.

Expected behavior: rejected with a validation error (BR-002).

### ERR-002 — Nonexistent reference

Condition: `routine_exercise_id` or `exercise_id` points to a row that does not
exist.

Expected behavior: rejected; both are foreign keys (BR-008).

### ERR-003 — Routine-exercise row not from an assigned version

Condition: `routine_exercise_id` belongs to a routine version the client has
never been assigned to — including any `draft` version (drafts are never
assignable, SPEC-010 ERR-008).

Expected behavior: rejected with a validation error (BR-004, BR-008).

### ERR-004 — Invalid performed values

Condition: missing/zero/negative `actual_reps`, negative `actual_weight`, or a
`performed_at` in the future.

Expected behavior: rejected with a validation error (BR-008, WL-05, WL-06).

### ERR-005 — Free log referencing an inactive exercise

Condition: a new free log references an `inactive` exercise.

Expected behavior: rejected; only `active` catalogue exercises can be referenced
by new free logs (BR-005, WL-02).

### ERR-006 — Unauthorized access

Condition: an anonymous visitor or a CLIENT attempts to record a log or view
workout history/progress.

Expected behavior: access denied (redirect for anonymous; 403 for CLIENT)
(BR-007; C-13).

### ERR-007 — Attempted edit or deletion

Condition: an attempt is made to edit or delete an existing log.

Expected behavior: no such operation exists; the record persists (BR-006). There
is no edit/delete ability in the policy and no edit/delete path in the UI (§9).

### ERR-008 — Nonexistent client

Condition: a log is recorded for a client id that does not exist.

Expected behavior: rejected; `client_id` is a foreign key (BR-008).

---

## 9. Authorization

| Operation | Anonymous | ADMIN | TRAINER | CLIENT |
| --- | --- | --- | --- | --- |
| Record workout log (assigned routine) | Denied | Allowed (BR-007, C-03) | Allowed (BR-007, C-03) | Denied (deferred to SPEC-013) |
| Record free workout log (catalogue exercise) | Denied | Allowed (BR-007, C-03) | Allowed (BR-007, C-03) | Denied (deferred to SPEC-013) |
| View client workout history | Denied | Allowed (BR-007, C-03) | Allowed (BR-007, C-03) | Denied (own logs: SPEC-013) |
| View prescription vs actual comparison | Denied | Allowed (BR-007, C-03) | Allowed (BR-007, C-03) | Denied (SPEC-013) |
| Edit / delete log | Denied | Denied (no such operation — BR-006) | Denied (no such operation — BR-006) | Denied (no such operation — BR-006) |
| Access another client's logs | Denied | Per rules (role-based, BR-009) | Per rules (role-based, BR-009) | Denied always (C-13) |

Notes:

- A User holding several roles receives the union of the column permissions
  (SPEC-001 BR-002); an ADMIN or TRAINER who is also CLIENT can log and review in
  the admin panel.
- Authorization is enforced server-side via Policies (e.g., a `WorkoutLogPolicy`
  granting create/view to ADMIN **or** TRAINER, with no delete ability);
  frontend-only restrictions are never sufficient (AGENTS.md §17).
- Logging and review are role-based, not ownership-based: any ADMIN or TRAINER
  may log for / review any client regardless of `recorded_by` (BR-009, WL-09).
- **UI placement constraint (requirement-level):** because `ClientResource` is
  ADMIN-only (SPEC-002 `ClientPolicy`), the TRAINER column above requires a
  standalone staff-facing surface for logs (a Workout Logs resource in the admin
  panel's Training group is the natural placement); the operations must never be
  reachable only through ADMIN-only screens (see §2 note).
- State/validation rules (ERR-001..ERR-005) are NOT authorization rules: they are
  enforced by form validation and the create path, so an authorized user still
  cannot record an invalid log (same stance as SPEC-010 §9 note).

---

## 10. Data Changes

This Specification describes the information that must exist; the exact
persistence schema, table names and model names are defined by the Architect
(following the repo's English naming convention, the same way
`exercises`/`Exercise`, `turnos`/`Turno`, `routines`/`Routine` were chosen:
expected `workout_logs` table with a `WorkoutLog` model).

Created:

- **Workout log records** (`workout_logs` table; one row per performed set,
  D-11 option 2 / BR-001):
  - `client_id` — FK to `clients.id`, NOT NULL (BR-008);
  - `performed_at` — timestamp, NOT NULL: when the set was performed (default
    now, never future, backdating allowed — WL-05). The grouping key for a
    "workout" in the history/comparison views (WL-01);
  - `routine_exercise_id` — FK to `routine_exercises.id`, nullable (BR-002); the
    prescribed set row the client was on (D-12 option 3, BR-004);
  - `exercise_id` — FK to `exercises.id`, nullable (BR-002); the free-log
    reference (C-11, BR-005);
  - `actual_weight` — decimal(6,2), nullable (WL-06; absent/zero = bodyweight,
    the SPEC-010 AR-06 convention);
  - `actual_reps` — unsigned integer, NOT NULL (WL-06);
  - `notes` — text, nullable (WL-06);
  - `recorded_by` — FK to `users.id`, NOT NULL: the staff User who recorded the
    log (BR-009; the `Attendance.recorded_by` precedent, SPEC-008 AT-08);
  - `created_at` / `updated_at` — timestamps; `logged_at` is `created_at`
    (BR-009, FR-005).
  - **Exactly-one-reference invariant** (BR-002): at most one of
    `routine_exercise_id` / `exercise_id` is set and at least one is set. Per the
    repo's validation-first convention (ADR-003), this is enforced by the create
    path/form validation; the Architect may add a DB CHECK constraint
    (`(routine_exercise_id IS NULL) != (exercise_id IS NULL)`) as optional
    hardening — no business difference.
  - Suggested indexes (Architect decision): on `(client_id, performed_at)` for
    the history list (FR-003); on `routine_exercise_id` for the comparison view
    and for "which logs reference this prescription row" (FR-004, BR-004); on
    `exercise_id` for free-log lookups (FR-002); on `recorded_by` for audit
    queries (BR-009).
  - FK behavior recommendation (Architect decision, consistent with the repo
    default): `restrictOnDelete` on all four FKs. Logs are never deleted
    (BR-006); clients, exercises and routine versions are never hard-deleted in
    their modules (SPEC-002 BR-006, SPEC-009 BR-008, SPEC-010 BR-008), so the
    restrict guard is a safety net. Draft-editing deletes working-copy
    `routine_exercises` rows (SPEC-010 §10/§11) but logs can never reference
    draft rows (ERR-003), so no log is ever blocked by or lost in that cascade.

Modified:

- No existing table is modified. Workout logs themselves are never modified after
  creation (BR-006, immutability).

Deleted:

- No hard deletion of workout log records in the MVP (BR-006, ERR-007); no
  delete operation.

No seeder is required: logs are created by staff in the admin panel only.

No change to the `users`, `roles`, `clients`, `exercises`, `routines`,
`routine_days`, `routine_exercises` or `routine_assignments` tables is made by
this Specification. The only added reference directions are
`workout_logs.routine_exercise_id` → `routine_exercises.id` and
`workout_logs.exercise_id` → `exercises.id` (the consuming-module reference
direction documented by SPEC-009 BR-011 / §10, already exercised by SPEC-010).

---

## 11. Acceptance Criteria

- [ ] AC-1: ADMIN or TRAINER can record a log for a client referencing a
  prescribed `RoutineExercise` (a set-level row of the client's assigned routine
  version); the log persists with actual weight, actual reps, performed_at,
  notes, `recorded_by` and `logged_at` (FR-001, BR-001, BR-008, BR-009).
- [ ] AC-2: Recording a log never creates, modifies or deletes any routine, day,
  set-row or assignment record (BR-003, C-10); the prescription is byte-for-byte
  unchanged after logging.
- [ ] AC-3: A log with both `routine_exercise_id` and `exercise_id`, or with
  neither, is rejected (ERR-001, BR-002).
- [ ] AC-4: A log referencing a `RoutineExercise` from a routine version the
  client was never assigned to — including a draft version — is rejected
  (ERR-003, BR-004, BR-008).
- [ ] AC-5: Free logging works: a log referencing an `active` catalogue
  `Exercise` is accepted, including for a client with no assigned routine
  (FR-002, AF-001, BR-002).
- [ ] AC-6: A new free log referencing an `inactive` exercise is rejected
  (ERR-005, BR-005).
- [ ] AC-7: Invalid performed values — missing/zero/negative `actual_reps`,
  negative `actual_weight`, future `performed_at` — are rejected (ERR-004,
  BR-008).
- [ ] AC-8: ADMIN or TRAINER can view a client's workout history grouped by
  performed date, with per-set rows showing exercise, actual weight/reps and,
  when referenced, the prescription target (FR-003).
- [ ] AC-9: ADMIN or TRAINER can view the minimal prescription-vs-actual
  comparison for logged sets that reference `RoutineExercise` rows (FR-004).
- [ ] AC-10: After a routine is versioned (SPEC-010 FR-006, D-12 option 3),
  existing logs keep referencing the old version's rows and are unchanged; the
  versioning operation creates/modifies/deletes no log (BR-004, AF-004).
- [ ] AC-11: Deactivating or editing an exercise (SPEC-009) never creates,
  modifies or deletes any log; a log referencing a now-inactive exercise still
  displays (AF-003, BR-005; SPEC-009 BR-010/BR-011 consumed here).
- [ ] AC-12: No edit or delete operation exists for logs; created logs persist
  (ERR-007, BR-006).
- [ ] AC-13: A CLIENT or anonymous visitor cannot record logs or view workout
  history/progress (403 or redirect) (ERR-006, BR-007, C-13).
- [ ] AC-14: A multi-role ADMIN + CLIENT or TRAINER + CLIENT user can log and
  review in the admin panel (SPEC-001 BR-002).
- [ ] AC-15: A log for a nonexistent client is rejected (ERR-008).

**Test plan (to be executed at Implementation; aligned with the existing Pest
layout under `tests/`, `tests/Pest.php` helpers `role()` / `userWithRoles()`,
`RefreshDatabase`, Livewire component tests as used in
`RoutineManagementTest` / `AttendanceManagementTest`):**

- `tests/Feature/Admin/WorkoutLogManagementTest.php` (Livewire): record an
  assigned-routine log with actual weight/reps/notes persisted (AC-1); assert the
  prescription rows and assignment rows are unchanged after logging (AC-2);
  both/neither reference rejected (AC-3); reference from a never-assigned or
  draft version rejected (AC-4); free log accepted, including for a client with
  no routine (AC-5); inactive-exercise free log rejected (AC-6); invalid values —
  missing/zero/negative reps, negative weight, future performed_at — rejected
  (AC-7); history grouped by date (AC-8); comparison view shows target vs actual
  (AC-9); versioning a routine leaves existing logs unchanged and still pointing
  at the old version's rows (AC-10); deactivating an exercise does not modify
  logs and a log referencing it still displays (AC-11); no edit/delete actions
  exist (AC-12); nonexistent client rejected (AC-15).
- `tests/Feature/Admin/WorkoutLogPolicyTest.php`: ADMIN and TRAINER can
  create/view logs; CLIENT and anonymous denied (403); a multi-role ADMIN +
  CLIENT or TRAINER + CLIENT user can log and review; no delete ability exists
  for anyone (AC-13, AC-12, AC-14).
- `tests/Unit/WorkoutLogTest.php`: the exactly-one-reference invariant (BR-002,
  ERR-001); value conventions (WL-06) and `recorded_by` audit (BR-009); the
  boundary rule that creating a log creates no routine/assignment/exercise record
  (BR-003, AC-2); the version-stability rule — a log's `routine_exercise_id`
  belongs to the client's assigned version at log time and survives a later
  versioning operation (BR-004, AC-10); free-log active-exercise rule (BR-005,
  AC-6); `Client::workoutLogs()` ordering by `performed_at` (FR-003).

---

## 12. Out of Scope

The following are explicitly NOT part of this Specification:

- **Client self-logging and client visibility of their own logs/progress** —
  recording or viewing logs from the client portal, and any progress display to
  the CLIENT (SPEC-013; D-18 option 3 pre-approved for SPEC-013; C-13 isolation
  always applies).
- **Notifications** to clients or staff about logged workouts, missed workouts or
  routine changes (depends on SPEC-013 client-communication infrastructure).
- **Advanced progress analytics** — charts, volume/tonnage totals, one-rep-max
  estimation, PR detection, period-over-period trends. Only the minimal history
  (FR-003) and per-set prescription-vs-actual comparison (FR-004) are in scope
  (WL-10).
- **Editing or deleting logs** — logs are immutable in the MVP (BR-006); a
  correction-with-audit capability is tracked as OQ-01.
- **A separate "workout session" entity** — a workout is the group of log rows
  sharing `performed_at` (WL-01); a session/summary entity is deferred (OQ-03).
- **RPE / perceived-exertion tracking** — `analyst-pass-001.md` §5.13 lists RPE
  as a possible logged field but never defines it; it is omitted from the MVP
  (OQ-02).
- **Rest-time / rest-timer logging and enforcement** — the prescription's
  `rest_seconds` (SPEC-010 AR-06) is not recorded or enforced by logs.
- **Real-time coaching, auto-feedback or prescription adjustment from logs** —
  the log never feeds back into the prescription automatically (BR-003, C-10).
- **Bulk import/export** of workout logs.
- **Logging tied to membership/access state** — no membership or access
  precondition applies (BR-010).
- **The trainer–client assignment** — logging targets `Client` records directly
  (SPEC-002 OQ-02; SPEC-010 §2 note).
- **Hard deletion of workout log data** — no delete operation (BR-006, ERR-007).

---

## 13. Dependencies

- **SPEC-001 (Authentication & Roles) — COMPLETED** (`docs/sdd/state.yaml`):
  authentication, fixed role catalog with `Role::ADMIN` / `Role::TRAINER`; admin
  panel access (ADMIN | TRAINER); `User::hasRole` / `User::hasAnyRole`; policy
  pattern; no hard deletion of User records (ADR-001).
- **SPEC-002 (Client Management) — COMPLETED** (`docs/sdd/state.yaml`): the
  `Client` model is the log target (`workout_logs.client_id`); ADMIN-only
  `ClientResource` — the constraint that forces a standalone staff-facing log
  surface (§2, §9).
- **SPEC-009 (Exercise Catalogue) — COMPLETED** (`docs/sdd/state.yaml`): the
  `Exercise` model is the free-log reference (`workout_logs.exercise_id`, C-11);
  `Exercise::scopeActive()` / `Exercise::isActive()` is the "currently offered"
  set for new free logs (BR-005, WL-02; the consuming-module reference direction
  documented by SPEC-009 BR-011 / §10).
- **SPEC-010 (Routines) — COMPLETED** (`docs/sdd/state.yaml`; this Specification
  depends on it): the set-level prescription rows (`routine_exercises`, D-11
  option 2), the versioning-with-reassignment model (D-12 option 3, BR-001/BR-002,
  AR-02), the assignment semantics with at most one active assignment per client
  (`Client::currentRoutine()`, BR-007/AR-03) and preserved assignment history
  (BR-008/AR-09). SPEC-011 consumes SPEC-010's BR-005/AC-17 boundary
  symmetrically (BR-003) and its AR-06 value conventions (WL-06).
- **SPEC-013 (Client Portal) — FUTURE**: client self-logging and client
  visibility of their own logs/progress (D-18 option 3 pre-approved;
  `docs/sdd/state.yaml`).
- Gate decisions: **D-11 option 2** (set-level prescription → per-set log rows)
  and **D-12 option 3** (versioning with reassignment → version-stable log
  references) — pre-approved (NIGHT MODE, `docs/sdd/state.yaml`).
- Confirmed decisions used: C-01 (roles, multi-role), C-03 (Trainer reviews
  workout progress), C-10 (prescription/execution separation), C-11 (log
  references RoutineExercise or Exercise — both cases), C-13 (client isolation),
  C-15 (presentation contexts).
- Architecture constraints used: ARCHITECTURE §7 (Actions — `AssignRoutine`
  precedent for rule-bearing operations if an Action is warranted), §8 (Models
  with simple domain behavior), §12 (authorization via Policies), §16 (Routines:
  prescription vs execution), §20 (simplest correct architecture).
- Flagged assumptions WL-01..WL-11 require Product Owner confirmation before
  Implementation (see §14.1).

---

## 14. Open Questions

### 14.1 Assumed decisions requiring PO confirmation

These flagged assumptions are operational/technical defaults **consistent with**
the pre-approved decisions D-11 / D-12, the confirmed decisions C-10 / C-11 /
C-03 / C-15, and the NIGHT MODE task directive for this Specification. They are
NOT confirmed business rules unless stated otherwise. Prefix **WL** distinguishes
this Specification's assumptions from SPEC-001 (A-xx), SPEC-002 (AD-xx),
SPEC-003 (AP-xx), SPEC-004 (AM-xx), SPEC-005 (PY-xx), SPEC-006 (AS-xx),
SPEC-007 (BK-xx), SPEC-008 (AT-xx), SPEC-009 (EX-xx) and SPEC-010 (AR-xx).

| ID | Assumption | Source | Blocks / Affects |
| --- | --- | --- | --- |
| WL-01 | Logging granularity = **one WorkoutLog row per performed set**, matching the set-level prescription rows (D-11 option 2). There is no `actual_sets` count field and no exercise-level log row. A "workout" for display is the group of log rows for a client sharing the same `performed_at` value; **no separate session entity** is introduced in the MVP. | Task directive "Decide granularity carefully ... the domain example ('60 kg × 10' twice) suggests set-level logging too"; D-11 option 2 pre-approved | BR-001, FR-001..FR-004, §10, OQ-03 |
| WL-02 | **Free logging is allowed in the MVP** (C-11 says both cases exist): the log form offers "from routine" (prescribed `RoutineExercise`) or "free exercise" (catalogue `Exercise`); new free logs may only reference `active` exercises (consistent with SPEC-010 AR-04's active-exercise rule for new prescription rows). | Task directive "Decide: is free logging allowed in MVP? Document as assumption"; C-11 (both cases exist) | FR-002, BR-002, BR-005, ERR-005, AC-5/AC-6 |
| WL-03 | **Who records: ADMIN/TRAINER on behalf of the client** in this Specification — the only implemented presentation context for this feature is the admin panel (C-15) and the CLIENT portal does not exist yet (SPEC-013). Client self-logging is deferred to SPEC-013 (D-18 option 3, pre-approved there, includes "log workouts"). | Task directive "who logs ... decide/document; C-03 says trainer reviews; C-11 says client performs"; C-15; analyst-pass-001 §5.13 ("Who records: ... Not defined (ties to D-18)") | §2, BR-007, §9, AC-13 |
| WL-04 | **Logs are immutable in the MVP**: no edit, no delete, no status transition. Correction of an erroneous log is a staff procedure (record a new log), not a system feature. | Task directive "editing/deleting logs (immutability? document as assumption)"; preservation pattern of AGENTS.md §12 / SPEC-005 PY-05 (confirmed payments immutable) | BR-006, FR-001, ERR-007, AF-005, AC-12, OQ-01 |
| WL-05 | `performed_at` is required, defaults to now, **must not be in the future**; **backdating is allowed** so staff can record a session performed earlier (no maximum backdate limit; OQ-04). | Task directive "past/future timestamps"; SPEC-005 PY-03 (backdating allowed for payment dates) | BR-008, ERR-004, §6, OQ-04 |
| WL-06 | Performed-value conventions: `actual_reps` required positive integer; `actual_weight` optional decimal ≥ 0, absent/zero = bodyweight; `notes` optional free text. | Task directive "weight/reps validation, negative values"; SPEC-010 AR-06 (same conventions for the prescription) | BR-008, ERR-004, §10, AC-7 |
| WL-07 | A `routine_exercise_id` reference is valid only when the `RoutineExercise` belongs to a routine version the client **has been assigned to** (active or historical assignment; drafts are never valid because drafts are never assignable — SPEC-010 ERR-008). No per-timestamp "which version was active at performed_at" check exists in the MVP because assignments do not record a deactivation timestamp. | Task directive "log referencing exercise not in the client's routine"; D-12 option 3 (logs reference the version the client was on); SPEC-010 BR-007/AR-03/AR-09 (history preserved) | BR-004, BR-008, ERR-003, AF-002, AC-4, OQ-05 |
| WL-08 | Log display reads the exercise's **current** catalogue attributes live (no per-log snapshot), and the prescription shown next to a log is read from the immutable `routine_exercise` row (no snapshot needed — set rows never change once their version is active/archived). | SPEC-010 AR-04 ("displaying a prescription reads the exercise's current attributes from the catalogue — no per-prescription snapshot") extended to logs | BR-004, BR-005, AF-003, FR-003 |
| WL-09 | Logging and review are **role-based, not ownership-based**: any ADMIN or TRAINER may log for / review any client regardless of `recorded_by`; the trainer–client assignment is not implemented (SPEC-002 OQ-02). | SPEC-010 BR-011 / AR-08 stance ("any ADMIN or TRAINER may operate on any routine regardless of created_by") extended to logs; task directive "who views (TRAINER/ADMIN)" | §2, BR-009, §9, AC-13/AC-14 |
| WL-10 | **Minimal progress-review scope**: (a) a client's workout history grouped by performed date with per-set rows and the prescription target when referenced (FR-003); (b) a simple prescription-vs-actual comparison per logged set (FR-004). No analytics, charts, volume totals, PRs or period trends in the MVP. | Task directive "keep MVP minimal, document scope as assumption"; C-03 ("reviews workout progress") | FR-003, FR-004, §12 |
| WL-11 | `recorded_by` is a **required audit field** set from the authenticated staff User at creation (never a form field); `logged_at` is the record's `created_at`. | Task directive "recorded_by?"; the `Attendance.recorded_by` precedent (SPEC-008 BR-011 / AT-08) | BR-008, BR-009, FR-005, §10, AC-1 |

### 14.2 Open questions to be answered before Implementation (or at latest before Review)

- OQ-01 (WL-04): Should a log-correction capability (edit or delete with an
  audit trail) be added later? This Specification assumes logs are immutable in
  the MVP; an erroneous log persists (AF-005).
- OQ-02 (analyst-pass-001 §5.13): Should an RPE / perceived-exertion field be
  added to logs in a later iteration? The source documents list it as a possible
  logged field but never define it; this Specification omits it.
- OQ-03 (WL-01): Does the future client portal (SPEC-013) self-logging need a
  first-class "workout session" entity (a workout header with per-exercise /
  per-set children), or is the `performed_at` grouping sufficient? The MVP
  assumes the flat per-set log grouped by `performed_at`.
- OQ-04 (WL-05): Is a maximum backdate limit needed for `performed_at` (e.g.,
  no logs older than N days)? This Specification imposes none.
- OQ-05 (WL-07): Should the "which version was the client on at performed_at"
  check be introduced later (requires recording an assignment deactivation
  timestamp on `routine_assignments`)? The MVP validates against the full
  assignment history without a time check.
- OQ-06 (WL-10): Should the progress view be grouped/filtered by routine version
  or routine day (e.g., "Day 1 of routine X over the last month")? The MVP
  groups by performed date only.
- OQ-07 (WL-03): When SPEC-013 implements client self-logging, must a CLIENT's
  self-recorded logs be editable by the client (and by staff), or do the
  immutability rules (WL-04) apply there too? Deferred by design to SPEC-013.

---

## 15. Related Documents

- Product documentation: `docs/product/product-definition-v0.1.md` (Business
  Areas: "Routines — Personalized exercise plans assigned to clients"; Open
  Questions: "Are routines versioned over time?")
- Domain documentation: `docs/domain/domain-model-v0.1.md` (§WorkoutLog,
  §Routine, §RoutineDay, §RoutineExercise, §Exercise; C-09, C-10, C-11)
- Requirements analysis: `docs/requirements/analyst-pass-001.md` (§5.13 Workout
  Tracking, D-11, D-12, C-10, C-11, C-03, C-13, D-18)
- Specifications: `docs/specs/SPEC-001.md` (roles, C-13/C-15),
  `docs/specs/SPEC-002.md` (Client records; ADMIN-only ClientResource),
  `docs/specs/SPEC-006.md` (ADMIN/TRAINER management pattern and assumption
  convention: AS-01, AS-xx), `docs/specs/SPEC-008.md` (`recorded_by` precedent:
  AT-01, AT-08, BR-011), `docs/specs/SPEC-009.md` (Exercise catalogue; BR-007
  active set, BR-010/BR-011 consumption; EX-xx assumption convention),
  `docs/specs/SPEC-010.md` (Routines; BR-001..BR-011, AR-01..AR-09, D-11/D-12,
  AC-17 boundary)
- Architecture documentation: `docs/architecture/SPEC-010.md` (Routine module:
  models, Actions, policies, prescription-vs-execution boundary, §16),
  `docs/architecture/SPEC-008.md` (`recorded_by` user-FK precedent),
  `docs/architecture/SPEC-009.md` (`Exercise` model, `scopeActive()`, the
  consuming-module reference direction), `ARCHITECTURE.md` (§7 Actions —
  `AssignRoutine`, §8 Models, §12 Authorization, §16 Routines: prescription vs
  execution, §20 simplest correct architecture)
- Architecture decisions: `docs/adr/ADR-001.md` (role/authorization
  foundation), `docs/adr/ADR-002.md` (module boundary discipline),
  `docs/adr/ADR-003.md` (validation-first representation / decimal convention),
  `docs/adr/ADR-004.md` (status-as-string / stored-state precedent)
- Workflow state: `docs/sdd/state.yaml` (NIGHT MODE pre-approval D-11/D-12 for
  SPEC-011; SPEC-001/002/009/010 completed; SPEC-011 `analysis` in progress;
  SPEC-013 depends on SPEC-011)
- Development rules: `AGENTS.md`

---

*Analyst note: SPEC-011 is analysis-complete. No NOT COVERED blocking business
decision was found: the pre-approved gates D-11 (set-level prescription → per-set
log rows) and D-12 (versioning with reassignment → version-stable log
references), the confirmed decisions C-10 / C-11 (prescription/execution
separation; the log references RoutineExercise or Exercise — both cases) and
C-03 / C-13 / C-15 (trainer reviews progress; client isolation; admin-panel
context), and the NIGHT MODE task directive cover everything this Specification
requires. Decisions whose behavior is not documented in `analyst-pass-001.md`
(log granularity, free-logging-in-MVP, who records, log immutability, timestamp
rules, progress-view scope) are routed by the directive to flagged assumptions
WL-01..WL-11 (§14.1), each consistent with D-11/D-12/C-10/C-11; they require
Product Owner confirmation before Implementation (or at latest before Review),
the same as every prior Specification. Client self-logging and client visibility
of their own logs are deferred to SPEC-013 by design (D-18 option 3 pre-approved
there).*
