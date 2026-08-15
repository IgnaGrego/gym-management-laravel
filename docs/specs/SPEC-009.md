# SPEC-009 — Exercise Catalogue

## Status

Draft (analysis phase).

This is the ninth Specification of the MVP. It depends on SPEC-001 (Authentication
& Roles) and SPEC-002 (Client Management), both COMPLETED and implemented in the
repository (`docs/sdd/state.yaml`). SPEC-010 (Routines) will depend on this
Specification, so the exercise catalogue is defined explicitly and kept
routine-friendly: the Exercise entity is the reference that RoutineExercise rows
(SPEC-010, gate D-10/D-11/D-12) will point to.

**NIGHT MODE notice:** this Specification is produced under the PO pre-approval
("modo nocturno", `docs/sdd/state.yaml` `project.po_decisions`). The gate is
pre-approved as follows:

- **D-20 — option 2** (exercise catalogue attributes + management): an Exercise
  has the attributes **name, muscle group, equipment, PLUS instructions/video and
  difficulty**, and the catalogue is **managed by ADMIN and TRAINER**
  (trainer-authored content is wanted).
- **C-09** (confirmed): a Routine is organized in days
  (`RoutineDay → RoutineExercise → Exercise`); a RoutineExercise references an
  Exercise. The Exercise is the catalogue-side of that reference.

All business gates needed by this Specification are covered by the pre-approved
decision D-20 (option 2) above or by confirmed decisions (C-01, C-09, C-13,
C-15). **No NOT COVERED blocking business decision was found for this
Specification.** Items whose behavior cannot be defined before routines exist
(the effect of deactivation/editing on routine prescriptions) are explicitly
deferred to SPEC-010 by design, the same way SPEC-006 deferred booking
consequences to SPEC-007 (BR-013).

**Assumption notice:** this specification contains explicitly flagged assumptions
(EX-01 to EX-10, see §14.1) that fill gaps required to make the specification
implementable. They are operational defaults consistent with the pre-approved
decision D-20 (field representation and optionality of the approved attributes),
catalogue invariants consistent with the established patterns (unique name like
SPEC-003 AP-04; active/inactive lifecycle like SPEC-003 AP-02 and SPEC-006 AS-07;
no hard deletion per AGENTS.md §12), or authorization consequences of
SPEC-010/011/013 not being implemented yet (CLIENT has no direct catalogue
access). **None of them is a confirmed business rule** unless stated otherwise.
Each requires Product Owner confirmation before Implementation (or at latest
before Review).

---

## 1. Objective

Provide the exercise catalogue of the gym management system:

- define the **Exercise** entity: a single exercise that can be included in
  routines (domain-model §Exercise; confirmed decision C-09), with the
  attributes approved by D-20 option 2: **name, muscle group, equipment,
  instructions/video and difficulty**;
- staff — **ADMIN and TRAINER** — can create, view, search, edit, activate and
  deactivate exercises from the admin panel (D-20 option 2, pre-approved);
- each exercise records: name (unique), muscle group, optional equipment,
  optional difficulty, optional instructions, optional video URL, and an
  active/inactive status (mirroring the Plan lifecycle, SPEC-003 AP-02);
- exercise records are never hard-deleted; deactivation is used instead
  (preservation pattern, AGENTS.md §12);
- the catalogue is **routine-friendly**: it is a standalone catalogue (no foreign
  keys, no dependency on clients/memberships), it exposes the active set for
  future routine prescription, and the routine-side consequences of
  deactivation/editing are explicitly deferred to SPEC-010 (same boundary
  discipline as SPEC-006 BR-013 for bookings);
- client isolation is preserved: a CLIENT never accesses the catalogue through
  any path defined here (C-13); client visibility of exercise names in their
  routine/workout views is a SPEC-010 / SPEC-011 / SPEC-013 concern, not this
  Specification.

This is the base catalogue for the Training modules: Routines (SPEC-010) will
reference exercises in `RoutineExercise` rows (C-09), and Workout Logs (SPEC-011)
and the Client Portal (SPEC-013) will later display exercise names.

---

## 2. Actors

Actors are defined by role, not by person (SPEC-001 §2). A single User may hold
one or more roles (confirmed decision C-01).

| Actor | Description |
| --- | --- |
| Anonymous visitor | No access to the exercise catalogue. Exercise data is never exposed on public pages in this Specification. |
| ADMIN | Staff who administer the gym. Can create, view, search, edit, activate and deactivate exercises (assumption EX-08; D-20 option 2 pre-approved). |
| TRAINER | Staff who train clients. Can create, view, search, edit, activate and deactivate exercises (assumption EX-08; D-20 option 2 pre-approved; consistent with C-03 "a Trainer may create routines" — the trainer authors the catalogue content the routines are built from). |
| CLIENT | A gym member. No catalogue management and no direct catalogue access in this Specification (assumption EX-08); client visibility of exercise names is deferred to SPEC-010 (Routines), SPEC-011 (Workout Logs) and SPEC-013 (Client Portal). Client isolation (C-13) always applies. |

Notes:

- A User holding ADMIN and/or TRAINER uses the admin panel; a User holding CLIENT
  uses the client portal (SPEC-001 FR-006, confirmed C-15).
- A User holding both a staff role and CLIENT (e.g., a trainer who also trains)
  is permitted by C-01; the mixed-role behavior is tracked as SPEC-001 OQ-04.
- "Staff" in this Specification means ADMIN and/or TRAINER; there is no
  RECEPTIONIST role (confirmed SPEC-001 A-04 / D-19 option 1; same convention as
  SPEC-005 PY-01, SPEC-007 BK-02, SPEC-008 AT-01).

---

## 3. Preconditions

1. SPEC-001 is implemented and completed: authentication, fixed role catalog
   (ADMIN, TRAINER, CLIENT), admin panel access (ADMIN or TRAINER), role helpers
   (`hasRole` / `hasAnyRole`), `Role::ADMIN` / `Role::TRAINER` constants,
   policy pattern, no hard deletion of User records (`docs/sdd/state.yaml`,
   ADR-001).
2. SPEC-002 is implemented and completed (context only: the exercise catalogue
   does not reference client records; `docs/sdd/state.yaml` records `depends_on`
   on SPEC-001 and SPEC-002).
3. An authenticated ADMIN or TRAINER exists and can access the admin panel
   (SPEC-001 FR-008, FR-006).
4. The role catalog stays at ADMIN / TRAINER / CLIENT; no RECEPTIONIST role
   (confirmed SPEC-001 A-04).
5. No exercise tables exist yet; the Exercises module is greenfield on top of the
   SPEC-001 / SPEC-002 foundations.
6. The gate decision D-20 (option 2) is pre-approved (NIGHT MODE,
   `docs/sdd/state.yaml`): attributes = name, muscle group, equipment, plus
   instructions/video and difficulty; management by ADMIN and TRAINER.
7. SPEC-010 (Routines) does NOT exist yet; this Specification only prepares the
   reference point (C-09) and defers routine-side behavior (BR-011, §12).

---

## 4. Functional Requirements

### FR-001 — Create exercise

An ADMIN or TRAINER can create an exercise. Required fields: name, muscle group.
Optional fields: equipment, difficulty, instructions, video (assumptions EX-01,
EX-03, EX-04, EX-05, EX-06). A new exercise is created with status `active` by
default (assumption EX-07). Creating an exercise does NOT create any routine,
routine day, routine exercise or workout record (C-09; the Routine entities are
SPEC-010).

### FR-002 — List, search and filter exercises

An ADMIN or TRAINER can list exercises and search them (by name and, optionally,
equipment) and filter them by status (active / inactive), muscle group and
difficulty, so the catalogue is browsable at a glance.

### FR-003 — View exercise detail

An ADMIN or TRAINER can view an exercise's full detail: name, muscle group,
equipment, difficulty, instructions, video URL and current status
(active/inactive).

### FR-004 — Edit exercise

An ADMIN or TRAINER can update an exercise's name, muscle group, equipment,
difficulty, instructions and video while the exercise is `active` or `inactive`
(assumption EX-07; same stance as Plan SPEC-003 AF-003 — editing is allowed
regardless of status). Editing re-applies the same validations as creation
(ERR-001..ERR-005). The effect of an edit on existing routine prescriptions is
NOT defined by this Specification (BR-010; deferred to SPEC-010, D-12).

### FR-005 — Activate / deactivate exercise

An ADMIN or TRAINER can deactivate an `active` exercise (it is no longer offered
for new routine prescriptions) and reactivate it (assumption EX-07; mirrors
SPEC-003 FR-005). Deactivation is the only lifecycle transition in this
Specification; there is no delete operation (BR-008). Deactivating or reactivating
an exercise never modifies any routine or prescription record (BR-009, BR-011;
no routines exist yet — SPEC-010).

### FR-006 — Display exercise status

Exercise lists and detail show the exercise's status (active/inactive), so staff
know which exercises are currently offered for new prescriptions.

### FR-007 — Display catalogue attributes

Exercise lists and detail show the catalogue attributes — muscle group (and the
list-level filters), equipment, difficulty, instructions and video — so the
catalogue is a usable reference for building routines (D-20 option 2).

---

## 5. Business Rules

### BR-001 — Exercise definition

An Exercise is a single exercise that can be included in routines (domain-model
§Exercise; confirmed decision C-09). Its attributes are exactly those approved by
D-20 option 2: name, muscle group, equipment, instructions/video and difficulty
(assumption EX-01). It is a catalogue entity, not a prescription: it contains no
sets, repetitions, weight or other prescription data (those belong to
RoutineExercise, C-09 / D-11, SPEC-010).

### BR-002 — Field requirement

The name and the muscle group are required; equipment, difficulty, instructions
and video are optional (assumption EX-01). An exercise is created and edited with
at least name + muscle group (ERR-001). Absent optional fields are stored as
null, not as empty strings or placeholder values (ADR-003 convention, same as
SPEC-003/006).

### BR-003 — Unique exercise name

The exercise name is unique among all exercises, including inactive ones: a
duplicate name is rejected (ERR-002, assumption EX-02). A deactivated exercise's
name continues to occupy its name; reactivating it does not create a conflict,
and a new exercise cannot reuse the name of any existing exercise, active or
inactive (same stance as Plan SPEC-003 BR-003 / AP-04).

### BR-004 — Fixed muscle-group set

The muscle group is chosen from a fixed, closed set of values (assumption EX-03);
a value outside the set is rejected (ERR-003). The recommended value set is
listed in §14.1 EX-03 and requires PO confirmation before Implementation. Stored
values are fixed identifiers; the display labels are a presentation concern.

### BR-005 — Fixed difficulty set (optional)

The difficulty, when present, is one of a fixed set: `beginner`,
`intermediate`, `advanced` (assumption EX-04); a value outside the set is
rejected (ERR-004). Difficulty is optional; an exercise without a difficulty is
valid (BR-002).

### BR-006 — Video is an external URL

The video, when present, must be a valid absolute URL (http/https) (assumption
EX-06); an invalid URL is rejected (ERR-005). No video file upload, no embedding
processing and no external-service validation is performed in the MVP.

### BR-007 — Lifecycle

An exercise is either `active` or `inactive` (assumption EX-07). A new exercise
is created `active` (FR-001). `active → inactive` (deactivate, FR-005) and
`inactive → active` (reactivate, FR-005) are the only transitions. An `inactive`
exercise is no longer offered for new routine prescriptions; the concrete effect
on routine creation is consumed and enforced by SPEC-010 (BR-011). Both states
remain editable (FR-004).

### BR-008 — No hard deletion of exercises

Exercise records are never hard-deleted; historical catalogue data is preserved
(AGENTS.md §12; same pattern as SPEC-001 BR-007 / SPEC-002 BR-006 / SPEC-003
BR-004 / SPEC-004 BR-015 / SPEC-006 BR-009; assumption EX-07). Deactivation is
used instead; no delete operation is provided and no delete policy is
registered.

### BR-009 — Catalogue management is ADMIN/TRAINER

Only ADMIN and TRAINER can create, list, search, filter, view, edit, activate and
deactivate exercises (D-20 option 2 pre-approved; assumption EX-08). CLIENT has
no catalogue access in this Specification; client visibility of exercise names is
deferred to SPEC-010 (Routines), SPEC-011 (Workout Logs) and SPEC-013 (Client
Portal). Anonymous visitors have no access (ERR-006).

### BR-010 — Edits are allowed regardless of status; routine effect deferred

An exercise may be edited at any time, including while it is `inactive`
(FR-004). Whether an edit to an exercise's attributes (e.g., a name change or a
muscle-group change) affects existing routine prescriptions is NOT defined by
this Specification (boundary; deferred to SPEC-010, whose gate D-12 defines
versioning with reassignment). This Specification provides no versioning or
attribute-history mechanism (see §12).

### BR-011 — Routine consequences are out of scope

This Specification defines the Exercise and its status without reference to
routines, because the Routine entities do not exist yet (SPEC-010). What it
means for a routine prescription when an exercise is deactivated or edited —
including whether new prescriptions may only use `active` exercises, and whether
existing prescriptions keep displaying the deactivated exercise — MUST be defined
by SPEC-010. This Specification imposes no routine-related restriction on
deactivation or editing and does not preclude SPEC-010 from adding them (same
boundary discipline as SPEC-006 BR-013 for bookings).

---

## 6. Main Flow

1. An authenticated ADMIN or TRAINER opens the Exercises section of the admin
   panel (FR-002).
2. Staff create an exercise: fill the required name and muscle group, and
   optionally equipment, difficulty, instructions and video, and save (FR-001).
3. The system validates: required fields present (ERR-001), name unique
   (ERR-002), muscle group in the fixed set (ERR-003), difficulty in the fixed
   set when present (ERR-004), video a valid URL when present (ERR-005).
4. The exercise is persisted as `active` (FR-001, BR-007) and appears in the
   exercise list (FR-002), where its status and catalogue attributes are shown
   (FR-006, FR-007).
5. Staff can open the exercise detail view (FR-003), edit fields (FR-004), or
   deactivate/reactivate the exercise (FR-005).
6. The exercise list and detail always show the exercise's status (FR-006).

---

## 7. Alternative Flows

### AF-001 — Deactivating an exercise

Staff deactivate an `active` exercise (FR-005). The exercise remains in the
system and in the list, marked inactive (BR-007); it is no longer offered for
new routine prescriptions. The effect on any existing routine prescription is a
SPEC-010 concern and is not defined here (BR-011).

### AF-002 — Reactivating an exercise

Staff reactivate an `inactive` exercise (FR-005); it becomes `active` again and
may be offered for new routine prescriptions (BR-007).

### AF-003 — Editing an exercise in either status

An exercise may be edited while `active` or `inactive` (FR-004, BR-010). Editing
re-applies the same validations as creation (ERR-001..ERR-005). A name change
that collides with another exercise is rejected (ERR-002). Whether an edit
affects existing routine prescriptions is deferred to SPEC-010 (BR-010, D-12).

### AF-004 — Minimal exercise

A valid exercise needs only a name and a muscle group; absent optional fields
(equipment, difficulty, instructions, video) are stored as null and displayed
with a placeholder (BR-002, FR-003; same pattern as SPEC-003 "Minimal Plan").

### AF-005 — Deactivated exercise's name stays occupied

A new exercise with the same name as an existing `inactive` exercise is rejected
as a duplicate (BR-003). To reuse the name, staff must reactivate the existing
exercise (or edit it), not create a new one (AF-002).

---

## 8. Error Cases

### ERR-001 — Missing required fields

Condition: creating/editing an exercise without the name or the muscle group.

Expected behavior: rejected with a validation error (FR-001, FR-004, BR-002).

### ERR-002 — Duplicate exercise name

Condition: creating/editing an exercise with a name already used by another
exercise — including an `inactive` one.

Expected behavior: rejected with a validation error (BR-003, EX-02). The current
record's own name is ignored on edit (same rule as Plan SPEC-003 ERR-002).

### ERR-003 — Invalid muscle group

Condition: the muscle group is not one of the fixed set (EX-03).

Expected behavior: rejected with a validation error (BR-004).

### ERR-004 — Invalid difficulty

Condition: the difficulty, when present, is not one of `beginner` /
`intermediate` / `advanced`.

Expected behavior: rejected with a validation error (BR-005, EX-04).

### ERR-005 — Invalid video URL

Condition: the video, when present, is not a valid absolute URL (http/https).

Expected behavior: rejected with a validation error (BR-006, EX-06).

### ERR-006 — Unauthorized access

Condition: an anonymous visitor or a CLIENT attempts to create, list, search,
filter, view, edit, activate or deactivate exercises.

Expected behavior: access denied (redirect for anonymous; 403 for CLIENT)
(BR-009, EX-08).

### ERR-007 — Attempted deletion

Condition: an attempt is made to delete an existing exercise.

Expected behavior: no such operation exists; the record persists (BR-008). There
is no delete ability in the policy and no delete path in the UI (§9).

---

## 9. Authorization

| Operation | Anonymous | ADMIN | TRAINER | CLIENT |
| --- | --- | --- | --- | --- |
| Create exercise | Denied | Allowed (BR-009, EX-08; D-20 option 2) | Allowed (BR-009, EX-08; D-20 option 2) | Denied |
| List / search / filter exercises | Denied | Allowed (BR-009) | Allowed (BR-009) | Denied |
| View exercise detail | Denied | Allowed (BR-009) | Allowed (BR-009) | Denied |
| Edit exercise | Denied | Allowed (BR-009) | Allowed (BR-009) | Denied |
| Activate / deactivate exercise | Denied | Allowed (BR-009, BR-007) | Allowed (BR-009, BR-007) | Denied |
| Delete exercise | Denied | Denied (no such operation — BR-008) | Denied (no such operation — BR-008) | Denied (no such operation — BR-008) |
| Client visibility of exercise names (routines / workout logs / portal) | Out of scope (SPEC-010, SPEC-011, SPEC-013) | — | — | Out of scope at this stage (EX-08) |

Notes:

- A User holding several roles receives the union of the column permissions
  (SPEC-001 BR-002); an ADMIN or TRAINER who is also CLIENT can manage the
  catalogue in the admin panel.
- Authorization is enforced server-side; frontend-only restrictions are never
  sufficient (AGENTS.md §17).
- Whether TRAINER receives the FULL management set (create / edit / deactivate /
  reactivate) is assumed as full (EX-08), per D-20 option 2 "management by ADMIN
  and TRAINER" and consistent with SPEC-006 AS-01; PO confirmation requested
  (OQ-04).

---

## 10. Data Changes

This Specification describes the information that must exist; the exact
persistence schema, table name and model name are defined by the Architect (e.g.,
an `exercises` table with an `Exercise` model, following the repo's English
naming convention for models, the same way `turnos`/`Turno` was chosen in
SPEC-006 §10).

Created:

- Exercise records with the following information:
  - `name` — required free text, unique among all exercises regardless of status
    (BR-003, EX-02); technical max length is an Architect/Developer detail
    (e.g., 255);
  - `muscle_group` — required, one of the fixed set (BR-004, EX-03). Following
    the repo's validation-first / string-with-constants convention (ADR-003,
    ADR-004 precedent: status values are strings validated against model
    constants, not DB enums), the recommended representation is a string column
    validated against a fixed list; the exact storage (string column vs DB
    enum) is an Architect decision with no business difference (the business
    invariant is BR-004);
  - `equipment` — optional free text (BR-002, EX-05); technical max length is an
    Architect/Developer detail (e.g., 255). No equipment entity/table is
    introduced (equipment is an attribute, not a first-class entity in the MVP);
  - `difficulty` — optional, one of `beginner` / `intermediate` / `advanced`
    (BR-005, EX-04); same string-with-constants convention as `muscle_group`;
  - `instructions` — optional long free text (BR-002); plain text, no rich-text
    formatting (EX-10); technical max length is an Architect/Developer detail
    (e.g., text column);
  - `video` — optional external URL string (http/https), validated as URL
    (BR-006, EX-06). The exact column name (e.g., `video_url`) is an
    Architect/Developer detail; the business meaning is "a link to an external
    video of the exercise";
  - `is_active` — boolean, default `true` (BR-007, EX-07): the lifecycle status,
    the same representation as Plan (SPEC-003 AP-02);
  - timestamps.
- A unique index on `name` enforcing BR-003 at the database level (same as
  `plans.name`, SPEC-003) and indexes on `muscle_group` and `is_active` to
  support the FR-002 filters. The exact index set is an Architect decision.
- **No foreign keys and no relationships in this Specification**: the exercise
  catalogue is standalone (BR-011; no reference to Client, Membership, Plan,
  Turno or User). The reference direction is from the consuming module: SPEC-010
  will add the `RoutineExercise` rows referencing `exercises.id` (C-09), the same
  way SPEC-007 will add `bookings.turno_id` against the standalone turnos table
  (SPEC-006 §10).
- **Routine-friendly exposure**: the model is expected to expose the active set
  (e.g., a `scopeActive()` query scope like `Turno::scopeActive`, SPEC-006) so
  SPEC-010 can filter to currently offered exercises when building prescriptions;
  the exact mechanism is an Architect/Developer detail.

Modified:

- Exercise name, muscle group, equipment, difficulty, instructions and video via
  edit (FR-004).
- Exercise status when deactivated/reactivated (FR-005, BR-007).

Deleted:

- No hard deletion of exercise records in the MVP (BR-008); no delete operation.
  Deactivation is used instead.

No seeder is required: exercises are created by staff in the admin panel only
(EX-09).

No change to the `users`, `roles`, `clients` or any other existing table is made
by this Specification.

---

## 11. Acceptance Criteria

- [ ] AC-1: ADMIN or TRAINER can create an exercise with name and muscle group
  (required) plus optional equipment, difficulty, instructions and video; the
  record is persisted as `active` and listed (FR-001, FR-002, BR-002, BR-007).
- [ ] AC-2: Creating or editing an exercise with a name already used by another
  exercise — including an `inactive` one — is rejected with a validation error
  (ERR-002, BR-003, AF-005).
- [ ] AC-3: Creating/editing an exercise with a muscle group outside the fixed
  set is rejected with a validation error (ERR-003, BR-004).
- [ ] AC-4: Creating/editing an exercise with a difficulty outside
  `beginner`/`intermediate`/`advanced` is rejected; omitting difficulty is
  accepted (ERR-004, BR-005).
- [ ] AC-5: Creating/editing an exercise with an invalid video URL is rejected;
  omitting the video is accepted (ERR-005, BR-006).
- [ ] AC-6: ADMIN or TRAINER can search exercises (by name, optionally by
  equipment) and filter them by status, muscle group and difficulty (FR-002).
- [ ] AC-7: ADMIN or TRAINER can view an exercise's full detail including status
  and all catalogue attributes (FR-003, FR-006, FR-007).
- [ ] AC-8: ADMIN or TRAINER can edit an active or inactive exercise's fields;
  changes persist (FR-004, BR-010).
- [ ] AC-9: ADMIN or TRAINER can deactivate an active exercise; the exercise
  remains in the system and is displayed as inactive (FR-005, FR-006, BR-007).
- [ ] AC-10: ADMIN or TRAINER can reactivate an inactive exercise (FR-005,
  AF-002, BR-007).
- [ ] AC-11: A CLIENT or anonymous visitor cannot create, list, search, filter,
  view, edit, activate or deactivate exercises (403 or redirect) (ERR-006,
  BR-009).
- [ ] AC-12: No delete operation exists for exercises; a created exercise record
  persists (ERR-007, BR-008).
- [ ] AC-13: Creating, editing, activating or deactivating an exercise never
  creates, modifies or deletes any routine, routine day, routine exercise or
  workout record (BR-009, BR-011; C-09; the Routine entities are SPEC-010).
- [ ] AC-14: A multi-role ADMIN + CLIENT or TRAINER + CLIENT user can manage the
  catalogue in the admin panel (SPEC-001 BR-002).

**Test plan (to be executed at Implementation; aligned with the existing Pest
layout under `tests/`, `tests/Pest.php` helpers `role()` / `userWithRoles()`,
`RefreshDatabase`, Livewire component tests as used in `PlanManagementTest`):**

- `tests/Feature/Admin/ExerciseManagementTest.php` (Livewire): create an
  exercise with required + optional fields stored active (AC-1); minimal
  exercise with only name + muscle group (AC-1, AF-004); duplicate name rejected
  on create and on edit onto another exercise's name, including inactive ones
  (AC-2); missing required fields rejected (AC-1, ERR-001); invalid muscle group
  rejected (AC-3); invalid difficulty rejected / omitted accepted (AC-4); invalid
  video URL rejected / omitted accepted (AC-5); search by name and equipment and
  filters by status/muscle group/difficulty (AC-6); detail view shows status and
  attributes (AC-7); edit persists changes (AC-8); deactivate/reactivate actions
  shown per status (AC-9, AC-10); no delete action exists (AC-12); creating an
  exercise touches only the exercises table (AC-13).
- `tests/Feature/Admin/ExercisePolicyTest.php`: ADMIN and TRAINER can
  viewAny/view/create/update; CLIENT and anonymous denied (403); a multi-role
  ADMIN + CLIENT or TRAINER + CLIENT user can manage the catalogue; no delete
  ability exists for anyone (AC-11, AC-12, AC-14).
- `tests/Unit/ExerciseTest.php`: default `is_active` true and absent optional
  fields null (BR-002, BR-007); string constants / fixed-set helpers for muscle
  group and difficulty (BR-004, BR-005); duplicate name rejected via the unique
  database constraint (BR-003); the boundary rule that creating an exercise
  creates no other record (BR-011).

---

## 12. Out of Scope

The following are explicitly NOT part of this Specification:

- **Routines and prescriptions** — Routine, RoutineDay, RoutineExercise, sets /
  repetitions / weight, and the effect of deactivating or editing an exercise on
  existing routine prescriptions (SPEC-010; gates D-10, D-11, D-12). This
  Specification only prepares the Exercise reference point (C-09; BR-011).
- **Workout Logs** — logging what a client actually performed referencing the
  exercise (SPEC-011).
- **Client-facing exercise visibility** — exercise names/attributes in the
  client portal, routine views or workout views (SPEC-010, SPEC-011, SPEC-013).
  CLIENT has no catalogue access in this Specification (EX-08).
- **Public website display** of the exercise catalogue.
- **Video file upload, video hosting, embedding processing or external-service
  validation** — the video is an external URL string only (BR-006, EX-06).
- **Exercise images / photos** — not part of the approved attribute set (D-20
  option 2).
- **An equipment entity/catalogue** — equipment is a free-text attribute of the
  exercise, not a first-class entity (EX-05).
- **Additional attributes or categories** beyond D-20 option 2 (e.g., a
  category/type field, a target-audience field, exercise alternatives).
- **Versioning or attribute-history** of exercises (BR-010; SPEC-010 gate D-12
  decides versioning for routines; exercise attributes are live-edited in the
  MVP).
- **Bulk import/export** of exercises.
- **Exercise-specific trainer ownership** — any exercise belongs to the shared
  catalogue; there is no "created by" tracking or per-trainer visibility rule in
  this Specification.
- **Hard deletion of exercise records** — no delete operation (BR-008).

---

## 13. Dependencies

- **SPEC-001 (Authentication & Roles) — COMPLETED** (`docs/sdd/state.yaml`):
  authentication, fixed role catalog with `Role::ADMIN` / `Role::TRAINER`;
  admin panel access (ADMIN | TRAINER); `User::hasRole` / `User::hasAnyRole`;
  policy pattern (`UserPolicy`, `PlanPolicy`, `TurnoPolicy`); no RECEPTIONIST
  role (D-19 option 1 confirmed). This Specification defines the exercise
  catalogue permissions (EX-08).
- **SPEC-002 (Client Management) — COMPLETED** (`docs/sdd/state.yaml`):
  referenced for context and conventions only; the exercise catalogue does not
  reference client records.
- **SPEC-010 (Routines) — FUTURE; depends on this Specification.** The Exercise
  entity (name, muscle group, equipment, difficulty, instructions, video,
  `is_active`) is the reference that `RoutineExercise` rows will point to
  (C-09). SPEC-010 MUST define: (a) whether new prescriptions may only use
  `active` exercises (BR-011); (b) what happens to existing routine prescriptions
  when an exercise is deactivated (BR-011) or edited (BR-010), read against its
  gate D-12 (versioning with reassignment); (c) how exercise attributes are
  displayed in routines. This Specification imposes no routine-related
  restriction and does not preclude SPEC-010 from adding them (BR-011).
- **SPEC-011 (Workout Logs & Progress) — FUTURE**: exercise references in
  workout logs (C-09, C-11).
- **SPEC-013 (Client Portal) — FUTURE**: client visibility of exercise names in
  their routine/workout views (EX-08).
- Gate decision: **D-20 option 2** (attributes + ADMIN/TRAINER management) —
  pre-approved (NIGHT MODE, `docs/sdd/state.yaml`).
- Confirmed decisions used: C-01 (roles, multi-role), C-09 (Routine →
  RoutineDay → RoutineExercise → Exercise; prescription = sets/reps/weight),
  C-13 (client isolation), C-15 (presentation contexts).
- Requirements analysis: `analyst-pass-001.md` §5.11 (Exercises), D-20, C-09,
  R-07 (routine granularity/versioning ambiguity — consumed by SPEC-010).
- Architecture constraints used: ARCHITECTURE §5 (presentation contexts), §12
  (authorization via Policies), §20 (simplest correct architecture).
- Flagged assumptions EX-01..EX-10 require Product Owner confirmation before
  Implementation (see §14.1).

---

## 14. Open Questions

### 14.1 Assumed decisions requiring PO confirmation

These flagged assumptions are operational defaults consistent with the
pre-approved decision D-20 (option 2), catalogue invariants consistent with the
established patterns (SPEC-001..SPEC-008), or authorization consequences of
SPEC-010/011/013 not being implemented yet. They are NOT confirmed business
rules unless stated otherwise; prefix EX distinguishes this Specification's
assumptions from SPEC-001 (A-xx), SPEC-002 (AD-xx), SPEC-003 (AP-xx),
SPEC-004 (AM-xx), SPEC-005 (PY-xx), SPEC-006 (AS-xx), SPEC-007 (BK-xx) and
SPEC-008 (AT-xx).

| ID | Assumption | Source | Blocks / Affects |
| --- | --- | --- | --- |
| EX-01 | Field set and optionality per D-20 option 2: **name and muscle group are REQUIRED**; equipment, difficulty, instructions and video are OPTIONAL. The alternative (all attributes optional, or muscle group optional) is rejected: the muscle group is the catalogue's primary classification and the base trio of D-20 option 1 ("name, muscle group, equipment") is kept required for name + muscle group, with equipment demoted to optional. | D-20 option 2 (pre-approved attribute list); task directive "recommend and document as assumption" for field representation | FR-001, FR-004, BR-002, ERR-001, §10, OQ-02 |
| EX-02 | The exercise name is unique among ALL exercises regardless of status; duplicates rejected; a deactivated exercise's name stays occupied. | Task directive "Uniqueness: exercise name unique? Document as assumption"; consistent with SPEC-003 AP-04 (unique plan name) | FR-001, BR-003, ERR-002, AF-005, AC-2 |
| EX-03 | Muscle group is a **fixed enumeration** (not free text), because a filterable catalogue requires a closed set. Recommended value set (stored identifiers; display labels are a presentation concern): `chest`, `back`, `shoulders`, `biceps`, `triceps`, `forearms`, `abs`, `quadriceps`, `hamstrings`, `glutes`, `calves`, `full_body`. The exact list is subject to PO confirmation (OQ-01). | Task directive "enum or free text? — recommend and document as assumption"; catalogue filterability (FR-002) | FR-001, FR-004, BR-004, ERR-003, §10, AC-3, OQ-01 |
| EX-04 | Difficulty is optional and a fixed enumeration: `beginner`, `intermediate`, `advanced`. Free-text difficulty is rejected (unfilterable). | Task directive "difficulty (optional, e.g., beginner/intermediate/advanced — recommend and document as assumption)" | FR-001, FR-004, BR-005, ERR-004, §10, AC-4 |
| EX-05 | Equipment is **optional free text** (not an enum and not a separate entity). Equipment names vary widely (barbell, dumbbell, cable machine, bodyweight, etc.); a closed enum would be restrictive and invent content the PO did not approve, and an equipment entity/table is rejected as speculative (no first-class equipment module is documented). | Task directive "equipment (optional free text or enum)"; no-speculative-fields discipline (ADR-002; SPEC-006 §10) | FR-001, FR-004, BR-002, §10, AC-6, OQ-08 |
| EX-06 | Video is an **external URL string** (http/https), validated with the framework URL rule. No file upload, no embedding processing, no external-service validation (D-20 says "video" as a link-style attribute; no upload infrastructure exists in the MVP). | D-20 option 2 ("instructions/video"); task directive "video (optional URL)"; no speculative infrastructure | FR-001, FR-004, BR-006, ERR-005, §10, §12, AC-5 |
| EX-07 | Lifecycle = **active / inactive toggle**, created `active` by default, reversible (deactivate/reactivate), no hard deletion; both states remain editable. An `inactive` exercise is no longer offered for new routine prescriptions; the concrete effect on routine creation is consumed by SPEC-010 (BR-011). | Task directive "lifecycle: active/inactive toggle (mirror Plan/Turno pattern, no hard delete per AGENTS §12)"; mirrors SPEC-003 AP-02/BR-004/BR-005 and SPEC-006 AS-07/BR-009 | FR-001, FR-005, BR-007, BR-008, §10, AC-1, AC-9, AC-10, AC-12 |
| EX-08 | Catalogue management = **ADMIN and TRAINER** (full set: create / list / search / filter / view / edit / activate / deactivate), per D-20 option 2. CLIENT has NO direct catalogue access in this Specification; client visibility of exercise names is deferred to SPEC-010 (Routines), SPEC-011 (Workout Logs) and SPEC-013 (Client Portal). Anonymous has no access. | D-20 option 2 (pre-approved); task directive "no direct catalogue access for CLIENT now, defer to those specs; document as assumption"; consistent with SPEC-006 AS-01 / SPEC-008 AT-01 (ADMIN/TRAINER staff) | §2, FR-001..FR-007, BR-009, §9, ERR-006, OQ-04 |
| EX-09 | No seeder / no starter exercise set: exercises are created by staff in the admin panel only. A pre-populated catalogue is not requested by any documentation and would invent content. | Same stance as SPEC-006 §10 ("No seeder is required") | §10, OQ-06 |
| EX-10 | Instructions is **plain long text** (no rich text / formatting / markup). No rich-text editor or HTML sanitization requirement is documented; plain text is the simplest correct representation (ARCHITECTURE §20). | Not documented — analyst necessity; simplest-correct principle | FR-001, FR-004, §10, OQ-05 |

### 14.2 Open questions to be answered before Implementation (or at latest before Review)

- OQ-01 (EX-03): Is the recommended muscle-group value set (12 values) correct,
  or should it be extended/renamed (e.g., separate `abs` and `core`, add
  `cardio`, Spanish display names)? The stored identifiers are assumed English
  (repo convention); only the exact set is open.
- OQ-02 (EX-01): Is the muscle group required at creation, or should exercises
  without a muscle group be allowed? This Specification assumes required.
- OQ-03: Should name uniqueness be case-sensitive (Postgres default) or
  case-insensitive (e.g., "Press" vs "press" rejected as duplicate)? This
  Specification assumes the database-level unique constraint as-is (like Plan
  SPEC-003 AP-04, where this was not addressed).
- OQ-04 (EX-08): Should TRAINER receive the FULL catalogue management set
  (create / edit / deactivate / reactivate), or a restricted subset (e.g.,
  create+edit but no deactivate)? D-20 option 2 says "management by ADMIN and
  TRAINER"; this Specification assumes full, consistent with SPEC-006 AS-01 and
  SPEC-008 AT-01.
- OQ-05 (EX-10): Should instructions support rich text/formatting, and is there
  a max length? This Specification assumes plain text with an
  Architect/Developer-chosen max length.
- OQ-06 (EX-09): Is a pre-populated starter exercise catalogue wanted for the
  MVP (seeder), or is the catalogue started empty and filled by staff? This
  Specification assumes empty.
- OQ-07 (BR-011, SPEC-010): Must SPEC-010 restrict new routine prescriptions to
  `active` exercises, and what happens to existing prescriptions when an
  exercise is deactivated or edited (name change, muscle-group change)? Deferred
  to SPEC-010 by design (same pattern as SPEC-006 BR-013 / SPEC-007 NC-01); must
  be answered when SPEC-010 is specified.
- OQ-08 (EX-05): Should equipment become a closed enumeration or a separate
  entity in a later iteration? This Specification assumes optional free text for
  the MVP.

---

## 15. Related Documents

- Product documentation: `docs/product/product-definition-v0.1.md` (Business
  Areas: "Exercises — Catalogue of individual exercises"; Open Questions)
- Domain documentation: `docs/domain/domain-model-v0.1.md` (§Exercise,
  §Routine → RoutineDay → RoutineExercise → Exercise; C-09)
- Requirements analysis: `docs/requirements/analyst-pass-001.md` (§5.11
  Exercises, D-20, C-09, R-07)
- Specifications: `docs/specs/SPEC-001.md` (roles, D-19 option 1, C-13/C-15),
  `docs/specs/SPEC-002.md` (client records, conventions),
  `docs/specs/SPEC-003.md` (Plan lifecycle / unique-name / ADMIN-management
  patterns: AP-02, AP-03, AP-04, BR-003..BR-006),
  `docs/specs/SPEC-006.md` (ADMIN/TRAINER management pattern: AS-01, AS-07,
  BR-009, BR-013 — the boundary-deferral model this Specification mirrors for
  SPEC-010)
- Architecture documentation: `docs/architecture/SPEC-001.md`,
  `docs/architecture/SPEC-002.md`, `ARCHITECTURE.md` (§5 presentation contexts,
  §12 Authorization, §20 simplest correct architecture)
- Architecture decisions: `docs/adr/ADR-001.md` (role/authorization
  foundation), `docs/adr/ADR-002.md` (module boundary discipline),
  `docs/adr/ADR-003.md` (validation-first representation),
  `docs/adr/ADR-004.md` (status-as-string / model constants)
- Workflow state: `docs/sdd/state.yaml` (NIGHT MODE pre-approval D-20 for
  SPEC-009; SPEC-001/002 completed; SPEC-010 depends on SPEC-009)
- Development rules: `AGENTS.md`

---

*Analyst note: SPEC-009 is analysis-complete. No NOT COVERED blocking business
decision was found (the only deferred behavior — routine consequences of
deactivation/editing — belongs to SPEC-010 by design, per BR-011 and the
SPEC-006 BR-013 precedent). Assumptions EX-01..EX-10 require PO confirmation
before Implementation (or at latest before Review), the same as every prior
Specification.*
