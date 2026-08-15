# SPEC-006 — Scheduling & Turnos

## Status

Draft (analysis phase).

This is the sixth Specification of the MVP. It depends on SPEC-001 (Authentication
& Roles) and SPEC-002 (Client Management), both COMPLETED and implemented in the
repository (`docs/sdd/state.yaml`). SPEC-007 (Bookings) will depend on this
Specification, so the turno/slot model is defined explicitly and kept
bookable-friendly.

**NIGHT MODE notice:** this Specification is produced under the PO pre-approval
("modo nocturno", `docs/sdd/state.yaml` `project.po_decisions`). The gates are
pre-approved as follows:

- **D-07 — option 1** (scheduling scope in MVP): a "turno" is a **bookable time
  slot for gym access (capacity-limited)**. It is NOT a trainer-led session.
  Full class / group-session management is future scope.
- **C-16** (confirmed): the MVP is primarily a free-weight gym. Full
  class/group-session management and capacity-limit enforcement are FUTURE
  scope, but the architecture must not preclude them. This Specification
  therefore models turnos as gym-access time slots, keeps the design compatible
  with future sessions/classes, and does NOT implement class management.
- **D-05** (access rule) is NOT a gate for this Specification: whether an active
  membership is required for booking/attendance belongs to SPEC-007 (Bookings)
  and SPEC-008 (Attendance). This Specification deliberately does NOT decide
  access rules; it only defines the scheduling/turno model itself.

**Assumption notice:** this specification contains explicitly flagged assumptions
(AS-01 to AS-09, see §14.1) that fill gaps required to make the specification
implementable. Each resolves an edge case or a lifecycle detail that is not
documented in `analyst-pass-001.md` nor covered by the pre-approved decisions,
using the existing confirmed patterns (SPEC-001..SPEC-004) as reference. **None
of them is a confirmed business rule** unless stated otherwise. Each requires
Product Owner confirmation before Implementation (or at latest before Review).

---

## 1. Objective

Provide the scheduling foundation of the gym management system:

- define the **turno** entity: a bookable time slot for gym access with a
  capacity limit (D-07 option 1, pre-approved);
- staff — ADMIN and TRAINER — can create, view, edit, deactivate, reactivate and
  cancel turnos from the admin panel;
- each turno records: date, start time, end time, capacity limit, status
  (active / inactive / cancelled) and an optional label;
- the MVP has one location: turnos implicitly belong to the gym's single
  location; no location field is introduced (C-14, ARCHITECTURE §17);
- the turno model is **bookable-friendly**: SPEC-007 (Bookings) will reference
  turnos and enforce capacity against bookings; this Specification stores the
  capacity field but deliberately does NOT enforce it (capacity checking is
  SPEC-007's concern);
- the access rule (D-05: what an active membership grants) is explicitly NOT
  decided here; it belongs to SPEC-007 / SPEC-008.

This is the base for the operational modules: Bookings (SPEC-007) will reference
turnos, and Attendance (SPEC-008) will later record gym access per turno.

---

## 2. Actors

Actors are defined by role, not by person (SPEC-001 §2). A single User may hold
one or more roles (confirmed decision C-01).

| Actor | Description |
| --- | --- |
| Anonymous visitor | No access to scheduling. Turno data is never exposed on public pages. |
| ADMIN | Staff who administer the gym. Can create, view, edit, deactivate, reactivate and cancel turnos (assumption AS-01). |
| TRAINER | Staff who train clients. Can create, view, edit, deactivate, reactivate and cancel turnos (assumption AS-01; SPEC-001 §2 defers TRAINER "schedules" feature permissions to later Specifications). |
| CLIENT | A gym member. No access to scheduling management or turno data at this stage (assumption AS-01); client-facing turno visibility and booking are defined by SPEC-007 (Bookings) and SPEC-013 (Client Portal). Client isolation (C-13) always applies. |

Notes:

- A User holding ADMIN and/or TRAINER uses the admin panel; a User holding CLIENT
  uses the client portal (SPEC-001 FR-006, confirmed C-15).
- A User holding both a staff role and CLIENT (e.g., a trainer who also trains)
  is permitted by C-01; the mixed-role behavior is tracked as SPEC-001 OQ-04.

---

## 3. Preconditions

1. SPEC-001 is implemented and completed: authentication, fixed role catalog
   (ADMIN, TRAINER, CLIENT), admin panel access (ADMIN or TRAINER), role helpers
   (`hasRole` / `hasAnyRole`), `Role::ADMIN` / `Role::TRAINER` constants,
   policy pattern, no hard deletion of User records (`docs/sdd/state.yaml`,
   ADR-001).
2. SPEC-002 is implemented and completed (context only: turnos do not reference
   client records; `docs/sdd/state.yaml` records `depends_on` on SPEC-001 and
   SPEC-002).
3. An authenticated ADMIN or TRAINER exists and can access the admin panel
   (SPEC-001 FR-008, FR-006).
4. The role catalog stays at ADMIN / TRAINER / CLIENT; no RECEPTIONIST role
   (confirmed SPEC-001 A-04).
5. No scheduling tables exist yet; the Scheduling module is greenfield on top of
   the SPEC-001 / SPEC-002 foundations.
6. The gate decision D-07 (option 1) is pre-approved (NIGHT MODE,
   `docs/sdd/state.yaml`); confirmed decision C-16 applies. Decision D-05
   (access rule) is intentionally NOT a gate of this Specification (see
   `analyst-pass-001.md` §8 and SPEC-004 OQ-01).

---

## 4. Functional Requirements

### FR-001 — Create turno

An ADMIN or TRAINER can create a turno. Required fields: date, start time, end
time, capacity limit. Optional field: label. A new turno is created with status
`active` by default (assumption AS-07). Creating a turno does NOT create any
booking, attendance or membership record (D-07; the Booking entity is
SPEC-007).

### FR-002 — List and filter turnos

An ADMIN or TRAINER can list turnos and filter them by date range and by status
(active / inactive / cancelled), so the daily schedule is visible at a glance.

### FR-003 — View turno detail

An ADMIN or TRAINER can view a turno's full detail: date, start time, end time,
capacity limit, status and label.

### FR-004 — Edit turno

An ADMIN or TRAINER can update a turno's date, start time, end time, capacity
limit and label while the turno is `active` or `inactive`. A `cancelled` turno
cannot be edited (BR-004, ERR-006).

### FR-005 — Deactivate turno

An ADMIN or TRAINER can deactivate an `active` turno; it becomes `inactive`
(no longer bookable; can be reactivated) (assumption AS-07).

### FR-006 — Reactivate turno

An ADMIN or TRAINER can reactivate an `inactive` turno; it becomes `active`
again (assumption AS-07).

### FR-007 — Cancel turno

An ADMIN or TRAINER can cancel an `active` or `inactive` turno; it becomes
`cancelled` (terminal state, cannot be edited or reactivated) (assumption
AS-07). This is the cancellation rule for the turno itself; consequences for
existing bookings are a SPEC-007 concern (BR-013, §12).

### FR-008 — Display turno status

Turno lists and detail show the turno's status (active / inactive / cancelled),
so staff know which slots are currently bookable.

### FR-009 — Store capacity limit

Each turno stores a capacity limit (required positive integer, assumption
AS-06). The capacity field exists in this Specification; its use to limit
bookings (checking capacity against existing bookings) is explicitly the
concern of SPEC-007 (D-07; C-16).

---

## 5. Business Rules

### BR-001 — Turno definition

A turno is a bookable time slot for gym access, capacity-limited (D-07 option
1, pre-approved). It is NOT a trainer-led session, and it is not a class/group
session. The domain model's `Schedule → Session → Booking` hierarchy
(domain-model §Schedule/Session) is NOT implemented in the MVP; the turno is
the scheduling entity (assumption AS-02).

### BR-002 — Turno status set

A turno has exactly three statuses: `active`, `inactive`, `cancelled`
(assumption AS-07). A new turno is created as `active` (FR-001).

### BR-003 — Status transitions

Allowed transitions:

- `active → inactive` (deactivate, FR-005);
- `inactive → active` (reactivate, FR-006);
- `active → cancelled` (cancel, FR-007);
- `inactive → cancelled` (cancel, FR-007).

No other transitions exist. `cancelled` is terminal (BR-004). Status
transitions are date-independent: staff may deactivate/reactivate/cancel a
turno regardless of whether its date is in the past, present or future
(assumption AS-04; operational cleanup).

### BR-004 — Cancelled is terminal

A `cancelled` turno cannot be edited (FR-004), reactivated (FR-006) or
cancelled again. If a slot is needed again, a new turno must be created.
This mirrors the terminal `cancelled` pattern of SPEC-004 (BR-009, AM-10).

### BR-005 — Interval invariant

A turno's end time must be strictly after its start time (ERR-002). Both times
fall on the same date as the turno's date field: a turno does not cross
midnight (assumption AS-03).

### BR-006 — Past dates

A turno's date must be today or in the future when created (FR-001) and when
the date/time fields are edited (FR-004); a past date is rejected (ERR-003,
assumption AS-04).

### BR-007 — Capacity limit

The capacity limit is required and must be a positive integer (≥ 1) (ERR-004,
assumption AS-06). There is no maximum capacity in the MVP.

### BR-008 — Overlapping turnos are allowed

Two or more turnos may overlap in time on the same date; each turno is an
independent, capacity-limited access slot (assumption AS-05). No
overlap-detection or uniqueness constraint is imposed. This keeps the design
compatible with future classes/sessions that may overlap with access slots
(C-16).

### BR-009 — No hard deletion of turnos

Turno records are never hard-deleted; historical schedule data is preserved
(AGENTS.md §12; same pattern as SPEC-001 BR-007 / SPEC-002 BR-006 / SPEC-003
BR-004 / SPEC-004 BR-015; assumption AS-08). Deactivation and cancellation are
used instead; no delete operation is provided.

### BR-010 — Single location

The MVP has one location (C-14). Turnos implicitly belong to the gym's single
location; no location field or location reference is introduced (assumption
AS-09). If multi-location support is added later, a location column can be
added without restructuring the turno (ARCHITECTURE §17).

### BR-011 — Local time, no timezone handling

Turno times are stored and interpreted in the gym's local time for the single
location. No timezone field and no timezone conversion is introduced in the
MVP (assumption AS-09; ARCHITECTURE §17 single location).

### BR-012 — Scheduling management is ADMIN/TRAINER

Only ADMIN and TRAINER can create, view, edit, deactivate, reactivate and
cancel turnos (assumption AS-01). CLIENT has no access to scheduling at this
stage; client-facing turno visibility is deferred to SPEC-007 (Bookings) and
SPEC-013 (Client Portal).

### BR-013 — Booking consequences are out of scope

This Specification defines the turno and its status transitions without
reference to bookings, because the Booking entity does not exist yet
(SPEC-007). What happens to existing bookings when a turno is deactivated or
cancelled — including any restriction on deactivating/cancelling a turno that
has bookings — MUST be defined by SPEC-007. This Specification imposes no
booking-related constraint on status transitions and does not preclude
SPEC-007 from adding them.

---

## 6. Main Flow

1. An authenticated ADMIN or TRAINER opens the Scheduling section of the admin
   panel (FR-002).
2. Staff create a turno: fills the required date, start time, end time and
   capacity limit, and optionally a label, and saves (FR-001).
3. The system validates: required fields present (ERR-001), interval invariant
   (ERR-002), date not in the past (ERR-003), capacity positive integer
   (ERR-004).
4. The turno is persisted as `active` (FR-001, BR-002) and appears in the turno
   list (FR-002), where its status is shown (FR-008).
5. Staff can open the turno detail view (FR-003), edit fields (FR-004),
   deactivate it (FR-005), reactivate it (FR-006) or cancel it (FR-007).
6. The turno list and detail always show the turno's status (FR-008).

---

## 7. Alternative Flows

### AF-001 — Reactivating an inactive turno

Staff reactivate an `inactive` turno (FR-006); it becomes `active` again and is
bookable again (BR-003, AS-07).

### AF-002 — Cancelling an inactive turno

Staff cancel an `inactive` turno (FR-007); it becomes `cancelled` (BR-003,
BR-004). The difference from deactivation: `inactive` is temporary and
reversible, `cancelled` is terminal.

### AF-003 — Editing an active turno

A turno may be edited (date, times, capacity, label) while `active` or
`inactive` (FR-004). Editing re-applies the same validations as creation
(ERR-002, ERR-003, ERR-004).

### AF-004 — Operational cleanup of past turnos

A turno whose date has passed remains in the system (BR-009) and can still be
deactivated, reactivated or cancelled by staff (BR-003, AS-04); only editing
date/time into the past is rejected (BR-006).

### AF-005 — Overlapping or identical turnos

Staff may create turnos that overlap in time, and may create two turnos with
the same date/start/end (they remain distinct records) (BR-008, AS-05).

---

## 8. Error Cases

### ERR-001 — Missing required fields

Condition: creating/editing a turno without the date, start time, end time or
capacity limit.

Expected behavior: rejected with a validation error (FR-001, FR-004).

### ERR-002 — Invalid interval

Condition: end time is not strictly after start time (end ≤ start), or times
are missing/malformed.

Expected behavior: rejected with a validation error (BR-005).

### ERR-003 — Past date

Condition: creating or editing a turno with a date before today.

Expected behavior: rejected with a validation error (BR-006, AS-04).

### ERR-004 — Invalid capacity

Condition: capacity limit is missing, not an integer, or less than 1.

Expected behavior: rejected with a validation error (BR-007, AS-06).

### ERR-005 — Unauthorized access

Condition: an anonymous visitor or a CLIENT attempts to access scheduling
(create, view, list, edit, deactivate, reactivate, cancel).

Expected behavior: access denied (redirect for anonymous; 403 for CLIENT)
(BR-012, AS-01).

### ERR-006 — Edit or reactivate a cancelled turno

Condition: staff attempt to edit (FR-004) or reactivate (FR-006) a `cancelled`
turno.

Expected behavior: rejected; a `cancelled` turno is terminal (BR-004).

### ERR-007 — Cross-midnight interval

Condition: a turno whose end time would fall on a different date than its start
time (e.g., 23:00–01:00).

Expected behavior: rejected as an invalid interval (end ≤ start on the same
date) (BR-005, AS-03).

---

## 9. Authorization

| Operation | Anonymous | ADMIN | TRAINER | CLIENT |
| --- | --- | --- | --- | --- |
| Create turno | Denied | Allowed (BR-012, AS-01) | Allowed (BR-012, AS-01) | Denied |
| List / filter turnos | Denied | Allowed (BR-012) | Allowed (BR-012) | Denied |
| View turno detail | Denied | Allowed (BR-012) | Allowed (BR-012) | Denied |
| Edit turno | Denied | Allowed (BR-012) | Allowed (BR-012) | Denied |
| Deactivate / reactivate turno | Denied | Allowed (BR-012) | Allowed (BR-012) | Denied |
| Cancel turno | Denied | Allowed (BR-012) | Allowed (BR-012) | Denied |
| Client-facing turno visibility / booking | Out of scope (SPEC-007, SPEC-013) | — | — | Out of scope at this stage (AS-01) |

Notes:

- A User holding several roles receives the union of the column permissions
  (SPEC-001 BR-002); an ADMIN who is also CLIENT can manage turnos in the admin
  panel.
- Authorization is enforced server-side; frontend-only restrictions are never
  sufficient (AGENTS.md §17).
- Whether TRAINER receives the FULL management set or a restricted subset is
  assumed as full (AS-01); PO confirmation requested (OQ-01).

---

## 10. Data Changes

This Specification describes the information that must exist; the exact
persistence schema, table name and model name are defined by the Architect
(e.g., a `turnos` table with a `Turno` model, following the repo's English
naming convention for models; `TimeSlot` is an acceptable equivalent).

Created:

- Turno records with the following information:
  - `date` (the day the slot occurs; today or future — BR-006);
  - `start_time` (start of the slot);
  - `end_time` (end of the slot; strictly after `start_time`, same date —
    BR-005). The interval may alternatively be represented as
    `start_time` + `duration`; both describe the same interval, the choice is
    an Architect decision with no business difference (the business invariant
    is BR-005);
  - `capacity_limit` (required positive integer — BR-007);
  - `status` (`active` / `inactive` / `cancelled`, default `active` — BR-002,
    AS-07);
  - `label` (optional free text, e.g., "Franja mañana", "Horario pico"; no
    business rules on content; technical max length is an Architect/Developer
    detail, e.g., 255);
  - timestamps.
- No location field: the MVP has one location and turnos implicitly belong to
  it (BR-010).
- No relationship to Client, Membership, Plan or User in this Specification;
  the Booking entity that will reference turnos is SPEC-007.

Modified:

- Turno date, start time, end time, capacity limit and label via edit
  (FR-004).
- Turno status on deactivate / reactivate / cancel (FR-005..FR-007).

Deleted:

- No hard deletion of turno records in the MVP (BR-009); no delete operation.
  Deactivation and cancellation are used instead.

No seeder is required: turnos are created by staff in the admin panel only.

---

## 11. Acceptance Criteria

- [ ] AC-1: ADMIN or TRAINER can create a turno with date, start time, end time
  and capacity limit (required) plus an optional label; the record is persisted
  as `active` and listed (FR-001, FR-002, BR-002).
- [ ] AC-2: Creating/editing a turno with end time ≤ start time is rejected
  with a validation error (ERR-002, BR-005).
- [ ] AC-3: Creating/editing a turno with a past date is rejected with a
  validation error (ERR-003, BR-006).
- [ ] AC-4: Creating/editing a turno with a capacity limit that is missing,
  non-integer or less than 1 is rejected with a validation error (ERR-004,
  BR-007).
- [ ] AC-5: ADMIN or TRAINER can list turnos and filter them by date range and
  by status (FR-002).
- [ ] AC-6: ADMIN or TRAINER can view a turno's full detail including status
  (FR-003, FR-008).
- [ ] AC-7: ADMIN or TRAINER can edit an active/inactive turno's fields;
  changes persist (FR-004).
- [ ] AC-8: ADMIN or TRAINER can deactivate an active turno; it becomes
  `inactive` and is displayed as such (FR-005, FR-008, BR-003).
- [ ] AC-9: ADMIN or TRAINER can reactivate an inactive turno; it becomes
  `active` again (FR-006, AF-001, BR-003).
- [ ] AC-10: ADMIN or TRAINER can cancel an active or inactive turno; it
  becomes `cancelled` and cannot be edited, reactivated or cancelled again
  (FR-007, ERR-006, BR-004).
- [ ] AC-11: A CLIENT or anonymous visitor cannot create, view, list, edit,
  deactivate, reactivate or cancel turnos (ERR-005, BR-012).
- [ ] AC-12: Overlapping turnos on the same date can both be created and exist
  independently (BR-008, AF-005).
- [ ] AC-13: No delete operation exists for turnos; a created turno record
  persists even after its date passes (BR-009, AF-004).
- [ ] AC-14: Creating, editing, deactivating, reactivating or cancelling a
  turno never creates, modifies or deletes any booking, attendance or
  membership record (D-07; BR-013; the Booking entity is SPEC-007).

---

## 12. Out of Scope

The following are explicitly NOT part of this Specification:

- Capacity enforcement: checking capacity against existing bookings, rejecting
  a booking when the turno is full, and any booking-time capacity logic
  (deferred to SPEC-007; C-16).
- The Booking entity and everything about bookings, including what happens to
  existing bookings when a turno is deactivated or cancelled, and any
  restriction on deactivating/cancelling a turno that has bookings (SPEC-007;
  BR-013).
- Client-facing turno visibility, client booking and the client portal
  (SPEC-007 Bookings, SPEC-013 Client Portal).
- The access rule (D-05): what an active membership grants for booking /
  attendance (SPEC-007 / SPEC-008).
- Class / group-session management, sessions led by trainers, and capacity
  management for classes (C-16 future scope; D-07 option 1).
- The domain model's `Schedule → Session → Booking` hierarchy; no Schedule or
  Session entities in the MVP (AS-02).
- Gym operating hours (opening/closing) validation and recurring / weekly
  schedule templates (`analyst-pass-001.md` §5.8: not defined).
- Trainer availability scheduling for trainer-led sessions.
- Multi-location and multi-tenancy (BR-010; ARCHITECTURE §17-18).
- Timezone support / timezone conversion (BR-011, AS-09).
- Turnos crossing midnight (BR-005, AS-03).
- Notifications to clients when a turno is cancelled or deactivated (depends on
  SPEC-007 bookings data).
- Waitlists, booking limits, no-show rules (SPEC-007; D-08).

---

## 13. Dependencies

- **SPEC-001 (Authentication & Roles) — COMPLETED** (`docs/sdd/state.yaml`):
  authentication and session foundation; fixed role catalog with `Role::ADMIN`
  and `Role::TRAINER`; admin panel access (ADMIN | TRAINER); `User::hasRole` /
  `User::hasAnyRole` helpers; policy pattern (`UserPolicy`, `ClientPolicy`,
  `PlanPolicy`, `MembershipPolicy`); `EnsureUserHasRole` middleware. SPEC-001 §2
  explicitly defers TRAINER feature permissions for "schedules" to later
  Specifications — this Specification defines them (AS-01).
- **SPEC-002 (Client Management) — COMPLETED** (`docs/sdd/state.yaml`):
  referenced for context and conventions only; turnos do not reference client
  records.
- **SPEC-007 (Bookings)** — future; depends on this Specification. The turno
  model (date, start/end, capacity_limit, status) is the reference that
  SPEC-007 will book against; SPEC-007 must define capacity enforcement and the
  booking consequences of turno status transitions (BR-013).
- Gate decisions: **D-07 option 1** (pre-approved, NIGHT MODE,
  `docs/sdd/state.yaml`); **C-16** (confirmed). Decision **D-05** is excluded
  by instruction (belongs to SPEC-007/008).
- Confirmed decisions used: C-01 (roles, multi-role), C-13 (client isolation),
  C-14 (single location), C-15 (presentation contexts), C-16 (free-weight gym,
  future classes).
- Architecture constraints used: ARCHITECTURE §5 (presentation contexts), §15
  (scheduling: future sessions/classes/capacity; MVP does not require full
  class management), §17 (single location), §20 (simplest correct
  architecture).
- Flagged assumptions AS-01 to AS-09 require Product Owner confirmation before
  Implementation (see §14.1).

---

## 14. Open Questions

### 14.1 Assumed decisions requiring PO confirmation

These are flagged assumptions. They are needed to make the Specification
implementable, but they are NOT confirmed business rules.

| ID | Assumption | Source | Blocks / Affects |
| --- | --- | --- | --- |
| AS-01 | Scheduling management (create, view, edit, deactivate, reactivate, cancel) is performed by ADMIN and TRAINER in the admin panel; CLIENT has no scheduling access at this stage (client-facing visibility deferred to SPEC-007/013). | Task directive "ADMIN/TRAINER per C-03/C-15"; SPEC-001 §2 (TRAINER "schedules" feature permissions defined by later Specifications); C-15 (admin panel is the ADMIN+TRAINER context); C-03 (Trainer operates gym activities) | FR-001..FR-008, BR-012, ERR-005, §9 |
| AS-02 | The turno is a standalone scheduling entity; the domain model's Schedule → Session → Booking hierarchy is NOT implemented in the MVP. | D-07 option 1 (turno = access slot, not session); objective; C-16 | BR-001, §12 |
| AS-03 | A turno is defined by date + start_time + end_time on a single date; it does not cross midnight. | Task objective "keep simple"; not documented | BR-005, ERR-007 |
| AS-04 | A turno's date must be today or future on create/edit; status transitions are date-independent (operational cleanup of past turnos allowed). | Task objective (edge case "past dates"); not documented | BR-003, BR-006, ERR-003, AF-004 |
| AS-05 | Overlapping turnos (and identical date/start/end turnos) are allowed; no overlap or uniqueness constraint. | Derived from D-07 (independent capacity-limited slots) and C-16 (future classes may overlap access slots); no documented prohibition | BR-008, AF-005, AC-12 |
| AS-06 | Capacity limit is required, integer ≥ 1; no maximum. | D-07 option 1 "capacity-limited"; task objective "MVP minimum" | BR-007, ERR-004, FR-009 |
| AS-07 | Status semantics: created `active`; `active` ↔ `inactive` reversible (deactivate/reactivate); `active`/`inactive` → `cancelled` terminal (no edit/reactivate/cancel again). | Mirrors SPEC-003 AP-02 (active/inactive toggle) and SPEC-004 AM-10 (cancelled terminal); status set given in task objective | BR-002, BR-003, BR-004, FR-005..FR-007, ERR-006 |
| AS-08 | No hard deletion of turno records; deactivation/cancellation instead; preservation pattern. | AGENTS.md §12; SPEC-001 BR-007 / SPEC-002 BR-006 / SPEC-003 BR-004 / SPEC-004 BR-015 | BR-009, AC-13 |
| AS-09 | MVP single location: no location field and no timezone handling; times are local. | C-14, ARCHITECTURE §17; task objective "single location — keep simple" | BR-010, BR-011 |

### 14.2 Open questions to be answered before Implementation (or at latest before Review)

- OQ-01: Should TRAINER receive the full scheduling management set (create /
  edit / deactivate / cancel) or a restricted subset (e.g., view-only, or
  create-only)? This Specification assumes full management (AS-01).
- OQ-02: Should two turnos with the same date, start and end be rejected as
  duplicates, or allowed as independent records? This Specification allows
  them (AS-05).
- OQ-03 (C-16): Is a slot type / category field needed now to prepare for
  future sessions/classes, or is it additive later? This Specification does
  not introduce one (no speculative fields).
- OQ-04: Must SPEC-007 restrict editing/deactivating/cancelling a turno once
  bookings exist, and what happens to those bookings? Deferred to SPEC-007 by
  design (BR-013); must be answered when SPEC-007 is specified.
- OQ-05: Are a maximum capacity and a minimum/maximum duration needed? This
  Specification imposes none (AS-06, BR-005).
- OQ-06: Should staff be allowed to create turnos only for today+future, or for
  any date (e.g., backfilling history)? This Specification assumes today+future
  (AS-04).
- OQ-07 (analyst-pass-001 §5.8): Are gym operating hours and recurring / weekly
  schedule templates needed in the MVP? This Specification assumes they are out
  of scope (§12).

---

## 15. Related Documents

- Product documentation: `docs/product/product-definition-v0.1.md`
- Domain documentation: `docs/domain/domain-model-v0.1.md`
- Requirements analysis: `docs/requirements/analyst-pass-001.md` (§5.8
  Scheduling, D-07, C-16, T-01, R-04)
- Specifications: `docs/specs/SPEC-001.md`, `docs/specs/SPEC-002.md`,
  `docs/specs/SPEC-003.md`, `docs/specs/SPEC-004.md`
- Architecture documentation: `docs/architecture/SPEC-001.md`,
  `docs/architecture/SPEC-002.md`, `ARCHITECTURE.md`
- Architecture decisions: `docs/adr/ADR-001.md`, `docs/adr/ADR-002.md`
- Workflow state: `docs/sdd/state.yaml`
- Development rules: `AGENTS.md`
