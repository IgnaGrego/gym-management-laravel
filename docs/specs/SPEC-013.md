# SPEC-013 — Client Portal

## Status

Ready (analysis complete).

This is the thirteenth Specification of the MVP. It depends on SPEC-001
(Authentication & Roles), SPEC-002 (Client Management), SPEC-003 (Plan
Management), SPEC-004 (Membership Management), SPEC-005 (Payments & Cuotas),
SPEC-006 (Scheduling & Turnos), SPEC-007 (Bookings), SPEC-008 (Attendance),
SPEC-010 (Routines), SPEC-011 (Workout Logs) and SPEC-015 (Presentation
Foundation & UX) — all COMPLETED and implemented in the repository
(`docs/sdd/state.yaml`).

**NIGHT MODE notice:** this Specification is produced under the PO pre-approval
("modo nocturno", `docs/sdd/state.yaml` `project.po_decisions`). The gate is
pre-approved as follows:

- **D-18 — option 3** (client portal scope, FULL): the portal includes —
  view own memberships, own payments/cuotas, own attendance; book turnos;
  cancel own bookings; view own assigned routine; log workouts; edit profile
  (`analyst-pass-001.md` §D-18 "Full: view, book, view routine, log workouts,
  edit profile" + the Recommended option 3).

This Specification EXTENDS the existing `/portal` base built by SPEC-015 (shared
Blade layout "El Area Gym", CLIENT profile read-only, ERR-005 notice, logout). It
does not re-specify that presentation foundation; it adds the portal's business
content and the interactive self-service actions defined by D-18 option 3. All
enabling business rules are already implemented by the predecessor Specifications
and are consumed here unchanged.

**Assumption notice:** this specification contains explicitly flagged assumptions
(CP-01 to CP-09, see §14.1) that are technical defaults consistent with the
implemented Specifications. The two business decisions that were previously
**NOT COVERED** — the profile fields a CLIENT may edit (NC-01) and CLIENT
visibility of their own health notes (NC-02) — are now **RESOLVED** by Product
Owner decision and recorded in §14.2. The edit-profile action is scoped to the
final editable set (`email` / `phone` / `emergency_contact`, CP-01), and the
CLIENT's own health notes are visible read-only (NC-02).

---

## 1. Objective

Provide the CLIENT self-service portal ("El Area Gym") as the third presentation
context of the application (C-15): the place where an authenticated CLIENT sees
their own gym data and performs the self-service actions in scope.

The portal must let a CLIENT:

- **view** (read-only, own data only): memberships, payments/cuotas, attendance,
  own bookings, own assigned routine, own workout log history, and own health
  notes (`injuries_notes` / `medical_conditions_notes`);
- **book turnos** (self-service): list upcoming bookable turnos and reserve a
  spot for themselves, subject to the active-membership gate (D-05 option 1) and
  the SPEC-007 booking rules;
- **cancel own bookings** (self-service, no penalty);
- **log workouts** (self-service): record what they actually performed, against
  their assigned routine or as a free catalogue log (SPEC-011 model);
- **edit profile** (self-service, final PO scope): update their own contact
  fields (`email`, `phone`, `emergency_contact`); `full_name` and `dni` remain
  staff-managed (identity/uniqueness) and are not client-editable.

The portal is read-only for everything except the four interactive actions above
(book, cancel, log workout, edit profile), per D-18 option 3. Client isolation
(C-13) is absolute and enforced server-side: a CLIENT must never access another
client's private information, and hiding UI elements is never sufficient
(AGENTS.md §17).

This Specification consumes the business rules of SPEC-002 (client fields, DNI
uniqueness, health-data confidentiality), SPEC-004 (membership states),
SPEC-005 (payments & cuotas), SPEC-006/007 (turnos + bookings, active-membership
gate, capacity, no-penalty cancellation), SPEC-008 (attendance records) and
SPEC-010/011 (routine versioning + workout logging). It introduces **no new
business rules** beyond the flagged assumptions (§14.1) and the two Product Owner
decisions recorded in §14.2 (profile-edit scope NC-01; read-only health-note
visibility NC-02); it otherwise only wires the existing rules into a
client-facing, ownership-scoped surface.

---

## 2. Actors

Actors are defined by role, not by person (SPEC-001 §2). A single User may hold
one or more roles (confirmed decision C-01).

| Actor | Use in this specification |
| --- | --- |
| Anonymous visitor | No access to the portal. Portal data is never exposed on public pages. |
| ADMIN | Staff who administer the gym. Does **not** use the portal through this role; ADMIN-panel behavior is unchanged. An ADMIN who ALSO holds CLIENT may access `/portal` and see their own client data (union of roles, SPEC-001 BR-002). |
| TRAINER | Staff who train clients. Does **not** use the portal through this role; admin-panel behavior is unchanged. A TRAINER who ALSO holds CLIENT may access `/portal` and see their own client data (SPEC-001 BR-002). |
| CLIENT | A gym member. The primary actor: views their own data and performs self-service book/cancel/log/edit-profile, always scoped to their own Client record (C-13). |

Notes:

- A User holding ADMIN and/or TRAINER uses the admin panel; a User holding CLIENT
  uses the client portal (SPEC-001 FR-006, confirmed C-15).
- The mixed-role behavior (a staff member who also trains) is tracked as
  SPEC-001 OQ-04. This Specification does not decide it beyond the union-of-roles
  consequence: such a user passes the `role:CLIENT` gate and sees their own
  client data in the portal, exactly like any other CLIENT.
- There is no RECEPTIONIST role (confirmed SPEC-001 A-04 / D-19 option 1).

---

## 3. Preconditions

1. SPEC-001 is implemented and completed: authentication, fixed role catalog
   (ADMIN, TRAINER, CLIENT), `role:CLIENT` middleware on `/portal`
   (`EnsureUserHasRole`), post-login redirect of CLIENT to `/portal`
   (`docs/sdd/state.yaml`, ADR-001).
2. SPEC-002 is implemented and completed: client records exist (`full_name`,
   `dni`, `email`, `phone`, `emergency_contact`, `injuries_notes`,
   `medical_conditions_notes`, `status`), DNI unique (BR-005), the optional 1:1
   `clients.user_id` link (D-01 option 2), `User::client()` and
   `Client::user()` relationships, health-data confidentiality (BR-007)
   (`docs/sdd/state.yaml`, ADR-002).
3. SPEC-004 is implemented and completed: membership records with the four-state
   machine (`pending`/`active`/`expired`/`cancelled`), period (`start_date`/
   `end_date`/`duration_days`), `scopeQualifying()` (D-05 option 1 predicate) and
   `Client::hasQualifyingMembership()` / `Client::accessDenialReason()`
   (`docs/sdd/state.yaml`, ADR-004).
4. SPEC-005 is implemented and completed: cuota records (one per membership,
   `pending`/`paid`/`cancelled`) and payment records (`confirmed` only,
   `cash`/`transfer`, `payment_date`, `amount`) (`docs/sdd/state.yaml`,
   ADR-005).
5. SPEC-006 is implemented and completed: turno records (`date`, `start_time`,
   `end_time`, `capacity_limit`, `status` active/inactive/cancelled)
   (`docs/sdd/state.yaml`).
6. SPEC-007 is implemented and completed: booking records (`client_id`,
   `turno_id`, `status` confirmed/cancelled, `booked_at`, `booked_by` nullable,
   `notes`), the `CreateBooking` Action (access gate, capacity atomicity, lead
   time, duplicate check), `Booking::cancel()` and `Booking::confirmedCountForTurno()`
   (`docs/sdd/state.yaml`, ADR-006). SPEC-007 deferred CLIENT self-service to
   this Specification (BK-01, BK-07, AF-006).
7. SPEC-008 is implemented and completed: attendance records (`client_id`,
   `attended_at`, `recorded_by`, optional `turno_id`, `notes`), immutable
   (`docs/sdd/state.yaml`). SPEC-008 deferred CLIENT self-view to this
   Specification (AT-01, BR-010).
8. SPEC-010 is implemented and completed: routine versions
   (`draft`/`active`/`archived`), assignments with at most one active assignment
   per client (`Client::currentRoutine()`), set-level prescription rows
   (`docs/sdd/state.yaml`). SPEC-010 deferred client visibility of the assigned
   routine to this Specification (AR-08).
9. SPEC-011 is implemented and completed: `WorkoutLog` model (one row per
   performed set, exactly-one exercise reference, immutable), `referenceRules()`
   validation, `recorded_by` audit. SPEC-011 deferred CLIENT self-logging and
   client visibility of their own logs to this Specification (WL-03, §12, OQ-07).
10. SPEC-015 is implemented and completed: the shared Blade layout, the
    `/portal` base rendering the CLIENT's own profile (name, DNI, email, phone,
    status) and the ERR-005 generic notice, and the logout affordance
    (`docs/sdd/state.yaml`).
11. The gate decision D-18 (option 3, full) is pre-approved (NIGHT MODE,
    `docs/sdd/state.yaml`).

---

## 4. Functional Requirements

### FR-001 — Portal overview (extends SPEC-015 FR-006)

`/portal` keeps the existing SPEC-015 base — the authenticated CLIENT's own
profile (name, DNI, email, phone, status) or the ERR-005 notice when no linked
Client record exists — and adds navigation to the portal sections listed below.
This Specification does not re-specify the layout, branding or logout (SPEC-015
remains authoritative for those).

### FR-002 — View own memberships (read-only)

A CLIENT can view the list of their own memberships, in chronological order
(start date): plan name, period (start/end dates, duration), and status
(`pending`/`active`/`expired`/`cancelled`). This is a read-only projection of
`Client::memberships()` (SPEC-004 FR-004); no membership operation is exposed.

### FR-003 — View own payments & cuotas (read-only)

A CLIENT can view their own cuotas and payments, read-only: for each membership,
its cuota (amount, status `pending`/`paid`/`cancelled`) and the payment(s)
recorded against it (amount, method `cash`/`transfer`, payment date, status
`confirmed`). This is a read-only projection reached through
`Client::memberships() → membership.cuota → cuota.payments` (SPEC-005). Staff
audit fields (`reference`, `notes`, `recorded_by`) are not shown (CP-06).

### FR-004 — View own attendance (read-only)

A CLIENT can view their own attendance history in chronological order
(`attended_at`): each record's access timestamp and the optional turno
(date + start/end time) it references (SPEC-008 FR-004). Read-only
(`Client::attendances()`).

### FR-005 — View own bookings (read-only)

A CLIENT can view their own bookings (`confirmed` and `cancelled`), showing the
turno (date, start/end time), the booking status, and the booking date. This is
the self-service counterpart of SPEC-007 FR-002, scoped to the owning client; it
is required so the CLIENT can see what to cancel (FR-007).

### FR-006 — Book a turno (self-service)

A CLIENT can, from the portal, list the upcoming bookable turnos and reserve a
spot for themselves. The list shows `active` turnos (SPEC-006 BR-002) whose date
is today through `today + 7 days` inclusive, whose start time has not passed, and
which are not full (confirmed bookings < `capacity_limit`). Selecting a turno
creates a `confirmed` booking for the CLIENT's own Client record, reusing the
SPEC-007 `CreateBooking` Action extended so a CLIENT may book **only** their own
Client record (the client id is never a form input; it is derived from the
authenticated user's linked Client). All SPEC-007 rules apply unchanged: active
membership gate (D-05 option 1), turno `active`, lead time, capacity enforced
atomically (no overselling), one confirmed booking per client per turno, no
waitlist. `booked_by` is `null` for self-service bookings (SPEC-007 BK-12;
CP-09).

### FR-007 — Cancel own booking (self-service)

A CLIENT can cancel a `confirmed` booking that belongs to them, before the
turno's start time (SPEC-007 BK-06), reusing `Booking::cancel()` (SPEC-007
FR-004). Cancellation is without penalty and frees the spot (D-08, SPEC-007
BR-010). A booking that does not belong to the CLIENT, or is already `cancelled`,
cannot be cancelled through the portal (ERR-008, ERR-009).

### FR-008 — View own routine (read-only)

A CLIENT can view their own currently assigned routine version
(`Client::currentRoutine()`, SPEC-010 AR-03), read-only: the routine name, its
ordinal days (day numbers), and each day's set-level prescription rows (exercise
name, set number, target reps, target weight, rest seconds, notes). If the client
has no active assignment, the portal shows an empty state (AF-004). This is the
client-facing read that SPEC-010 deferred (AR-08).

### FR-009 — Log a workout (self-service)

A CLIENT can record what they actually performed (SPEC-011), for their own Client
record, reusing the SPEC-011 `WorkoutLog` model and validation (BR-008) extended
for CLIENT self-recording:

- the client id is derived from the authenticated user's linked Client, never a
  form input (C-13);
- each log references **either** a prescribed set row of the client's assigned
  routine (the assigned-routine case) **or** an `active` catalogue exercise (the
  free-log case) — exactly one (SPEC-011 BR-002, C-11);
- fields: `performed_at` (default now, not in the future; backdating allowed),
  `actual_weight` (optional ≥ 0), `actual_reps` (required positive integer),
  `notes` (optional);
- `recorded_by` is set to the authenticated CLIENT's User (not a form field);
- logs are immutable: no edit, no delete (SPEC-011 BR-006; CP-04).

### FR-010 — View own workout history (read-only)

A CLIENT can view their own workout log history, grouped by `performed_at`
(SPEC-011 FR-003 scoped to the owning client): per-set rows showing exercise,
performed timestamp, actual weight/reps and notes. This is the client-facing read
that SPEC-011 §12 deferred; the prescription-vs-actual comparison (SPEC-011
FR-004) remains staff-only and is NOT shown to the client (CP-05).

### FR-011 — Profile: edit contact fields + view own health notes

A CLIENT can update their own contact fields: `email`, `phone`, and
`emergency_contact` (all optional). The portal edit form presents only these
three fields (NC-01, CP-01). `full_name` and `dni` remain staff-managed identity
fields and are NOT editable through the portal (NC-01, SPEC-002 BR-005); `status`
is NOT editable (SPEC-012 lifecycle, staff-only). Editing validates the email and
phone formats (SPEC-002 ERR-006 pattern) and never modifies any User, Membership,
Payment, Booking, Attendance, Routine or WorkoutLog record.

The same profile section also shows the CLIENT's **own** health notes
(`injuries_notes`, `medical_conditions_notes`) in **read-only** mode (NC-02,
SPEC-002 BR-007): the CLIENT may view their own notes but never edit them, and
never sees another client's health data (C-13, BR-002).

---

## 5. Business Rules

### BR-001 — CLIENT-only portal

`/portal` and every portal section are served only to authenticated users holding
the CLIENT role, via the existing `auth` + `role:CLIENT` middleware (SPEC-001
FR-006, BR-004; SPEC-015 §9). An anonymous visitor is redirected to login; an
authenticated user without the CLIENT role is denied (403). This Specification
does not change the admin panel or the `role:CLIENT` gate.

### BR-002 — Client isolation (C-13), server-side

A CLIENT may see and act on **only their own** data. Every portal read resolves
the authenticated user's linked Client (`auth()->user()->client`) and scopes the
query to that Client's records; no `client_id` or foreign-record id from the
request is trusted for reads. Every mutation (book, cancel, log, edit profile)
derives the target Client from the authenticated user and rejects any request
that targets a different client's record. This is enforced server-side; hiding
navigation or controls is never sufficient (AGENTS.md §17, SPEC-015 BR-002).

### BR-003 — Read-only except the four self-service actions

Memberships, payments/cuotas, attendance, bookings, the assigned routine and
workout history are READ-ONLY in the portal. The only interactive actions are:
book a turno (FR-006), cancel an own booking (FR-007), log a workout (FR-009),
and edit profile (FR-011) — exactly the D-18 option 3 interactive scope. No
membership, payment, cuota, turno, attendance, routine or assignment operation is
exposed to the CLIENT.

### BR-004 — Booking self-service reuses SPEC-007 rules

A CLIENT self-booking is subject to the same rules as a staff booking (SPEC-007):
active-membership gate at booking time (D-05 option 1, no grace period;
SPEC-007 BR-005); turno `active` (BR-006); turno date today..+7 and start time
not passed (BR-007); capacity enforced atomically (BR-008); one confirmed booking
per client per turno (BR-009); no waitlist (BR-013). The only difference is the
actor: the CLIENT books only their own Client record and `booked_by` is `null`
(CP-09).

### BR-005 — Cancellation reuses SPEC-007 rules

A CLIENT may cancel only their own `confirmed` booking, before the turno's start
time, without penalty; the spot reopens (SPEC-007 BR-004, BR-010, BK-06, D-08).
A booking is never reactivated after cancellation.

### BR-006 — Workout self-logging reuses SPEC-011 rules

A CLIENT self-log is subject to the same rules as a staff log (SPEC-011 BR-008):
exactly one exercise reference (a prescribed set row of a version the client was
assigned to, or an `active` catalogue exercise); `actual_reps` positive integer;
`actual_weight` optional ≥ 0; `performed_at` not in the future; `recorded_by`
set to the authenticated User. Logs are immutable (no edit/delete). Workout
logging is NOT gated on an active membership (SPEC-011 BR-010).

### BR-007 — Routine read is current assignment only

The portal routine view shows only the client's current active assignment
(`Client::currentRoutine()`, SPEC-010 AR-03) and is read-only. Archived versions
and assignment history are not shown to the CLIENT (staff-only, SPEC-010 FR-004).

### BR-008 — Edit profile is the final contact-field set; health notes are read-only

Only `email`, `phone` and `emergency_contact` are client-editable (NC-01, CP-01).
All three are optional contact fields; none is an identity field and none carries
the SPEC-002 uniqueness rule. The remaining Client fields are not client-editable:
`full_name` and `dni` (identity; staff-managed, DNI uniqueness is SPEC-002
BR-005), the health notes `injuries_notes`/`medical_conditions_notes` (read-only
to their owner, NC-02/SPEC-002 BR-007), and `status` (SPEC-012 lifecycle).
Editing validates email/phone formats (SPEC-002 ERR-006 pattern) and does not
synchronize to, or modify, the linked `User` record.

The CLIENT's own health notes (`injuries_notes`, `medical_conditions_notes`) are
VISIBLE to the CLIENT in read-only mode (NC-02). They are never editable through
the portal and are scoped strictly to the owning client's record (C-13, BR-002).

### BR-009 — No new business rules

This Specification does not invent any business rule beyond the flagged
assumptions (§14.1). It only consumes the implemented rules of SPEC-002/004/005/
006/007/008/010/011 and wires them into a CLIENT-facing, ownership-scoped
surface.

### BR-010 — ERR-005 boundary

A CLIENT with no usable linked Client record receives the SPEC-015 ERR-005
generic notice ("Perfil no disponible. Contactá a recepción.") and no portal
business data, exactly as specified by SPEC-015 FR-006/ERR-005.

---

## 6. Main Flow

1. A CLIENT logs in and is redirected to `/portal` (SPEC-001). The shared layout
   ("El Area Gym") renders, with the CLIENT's own profile summary and portal
   navigation (SPEC-015; FR-001).
2. The CLIENT opens a read-only section (memberships, payments/cuotas,
   attendance, bookings, routine, workout history). The controller resolves
   `auth()->user()->client` and renders only that Client's records (BR-002).
3. **Book a turno (FR-006):** the CLIENT opens the turnos section, which lists
   upcoming bookable turnos (active, today..+7, not started, not full). The
   CLIENT selects a turno and confirms. The system reuses `CreateBooking` for the
   CLIENT's own Client record and enforces: active-membership gate, turno
   bookability, lead time, capacity (atomically), no duplicate (SPEC-007). On
   success a `confirmed` booking is persisted (`booked_by` null) and shown in the
   client's bookings (FR-005). On failure the reason is shown (ERR-003..ERR-007).
4. **Cancel a booking (FR-007):** the CLIENT opens their own bookings, selects a
   `confirmed` booking, and cancels. `Booking::cancel()` runs; the booking
   becomes `cancelled` and the spot reopens. A booking belonging to another
   client, or already `cancelled`, is rejected (ERR-008, ERR-009).
5. **Log a workout (FR-009):** the CLIENT opens the workout section, selects
   either a prescribed set from their assigned routine or a free `active`
   exercise, enters performed date, weight, reps and notes, and saves. The system
   validates (SPEC-011 BR-008) and persists a `WorkoutLog` with `recorded_by` =
   the CLIENT's User. The log appears in the client's own history (FR-010).
6. **Edit profile (FR-011):** the CLIENT opens the profile section, edits email /
   phone / emergency contact, and saves. The system validates formats and
   persists only those fields on the Client record.

---

## 7. Alternative Flows

### AF-001 — CLIENT with no linked Client record

An authenticated user with the CLIENT role but no linked `Client` record requests
`/portal` or any portal section. The portal renders the SPEC-015 ERR-005 generic
notice and no portal business data; no fallback identity, cross-client lookup or
lifecycle rule is introduced (BR-010, SPEC-015 ERR-005).

### AF-002 — Client with no active membership tries to book

The CLIENT has no qualifying membership (`hasQualifyingMembership()` false). The
booking is rejected with the SPEC-007 access-gate reason (no membership / expired
/ no active membership — `Client::accessDenialReason()`); the reason is surfaced
to the client (ERR-003, D-05 option 1, no grace period).

### AF-003 — Turno is full

The CLIENT selects a turno whose confirmed bookings equal its `capacity_limit`.
The booking is rejected as "turno full"; there is no waitlist (SPEC-007 BR-008,
BR-013). The client must wait for a cancellation and book then.

### AF-004 — Client with no assigned routine

`Client::currentRoutine()` is null (SPEC-010 AF-006). The routine section shows
an empty state; workout logging is still possible via the free-log path
(SPEC-011 AF-001, FR-009).

### AF-005 — A staff member who also holds CLIENT

An ADMIN or TRAINER who also holds the CLIENT role (C-01, SPEC-001 OQ-04) accesses
`/portal`; they see their own linked Client's data and can self-serve exactly like
any other CLIENT. Their staff abilities are unaffected (they remain in the admin
panel context).

### AF-006 — Membership expires between booking and turno date

A client booked a turno while holding an active membership; the membership
expires before the turno date. The booking remains `confirmed` (the gate is
evaluated at booking time only — SPEC-007 BK-09/AF-005). Admission at the door is
SPEC-008, not this Specification.

### AF-007 — Self-service booking records null `booked_by`

A CLIENT books a turno. The created booking stores `booked_by = null`
(SPEC-007 BK-12); `booked_at` is the creation time. Staff-created bookings keep
their `booked_by` staff User; no data migration or change to existing records is
required (CP-09).

### AF-008 — Client cancels and re-books

A client cancels a booking (FR-007); the spot reopens (SPEC-007 BR-010). The
client (or another client) books the turno again; a fresh `confirmed` booking is
created (SPEC-007 AF-003). The cancelled record is never reactivated.

---

## 8. Error Cases

### ERR-001 — No linked Client record

Condition: an authorized CLIENT with no usable linked `Client` record requests
portal content.

Expected behavior: the SPEC-015 ERR-005 generic notice is rendered; no portal
business data is produced (BR-010).

### ERR-002 — Unauthorized access

Condition: an anonymous visitor or an authenticated non-CLIENT requests `/portal`
or a portal section.

Expected behavior: redirect to login (anonymous) or 403 (non-CLIENT), per the
existing `role:CLIENT` gate (BR-001).

### ERR-003 — No active membership (booking gate)

Condition: a CLIENT without a qualifying active membership attempts to book.

Expected behavior: rejected with the SPEC-007 access-gate reason; no booking is
created (BR-004, D-05 option 1).

### ERR-004 — Turno full

Condition: a CLIENT books a turno whose confirmed bookings equal its
`capacity_limit`.

Expected behavior: rejected as "turno full"; no waitlist (BR-004, SPEC-007
BR-008/BR-013).

### ERR-005 — Turno not bookable

Condition: a CLIENT books a turno whose status is `inactive`/`cancelled`, whose
date is in the past, beyond the lead-time window, or whose same-day start time
has passed.

Expected behavior: rejected with the SPEC-007 validation error (BR-004, SPEC-007
BR-006/BR-007).

### ERR-006 — Duplicate confirmed booking

Condition: a CLIENT books a turno they already have a `confirmed` booking for.

Expected behavior: rejected (SPEC-007 BR-009/ERR-008).

### ERR-007 — Race on the last spot

Condition: two booking attempts race for the last spot of a turno.

Expected behavior: exactly one succeeds; the other is rejected as "turno full".
No overselling (BR-004, SPEC-007 BR-008/ERR-011, ADR-006).

### ERR-008 — Cancel a booking that is not own

Condition: a CLIENT attempts to cancel a booking that belongs to a different
client.

Expected behavior: denied (404/403); the request never reveals or mutates
another client's booking (BR-002, C-13).

### ERR-009 — Cancel a non-confirmed booking

Condition: a CLIENT attempts to cancel an own booking that is already
`cancelled`.

Expected behavior: rejected; only `confirmed` bookings are cancellable
(SPEC-007 BR-004/ERR-009).

### ERR-010 — Invalid workout log

Condition: a CLIENT submits a log with both/neither exercise reference, a
reference from a routine version they were never assigned to, an `inactive` free
exercise, missing/zero/negative reps, negative weight, or a future
`performed_at`.

Expected behavior: rejected with the SPEC-011 validation errors (BR-006,
SPEC-011 ERR-001..ERR-005).

### ERR-011 — Invalid profile edit / non-editable field

Condition: a CLIENT submits an invalid email/phone, or attempts to edit a
non-editable field (`full_name`, `dni`, `injuries_notes`,
`medical_conditions_notes`, `status`).

Expected behavior: rejected / ignored; only the three contact fields
(`email`/`phone`/`emergency_contact`) are accepted and validated (BR-008, NC-01,
CP-01). The health notes remain read-only and are not writable through the
portal (NC-02). No DNI uniqueness path exists in the portal because DNI is not
client-editable (NC-01).

### ERR-012 — Cross-client data access attempt

Condition: a request supplies a record id (booking, turno, log, membership, etc.)
that does not belong to the authenticated client.

Expected behavior: denied server-side; the response does not reveal the other
client's data (BR-002, C-13).

---

## 9. Authorization

| Operation | Anonymous | ADMIN | TRAINER | CLIENT |
| --- | --- | --- | --- | --- |
| Access `/portal` | Redirect to login | Denied unless also CLIENT | Denied unless also CLIENT | Allowed (BR-001) |
| View own profile (SPEC-015) | Denied | — | — | Allowed (own record) |
| View own health notes (`injuries_notes` / `medical_conditions_notes`) | Denied | — | — | Allowed (own record, read-only; NC-02) |
| View own memberships / payments / attendance / bookings / routine / workout history | Denied | — | — | Allowed (own records only, BR-002) |
| Book a turno (self) | Denied | — | — | Allowed (own Client record; SPEC-007 rules) |
| Cancel own booking | Denied | — | — | Allowed (own `confirmed` booking only) |
| Log own workout | Denied | — | — | Allowed (own Client record; SPEC-011 rules) |
| Edit own profile (contact fields) | Denied | — | — | Allowed (email/phone/emergency contact only; NC-01) |
| View / edit another client's data | Denied | Per staff rules (unchanged) | Per staff rules (unchanged) | Denied always (BR-002, C-13) |
| Manage memberships / payments / turnos / attendance / routines / assignments | Denied | Per staff specs (unchanged) | Per staff specs (unchanged) | Denied (BR-003) |

Notes:

- The admin-panel permissions defined by SPEC-002..011 are UNCHANGED. This
  Specification only adds the CLIENT self-access column; it never weakens the
  staff policies (BookingPolicy/WorkoutLogPolicy/AttendancePolicy/MembershipPolicy/
  CuotaPolicy/PaymentPolicy/RoutinePolicy/ClientPolicy).
- C-13 isolation is enforced server-side by ownership scoping in the portal
  controllers and by extending the relevant Policies/Actions for CLIENT
  self-access (e.g., a CLIENT may `create` a Booking/WorkoutLog only for their
  own Client; a CLIENT may `update` only their own Booking/Client record). The
  exact mechanism (ownership-scoped Policy methods vs. controller/Action checks)
  is an Architect decision; the behavior is this table plus BR-002.
- A User holding several roles receives the union of permissions (SPEC-001
  BR-002): an ADMIN/TRAINER who also holds CLIENT may use both contexts.
- State/validation rules (ERR-003..ERR-011) are NOT authorization rules: they are
  enforced by the reused Actions/model methods (CreateBooking, Booking::cancel,
  WorkoutLog validation, the edit-profile validation), the same way SPEC-007/011
  separate authorization from business validation.
- Navigation visibility is never an authorization mechanism (AGENTS.md §17).

---

## 10. Data Changes

This Specification describes the information that must exist and be modified; the
exact persistence schema and route/controller layout are defined by the Architect.

Created:

- `Booking` records created by CLIENT self-service (FR-006), with `booked_by =
  null` (CP-09, SPEC-007 BK-12). No new column: `booked_by` already exists and is
  nullable.
- `WorkoutLog` records created by CLIENT self-service (FR-009), with
  `recorded_by` = the CLIENT's User. No new column: `recorded_by` already exists.

Modified:

- `Client` `email`, `phone`, `emergency_contact` via the portal edit-profile
  action (FR-011, BR-008, NC-01). No other Client field — including
  `full_name`, `dni` and the health notes — and no User record, is modified. The
  health notes are exposed read-only (never written) through the portal
  (NC-02).
- `Booking` `status` via `Booking::cancel()` on self-cancellation (FR-007).

Deleted:

- None. No hard deletion of any record, consistent with the preservation pattern
  of all predecessor Specifications (SPEC-001 BR-007 / SPEC-002 BR-006 / SPEC-004
  BR-014 / SPEC-006 BR-009 / SPEC-007 BR-011 / SPEC-008 BR-008 / SPEC-011 BR-006).

No new tables, columns or migrations are required by this Specification: all data
already exists in the SPEC-002..011 schemas. The portal is an additive read +
self-service surface over existing models and Actions.

---

## 11. Acceptance Criteria

- [ ] AC-1: An authenticated CLIENT can access `/portal` and sees their own
  profile and portal navigation; an anonymous visitor is redirected to login and
  a non-CLIENT is denied (403) (BR-001, ERR-002, SPEC-015).
- [ ] AC-2: The portal lists only the authenticated CLIENT's own memberships
  (plan, period, status), in chronological order (FR-002, BR-002).
- [ ] AC-3: The portal lists only the authenticated CLIENT's own cuotas and
  payments (amount, status, method, payment date) (FR-003, BR-002).
- [ ] AC-4: The portal lists only the authenticated CLIENT's own attendance
  history in chronological order (FR-004, BR-002).
- [ ] AC-5: The portal lists only the authenticated CLIENT's own bookings with
  status and turno details (FR-005, BR-002).
- [ ] AC-6: A CLIENT can book an `active`, time-valid, non-full turno within the
  lead-time window; the booking is persisted `confirmed` with `booked_by = null`
  and appears in their own bookings (FR-006, BR-004, CP-09, SPEC-007).
- [ ] AC-7: A CLIENT without a qualifying active membership cannot book (rejected
  with the access-gate reason) (ERR-003, D-05 option 1).
- [ ] AC-8: A CLIENT cannot book a full turno (ERR-004), an `inactive`/`cancelled`
  turno, a past/out-of-window turno, or a started same-day turno (ERR-005), or
  create a duplicate confirmed booking (ERR-006); the last-spot race produces
  exactly one success (ERR-007).
- [ ] AC-9: A CLIENT can cancel their own `confirmed` booking; it becomes
  `cancelled` and the spot reopens (FR-007, BR-005, SPEC-007 BR-010).
- [ ] AC-10: A CLIENT cannot cancel a booking belonging to another client
  (ERR-008) or an already-`cancelled` booking (ERR-009).
- [ ] AC-11: A CLIENT can view their own current assigned routine (days + set
  rows + exercise names) read-only; with no active assignment an empty state is
  shown (FR-008, BR-007).
- [ ] AC-12: A CLIENT can log a workout (assigned-routine set row or free `active`
  exercise) with `recorded_by` = their own User; the log persists and is immutable
  (FR-009, BR-006, SPEC-011).
- [ ] AC-13: Invalid workout logs are rejected — both/neither reference, a
  reference from a never-assigned version, an `inactive` free exercise, invalid
  weight/reps, or a future `performed_at` (ERR-010, SPEC-011).
- [ ] AC-14: A CLIENT can view their own workout history grouped by date; the
  staff prescription-vs-actual comparison is NOT shown to the client (FR-010,
  CP-05).
- [ ] AC-15: A CLIENT can edit only their own `email`, `phone` and
  `emergency_contact`; invalid formats are rejected; `full_name`, `dni`,
  `injuries_notes`, `medical_conditions_notes` and `status` are not editable
  (FR-011, BR-008, NC-01, CP-01).
- [ ] AC-16: A CLIENT never sees or mutates another client's data through any
  portal path, including by supplying a foreign record id (ERR-012, BR-002, C-13).
- [ ] AC-17: A CLIENT has no membership/payment/cuota/turno/attendance/routine/
  assignment management ability in the portal (BR-003).
- [ ] AC-18: A CLIENT with no linked Client record sees the SPEC-015 ERR-005
  generic notice and no portal business data (BR-010, ERR-001).
- [ ] AC-19: Staff admin-panel permissions (SPEC-002..011 Policies) are unchanged
  by this Specification; a multi-role ADMIN/TRAINER + CLIENT user can still use
  the admin panel and also self-serve in the portal (AF-005, SPEC-001 BR-002).
- [ ] AC-20: A CLIENT can view their own health notes (`injuries_notes`,
  `medical_conditions_notes`) in read-only mode and never sees another client's
  health notes (FR-011, BR-008, NC-02, BR-002, C-13).

**Test plan (to be executed at Implementation; aligned with the existing Pest
layout under `tests/`, `tests/Pest.php` helpers `role()` / `userWithRoles()`,
`RefreshDatabase`, and the SPEC-015 `clientWithUser(...)` / `withoutVite()`
boundary):**

- `tests/Feature/Portal/PortalAccessTest.php`: CLIENT → 200; anonymous → redirect;
  non-CLIENT → 403; no linked Client → ERR-005 notice and no business content
  (AC-1, AC-18).
- `tests/Feature/Portal/PortalIsolationTest.php`: two CLIENT fixtures; each
  portal response contains only the authenticated client's memberships/payments/
  attendance/bookings/routine/logs; a foreign record id on a mutation path is
  rejected without leaking data (AC-16, ERR-012, BR-002, C-13).
- `tests/Feature/Portal/PortalReadOnlySectionsTest.php`: memberships (AC-2),
  payments/cuotas (AC-3), attendance (AC-4), bookings (AC-5), routine read + empty
  state (AC-11), workout history (AC-14).
- `tests/Feature/Portal/PortalBookingTest.php`: self-book success with
  `booked_by = null` (AC-6); access-gate denial with reason (AC-7); full/
  not-bookable/duplicate/out-of-window rejections (AC-8); race on last spot
  (ERR-007); self-cancel + spot reopen (AC-9); cancel non-own / non-confirmed
  denied (AC-10).
- `tests/Feature/Portal/PortalWorkoutLogTest.php`: self-log assigned-routine and
  free-exercise success with `recorded_by = own user` and immutability (AC-12);
  invalid-reference/value rejections (AC-13).
- `tests/Feature/Portal/PortalProfileEditTest.php`: edit email/phone/emergency
  contact success (AC-15); invalid formats rejected; `full_name`/`dni`/health
  notes/`status` non-editable (AC-15, NC-01, BR-008); own health notes visible
  read-only and another client's health notes never exposed (AC-20, NC-02).
- `tests/Feature/Portal/PortalPolicyTest.php`: CLIENT ownership-scoped abilities
  (view own / create own / update own) allowed; cross-client denied; staff
  policies unchanged (AC-17, AC-19).
- Regression: existing SPEC-001..015 tests continue to pass; staff Filament
  resources are unaffected (AC-19).

---

## 12. Out of Scope

The following are explicitly NOT part of this Specification:

- **Membership, payment, cuota, turno, attendance, routine and assignment
  management by a CLIENT** — the portal is read-only for these; all management
  remains ADMIN/TRAINER (BR-003, SPEC-002..011).
- **Online payment in the portal** — SPEC-014 (Mercado Pago) is EXCLUDED from the
  backlog by PO decision; the portal never processes a payment. The portal only
  DISPLAYS the client's own cuotas/payments (SPEC-005 §12).
- **Registering new clients or provisioning accounts from the portal** — public
  registration is SPEC-012 (staff approval); provisioning is SPEC-002 (ADMIN).
- **The presentation foundation** — layout, branding ("El Area Gym"), ERR-005,
  logout and the toolchain are SPEC-015 and are not re-specified here.
- **Editing `full_name`, `dni`, health notes (`injuries_notes`,
  `medical_conditions_notes`) or `status` from the portal** — `full_name` and
  `dni` remain staff-managed identity fields (NC-01); the health notes are
  view-only for their owner and not editable (NC-02); `status` remains staff-only
  (SPEC-012). None of these fields is editable through the portal.
- **Client progress analytics** — the prescription-vs-actual comparison, charts,
  volume totals and PR detection remain staff-only (SPEC-011 FR-004, WL-10); the
  client only sees their own flat log history (CP-05).
- **Edit/delete of workout logs** — logs (staff- and client-recorded) are
  immutable in the MVP (SPEC-011 BR-006; CP-04).
- **Notifications** to clients or staff about bookings, cancellations, routines
  or payments — no notification infrastructure exists in the MVP (SPEC-007 NC-01,
  SPEC-011 §12).
- **No-show handling and the `confirmed → completed` booking transition** —
  SPEC-008/SPEC-007 territory; not part of the portal.
- **Public site content, plans listing and public registration** — SPEC-012 /
  SPEC-015 scope.
- **A mobile app or any public API** — the portal is a server-rendered Blade
  surface (C-14, ARCHITECTURE §19).

---

## 13. Dependencies

- **SPEC-001 (Authentication & Roles) — COMPLETED**: authentication, fixed role
  catalog, the `role:CLIENT` middleware (`EnsureUserHasRole`) on `/portal`,
  post-login CLIENT redirect, `User::hasRole`/`hasAnyRole`, policy pattern
  (ADR-001).
- **SPEC-002 (Client Management) — COMPLETED**: Client fields, DNI uniqueness
  (BR-005), the `clients.user_id` 1:1 link (`User::client()`,
  `Client::user()`), health-data confidentiality (BR-007), the client status
  values (`pending`/`active`/`rejected`, SPEC-012) shown by SPEC-015.
- **SPEC-004 (Membership Management) — COMPLETED**: membership states and
  `Client::hasQualifyingMembership()` / `Client::accessDenialReason()` consumed by
  the booking gate and the membership view.
- **SPEC-005 (Payments & Cuotas) — COMPLETED**: cuota/payment records consumed
  read-only by the payments/cuotas view.
- **SPEC-006 (Scheduling & Turnos) — COMPLETED**: turno model consumed by the
  bookable-turnos list.
- **SPEC-007 (Bookings) — COMPLETED**: `CreateBooking` Action (reused, extended
  for CLIENT self), `Booking::cancel()`, `Booking::confirmedCountForTurno()`,
  `booked_by` nullable (BK-12). SPEC-007 deferred CLIENT self-service to this
  Specification (BK-01, BK-07, AF-006).
- **SPEC-008 (Attendance) — COMPLETED**: attendance records consumed read-only;
  SPEC-008 deferred CLIENT self-view to this Specification (AT-01, BR-010).
- **SPEC-010 (Routines) — COMPLETED**: `Client::currentRoutine()`, routine/day/
  set-row models consumed read-only; SPEC-010 deferred client visibility to this
  Specification (AR-08).
- **SPEC-011 (Workout Logs & Progress) — COMPLETED**: `WorkoutLog` model,
  `referenceRules()`, immutability, `recorded_by`; SPEC-011 deferred CLIENT
  self-logging and client visibility to this Specification (WL-03, §12, OQ-07).
- **SPEC-015 (Presentation Foundation & UX) — COMPLETED**: the shared layout, the
  `/portal` base (profile + ERR-005 + logout) that this Specification extends
  (FR-001, BR-010).
- Gate decision: **D-18 option 3** (full portal scope) — pre-approved (NIGHT
  MODE, `docs/sdd/state.yaml`).
- Confirmed decisions used: C-01 (roles, multi-role), C-02 (client aggregates
  memberships/payments/bookings/attendance/routines/logs), C-13 (client
  isolation), C-15 (presentation contexts), D-01 option 2 (Client standalone,
  linked User optional), D-05 option 1 (active membership required), D-06 option
  2 (multiple active memberships).
- Requirements analysis: `analyst-pass-001.md` §5.14 (Client Portal), D-18,
  R-08.
- Flagged assumptions CP-01..CP-09 (§14.1) and the resolved Product Owner
  decisions NC-01/NC-02 (§14.2) as documented.

---

## 14. Open Questions

### 14.1 Assumed decisions (technical defaults consistent with implemented specs)

These flagged assumptions are technical defaults that align the portal with the
implemented Specifications. They are NOT new business rules unless stated
otherwise; prefix **CP** distinguishes this Specification's assumptions from
SPEC-001 (A-xx), SPEC-002 (AD-xx), SPEC-004 (AM-xx), SPEC-005 (PY-xx), SPEC-006
(AS-xx), SPEC-007 (BK-xx), SPEC-008 (AT-xx), SPEC-009 (EX-xx), SPEC-010 (AR-xx)
and SPEC-011 (WL-xx).

| ID | Assumption | Source | Blocks / Affects |
| --- | --- | --- | --- |
| CP-01 | **Edit-profile final editable set (resolved by NC-01).** The CLIENT may edit only `email`, `phone` and `emergency_contact` (all optional contact fields). `full_name` and `dni` (identity; DNI unique per SPEC-002 BR-005) are staff-managed and NOT client-editable; `status` (SPEC-012 lifecycle) is staff-only. The health notes `injuries_notes`/`medical_conditions_notes` are NOT client-editable and are exposed read-only to their owner (NC-02). This is the D-18 option 3 "edit profile" implementation, aligned with the resolved PO decisions NC-01/NC-02 (§14.2). | Task directive "specify a minimal safe set (e.g., email/phone/emergency contact) consistent with SPEC-002"; D-18 option 3 pre-approved; PO decisions NC-01/NC-02; SPEC-002 FR-001/BR-005/BR-007 | FR-011, BR-008, ERR-011, AC-15, AC-20 |
| CP-02 | Portal navigation is a set of pages under `/portal` (overview + sections for memberships, payments, attendance, turnos/bookings, routine, workouts, profile). The exact route/controller layout is an Architect decision; this Specification defines the features and their authorization, not the URL scheme. | Task directive "Main portal features/endpoints"; SPEC-015 route precedent | FR-001..FR-011, §9 |
| CP-03 | Workout self-logging offers BOTH the assigned-routine path and the free-log path, mirroring SPEC-011 (C-11 "both cases exist"; WL-02 free logging). | SPEC-011 BR-002/BR-005, WL-02 | FR-009, AC-12/AC-13 |
| CP-04 | Client-recorded workout logs are immutable (no edit/delete), the same as staff logs — resolves SPEC-011 OQ-07 ("do immutability rules apply to client self-logs?"). | SPEC-011 BR-006/WL-04 | FR-009, BR-006, §12 |
| CP-05 | The portal provides a minimal own-workout-history view (flat per-set log grouped by date, SPEC-011 FR-003 scoped to the owner). The prescription-vs-actual comparison (SPEC-011 FR-004) stays staff-only. D-18 option 3 lists "log workouts" but not "view own logs"; this view is included because SPEC-011 §12 deferred "client visibility of their own logs" to SPEC-013. | SPEC-011 §12 / FR-003 | FR-010, AC-14 |
| CP-06 | The payments/cuotas view shows amount, status, method and payment date; staff audit fields (`reference`, `notes`, `recorded_by`) are not shown to the client. | SPEC-005 §10 fields; minimal read | FR-003 |
| CP-07 | The attendance view shows `attended_at` and the optional turno; `recorded_by` (staff name) is not shown (presentation choice, no business rule). | SPEC-008 §10 fields | FR-004 |
| CP-08 | Edit-profile is not gated on the Client's `status` (`pending`/`active`/`rejected`): a CLIENT may edit their contact fields regardless of status. No new lifecycle rule is introduced (status remains a technical display default, SPEC-015 FR-006). | SPEC-015 FR-006 status-as-display; SPEC-012 status lifecycle is staff-only | FR-011, BR-008 |
| CP-09 | CLIENT self-bookings persist `booked_by = null` (SPEC-007 BK-12 reserved this null for the client self-service path); `booked_at` is the creation time. | SPEC-007 BK-12, §10 | FR-006, AF-007, AC-6 |

### 14.2 Resolved decisions (Product Owner)

The two business decisions that were previously **NOT COVERED** have now been
resolved by the Product Owner. They are recorded here as authoritative for this
Specification and supersede the earlier "NOT COVERED" framing.

- **NC-01 — Which profile fields a CLIENT may edit (RESOLVED).** The CLIENT may
  edit **ONLY** `email`, `phone` and `emergency_contact`. `full_name` and `dni`
  remain staff-managed (identity/uniqueness, SPEC-002 BR-005) and are NOT
  client-editable. There is no client-side DNI-uniqueness path in the portal
  (ERR-011). **Affects:** FR-011, BR-008, ERR-011, AC-15, CP-01.
- **NC-02 — Client visibility of their own health notes (RESOLVED).** The CLIENT
  **MAY view** their own health notes (`injuries_notes`, `medical_conditions_notes`)
  in **read-only** mode. A CLIENT must **never** see another client's health data
  (C-13, BR-002, SPEC-002 BR-007). The health notes remain staff-managed for
  editing and are not client-editable. **Affects:** FR-011, BR-008, §9, §10,
  AC-20.

### 14.3 Other open questions (non-blocking for this analysis)

- OQ-01 (CP-02): Should the portal use one controller per section or a single
  `ClientPortalController` with multiple methods? (Architect decision; no business
  rule implied.)
- OQ-02 (CP-06/CP-07): Should `reference` (bank-transfer identifier) be shown to
  the client in the payments view, and should `recorded_by` be shown in the
  attendance view? (Presentation choice; no business rule implied.)
- OQ-03 (CP-03): Should the portal restrict workout self-logging to the
  client's assigned routine only (no free logging), or keep both paths? This
  Specification keeps both (SPEC-011 WL-02/C-11).
- OQ-04 (CP-05): Should the client progress view eventually mirror the staff
  prescription-vs-actual comparison (SPEC-011 FR-004)? Deferred; the MVP shows
  only the flat history.

---

## 15. Related Documents

- Product documentation: `docs/product/product-definition-v0.1.md`
- Domain documentation: `docs/domain/domain-model-v0.1.md` (§Client, §Membership,
  §Payment, §Booking, §Attendance, §Routine, §WorkoutLog; C-01/C-02/C-13/C-15)
- Requirements analysis: `docs/requirements/analyst-pass-001.md` (§5.14 Client
  Portal, D-18, C-13, R-08)
- Specifications: `docs/specs/SPEC-001.md`, `docs/specs/SPEC-002.md`,
  `docs/specs/SPEC-003.md`, `docs/specs/SPEC-004.md`, `docs/specs/SPEC-005.md`,
  `docs/specs/SPEC-006.md`, `docs/specs/SPEC-007.md` (BK-01/BK-07/BK-12/AF-006),
  `docs/specs/SPEC-008.md` (AT-01/BR-010), `docs/specs/SPEC-010.md` (AR-08),
  `docs/specs/SPEC-011.md` (WL-03/§12/OQ-07), `docs/specs/SPEC-015.md`
  (FR-006/ERR-005/BR-002/BR-003)
- Architecture documentation: `docs/architecture/SPEC-015.md` (`/portal` base and
  the `User::client()` relationship), `ARCHITECTURE.md` (§5 presentation
  contexts, §12 authorization, §17 single location, §20 simplest correct
  architecture)
- Architecture decisions: `docs/adr/ADR-001.md` (role/authorization foundation),
  `docs/adr/ADR-002.md` (Client link), `docs/adr/ADR-003.md`
  (validation-first representation), `docs/adr/ADR-004.md` (status-as-string),
  `docs/adr/ADR-005.md` (cuota generation), `docs/adr/ADR-006.md` (booking
  capacity atomicity)
- Workflow state: `docs/sdd/state.yaml` (NIGHT MODE pre-approval D-18 option 3
  for SPEC-013; all predecessor specs completed; SPEC-014 excluded)
- Development rules: `AGENTS.md`

---

*Analyst note: SPEC-013 is analysis-complete and ready. The pre-approved gate
D-18 (option 3, full) plus the confirmed decisions (C-01, C-02, C-13, C-15,
D-01 option 2, D-05 option 1, D-06 option 2) and the implemented rules of
SPEC-002..011 and SPEC-015 cover the portal scope. The two formerly-uncovered
business decisions are now RESOLVED by the Product Owner and recorded in §14.2:
NC-01 (a CLIENT may edit only `email`/`phone`/`emergency_contact`; `full_name`
and `dni` stay staff-managed) and NC-02 (a CLIENT may view their own health notes
read-only, never another client's). All other decisions are routed to flagged
assumptions CP-01..CP-09 (§14.1), each a technical default consistent with the
implemented Specifications.*
