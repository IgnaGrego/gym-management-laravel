# SPEC-008 — Attendance

## Status

Draft (analysis phase).

This is the eighth Specification of the MVP. It depends on SPEC-001
(Authentication & Roles), SPEC-002 (Client Management) and SPEC-004 (Membership
Management), all COMPLETED and implemented in the repository
(`docs/sdd/state.yaml`). It also reads the Turno model (SPEC-006, COMPLETED) for
the optional session link. SPEC-007 (Bookings) is BLOCKED on an unrelated
decision (`docs/sdd/state.yaml`): this Specification does NOT depend on it and
deliberately keeps the booking link future/optional.

**NIGHT MODE notice:** this Specification is produced under the PO pre-approval
("modo nocturno", `docs/sdd/state.yaml` `project.po_decisions`). The gates are
pre-approved as follows:

- **D-05 — option 1** (access rule): an **ACTIVE membership is required for gym
  access (attendance)**. **No grace period** after expiry (option 3 not chosen).
- **D-09 — option 3** (attendance scope + recording): attendance covers **BOTH
  gym access AND sessions**, and recording is **STAFF MANUAL check-in in the
  MVP** (client self-check-in via PIN/QR is future scope). IMPORTANT: in this
  MVP there are no trainer-led sessions (D-07 option 1, SPEC-006: a turno is a
  gym-access slot), so "both" is interpreted as: attendance **records gym-access
  events**, and the sessions dimension stays represented **conceptually** — an
  attendance record may optionally reference a turno (and, in the future, a
  booking) if one exists, but the primary scope is gym access.
- **D-19 — option 1** (roles and staff types, CONFIRMED in SPEC-001): the role
  catalog stays at ADMIN / TRAINER / CLIENT; there is **no RECEPTIONIST role**;
  front-desk tasks (check-in) are assigned to **TRAINER** (and ADMIN).

All business gates needed by this Specification are covered by the pre-approved
decisions above or by confirmed decisions (C-01, C-02, C-13, C-15, D-01 option
2, D-06 option 2). **No NOT COVERED blocking business decision was found for
this Specification.**

**Assumption notice:** this specification contains explicitly flagged assumptions
(AT-01 to AT-10, see §14.1) that fill gaps required to make the specification
implementable. They are either operational defaults consistent with the
pre-approved decisions (duplicate check-ins, backdating, booking-link deferral,
turno-link validation), authorization consequences of SPEC-013 not being
implemented yet, or natural invariants of the Attendance concept (immutability,
audit trail). **None of them is a confirmed business rule** unless stated
otherwise. Each requires Product Owner confirmation before Implementation (or
at latest before Review).

---

## 1. Objective

Provide attendance management in the gym management system:

- define the **Attendance** entity: an event record that a Client accessed the
  gym (domain-model §Attendance, D-09 option 3). In the MVP it records a
  gym-access event; the sessions dimension is represented conceptually through
  the optional turno link (D-07 option 1, SPEC-006 — no trainer-led sessions
  exist; no session entity);
- **recording mechanism**: staff — ADMIN and TRAINER — manually check in
  clients from the admin panel (D-09 option 3; D-19 option 1; no RECEPTIONIST
  role, front-desk tasks assigned to TRAINER);
- **access gate (D-05 option 1)**: only clients with at least one ACTIVE
  membership may be checked in; there is **no grace period** after expiry. The
  gate is evaluated **at check-in time**, so a membership that expires while the
  client attends (edge case E-01) is handled at the door: the client is checked
  in while their membership is active and denied once it is not;
- a check-in record stores: the client, the access timestamp, the staff User who
  recorded it, and optionally the turno (and, in the future, the booking) the
  client was attending, plus optional notes. The record is minimal:
  client + timestamp + who recorded it + optional links (task directive);
- attendance records are an **immutable event log**: no edit, no delete, no
  status transitions (preservation pattern, AGENTS.md §12; the same audit
  stance as SPEC-005 PY-05);
- attendance does **NOT require a booking** in the MVP: bookings do not exist
  yet (SPEC-007 BLOCKED) and gym access is the primary scope (D-09 option 3 read
  against D-07 option 1). The booking link is future/optional (AT-04);
- client isolation is preserved: a CLIENT never accesses another client's
  attendance data (C-13); client self-view of their own attendance belongs to
  SPEC-013 (Client Portal).

This is the operational record that later Specifications consume: the Client
Portal (SPEC-013) will expose a client's own attendance history, and Bookings
(SPEC-007, when unblocked) will tie a confirmed booking to the `completed`
attendance event (SPEC-007 BK-03 / BK-13 — reserved here, not implemented).

---

## 2. Actors

Actors are defined by role, not by person (SPEC-001 §2). A single User may hold
one or more roles (confirmed decision C-01).

| Actor | Description |
| --- | --- |
| Anonymous visitor | No access to attendance. Attendance data is never exposed on public pages. |
| ADMIN | Staff who administer the gym. Can record (check in), list, filter and view attendance records (assumption AT-01; D-19 option 1). |
| TRAINER | Staff who train clients. Can record (check in), list, filter and view attendance records (assumption AT-01; D-19 option 1: front-desk tasks assigned to TRAINER; consistent with SPEC-006 AS-01 and SPEC-005 PY-01). |
| CLIENT | A gym member. No attendance management in this Specification: the client cannot record or view attendance here — client self-view of their own attendance is deferred to SPEC-013 (Client Portal), which is not implemented yet (assumption AT-01). Client isolation (C-13) always applies. |

Notes:

- A User holding ADMIN and/or TRAINER uses the admin panel; a User holding CLIENT
  uses the client portal (SPEC-001 FR-006, confirmed C-15).
- A User holding both a staff role and CLIENT (e.g., a trainer who also trains)
  is permitted by C-01; a staff member who also holds CLIENT may themselves be
  checked in as a client (edge case E-11) — see AF-005 and assumption AT-10; the
  mixed-role behavior is tracked as SPEC-001 OQ-04.
- "Staff" in this Specification means ADMIN and/or TRAINER; there is no
  RECEPTIONIST role (confirmed SPEC-001 A-04 / D-19 option 1; same convention as
  SPEC-005 PY-01 and SPEC-007 BK-02).

---

## 3. Preconditions

1. SPEC-001 is implemented and completed: authentication, fixed role catalog
   (ADMIN, TRAINER, CLIENT), admin panel access (ADMIN or TRAINER), role helpers
   (`hasRole` / `hasAnyRole`), policy pattern, no hard deletion of User records
   (`docs/sdd/state.yaml`, ADR-001).
2. SPEC-002 is implemented and completed: client records exist, DNI unique,
   ADMIN-only client management; a linked User account is optional (PO-confirmed
   D-01) (`docs/sdd/state.yaml`, ADR-002).
3. SPEC-004 is implemented and completed: membership records exist with
   `client_id`, `plan_id`, `start_date`, `end_date`, `duration_days` and the
   four-state machine (`pending` / `active` / `expired` / `cancelled`); the
   `expired` state is materialized by the daily command `memberships:expire`
   (ADR-004); a client may hold several concurrent memberships, including several
   `active` ones (D-06 option 2 confirmed; SPEC-004 BR-010) (`docs/sdd/state.yaml`).
4. SPEC-006 is implemented and completed (context for the optional link only):
   turno records exist with `date`, `start_time`, `end_time`, `capacity_limit`
   and the three-state machine (`active` / `inactive` / `cancelled`); a turno is
   a gym-access slot, not a trainer-led session (D-07 option 1) (`docs/sdd/state.yaml`).
5. An authenticated ADMIN or TRAINER exists and can access the admin panel
   (SPEC-001 FR-008).
6. SPEC-007 (Bookings) is BLOCKED and NOT implemented: no bookings table exists;
   this Specification does NOT depend on it and defers the booking link (AT-04).
7. SPEC-013 (Client Portal) is NOT implemented yet: attendance entry points are
   staff-created in the admin panel only (AT-01).
8. No attendance tables exist yet; the Attendance module is greenfield on top of
   the SPEC-001/002/004 foundations (SPEC-006 is a completed context for the
   optional turno link).
9. The gates D-05 (option 1) and D-09 (option 3) are pre-approved, and D-19
   (option 1) is confirmed (NIGHT MODE, `docs/sdd/state.yaml`).

---

## 4. Functional Requirements

### FR-001 — Record check-in (create attendance)

An ADMIN or TRAINER can record a check-in for an existing client. The staff
member selects the client (search by name or DNI, per SPEC-002 fields). Required
field: client. Optional fields: turno (the gym-access slot the client is
attending), notes. `attended_at` defaults to the current gym-local time (AT-05).
The system evaluates the access gate (BR-003): if the client qualifies, an
attendance record is persisted with `attended_at`, `recorded_by` = the current
staff User, the optional turno link and notes; if the client does not qualify,
no record is created and the denial reason is shown (FR-005, ERR-003, ERR-004).
Recording a check-in does NOT create or modify any Client, Membership, Turno,
Plan or User record (BR-012).

### FR-002 — List and filter attendance

An ADMIN or TRAINER can list attendance records and filter them by client (name
or DNI), date range, recorded_by, and turno, so the daily access log and each
client's check-ins are visible at a glance.

### FR-003 — View attendance detail

An ADMIN or TRAINER can view an attendance record's full detail: client,
`attended_at`, `recorded_by` (which staff User recorded the check-in), the
optional turno link, and notes.

### FR-004 — View a client's attendance history

An ADMIN or TRAINER can view all attendance records of a client in chronological
order (`attended_at`), so the client's access history is visible (C-02; same
pattern as SPEC-004 FR-004 for membership history).

### FR-005 — Display the access decision and its reason

The check-in flow shows the access decision for the selected client: whether the
client currently qualifies (at least one active membership, BR-003) and, when
they do not, the reason (no membership at all vs. no qualifying active
membership vs. membership expired), so staff understand why a check-in is denied
(ERR-003, ERR-004). Display only: the enforcement is BR-003, server-side.

### FR-006 — Display who recorded the check-in

Attendance lists and detail views always show `recorded_by` (the staff User who
recorded the check-in), so the access log is auditable (BR-011, AT-08).

---

## 5. Business Rules

### BR-001 — Attendance definition

An Attendance record is an event record that a Client accessed the gym
(domain-model §Attendance; D-09 option 3). In the MVP it records a gym-access
event. The sessions dimension is represented conceptually: the optional
`turno_id` link may reference the gym-access slot the client used (D-07 option
1, SPEC-006 AS-02). There is no Session entity and no class-attendance concept
in the MVP (C-16, SPEC-006 AS-02). An attendance record has NO status: it is an
event, not a stateful entity (AT-07).

### BR-002 — Attendance references one client

An attendance record belongs to exactly one Client; `client_id` is mandatory
(FR-001). The reference is the Client record (PO-confirmed D-01 option 2): a
client does not need a linked User account to be checked in by staff (AF-003).
An attendance record is never created without a client (ERR-001, ERR-002).

### BR-003 — Access gate: active membership required (D-05 option 1)

A check-in can be recorded only for a client who, **at check-in time**, has at
least one membership with:

- status `active` (SPEC-004 BR-004) AND
- `end_date >= today` (defensive against the `memberships:expire` command window:
  no membership is ever reported active after its end date — SPEC-004 BR-007;
  same rule as SPEC-007 BR-005).

There is **no grace period** after expiry (D-05 option 3 not chosen). Memberships
in `pending`, `expired` or `cancelled` state do not qualify (SPEC-004 BR-005,
BR-007, BR-008). Multiple active memberships (D-06 option 2, confirmed): at
least one qualifying membership suffices; there is no "primary membership"
selection (same as SPEC-007 BK-08).

### BR-004 — Gate evaluated at check-in time only

The access gate is evaluated when the check-in is recorded. A membership that
expires **after** the check-in does not retroactively invalidate the attendance
record (edge case E-01 handled at the door; same rule as SPEC-007 BK-09). A
client who was checked in while qualifying keeps the record; the next check-in
is evaluated fresh against the then-current membership state.

### BR-005 — Recording mechanism is staff manual

Attendance is recorded by staff manual check-in from the admin panel (D-09
option 3). Client self-check-in (PIN/QR) is NOT part of the MVP (future scope).
There is no public or client-facing check-in entry point in this Specification
(AT-01; SPEC-013 future).

### BR-006 — No booking requirement

Attendance does NOT require a booking in the MVP: bookings do not exist yet
(SPEC-007 BLOCKED) and gym access is the primary scope (D-09 option 3 read
against D-07 option 1). A client with a qualifying membership can be checked in
whether or not a booking exists; the booking link is future/optional (AT-02,
AT-04). The `confirmed → completed` transition reserved by SPEC-007 (BK-03,
BK-13) is NOT implemented by this Specification (see §12).

### BR-007 — Access timestamp

`attended_at` is the gym-local timestamp of the access event (same local-time
convention as SPEC-006 BR-011; no timezone handling). It is required and must
not be in the future (ERR-005). It defaults to the current time at check-in;
backdating is allowed so staff can record a check-in that happened earlier
(AT-05; same backdating stance as SPEC-005 PY-03).

### BR-008 — No hard deletion, no edit

Attendance records are never hard-deleted and cannot be edited: they are an
immutable event log (preservation pattern, AGENTS.md §12 — the same stance as
SPEC-001 BR-007 / SPEC-002 BR-006 / SPEC-004 BR-014 / SPEC-006 BR-009, plus
SPEC-005 PY-05 immutability for recorded financial events). No edit or delete
operation is provided; a mistaken check-in is corrected manually outside the
system (AT-07).

### BR-009 — Attendance management is ADMIN/TRAINER

Only ADMIN and TRAINER can record (check in), list, filter and view attendance
records in the MVP (assumption AT-01; D-19 option 1). CLIENT has no attendance
access here; client self-view of their own attendance is deferred to SPEC-013
(BR-010).

### BR-010 — Client isolation

A CLIENT must never access another client's attendance data (C-13). In this
Specification, CLIENT has no attendance access at all (BR-009); when SPEC-013 is
implemented, a CLIENT will view only their OWN attendance records.

### BR-011 — Audit trail

Every attendance record stores `recorded_by` — the staff User who performed the
check-in — for auditability (same convention as SPEC-005 PY-06 for payments;
assumption AT-08). `recorded_by` is required; it is set to the authenticated
staff User on check-in and is never modified (BR-008).

### BR-012 — Recording boundary

Recording a check-in creates ONLY the attendance record. It never creates,
modifies or deletes a Client, Membership, Turno, Plan or User record, and it
never creates or modifies a Booking (none exist; SPEC-007 BLOCKED) (C-02/C-07
spirit; same boundary discipline as SPEC-007 BR-001/BR-002 and SPEC-006
BR-013). The optional turno link only references an existing turno (AT-06,
ERR-006); it does not change the turno's state or capacity.

---

## 6. Main Flow

1. An authenticated ADMIN or TRAINER opens the Attendance section of the admin
   panel (FR-002).
2. Staff search and select an existing client by name or DNI (FR-001).
3. The system evaluates the access gate against the client's memberships:
   at least one membership with status `active` AND `end_date >= today`
   (BR-003, D-05 option 1).
4. If the client qualifies: staff confirm the check-in, optionally select the
   turno being attended and add notes, and save. The system validates: client
   exists (ERR-002), `attended_at` not in the future (ERR-005), optional turno
   exists (ERR-006), required fields present (ERR-001).
5. The attendance record is persisted with `attended_at` (default now, or the
   backdated value), `recorded_by` = the current staff User, and the optional
   turno link and notes (FR-001, BR-011). The record appears in the attendance
   list (FR-002) and in the client's attendance history (FR-004), with
   `recorded_by` shown (FR-006).
6. If the client does NOT qualify: no record is created and the system shows the
   denial reason (no membership / no qualifying active membership / membership
   expired) (FR-005, ERR-003, ERR-004).

---

## 7. Alternative Flows

### AF-001 — Backdated check-in

A client arrived before being checked in (e.g., staff were busy at the door).
Staff record the check-in with `attended_at` set to the actual access time
(past). The record is created with the backdated timestamp and `recorded_by` =
current user; `attended_at` must still not be in the future (BR-007, AT-05,
ERR-005).

### AF-002 — Check-in with a turno link

Staff optionally select the turno the client is attending (e.g., the access slot
they used). The referenced turno must exist (ERR-006); no turno status, time or
capacity validation is applied to the link in this Specification (AT-06 — the
turno link is optional metadata; booking/capacity semantics belong to SPEC-007,
which is blocked). The turno record itself is not modified (BR-012).

### AF-003 — Client without a linked User account

A client without a provisioned User account (SPEC-002 BR-001, D-01 option 2
confirmed) can still be checked in by staff: the attendance record references
the Client record, not the User (BR-002). The client simply cannot self-serve
yet (SPEC-013).

### AF-004 — Multiple check-ins on the same day

A client leaves and returns, or attends twice in the same day (e.g., morning and
evening slot). Each check-in creates an independent attendance record; there is
no uniqueness constraint on (client, day) and no rejection of a second check-in
(AT-03). Each record is a separate gym-access event.

### AF-005 — Staff member who is also a client (E-11)

A User holding TRAINER (or ADMIN) and CLIENT may themselves be checked in as a
client by another staff member (or, for ADMIN, possibly by a TRAINER), provided
the access gate passes — the check-in is evaluated purely against the Client's
memberships (BR-003). This follows the union-of-permissions rule (SPEC-001
BR-002, C-01); the broader mixed-role behavior (e.g., who may check in whom when
the same person holds both roles) is tracked as SPEC-001 OQ-04 (AT-10).

### AF-006 — Membership expires right after the check-in (E-01)

A client is checked in while holding a qualifying active membership; the
membership expires later that day or the next. The already-recorded check-in
remains valid: the gate is evaluated at check-in time only (BR-004, AT-09). The
next check-in is evaluated fresh; with no grace period (D-05 option 1), a
membership whose end date has passed does not qualify (BR-003, ERR-004).

### AF-007 — Self-service attendance view (future)

When SPEC-013 (Client Portal) is implemented, a CLIENT will view their own
attendance history; until then there is no client-facing attendance path
(AT-01, BR-010). Out of scope here (§12).

---

## 8. Error Cases

### ERR-001 — Missing required fields

Condition: recording a check-in without a client.

Expected behavior: rejected with a validation error; an attendance record always
references a client (FR-001, BR-002).

### ERR-002 — Nonexistent client

Condition: the selected client does not exist (e.g., stale reference).

Expected behavior: rejected with a validation error; the attendance record
references an existing client (BR-002).

### ERR-003 — No membership at all (access gate)

Condition: recording a check-in for a client with no membership records.

Expected behavior: rejected with the access-gate reason "the client has no
membership"; no record is created (BR-003, D-05 option 1, FR-005).

### ERR-004 — No qualifying active membership / expired (access gate)

Condition: recording a check-in for a client whose memberships are all
`pending`, `expired` or `cancelled`, or whose only `active` membership has an
end date before today (edge case E-01 at the door; no grace period — D-05 option
1).

Expected behavior: rejected with the access-gate reason "no active membership" /
"membership expired"; no record is created (BR-003, FR-005).

### ERR-005 — Future timestamp

Condition: `attended_at` is in the future.

Expected behavior: rejected with a validation error (BR-007, AT-05).

### ERR-006 — Nonexistent turno link

Condition: a turno is referenced in the check-in but does not exist (e.g., stale
reference).

Expected behavior: rejected with a validation error; the optional turno link
must reference an existing turno (AT-06, BR-012).

### ERR-007 — Unauthorized access

Condition: an anonymous visitor or a CLIENT attempts to record, list, filter or
view attendance.

Expected behavior: access denied (redirect for anonymous; 403 for CLIENT)
(BR-009, AT-01). A CLIENT cannot access another client's attendance data
(BR-010, C-13).

### ERR-008 — Attempted edit or deletion

Condition: an attempt is made to edit or delete an existing attendance record.

Expected behavior: no such operation exists; the record is immutable (BR-008,
AT-07). There is no update or delete ability in the policy and no edit/delete
path in the UI (§9).

---

## 9. Authorization

| Operation | Anonymous | ADMIN | TRAINER | CLIENT |
| --- | --- | --- | --- | --- |
| Record check-in (create attendance) | Denied | Allowed (BR-009, AT-01) | Allowed (BR-009, AT-01) | Denied |
| List / filter attendance | Denied | Allowed (BR-009) | Allowed (BR-009) | Denied |
| View attendance detail | Denied | Allowed (BR-009) | Allowed (BR-009) | Denied |
| View a client's attendance history | Denied | Allowed (BR-009) | Allowed (BR-009) | Denied |
| Edit / delete attendance records | Denied | Denied (no such operation — BR-008) | Denied (no such operation — BR-008) | Denied (no such operation — BR-008) |
| Client self-view of own attendance | Out of scope (SPEC-013) | — | — | Out of scope at this stage (AT-01) |
| Access another client's attendance data | Denied | Allowed (staff duty) | Allowed (staff duty) | Denied always (BR-010, C-13) |

Notes:

- A User holding several roles receives the union of the column permissions
  (SPEC-001 BR-002); an ADMIN or TRAINER who is also CLIENT can record and view
  attendance in the admin panel (AF-005).
- Authorization is enforced server-side; frontend-only restrictions are never
  sufficient (AGENTS.md §17).
- The access gate (BR-003) is NOT an authorization rule: it is a business
  validation enforced at check-in time, the same way SPEC-007 enforces its
  access gate and SPEC-004/006 enforce their state rules.
- Whether TRAINER receives the FULL attendance set is assumed as full (AT-01),
  consistent with SPEC-006 AS-01 and SPEC-005 PY-01; PO confirmation requested
  (OQ-04).

---

## 10. Data Changes

This Specification describes the information that must exist; the exact
persistence schema, table name and model name are defined by the Architect (e.g.,
an `attendances` table with an `Attendance` model, following the repo's English
naming convention for models, the same way `turnos`/`Turno` was chosen in
SPEC-006 §10).

Created:

- Attendance records with the following information:
  - `client_id` — foreign key to `clients.id`, required (BR-002);
  - `attended_at` — timestamp, required, not in the future, defaults to the
    current gym-local time (BR-007, AT-05). The exact column type (e.g.,
    `timestamp` / `dateTime`) is an Architect decision; the business meaning is
    "when the client accessed the gym";
  - `recorded_by` — foreign key to `users.id`, required (BR-011, AT-08): the
    staff User who performed the check-in (SPEC-005 PY-06 precedent);
  - `turno_id` — foreign key to `turnos.id`, nullable (AT-06): the optional
    gym-access slot the client attended. Nullable and optional; no
    booking/capacity semantics in this Specification (BR-012);
  - `notes` — optional free text (FR-001; no business rules on content;
    technical max length is an Architect/Developer detail, e.g., 500);
  - `created_at` / `updated_at` timestamps.
  - **No `booking_id` column in the MVP** — DEFERRED (AT-04): the bookings table
    does not exist (SPEC-007 BLOCKED). When SPEC-007 is unblocked and
    implemented, a follow-up migration adds a nullable `booking_id` and the
    `confirmed → completed` tie-in is defined then (SPEC-007 BK-03/BK-13).
  - **No `status` column**: an attendance record is an event, not a stateful
    entity (BR-001, AT-07).
- Indexes to support the lists: on `client_id` (per-client history, FR-004), on
  `attended_at` (date-range filter, FR-002), and optionally on `recorded_by` and
  `turno_id` (filters/joins, FR-002). The exact index set is an Architect
  decision.

Modified:

- None. Attendance records are immutable: no field is modified by any operation
  (BR-008, AT-07).

Deleted:

- No hard deletion of attendance records in the MVP (BR-008); no delete
  operation.

No seeder is required: attendance records are created by staff check-in in the
admin panel only.

No change to the `clients`, `memberships`, `turnos` or `users` tables is made by
this Specification.

---

## 11. Acceptance Criteria

- [ ] AC-1: ADMIN or TRAINER can record a check-in for an existing client with at
  least one qualifying active membership; the record is persisted with
  `attended_at` (default now), `recorded_by` = the current staff User, and
  appears in the attendance list and in the client's attendance history
  (FR-001, FR-002, FR-004, BR-002, BR-003, BR-011).
- [ ] AC-2: Recording a check-in for a client with no membership is rejected with
  the "no membership" reason and no record is created (ERR-003, BR-003, FR-005,
  D-05 option 1).
- [ ] AC-3: Recording a check-in for a client whose memberships are all
  `pending`/`expired`/`cancelled`, or whose only `active` membership has an end
  date before today, is rejected with the "no active membership"/"membership
  expired" reason and no record is created (ERR-004, BR-003, E-01, no grace
  period).
- [ ] AC-4: A client with at least one qualifying active membership — including a
  client holding several concurrent active memberships (D-06 option 2) — can be
  checked in (BR-003, AF-005).
- [ ] AC-5: A check-in with `attended_at` in the future is rejected; a backdated
  check-in (past timestamp) is accepted (ERR-005, BR-007, AT-05, AF-001).
- [ ] AC-6: A check-in referencing a nonexistent turno is rejected; a check-in
  with no turno or with an existing turno is accepted and the turno record is
  not modified (ERR-006, AT-06, BR-012, AF-002).
- [ ] AC-7: Multiple check-ins for the same client on the same day are each
  recorded as independent records; none is rejected as a duplicate (AT-03,
  AF-004).
- [ ] AC-8: Attendance records have no edit and no delete operation; a created
  record persists unchanged (BR-008, ERR-008, AT-07).
- [ ] AC-9: A CLIENT or anonymous visitor cannot record, list, filter or view
  attendance (403 or redirect) (ERR-007, BR-009); a CLIENT cannot access another
  client's attendance data (BR-010, C-13).
- [ ] AC-10: A multi-role ADMIN + CLIENT or TRAINER + CLIENT user can record and
  view attendance in the admin panel (SPEC-001 BR-002, AF-005).
- [ ] AC-11: The attendance list shows `recorded_by` and supports filtering by
  client, date range, recorded_by and turno; the client's attendance history is
  in chronological order (FR-002, FR-003, FR-004, FR-006).
- [ ] AC-12: Recording a check-in never creates, modifies or deletes a Client,
  Membership, Turno, Plan or User record (BR-012, C-02/C-07).
- [ ] AC-13: The check-in flow displays the access decision and, on denial, the
  reason (no membership vs. no active membership vs. membership expired)
  (FR-005, ERR-003, ERR-004).
- [ ] AC-14: No `booking_id` column and no booking transition exist in this
  Specification: the `confirmed → completed` tie-in reserved by SPEC-007
  (BK-03/BK-13) is NOT implemented, and no attendance operation touches a
  booking (AT-04, BR-006, BR-012).

**Test plan (to be executed at Implementation; aligned with the existing Pest
layout under `tests/`, `tests/Pest.php` helpers `role()` / `userWithRoles()`,
`RefreshDatabase`, Livewire component tests as used in `TurnoManagementTest`):**

- `tests/Feature/Admin/AttendanceManagementTest.php` (Livewire): record a
  check-in with status-created record, `attended_at` default and `recorded_by`
  (AC-1); gate denials with reason (AC-2, AC-3, AC-13); gate pass with one and
  with several concurrent active memberships (AC-4); future timestamp rejected,
  backdating accepted (AC-5); turno link validation (AC-6); multiple check-ins
  per day (AC-7); no edit/delete path (AC-8); recording never touches
  client/membership/turno/plan/user records (AC-12); no booking column /
  transition (AC-14).
- `tests/Feature/Admin/AttendancePolicyTest.php`: ADMIN and TRAINER can
  viewAny/view/create; CLIENT and anonymous denied (403); a multi-role
  ADMIN + CLIENT or TRAINER + CLIENT user can manage attendance; no update or
  delete ability exists for anyone (AC-9, AC-10, ERR-008).
- `tests/Feature/Attendance/AccessGateTest.php`: the D-05 option 1 gate — reject
  no membership, pending-only, expired-only, cancelled-only, active-but-end-date-
  passed; allow with one active membership and with several concurrent active
  memberships (D-06 option 2); no grace period (AC-2..AC-4).
- `tests/Unit/AttendanceTest.php`: relationships (client, recordedBy, turno),
  `attended_at` default, the required `recorded_by` (BR-011), immutability (no
  status-changing, update or delete methods; no booking link in the MVP shape —
  AT-04), and the boundary rule that creating an attendance record creates no
  other record (BR-012).

---

## 12. Out of Scope

The following are explicitly NOT part of this Specification:

- **Bookings and the `confirmed → completed` transition** — SPEC-007 is BLOCKED
  and no bookings table exists. The transition reserved by SPEC-007 (BK-03,
  BK-13: "when attendance is recorded, a confirmed booking becomes completed")
  is NOT implemented here; the booking link column is deferred (AT-04, BR-006).
  Whether a client has a booking does not affect check-in in the MVP (BR-006).
- **Self check-in (PIN/QR)** and any client-facing or public check-in entry
  point — D-09 option 3 pre-approved: staff manual in the MVP; self check-in is
  future scope (BR-005).
- **Client self-view of own attendance** — SPEC-013 (Client Portal), not
  implemented yet (AT-01, BR-010, AF-007).
- **Check-out / session duration tracking** — attendance records the gym-access
  event only (check-in); no check-out event, no duration computation, no
  "currently inside" status.
- **No-show handling and booking penalties** — D-08 package (no penalties) and
  SPEC-007's responsibility; attendance does not mark bookings as missed in this
  Specification (BK-13 of SPEC-007; SPEC-007 §12).
- **Capacity enforcement of turnos** and any booking/capacity consequence of an
  attendance record referencing a turno — SPEC-007 (blocked) owns capacity
  semantics (SPEC-006 FR-009, C-16; AT-06).
- **Class / group-session attendance** and the `Schedule → Session → Booking`
  hierarchy — C-16 future scope; SPEC-006 AS-02; no session entity exists
  (BR-001).
- **Turno management** (create/edit/deactivate/reactivate/cancel) — SPEC-006,
  implemented; this Specification only optionally references turnos.
- **Membership management and the membership state machine** — SPEC-004,
  implemented; this Specification only consumes membership state for the access
  gate (BR-003).
- **Membership activation (pending → active)** — the SPEC-005 contract (SPEC-004
  FR-008); SPEC-005 is BLOCKED, so operationally memberships created in the admin
  panel remain `pending` and therefore do not qualify for check-in until SPEC-005
  exists (SPEC-004 AF-001; see §13). The gate and its tests use active
  memberships created directly (factories), as SPEC-007 does.
- **Editing, deleting, voiding or correcting attendance records** — no such
  operation exists; a mistaken check-in is corrected manually outside the system
  (BR-008, AT-07; same stance as SPEC-005 refunds "handled manually").
- **Notifications** to clients when a check-in is recorded — no notification
  infrastructure exists in the MVP.
- **Multi-location and timezone handling** — single location, gym-local time
  (C-14, ARCHITECTURE §17; SPEC-006 BR-011).
- **Hard deletion of attendance records** — no delete operation (BR-008).

---

## 13. Dependencies

- **SPEC-001 (Authentication & Roles) — COMPLETED** (`docs/sdd/state.yaml`):
  authentication, fixed role catalog with `Role::ADMIN` / `Role::TRAINER`;
  admin panel access (ADMIN | TRAINER); `User::hasRole` / `User::hasAnyRole`;
  policy pattern; no RECEPTIONIST role (D-19 option 1 confirmed); client
  isolation foundation (C-13). This Specification defines the attendance
  permissions (AT-01).
- **SPEC-002 (Client Management) — COMPLETED**: the client record is the required
  subject of an attendance record (`client_id`, BR-002); the linked User account
  is optional (D-01 option 2 confirmed) — staff can check in clients without
  accounts (AF-003).
- **SPEC-004 (Membership Management) — COMPLETED**: the membership state machine
  (`pending`/`active`/`expired`/`cancelled`) and the `end_date` period are
  consumed by the access gate (BR-003, D-05 option 1); multiple active
  memberships are allowed (D-06 option 2 confirmed, SPEC-004 BR-010); `expired`
  is materialized by the daily command `memberships:expire` (ADR-004) and the
  gate defensively checks `end_date >= today`. SPEC-005 is NOT required for this
  Specification: the gate reads membership state as-is, whether memberships were
  activated by SPEC-005 or remain `pending` (same stance as SPEC-007).
- **SPEC-006 (Scheduling & Turnos) — COMPLETED**: the `turnos` table exists and
  provides the optional `turno_id` link (AT-06). The turno is a gym-access slot,
  not a trainer-led session (D-07 option 1), which grounds the "gym access is
  the primary scope" reading of D-09 option 3 (BR-001, BR-006).
- **SPEC-007 (Bookings) — BLOCKED, NOT a dependency of this Specification**:
  no bookings table exists; the booking link column is deferred (AT-04) and the
  `confirmed → completed` transition is NOT implemented (§12). When SPEC-007 is
  unblocked and implemented, the booking link and the transition are defined in
  a follow-up.
- **SPEC-013 (Client Portal) — FUTURE**: client self-view of own attendance
  (AT-01, BR-010, AF-007). Not required for this Specification.
- Gate decisions: **D-05 option 1** (active membership required for attendance,
  no grace period), **D-09 option 3** (both scopes; staff manual recording) —
  pre-approved (NIGHT MODE, `docs/sdd/state.yaml`); **D-19 option 1** (no
  RECEPTIONIST; front-desk tasks to TRAINER) — confirmed.
- Confirmed decisions used: C-01 (roles, multi-role), C-02 (client aggregates
  attendance records), C-13 (client isolation), C-15 (presentation contexts),
  C-16 (free-weight gym; no class management in MVP), D-01 option 2 (client
  standalone; linked User optional), D-06 option 2 (multiple active
  memberships).
- Requirements analysis: `analyst-pass-001.md` §5.10 (Attendance), D-05, D-09,
  D-19, C-18, E-01, R-03.
- Flagged assumptions AT-01..AT-10 (§14.1) require Product Owner confirmation
  before Implementation (or at latest before Review).

---

## 14. Open Questions

### 14.1 Assumed decisions requiring PO confirmation

These flagged assumptions are operational defaults consistent with the
pre-approved decisions, authorization consequences of SPEC-013 not being
implemented, or natural invariants of the Attendance concept. They are NOT
confirmed business rules unless stated otherwise; prefix AT distinguishes this
Specification's assumptions from SPEC-001 (A-xx), SPEC-002 (AD-xx), SPEC-003
(AP-xx), SPEC-004 (AM-xx), SPEC-005 (PY-xx), SPEC-006 (AS-xx) and SPEC-007
(BK-xx).

| ID | Assumption | Source | Blocks / Affects |
| --- | --- | --- | --- |
| AT-01 | "Staff" for attendance (record check-in / list / filter / view / view client history) = ADMIN and TRAINER; there is no RECEPTIONIST role (D-19 option 1 confirmed in SPEC-001 A-04; front-desk tasks assigned to TRAINER). CLIENT has no attendance access in the MVP; client self-view of own attendance is deferred to SPEC-013. TRAINER receives the FULL attendance set, consistent with SPEC-006 AS-01 and SPEC-005 PY-01. | D-19 option 1 (confirmed); SPEC-001 A-04; task directive "ADMIN/TRAINER per D-19/C-15" | §2, FR-001..FR-004, BR-009, §9, ERR-007, OQ-04 |
| AT-02 | Attendance does NOT require a booking in the MVP: D-09 option 3 ("both; staff manual") is read against D-07 option 1 (turno = gym-access slot; no trainer-led sessions — SPEC-006) and the blocked SPEC-007 (no bookings table). Attendance records gym-access events; the sessions dimension is represented only conceptually via the optional turno link. A client with a qualifying membership is checked in whether or not a booking exists (bookings do not exist yet). | D-09 option 3 + D-07 option 1 + SPEC-007 blocked; task directive ("primary scope is gym access; optional links") | BR-001, BR-006, §12, AC-14 |
| AT-03 | Multiple check-ins per day are ALLOWED: each check-in creates an independent attendance record (event-log semantics, D-09 "records events"); no uniqueness constraint on (client, day). The alternative (at most one check-in per day) is rejected for the MVP because each check-in is a separate gym-access event; tracked as OQ-01. | Task directive ("pick sensible default consistent with 'records events', document as AS") | FR-002, BR-001, AF-004, AC-7 |
| AT-04 | The booking link column (`booking_id`) is DEFERRED: the attendance table created by this Specification does NOT include it, because the bookings table does not exist (SPEC-007 BLOCKED). When SPEC-007 is unblocked and implemented, a follow-up migration adds a nullable `booking_id` and the `confirmed → completed` transition is defined then (SPEC-007 BK-03/BK-13). The alternative (include a nullable unused column now) is rejected following the no-speculative-fields / module-boundary discipline (ADR-002; SPEC-006 §10 alternative 5). | Task directive ("decide if the booking link column is included now or deferred; document as assumption"); SPEC-007 blocked | §10, BR-006, AC-14, OQ-03 |
| AT-05 | `attended_at` is the gym-local timestamp of the access event. It defaults to the current time at check-in; it must not be in the future; backdating is allowed so staff can record a check-in that happened earlier. | Analyst necessity (validation); consistent with SPEC-005 PY-03 (backdating of payment dates) | FR-001, BR-007, ERR-005, AF-001, AC-5 |
| AT-06 | The optional `turno_id` link: when provided, the referenced turno must exist (ERR-006); no turno status/time/capacity validation is applied to the link in this Specification — the link is optional metadata representing the slot the client used, and booking/capacity semantics belong to SPEC-007 (blocked). | Task directive ("optional turno_id for future session linkage"); SPEC-006 BR-013 | FR-001, BR-012, ERR-006, AF-002, AC-6 |
| AT-07 | Attendance records are immutable event-log entries: no edit, no delete, no status (an attendance record is an event, not a stateful entity). A mistaken check-in is corrected manually outside the system. | Preservation pattern (AGENTS.md §12); SPEC-005 PY-05 (immutability of recorded financial events); SPEC-006 BR-009 | BR-001, BR-008, ERR-008, §12, AC-8 |
| AT-08 | Every attendance record stores `recorded_by` (the staff User who performed the check-in) for auditability; the field is required and set to the authenticated staff User on check-in. | Same convention as SPEC-005 PY-06 (`recorded_by` on payments) | BR-011, §10, FR-006, AC-1, AC-11 |
| AT-09 | The access gate is evaluated at check-in time only; a membership that expires after the check-in does not retroactively invalidate the record (edge case E-01 handled at the door). | D-05 option 1; same rule as SPEC-007 BK-09 | BR-004, AF-006, AC-3 |
| AT-10 | A staff User may record attendance for any Client, including a Client linked to their own User account when the staff member also holds the CLIENT role (edge case E-11). The gate is evaluated purely against the Client's memberships; the broader mixed-role behavior is tracked in SPEC-001 OQ-04. | Union-of-permissions rule (C-01, SPEC-001 BR-002); E-11 | AF-005, AC-10, OQ-02 |

### 14.2 Open questions to be answered before Implementation (or at latest before Review)

- OQ-01 (AT-03): Should a client be allowed multiple check-ins per day (each an
  independent record), or at most one per day? This Specification assumes
  multiple allowed (event-log semantics).
- OQ-02 (E-11, AT-10): For a staff User who also holds CLIENT, may they record
  their OWN check-in, or must another staff member record it? This Specification
  allows any authorized staff member (including themselves via the admin panel)
  to check in a client, evaluated purely against the client's memberships;
  SPEC-001 OQ-04 tracks the general mixed-role behavior.
- OQ-03 (AT-04): When SPEC-007 is unblocked, should the booking tie-in be a
  nullable `booking_id` column on attendance, or should completion be tracked on
  the booking side only (booking status → `completed`)? Design decision to
  revisit when SPEC-007 is specified.
- OQ-04 (AT-01): Should TRAINER receive the full attendance set (record / list /
  view), or a restricted subset (e.g., record-only, view-only)? This
  Specification assumes full, consistent with SPEC-006 AS-01 and SPEC-005 PY-01.
- OQ-05: Should the admin UI offer a client-side "attendance" relation manager
  (like `MembershipsRelationManager`) in addition to the Attendance resource?
  (Presentation choice; no business rule implied.)
- OQ-06: Should `attended_at` be editable after creation (e.g., to fix a wrong
  timestamp) or must a correction be handled manually outside the system? This
  Specification assumes immutable records (AT-07) with manual correction outside
  the system.
- OQ-07: Is a backdating limit needed (e.g., no check-in older than N days)?
  This Specification allows backdating without an explicit limit (AT-05), subject
  only to "not in the future".

---

## 15. Related Documents

- Product documentation: `docs/product/product-definition-v0.1.md` (Open
  Questions: "Is attendance linked to class bookings, general gym access, or
  both?" — resolved by D-09 option 3 / this Specification)
- Domain documentation: `docs/domain/domain-model-v0.1.md` (§Attendance; C-02)
- Requirements analysis: `docs/requirements/analyst-pass-001.md` (§5.10
  Attendance, D-05, D-09, D-19, C-18, E-01, R-03)
- Specifications: `docs/specs/SPEC-001.md` (roles, D-19 option 1, C-13/C-15),
  `docs/specs/SPEC-002.md` (client records, D-01 option 2),
  `docs/specs/SPEC-004.md` (BR-004/BR-005/BR-007/BR-008/BR-010/BR-016, AM-10,
  OQ-01), `docs/specs/SPEC-006.md` (turno model, D-07 option 1, AS-01/AS-02),
  `docs/specs/SPEC-007.md` (BK-03/BK-13 — the reserved `completed` tie-in;
  BLOCKED, not a dependency)
- Architecture documentation: `docs/architecture/SPEC-001.md`,
  `docs/architecture/SPEC-002.md`, `docs/architecture/SPEC-004.md`,
  `docs/architecture/SPEC-006.md`, `ARCHITECTURE.md` (§12 Authorization, §15
  Scheduling, §17 single location, §20 simplest correct architecture)
- Architecture decisions: `docs/adr/ADR-001.md` (role/authorization
  foundation), `docs/adr/ADR-002.md` (module boundary discipline),
  `docs/adr/ADR-003.md` (validation-first representation),
  `docs/adr/ADR-004.md` (status-as-string / `memberships:expire` command)
- Workflow state: `docs/sdd/state.yaml` (NIGHT MODE pre-approvals D-05/D-09 for
  SPEC-008; SPEC-001/002/004/006 completed; SPEC-007 blocked)
- Development rules: `AGENTS.md`
