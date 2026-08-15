# SPEC-007 — Bookings

## Status

Approved

This is the seventh Specification of the MVP. It depends on SPEC-001
(Authentication & Roles), SPEC-002 (Client Management), SPEC-004 (Membership
Management) and SPEC-006 (Scheduling & Turnos), all COMPLETED and implemented in
the repository (`docs/sdd/state.yaml`).

**NIGHT MODE notice:** this Specification is produced under the PO pre-approval
("modo nocturno", `docs/sdd/state.yaml` `project.po_decisions`). The gates are
pre-approved as follows:

- **D-05 — option 1** (access rule): an **ACTIVE membership is required for
  bookings** (and attendance — SPEC-008; here it applies to booking). **No grace
  period** after expiry (option 3 not chosen; option 1 is the pre-approved one).
- **D-08** (booking rules): the PO pre-approved the RECOMMENDED minimal MVP
  defaults **as a package**: **short lead time, no waitlist, cancellation
  without penalty, cancelled spots re-bookable**. Concrete values that the
  analyst must pick (e.g., what "short lead time" means) are documented as
  flagged assumptions (BK-04, BK-05, BK-06) requiring PO confirmation, following
  SPEC-006's assumption pattern. This Specification does NOT invent complex
  rules: no penalties, no waitlist, no per-client booking limits.
- **D-07 — option 1** (from SPEC-006): a turno is a **bookable time slot for gym
  access, capacity-limited**. The Turno model (`date`, `start_time`, `end_time`,
  `capacity_limit`, `status` active/inactive/cancelled) already exists and is
  implemented (SPEC-006 COMPLETED). SPEC-006 stores `capacity_limit` but does
  NOT enforce it; **capacity enforcement is this Specification's concern** (C-16,
  SPEC-006 FR-009 / §12).

**Product Owner decisions recorded in this revision:**

- **NC-01 (resolved 2026-08-15):** when staff CANCEL or DEACTIVATE a turno that
  has `confirmed` bookings, those `confirmed` bookings are AUTOMATICALLY
  cancelled and their spots are freed. Lowering a turno's `capacity_limit` below
  the number of `confirmed` bookings is NOT allowed — the operation is rejected
  until the bookings are cancelled first (by cancelling/deactivating the turno
  or cancelling bookings individually). No client notification is sent (no
  notification infrastructure exists in the MVP). Recorded in
  `docs/sdd/state.yaml` `SPEC-007.po_decisions`. There are no remaining blocking
  items for this Specification.

**Assumption notice:** this specification contains explicitly flagged assumptions
(BK-01 to BK-15, see §14.2) that fill gaps required to make the specification
implementable. They are either concrete values for the pre-approved D-08 package
(lead time, cancellation boundary, no per-client limit), authorization
consequences of SPEC-013 not being implemented yet, or natural invariants of the
Booking concept. **None of them is a confirmed business rule** unless stated
otherwise. Each requires Product Owner confirmation before Implementation (or at
latest before Review).

---

## 1. Objective

Provide booking management in the gym management system:

- define the **Booking** entity: a reservation made by a client for a turno
  (domain-model §Booking, adapted to D-07 option 1 — the turno is the bookable
  entity; the domain's `Schedule → Session → Booking` hierarchy is NOT
  implemented in the MVP, SPEC-006 AS-02);
- staff — ADMIN and TRAINER — can create, list/filter, view and cancel bookings
  from the admin panel on behalf of clients (assumption BK-01, BK-02);
  client-facing self-booking and self-cancellation belong to SPEC-013 (Client
  Portal), which is NOT implemented yet;
- **capacity enforcement**: a booking counts toward the turno's
  `capacity_limit`; a booking is rejected when the turno is full, and the check
  is enforced atomically so concurrent bookings cannot oversell a turno
  (D-07 option 1; C-16; SPEC-006 FR-009 deferred enforcement to this
  Specification);
- **access gate**: only clients with at least one ACTIVE membership may book
  (D-05 option 1, pre-approved; no grace period). The multiple-active-membership
  rule (D-06 option 2, confirmed) is handled: at least one active membership
  suffices;
- **booking lifecycle (D-08 package; NC-01 resolved)**: a booking is created
  `confirmed`; cancellation is without penalty at any time before the turno's
  start; a cancelled spot reopens and is re-bookable; there is NO waitlist. When
  the turno is cancelled or deactivated, its `confirmed` bookings are
  automatically cancelled and their spots freed (NC-01). A `completed` status is
  reserved as the SPEC-008 (Attendance) tie-in and is out of scope here (BK-03);
- a booking references the Client record (not the User account), so clients
  without a linked User account can be booked by staff (D-01 option 2 confirmed;
  BK-01).

This is the base for Attendance (SPEC-008), which will record gym access per
turno, and for the Client Portal (SPEC-013), which will expose self-service
booking.

---

## 2. Actors

Actors are defined by role, not by person (SPEC-001 §2). A single User may hold
one or more roles (confirmed decision C-01).

| Actor | Description |
| --- | --- |
| Anonymous visitor | No access to bookings. Booking data is never exposed on public pages. |
| ADMIN | Staff who administer the gym. Can create, view, list and cancel bookings on behalf of clients (assumption BK-02). |
| TRAINER | Staff who train clients. Can create, view, list and cancel bookings on behalf of clients (assumption BK-02, consistent with SPEC-006 AS-01 and SPEC-005 PY-01). |
| CLIENT | A gym member. No booking management in this Specification: the client cannot create, view or cancel their own bookings here — client-facing self-booking/self-cancellation is deferred to SPEC-013 (Client Portal), which is not implemented yet (assumption BK-01, BK-07). Client isolation (C-13) always applies. |

Notes:

- A User holding ADMIN and/or TRAINER uses the admin panel; a User holding CLIENT
  uses the client portal (SPEC-001 FR-006, confirmed C-15).
- A User holding both a staff role and CLIENT (e.g., a trainer who also trains)
  is permitted by C-01; the mixed-role behavior is tracked as SPEC-001 OQ-04.
- "Staff" in this Specification means ADMIN and/or TRAINER; there is no
  RECEPTIONIST role (confirmed SPEC-001 A-04; same convention as SPEC-005 PY-01).

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
4. SPEC-006 is implemented and completed: turno records exist with `date`,
   `start_time`, `end_time`, `capacity_limit` (positive integer) and the
   three-state machine (`active` / `inactive` / `cancelled`); the turno is the
   bookable slot; capacity is stored but NOT enforced by SPEC-006 (FR-009, C-16;
   enforcement is this Specification's concern) (`docs/sdd/state.yaml`).
5. An authenticated ADMIN or TRAINER exists and can access the admin panel
   (SPEC-001 FR-008).
6. The Client Portal (SPEC-013) is NOT implemented yet: booking entry points are
   staff-created in the admin panel (BK-01).
7. No bookings table exists yet; the Bookings module is greenfield on top of the
   SPEC-001/002/004/006 foundations.
8. The gates D-05 (option 1), D-08 (Recommended MVP package) and D-07 (option 1)
   are pre-approved (NIGHT MODE, `docs/sdd/state.yaml`), and NC-01 (turno status
   changes with existing bookings) is resolved by the PO (2026-08-15, §14.1).

---

## 4. Functional Requirements

### FR-001 — Create booking (staff on behalf)

An ADMIN or TRAINER can create a booking for an existing client on an existing
turno. Required fields: client, turno. Optional field: notes. A new booking is
created with status `confirmed` (BR-003) and `booked_at` set to the creation
time. Creating a booking does NOT create or modify any Client, Turno, Membership
or Payment record (BR-002; C-07). The system validates: client and turno exist
(ERR-002), turno is `active` (ERR-003), turno time validity and lead time
(ERR-004, ERR-005), access gate (ERR-006), capacity (ERR-007), no duplicate
confirmed booking (ERR-008).

### FR-002 — List and filter bookings

An ADMIN or TRAINER can list bookings and filter them by client (name or DNI),
turno date, and status (confirmed / cancelled), so the daily occupancy and each
client's bookings are visible at a glance.

### FR-003 — View booking detail

An ADMIN or TRAINER can view a booking's full detail: client, turno (date, start
time, end time), status, booked_at and notes.

### FR-004 — Cancel booking

An ADMIN or TRAINER can cancel a `confirmed` booking; it becomes `cancelled`
(terminal in this Specification, BR-004) and its spot reopens (BR-010, D-08).
Cancellation is without penalty (D-08). A `cancelled` booking cannot be
cancelled again (ERR-009).

### FR-005 — Display booking status

Booking lists and detail views always show the booking's status (confirmed /
cancelled), so staff know which reservations are currently valid (BR-003).

### FR-006 — Display remaining capacity on turnos

The turno detail view (SPEC-006 FR-003) shows the number of `confirmed` bookings
vs. the turno's `capacity_limit` (e.g., "3/10"), so staff see how many spots
remain when creating bookings. Display only: the enforcement is BR-008. The exact
placement (turno detail page, booking form hint, or both) is a presentation
choice (assumption BK-14).

### FR-007 — Auto-cancel bookings on turno cancellation/deactivation

When staff cancel (SPEC-006 FR-007) or deactivate (SPEC-006 FR-005) a turno, the
system automatically cancels every `confirmed` booking of that turno: each
becomes `cancelled` and its spot is freed (BR-004, BR-010, NC-01). No client
notification is sent (NC-01). Separately, staff cannot lower a turno's
`capacity_limit` below its current number of `confirmed` bookings: such an edit
(SPEC-006 FR-004) is rejected until the excess bookings are cancelled first
(BR-014, ERR-012, NC-01).

---

## 5. Business Rules

### BR-001 — Booking definition

A Booking is a reservation made by a client for a turno (domain-model §Booking,
adapted to D-07 option 1). It reserves ONE spot in ONE turno for ONE client.
It is a gym-access reservation, not a trainer-led session and not a class
(SPEC-006 BR-001). A booking does not create, modify or delete any Client, Turno,
Membership or Payment record (C-07; BR-002).

### BR-002 — Booking references one client and one turno

A booking belongs to exactly one client and exactly one turno; both references
are mandatory at creation (FR-001). A booking is never created without both. The
client reference is the Client record (D-01 option 2 confirmed): the client does
not need a linked User account to be booked by staff (BK-01).

### BR-003 — Booking status set

A booking has exactly two statuses in this Specification: `confirmed` and
`cancelled` (assumption BK-03). A new booking is created `confirmed` (FR-001). A
third status `completed` is RESERVED as the SPEC-008 (Attendance) tie-in: when
attendance is recorded, a confirmed booking becomes `completed`. Until SPEC-008
exists, no booking ever enters `completed` (BK-13).

### BR-004 — Status transitions

Allowed transitions in this Specification:

- `confirmed → cancelled` (cancel booking, FR-004, no penalty per D-08);
- `confirmed → cancelled` (automatic, NC-01) when the booking's turno is
  cancelled or deactivated by staff (FR-007): the system cancels every
  `confirmed` booking of that turno and frees their spots.

No other transition exists in this Specification. `cancelled` is terminal here
(no un-cancel / reactivation): after cancellation, the client (or anyone) may
book again by creating a NEW booking — the cancelled record itself is never
reactivated (BK-06). The `confirmed → completed` transition is defined by
SPEC-008 and is out of scope here (BK-03, BK-13).

### BR-005 — Access gate: active membership required (D-05 option 1)

A booking can be created only for a client who, **at booking time**, has at least
one membership with:

- status `active` (SPEC-004 BR-004) AND
- `end_date >= today` (defensive against the `memberships:expire` command window:
  no membership is ever reported active after its end date — SPEC-004 BR-007).

There is **no grace period** after expiry (D-05 option 3 not chosen). Memberships
in `pending`, `expired` or `cancelled` state do not qualify (SPEC-004 BR-005,
BR-007, BR-008). Multiple active memberships (D-06 option 2, confirmed): at least
one qualifying membership suffices; there is no "primary membership" selection
(BK-08). The check is evaluated at booking time only; a membership that expires
after the booking is created does not cancel the booking (BK-09).

### BR-006 — Turno must be bookable

A booking can be created only for a turno whose status is `active` (SPEC-006
BR-002). Turnos with status `inactive` or `cancelled` are not bookable (ERR-003).

### BR-007 — Turno time validity and lead time

A booking can be created only for a turno whose date is today or in the future,
within the lead-time window, and whose start time has not passed (BK-04):

- the turno's `date` must be between `today` and `today + 7 days` (inclusive)
  — the MVP default for D-08's "short lead time" (assumption BK-04);
- for a same-day turno, the turno's `start_time` must be in the future (a turno
  that already started cannot be booked);
- there is no minimum advance notice (same-day booking is allowed until the
  turno starts).

Times are the gym's local time, same convention as SPEC-006 BR-011.

### BR-008 — Capacity enforcement

A booking can be created only if the number of `confirmed` bookings for the turno
is strictly less than the turno's `capacity_limit` (D-07 option 1; SPEC-006
FR-009). `cancelled` bookings do NOT count toward capacity (BK-11; D-08
"cancelled spots re-bookable"). The capacity check and the booking insert are
executed **atomically**: when two booking attempts race for the last spot, exactly
one succeeds and the other is rejected as "turno full". Overselling is never
allowed. The enforcement mechanism (e.g., transaction + row lock on the turno, a
database-level guard, or a unique partial index combined with the check) is an
Architect decision; the behavior is this rule (ERR-007, ERR-011).

### BR-009 — One confirmed booking per client per turno

A client can have at most one `confirmed` booking per turno (assumption BK-10). A
second `confirmed` booking for the same client and turno is rejected (ERR-008).
A `cancelled` booking does not block a new booking: after cancelling, the same
client may book the same turno again (the spot reopened — D-08).

### BR-010 — Cancellation frees the spot

Cancelling a `confirmed` booking frees its spot: the spot is re-bookable by any
client (including the same client) and the cancelled booking no longer counts
toward capacity (D-08; BK-11).

### BR-011 — No hard deletion of booking records

Booking records are never hard-deleted; historical booking data is preserved
(AGENTS.md §12; same pattern as SPEC-001 BR-007 / SPEC-002 BR-006 / SPEC-004
BR-014 / SPEC-006 BR-009). Cancellation is used instead; no delete operation is
provided (BK-15).

### BR-012 — Booking management is ADMIN/TRAINER

Only ADMIN and TRAINER can create, list, view and cancel bookings in the MVP
(assumption BK-02). CLIENT has no booking management here; client-facing
self-service (view own bookings, self-book, self-cancel) is deferred to SPEC-013
(BK-01, BK-07). Client isolation (C-13) always applies: a CLIENT must never access
another client's booking data.

### BR-013 — No waitlist

There is no waitlist: when a turno is full, the booking is rejected and there is
no queue or waiting list mechanism (D-08 pre-approved). A client who wants a spot
in a full turno must wait until a spot frees (a cancellation) and then book
normally.

### BR-014 — Turno status changes vs. existing bookings (NC-01, resolved)

When staff cancel (SPEC-006 FR-007) or deactivate (SPEC-006 FR-005) a turno, the
system automatically cancels every `confirmed` booking of that turno; those
bookings become `cancelled` and their spots are freed (BR-004, BR-010, FR-007).
Lowering a turno's `capacity_limit` (SPEC-006 FR-004) to a value below its
number of `confirmed` bookings is NOT allowed: the edit is rejected until the
excess bookings are cancelled first — either by cancelling/deactivating the
turno or by cancelling bookings individually (ERR-012). No client notification
is sent for any of these effects (no notification infrastructure exists in the
MVP).

---

## 6. Main Flow

1. An authenticated ADMIN or TRAINER opens the Bookings section of the admin
   panel (FR-002).
2. Staff create a booking: selects an existing client (SPEC-002), an existing
   turno, and optionally notes, and saves (FR-001).
3. The system validates: required fields present (ERR-001), client and turno
   exist (ERR-002), turno status `active` (ERR-003), turno time validity and
   lead time (ERR-004, ERR-005), access gate — client has an active membership
   (ERR-006), capacity — confirmed bookings < capacity_limit (ERR-007), no
   duplicate confirmed booking (ERR-008).
4. The booking is persisted with status `confirmed` and `booked_at` = now
   (FR-001, BR-003) and appears in the booking list with its status shown
   (FR-002, FR-005).
5. Staff can open the booking detail view (FR-003) and, if needed, cancel the
   booking (FR-004); the booking becomes `cancelled` (terminal) and its spot
   reopens (BR-010).
6. Turno details show the occupied/capacity count so staff can see remaining
   spots (FR-006).
7. When staff cancel or deactivate a turno (SPEC-006 FR-007 / FR-005), the
   system automatically cancels all its `confirmed` bookings and frees their
   spots (FR-007, BR-004, BR-014); staff cannot lower the turno's
   `capacity_limit` below the number of `confirmed` bookings — that edit is
   rejected (BR-014, ERR-012).

---

## 7. Alternative Flows

### AF-001 — Client without a linked User account

A client without a provisioned User account (SPEC-002 BR-001, D-01 option 2
confirmed) can still be booked by staff: the booking references the Client
record, not the User (BR-002, BK-01). The client simply cannot self-serve yet
(SPEC-013).

### AF-002 — A client books several different turnos

A client may hold several `confirmed` bookings across different turnos (no
per-client/per-day/per-week limit in the MVP — BK-05), each subject to the
access gate, capacity and the one-booking-per-turno rule (BR-008, BR-009).

### AF-003 — A client re-books a turno after cancelling

A client cancels a booking; the spot reopens (BR-010). The client (or another
client) creates a NEW booking for the same turno; the new booking is a fresh
`confirmed` record. The cancelled record is never reactivated (BR-004, BK-06).

### AF-004 — Cancellation frees capacity for another client

A turno is full. A client cancels their booking; the spot reopens. A new booking
by a different client now succeeds (BR-008, BR-010, D-08 "cancelled spots
re-bookable").

### AF-005 — Membership expires between booking and turno date

A client books a turno while holding an active membership; the membership
expires before the turno's date. The booking remains `confirmed`: the access gate
is evaluated at booking time only (BR-005, BK-09). Whether the client is admitted
at the door is the attendance gate of SPEC-008 (D-05 applies there too); this
Specification does not auto-cancel the booking.

### AF-006 — Client self-service (future)

When SPEC-013 (Client Portal) is implemented, a CLIENT will view, book and
cancel their own bookings; until then there is no client-facing booking path
(BK-01, BK-07). Out of scope here (§12).

### AF-007 — Turno cancelled or deactivated with confirmed bookings

A turno has one or more `confirmed` bookings. Staff cancel (SPEC-006 FR-007) or
deactivate (SPEC-006 FR-005) the turno. The system automatically cancels every
`confirmed` booking of that turno: each becomes `cancelled` and its spot is
freed (FR-007, BR-004, BR-014, NC-01). No client notification is sent (NC-01).
Reactivating an `inactive` turno (SPEC-006 FR-006) does NOT restore the
auto-cancelled bookings — those clients (or others) must create new bookings for
the reactivated turno (BR-004, BK-06).

---

## 8. Error Cases

### ERR-001 — Missing required fields

Condition: creating a booking without a client or a turno.

Expected behavior: rejected with a validation error (FR-001, BR-002).

### ERR-002 — Nonexistent client or turno

Condition: the selected client or turno does not exist (e.g., stale reference).

Expected behavior: rejected with a validation error; the booking references an
existing client and an existing turno (BR-002).

### ERR-003 — Turno not bookable

Condition: creating a booking for a turno whose status is `inactive` or
`cancelled` (SPEC-006 BR-002/BR-003/BR-004).

Expected behavior: rejected with a validation error (BR-006).

### ERR-004 — Turno time invalid

Condition: creating a booking for a turno whose date is in the past, or whose
date is today and start time has already passed.

Expected behavior: rejected with a validation error (BR-007, BK-04).

### ERR-005 — Booking beyond lead-time window

Condition: creating a booking for a turno whose date is more than 7 days in the
future (the MVP lead-time default, BK-04).

Expected behavior: rejected with a validation error (BR-007).

### ERR-006 — No active membership (access gate)

Condition: creating a booking for a client with no membership, or whose
memberships are all `pending`, `expired` or `cancelled`, or whose only active
membership has an end date before today.

Expected behavior: rejected with a validation error; an active membership is
required at booking time, with no grace period (BR-005, D-05 option 1).

### ERR-007 — Turno full

Condition: creating a booking for a turno whose number of `confirmed` bookings is
equal to its `capacity_limit`.

Expected behavior: rejected with a "turno full" error; no waitlist exists
(BR-008, BR-013, D-08).

### ERR-008 — Duplicate confirmed booking

Condition: creating a second `confirmed` booking for the same client and turno.

Expected behavior: rejected with a validation error (BR-009, BK-10).

### ERR-009 — Cancelling a non-confirmed booking

Condition: cancelling a booking whose status is already `cancelled`.

Expected behavior: rejected; only `confirmed` bookings can be cancelled (BR-004).

### ERR-010 — Unauthorized access

Condition: an anonymous visitor or a CLIENT attempts to create, list, view or
cancel bookings.

Expected behavior: access denied (redirect for anonymous; 403 for CLIENT)
(BR-012, BK-02). A CLIENT cannot access another client's booking data (C-13).

### ERR-011 — Race condition on the last spot

Condition: two booking attempts for the same turno are submitted concurrently
when only one spot remains.

Expected behavior: exactly one booking is created; the other is rejected as
"turno full". The system never oversells the turno (BR-008).

### ERR-012 — Lowering turno capacity below confirmed bookings

Condition: staff edit a turno's `capacity_limit` (SPEC-006 FR-004) to a value
below the turno's current number of `confirmed` bookings.

Expected behavior: the edit is rejected; the `capacity_limit` cannot be lowered
below the confirmed count (BR-014, NC-01). Staff must first cancel the excess
bookings — by cancelling/deactivating the turno or by cancelling bookings
individually (FR-007).

---

## 9. Authorization

| Operation | Anonymous | ADMIN | TRAINER | CLIENT |
| --- | --- | --- | --- | --- |
| Create booking (on behalf) | Denied | Allowed (BR-012, BK-02) | Allowed (BR-012, BK-02) | Denied (self-booking is SPEC-013, BK-01) |
| List / filter bookings | Denied | Allowed (BR-012) | Allowed (BR-012) | Denied (own-booking view is SPEC-013) |
| View booking detail | Denied | Allowed (BR-012) | Allowed (BR-012) | Denied (own-booking view is SPEC-013) |
| Cancel booking | Denied | Allowed (BR-012, BK-07) | Allowed (BR-012, BK-07) | Denied (self-cancel is SPEC-013) |
| Client-facing self-booking / self-cancel / own-booking view | Out of scope (SPEC-013) | — | — | Out of scope at this stage (BK-01) |
| Access another client's booking data | Denied | Allowed (staff duty) | Allowed (staff duty) | Denied always (BR-012, C-13) |

Notes:

- A User holding several roles receives the union of the column permissions
  (SPEC-001 BR-002); an ADMIN who is also CLIENT can manage bookings in the
  admin panel.
- Authorization is enforced server-side; frontend-only restrictions are never
  sufficient (AGENTS.md §17).
- The state rules (BR-003, BR-004 — e.g., a `cancelled` booking cannot be
  cancelled again) are NOT authorization rules: they are enforced by the model
  lifecycle / validation, the same way SPEC-006 enforces its state rules
  (`Turno::cancel()`, SPEC-006 §5).
- Whether TRAINER receives the FULL booking management set is assumed as full
  (BK-02), consistent with SPEC-006 AS-01 and SPEC-005 PY-01; PO confirmation
  requested.

---

## 10. Data Changes

This Specification describes the information that must exist; the exact
persistence schema, table name and model name are defined by the Architect (e.g.,
a `bookings` table with a `Booking` model, following the repo's English naming
convention for models, the same way `turnos`/`Turno` was chosen in SPEC-006 §10).

Created:

- Booking records with the following information:
  - `client_id` — foreign key to `clients.id`, required (BR-002);
  - `turno_id` — foreign key to `turnos.id`, required (BR-002);
  - `status` — one of `confirmed` / `cancelled`, default `confirmed` (BR-003).
    Storage representation (string column vs. DB enum) is an Architect decision;
    the business rule is the two-state machine plus the SPEC-008 `completed`
    tie-in (BK-03). A string column with model constants matches the
    `Turno`/`Membership` precedent (ADR-004);
  - `booked_at` — timestamp, required, set to the creation time (FR-001). The
    Architect may implement it as an explicit column or reuse `created_at` if
    that is simpler and equivalent; the business meaning is "when the
    reservation was made";
  - `notes` — optional free text (FR-001; no business rules on content;
    technical max length is an Architect/Developer detail, e.g., 500);
  - `created_at` / `updated_at` timestamps.
- Uniqueness invariant (BR-009): at most one `confirmed` booking per
  (`client_id`, `turno_id`). The exact mechanism (partial unique index
  `WHERE status = 'confirmed'`, or a check + constraint) is an Architect
  decision; the business rule is BR-009.
- Index on `turno_id` to support capacity counting (BR-008) and booking lists by
  turno; index on `client_id` to support per-client lists (FR-002). The exact
  index set is an Architect decision.
- Optional audit field `booked_by` (nullable foreign key to `users.id`) recording
  the staff User who created the booking may be added by the Architect, following
  the SPEC-005 `recorded_by` precedent (PY-06); it is null when a booking is
  later created by the client's self-service (SPEC-013). Flagged as assumption
  BK-12; the business requires `booked_at`, not `booked_by`.

Modified:

- Booking `status` on cancel: `confirmed → cancelled` (FR-004, BR-004).
- Booking `status` on turno cancellation/deactivation (FR-007, BR-004, BR-014,
  NC-01): every `confirmed` booking of the turno transitions to `cancelled` and
  its spot is freed. No other field is modified by any operation in this
  Specification.

Deleted:

- No hard deletion of booking records in the MVP (BR-011); no delete operation.
  Cancellation is used instead.

No seeder is required: bookings are created by staff in the admin panel only.

No change to the `turnos` table is made by this Specification: capacity is
enforced against existing bookings at booking time (BR-008), and the turno's
`capacity_limit` value is read as-is (SPEC-006 FR-009). This Specification adds a
constraint on the turno-edit operation (the `capacity_limit` cannot be lowered
below the number of `confirmed` bookings, BR-014, ERR-012) but does not alter
the `turnos` schema.

---

## 11. Acceptance Criteria

- [ ] AC-1: ADMIN or TRAINER can create a booking for an existing client on an
  existing `active`, time-valid turno (within the lead-time window); the record
  is persisted with status `confirmed`, `booked_at` set, and appears in the
  booking list (FR-001, FR-002, BR-003, BR-007).
- [ ] AC-2: Creating a booking with a missing, or nonexistent, client or turno is
  rejected with a validation error (ERR-001, ERR-002, BR-002).
- [ ] AC-3: Creating a booking for an `inactive` or `cancelled` turno is rejected
  (ERR-003, BR-006).
- [ ] AC-4: Creating a booking for a turno whose date is in the past, or whose
  date is today and start time has passed, is rejected (ERR-004, BR-007).
- [ ] AC-5: Creating a booking for a turno more than 7 days in the future (the
  MVP lead-time default) is rejected (ERR-005, BK-04).
- [ ] AC-6: Creating a booking for a client with no active membership (none,
  `pending`, `expired` or `cancelled` only, or an active membership whose end
  date has passed) is rejected (ERR-006, BR-005, D-05 option 1); a client with at
  least one qualifying active membership — including a client holding several
  concurrent active memberships (D-06 option 2) — can book.
- [ ] AC-7: Creating a booking for a full turno (confirmed bookings =
  `capacity_limit`) is rejected with a "turno full" error; after a confirmed
  booking on that turno is cancelled, a new booking succeeds (ERR-007, BR-008,
  BR-010, AF-004).
- [ ] AC-8: Creating a second `confirmed` booking for the same client and turno
  is rejected (ERR-008, BR-009); after cancelling, the same client can book the
  same turno again (BR-004, AF-003).
- [ ] AC-9: Race condition: two concurrent booking attempts for the last spot of
  a turno result in exactly one `confirmed` booking; the other is rejected as
  "turno full" (ERR-011, BR-008). No overselling.
- [ ] AC-10: ADMIN or TRAINER can cancel a `confirmed` booking; it becomes
  `cancelled`, is displayed as such, and is terminal (FR-004, FR-005, BR-003,
  BR-004).
- [ ] AC-11: Cancelling a `cancelled` booking is rejected (ERR-009, BR-004).
- [ ] AC-12: A CLIENT or anonymous visitor cannot create, list, view or cancel
  bookings (403 or redirect) (ERR-010, BR-012); a CLIENT cannot access another
  client's bookings (C-13).
- [ ] AC-13: No delete operation exists for bookings; a created booking record
  persists (BR-011).
- [ ] AC-14: Creating or cancelling a booking never creates, modifies or deletes
  a Client, Turno, Membership or Payment record (BR-002, BR-001, C-07).
- [ ] AC-15: The turno detail view shows the number of confirmed bookings vs.
  `capacity_limit` (FR-006, BK-14).
- [ ] AC-16: Cancelling or deactivating a turno that has `confirmed` bookings
  automatically cancels every such booking and frees their spots; no client
  notification is sent (FR-007, BR-004, BR-014, AF-007, NC-01).
- [ ] AC-17: Lowering a turno's `capacity_limit` below its number of `confirmed`
  bookings is rejected until the excess bookings are cancelled first (ERR-012,
  BR-014, NC-01).

**Test plan (to be executed at Implementation; aligned with the existing Pest
layout under `tests/`, `tests/Pest.php` helpers `role()` / `userWithRoles()`,
`RefreshDatabase`, Livewire component tests as used in `TurnoManagementTest`):**

- `tests/Feature/Admin/BookingManagementTest.php` (Livewire): create a booking
  with status `confirmed` and `booked_at` (AC-1); validation errors — missing
  fields, nonexistent client/turno, inactive/cancelled turno, past turno,
  started turno, beyond lead time (AC-2..AC-5); cancel flow and terminality
  (AC-10, AC-11); no delete operation (AC-13); creating/cancelling never touches
  client/turno/membership/payment records (AC-14); capacity display (AC-15).
- `tests/Feature/Admin/BookingPolicyTest.php`: ADMIN and TRAINER can
  viewAny/view/create/update(cancel); CLIENT and anonymous denied (403); a
  multi-role ADMIN + CLIENT or TRAINER + CLIENT user can manage bookings; no
  delete ability (AC-12, BR-012).
- `tests/Feature/Booking/AccessGateTest.php`: the D-05 option 1 gate — reject no
  membership, pending-only, expired-only, cancelled-only, active-but-end-date-
  passed; allow with one active membership and with several concurrent active
  memberships (D-06 option 2); no grace period (AC-6).
- `tests/Feature/Booking/CapacityTest.php`: reject when full (AC-7); cancel
  frees the spot (AC-7, BR-010); the race condition — two concurrent bookings
  for the last spot produce exactly one success (AC-9, ERR-011); cancelled
  bookings do not count toward capacity (BK-11).
- `tests/Feature/Booking/TurnoStatusInterplayTest.php`: cancelling or
  deactivating a turno with `confirmed` bookings auto-cancels them and frees
  their spots (AC-16, FR-007, BR-014, AF-007); lowering `capacity_limit` below
  the confirmed count is rejected (AC-17, ERR-012).
- `tests/Unit/BookingTest.php`: constants (`confirmed`/`cancelled`), default
  status `confirmed`, relationships (client, turno), the one-confirmed-booking-
  per-client-per-turno invariant (BR-009), the duplicate-rejection rule
  (ERR-008), the capacity-counting rule over confirmed bookings only (BR-008).

---

## 12. Out of Scope

The following are explicitly NOT part of this Specification:

- **Client-facing self-service**: a CLIENT viewing their own bookings,
  self-booking, and self-cancellation — SPEC-013 (Client Portal), not implemented
  yet (BK-01, BK-07, AF-006).
- **Attendance and the `completed` status**: when a booking becomes `completed`
  (attendance recorded), no-show handling, and the admission gate at the door —
  SPEC-008 (Attendance; D-05 applies there too). Until SPEC-008, a confirmed
  booking whose turno has passed remains `confirmed` (BK-13); the exact
  completion transition is out of scope.
- **Waitlist and any queuing mechanism** — D-08 pre-approved: none (BR-013).
- **Booking penalties, fees, deposits, no-show charges, or recredits** — D-08
  pre-approved: none.
- **Maximum bookings per client (per day / per week)** — the D-08 minimal
  package includes no such limit; none is imposed (BK-05).
- **Notifications** to clients when a booking is created, cancelled, or when a
  turno is cancelled/deactivated — no notification infrastructure exists in the
  MVP (NC-01; consistent with SPEC-006 §12).
- **Paid bookings / online booking** on the public site — SPEC-012 / SPEC-013;
  a booking is a reservation only, with no payment attached.
- **Turno management** (create/edit/deactivate/reactivate/cancel) — SPEC-006,
  implemented; this Specification only consumes turnos and enforces capacity.
- **Class / group-session management** and the `Schedule → Session → Booking`
  hierarchy — C-16 future scope; SPEC-006 AS-02.
- **Recurring / weekly schedule templates, operating hours validation,
  multi-location, timezone support** — SPEC-006 §12, unchanged.
- **Hard deletion of bookings** — no delete operation (BR-011).

---

## 13. Dependencies

- **SPEC-001 (Authentication & Roles) — COMPLETED** (`docs/sdd/state.yaml`):
  authentication, fixed role catalog with `Role::ADMIN` / `Role::TRAINER`;
  admin panel access (ADMIN | TRAINER); `User::hasRole` / `User::hasAnyRole`;
  policy pattern; client isolation foundation (C-13). Spec deferred
  authorization per module to later Specifications; this Specification defines
  the booking permissions (BK-02).
- **SPEC-002 (Client Management) — COMPLETED**: the client record is the
  required subject of a booking (`client_id`, BR-002); the linked User account
  is optional (D-01 option 2 confirmed) — staff can book clients without
  accounts (AF-001, BK-01).
- **SPEC-004 (Membership Management) — COMPLETED**: the membership state machine
  (`pending`/`active`/`expired`/`cancelled`) and the `end_date` period are
  consumed by the access gate (BR-005, D-05 option 1); multiple active
  memberships are allowed (D-06 option 2 confirmed, SPEC-004 BR-010); `expired`
  is materialized by the daily command `memberships:expire` (ADR-004) and the
  gate defensively checks `end_date >= today`. SPEC-005 is NOT required for this
  Specification: the gate reads membership state as-is, whether memberships were
  activated by SPEC-005 or remain `pending`.
- **SPEC-006 (Scheduling & Turnos) — COMPLETED**: the turno is the bookable
  entity (`turnos` table: `date`, `start_time`, `end_time`, `capacity_limit`,
  `status` active/inactive/cancelled); `capacity_limit` is stored but NOT
  enforced there — capacity enforcement is this Specification's concern
  (FR-009, BR-008, C-16). SPEC-006 BR-013 / OQ-04 explicitly defer the booking
  consequences of turno status changes to this Specification; this deferral is
  resolved by the PO decision NC-01 (§14.1).
- **SPEC-008 (Attendance) — FUTURE**: consumes bookings; defines the
  `confirmed → completed` transition and the admission gate at the door (BK-03,
  BK-13). Not required for this Specification's implementation.
- **SPEC-013 (Client Portal) — FUTURE**: client-facing self-booking,
  self-cancellation and own-booking view (BK-01, BK-07, AF-006). Not required
  for this Specification's implementation.
- Gate decisions: **D-05 option 1** (active membership required for bookings, no
  grace period), **D-08** (Recommended minimal booking rules package), **D-07
  option 1** (turno = capacity-limited access slot) — pre-approved (NIGHT MODE,
  `docs/sdd/state.yaml`).
- Confirmed decisions used: C-01 (roles, multi-role), C-02 (client aggregates
  bookings), C-07 (Plan/Membership/Payment separate — a booking touches none of
  them), C-13 (client isolation), C-15 (presentation contexts), C-16 (free-weight
  gym; capacity enforcement not precluded).
- Requirements analysis: `analyst-pass-001.md` §5.9 (Bookings), D-05, D-08, D-07,
  C-18, T-01, E-01/E-06, R-03.
- Flagged assumptions BK-01..BK-15 (§14.2) require Product Owner confirmation
  before Implementation (or at latest before Review); NC-01 (§14.1) is resolved
  by the PO (2026-08-15).

---

## 14. Open Questions

### 14.1 Resolved decisions (PO)

**NC-01 — Turno status changes with existing bookings (RESOLVED 2026-08-15).**

This item was deferred to this Specification by SPEC-006 (BR-013, OQ-04) and was
not covered by `analyst-pass-001.md` (C-18, §5.9 "not defined") nor by the
pre-approved NIGHT MODE decisions. The Product Owner resolved it (recorded in
`docs/sdd/state.yaml` `SPEC-007.po_decisions`):

- When staff CANCEL (SPEC-006 FR-007) or DEACTIVATE (SPEC-006 FR-005) a turno
  that has `confirmed` bookings, those `confirmed` bookings are AUTOMATICALLY
  cancelled and their spots are freed (BR-004, BR-014, FR-007).
- Lowering a turno's `capacity_limit` (SPEC-006 FR-004) below the number of
  `confirmed` bookings is NOT allowed: the operation is rejected until the
  bookings are cancelled first — either by cancelling/deactivating the turno or
  by cancelling bookings individually (BR-014, ERR-012).
- No client notification is sent (no notification infrastructure exists in the
  MVP).

There are no remaining blocking items for this Specification.

### 14.2 Assumptions requiring PO confirmation

These flagged assumptions are concrete values for the pre-approved D-08 package,
authorization consequences of SPEC-013 not being implemented, or natural
invariants of the Booking concept. They are NOT confirmed business rules unless
stated otherwise; prefix BK distinguishes this Specification's assumptions from
SPEC-001 (A-xx), SPEC-002 (AD-xx), SPEC-003 (AP-xx), SPEC-004 (AM-xx), SPEC-005
(PY-xx) and SPEC-006 (AS-xx).

| ID | Assumption | Source | Blocks / Affects |
| --- | --- | --- | --- |
| BK-01 | The booking UI entry point in the MVP is the admin panel: ADMIN/TRAINER create bookings on behalf of clients. Client-facing self-booking, self-cancellation and own-booking view are deferred to SPEC-013 (Client Portal), which is not implemented yet. Bookings reference Client records, so clients without a linked User can be booked by staff. | Task directive; SPEC-013 pending; D-01 option 2 confirmed (Client standalone) | §2, FR-001, BR-002, BR-012, ERR-010, AF-001, AF-006 |
| BK-02 | "Staff" for booking management (create / list / view / cancel) = ADMIN and TRAINER, consistent with SPEC-006 AS-01 (TRAINER full scheduling management) and SPEC-005 PY-01. | Derived from task directive ("ADMIN/TRAINER on behalf"); SPEC-001 A-04 (no RECEPTIONIST) | §2, FR-001..FR-004, BR-012, §9, ERR-010 |
| BK-03 | Booking status set in the MVP: `confirmed` (default on create) and `cancelled` (terminal). A `completed` status is RESERVED as the SPEC-008 (Attendance) tie-in and is out of scope here; no booking enters `completed` until SPEC-008 exists. | Task directive ("define minimally per D-08: confirmed/cancelled now, completed later tie-in") | BR-003, BR-004, §10, AC-10 |
| BK-04 | Lead time (concrete value for D-08 "short lead time"): a booking can be made for a turno whose date is between `today` and `today + 7 days` (inclusive); same-day booking is allowed until the turno's start time; no minimum advance notice. | Task directive ("pick sensible MVP defaults" for "short lead time") | BR-007, ERR-004, ERR-005, AC-4, AC-5 |
| BK-05 | No maximum bookings per client in the MVP: the D-08 minimal package includes no per-client/per-day/per-week limit, so none is imposed; a client may hold several confirmed bookings across different turnos, subject to BR-008/BR-009. | D-08 Recommended package (no limit listed); task directive "do NOT invent complex rules" | BR-009, AF-002, §12 |
| BK-06 | Cancellation boundary: a `confirmed` booking can be cancelled at any time before the turno's start time, without penalty (D-08). Cancelling a booking whose turno has already started is not available in this Specification (that is no-show/attendance handling, SPEC-008). A `cancelled` booking is terminal: no un-cancel; a new booking must be created. | D-08 package ("cancellation without penalty"); boundary = analyst default; C-18 open question deferred | BR-004, FR-004, ERR-009, AF-003 |
| BK-07 | Who may cancel: ADMIN/TRAINER in the MVP admin panel; the owning CLIENT's self-cancellation is deferred to SPEC-013. | Task directive ("who cancels"); SPEC-013 pending | §9, FR-004, ERR-010 |
| BK-08 | Access gate evaluation (D-05 option 1): at booking time the client must have at least one membership with status `active` AND `end_date >= today`; with multiple active memberships (D-06 option 2) at least one qualifying membership suffices; no "primary membership" concept. | D-05 option 1 pre-approved; D-06 option 2 confirmed; SPEC-004 BR-007 | BR-005, ERR-006, AC-6 |
| BK-09 | A membership that expires between the booking and the turno date does NOT cancel the booking: the access gate is evaluated at booking time only; admission at the door is SPEC-008 (D-05 applies there). | Task directive ("membership expiring between booking and turno date — document as assumption"); edge case E-01 | BR-005, AF-005, §12 |
| BK-10 | One `confirmed` booking per client per turno: a client cannot book the same turno twice; a duplicate confirmed booking is rejected. Preserves the "one reservation = one spot" semantics of D-07 and fair capacity use. | Task directive (unique constraint question); natural invariant | BR-009, ERR-008, AC-8 |
| BK-11 | Capacity counting: only `confirmed` bookings count toward `capacity_limit`; `cancelled` bookings do not (spots reopen per D-08). | D-08 package ("cancelled spots re-bookable") | BR-008, BR-010, AC-7 |
| BK-12 | Audit field `booked_by` (nullable FK to users) is OPTIONAL: the Architect may add it following the SPEC-005 `recorded_by` precedent (PY-06) for auditability; the business requires `booked_at`, not `booked_by`. | Task directive field list; SPEC-005 PY-06 precedent | §10, tests |
| BK-13 | No-show consequence in this Specification: a `confirmed` booking whose turno has passed without cancellation or attendance remains `confirmed` (no automatic transition) and no penalty applies (D-08); the `completed`/no-show transition is defined by SPEC-008. | D-08 (no penalties); C-18 open question deferred to SPEC-008 | BR-003, BR-004, §12 |
| BK-14 | The turno detail view shows the occupied/capacity count (confirmed bookings vs. `capacity_limit`) so staff see remaining spots; exact placement is a presentation choice. | Analyst necessity (operational visibility); display only | FR-006, AC-15 |
| BK-15 | No hard deletion of booking records; cancellation is used instead (preservation pattern). | AGENTS.md §12; SPEC-001 BR-007 / SPEC-004 BR-014 / SPEC-006 BR-009 | BR-011, AC-13 |

### 14.3 Other open questions (non-blocking for this analysis)

- OQ-01 (NC-01 sub-question): if the PO decides that cancelled-turno bookings are
  auto-cancelled, should the system record a reason or keep the historical
  `confirmed` state visible? (Deferred; tied to NC-01.)
- OQ-02: Should the `booked_by` audit field be included (BK-12), and should the
  booking record who cancelled it (`cancelled_by`)? (Audit/presentation choice;
  no business rule implied.)
- OQ-03: Is the 7-day lead time (BK-04) the right MVP default, and should staff
  be able to override it (e.g., booking further ahead on request)? (Operational
  choice; the rule as written applies to all bookings.)
- OQ-04: Is a per-client booking limit needed before SPEC-013 goes live (e.g.,
  when self-booking arrives)? This Specification assumes none (BK-05).
- OQ-05: Should the admin UI offer a client-side "bookings" relation manager
  (like `MembershipsRelationManager`) in addition to the Bookings resource?
  (Presentation choice; no business rule implied.)
- OQ-06: Are bookings created before SPEC-008 (while the `completed` tie-in does
  not exist) exposed to staff with any interim indication that completion is
  pending implementation? (Operational note; no business rule implied —
  analogous to SPEC-004 OQ-10.)

---

## 15. Related Documents

- Product documentation: `docs/product/product-definition-v0.1.md` (Open
  Questions: "What happens when a booking is missed or cancelled?" — resolved by
  D-08 / SPEC-008)
- Domain documentation: `docs/domain/domain-model-v0.1.md` (§Booking; C-02)
- Requirements analysis: `docs/requirements/analyst-pass-001.md` (§5.9 Bookings,
  D-05, D-08, D-07, C-18, T-01, E-01/E-06, R-03)
- Specifications: `docs/specs/SPEC-001.md`, `docs/specs/SPEC-002.md`,
  `docs/specs/SPEC-003.md`, `docs/specs/SPEC-004.md` (BR-004/BR-007/BR-008/
  BR-010/BR-016, AM-10, OQ-01), `docs/specs/SPEC-006.md` (BR-002/BR-013,
  FR-009, OQ-04, AS-01/AS-02/AS-04/AS-06/AS-07/AS-08/AS-09)
- Architecture documentation: `docs/architecture/SPEC-001.md`,
  `docs/architecture/SPEC-002.md`, `docs/architecture/SPEC-004.md`,
  `docs/architecture/SPEC-006.md` (§5 Models — bookable-friendly turno shape;
  §6 Data Changes — `bookings.turno_id` reference direction), `ARCHITECTURE.md`
  (§12 Authorization, §15 Scheduling, §20 simplest correct architecture)
- Architecture decisions: `docs/adr/ADR-001.md` (role/authorization
  foundation), `docs/adr/ADR-002.md` (module boundary discipline),
  `docs/adr/ADR-003.md` (validation-first representation),
  `docs/adr/ADR-004.md` (status-as-string / expiry command)
- Workflow state: `docs/sdd/state.yaml` (NIGHT MODE pre-approvals D-05/D-08 for
  SPEC-007; SPEC-006 completed; SPEC-005 blocked)
- Development rules: `AGENTS.md`
