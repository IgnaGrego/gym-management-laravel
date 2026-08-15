# SPEC-004 — Membership Management

## Status

Draft (analysis phase).

This is the fourth Specification of the MVP. It depends on SPEC-001 (Authentication
& Roles), SPEC-002 (Client Management) and SPEC-003 (Plan Management), all
COMPLETED and implemented in the repository (`docs/sdd/state.yaml`): User / Role /
Client / Plan models, `role_user` pivot, UserPolicy / ClientPolicy / PlanPolicy,
Filament resources (UserResource, ClientResource, PlanResource), migrations for
`users` / `roles` / `role_user` / `clients` / `plans`, and the ADR-003 pricing
convention (decimal(10,2), single implicit currency, no plan-level period).

**Assumption notice:** this specification contains explicitly flagged assumptions
(AM-01 to AM-10, see §14.1) that borrow the documented "Recommended" options of
`analyst-pass-001.md` §8 (D-02, D-03, D-04, D-06, D-16), resolve the SPEC-003 open
question OQ-06, or fill gaps required to make the specification implementable.
**None of them is a confirmed business rule** unless stated otherwise. Each
requires Product Owner confirmation before Implementation.

**Boundary notice:** cuota generation and payment allocation are **deferred to
SPEC-005 (Payments & Cuotas)**. This Specification defines only the membership
record, its period semantics, its state machine, manual renewal, and the
multiplicity rule. The `pending → active` transition is specified as a contract
that SPEC-005 triggers when the first cuota of the membership is confirmed paid.
The access rule (what an active membership grants: attendance / booking) belongs
to decision D-05 and is a gate of SPEC-007 / SPEC-008; it is deliberately NOT
decided here (see §12 Out of Scope and OQ-01).

---

## 1. Objective

Provide membership management in the gym management system:

- an ADMIN can create, list, search, view, renew and cancel a client's
  membership records (confirmed decision C-05: "A Membership is a client's
  enrollment in a Plan for a specific period");
- a membership period is a fixed duration from a start date, and renewal is
  manual (D-03 Recommended option 1, borrowed as AM-02);
- a membership has exactly four states: pending, active, expired, cancelled
  (D-04 Recommended option 2, borrowed as AM-03);
- a client may hold more than one membership at the same time, including several
  active ones (D-06 Recommended option 2, borrowed as AM-04; confirmed decision
  C-08 "multiple Membership records over time");
- the effect of editing or deactivating a plan on existing memberships (SPEC-003
  OQ-06) is resolved at the membership level (AM-09);
- membership status is the reference consumed by the access rules of later
  Specifications; the rules themselves (which membership grants attendance /
  booking, grace period) are D-05, a gate of SPEC-007 / SPEC-008, and are NOT
  defined by this Specification.

The membership is the base record for the Payments & Cuotas module (SPEC-005),
which generates cuotas per membership period and allocates payments to them, and
for the operational modules (Bookings SPEC-007, Attendance SPEC-008) that consume
membership state. Plan, Membership and Payment remain separate, persistent
concepts (C-07, ARCHITECTURE §14).

---

## 2. Actors

Actors are defined by role, not by person (SPEC-001 §2). A single User may hold
one or more roles (confirmed decision C-01).

| Actor | Description |
| --- | --- |
| Anonymous visitor | No access to membership management. Membership data is never exposed on public pages. |
| ADMIN | Staff who administer the gym. In this Specification, the only actor that can create, list, search, view, renew and cancel memberships (assumption AM-06). |
| TRAINER | Staff who train clients. No membership management capability in this Specification; TRAINER read access to memberships is an open question (OQ-06). |
| CLIENT | A gym member. Cannot manage memberships; access to their own membership data is defined by SPEC-013 (client portal) and the access rule D-05 (SPEC-007/008). Client isolation (C-13) always applies. |

Notes:

- A User holding ADMIN and/or TRAINER uses the admin panel; a User holding CLIENT
  uses the client portal (SPEC-001 FR-006, confirmed C-15).
- A User holding both a staff role and CLIENT (e.g., a trainer who also trains)
  is permitted by C-01; the mixed-role behavior is tracked as SPEC-001 OQ-04.

---

## 3. Preconditions

1. SPEC-001 is implemented and completed: authentication, fixed role catalog
   (ADMIN, TRAINER, CLIENT), admin panel access (ADMIN or TRAINER), role helpers
   (`hasRole` / `hasAnyRole`), `Role::ADMIN` constant, no hard deletion of User
   records (`docs/sdd/state.yaml`, ADR-001).
2. SPEC-002 is implemented and completed: client records exist, DNI unique,
   ADMIN-only client management (`docs/sdd/state.yaml`, ADR-002).
3. SPEC-003 is implemented and completed: plan records exist with `name`,
   `price`, optional `enrollment_fee` and `is_active` status; no plan-level period
   attribute (PO-confirmed AP-06; ADR-003) (`docs/sdd/state.yaml`).
4. An authenticated ADMIN exists and can access the admin panel (SPEC-001 FR-008).
5. The role catalog stays at ADMIN / TRAINER / CLIENT; no RECEPTIONIST role
   (confirmed SPEC-001 A-04).
6. Decision D-01 (Client is a standalone record; linked User optional) is
   confirmed (PO; `docs/sdd/state.yaml`): a membership requires a Client record,
   NOT a linked User account.
7. No memberships table exists yet; the Memberships module is greenfield on top
   of the SPEC-001/002/003 foundations.
8. The gate decisions D-02, D-03, D-04, D-06 (and D-16 for the activation rule)
   are assumed per the documented Recommended options (AM-01 to AM-05) and
   require Product Owner confirmation before Implementation.

---

## 4. Functional Requirements

### FR-001 — Create membership

An ADMIN can create a membership for an existing client on an existing plan.
Required fields: client, plan, start date, duration (in days). The system
computes the end date from the start date and duration (BR-003, AM-07). A new
membership is created with status `pending` (BR-005, AM-03). Creating a
membership does NOT create any payment or cuota record (C-07, BR-001; cuota
generation is SPEC-005) and does NOT modify the client, plan or user records.

### FR-002 — List and search memberships

An ADMIN can list memberships and search them by client (name or DNI), plan
(name), status, and period dates (start/end).

### FR-003 — View membership detail

An ADMIN can view a membership's full detail: client, plan, period
(start/end dates, duration), and current status.

### FR-004 — View a client's membership history

An ADMIN can view all memberships of a client, including past states, in
chronological order (start date), so the client's enrollment history is visible
(C-08).

### FR-005 — Renew membership

An ADMIN can renew a client's active or expired membership (AM-08). Renewal
creates a NEW membership record for the same client and the same plan, with a
new period; the original membership record is never modified by the renewal
(BR-011). The new membership is created with status `pending`, like any new
membership (BR-005).

### FR-006 — Cancel membership

An ADMIN can cancel a membership whose status is `pending` or `active` (BR-008).
Cancellation is a manual, terminal transition; the membership is no longer
considered active even if its end date has not passed.

### FR-007 — Display membership status

Membership lists and detail views always show the membership's current status
(pending / active / expired / cancelled), so the ADMIN knows which memberships
are currently valid (BR-004).

### FR-008 — Activation transition (contract with SPEC-005)

The membership model exposes a transition from `pending` to `active` that is
invoked only when the first cuota of the membership is confirmed as paid
(BR-006, AM-05). The transition must enforce its business rules (only from
`pending`, only while within the validity period). The payment / cuota mechanics
that invoke this transition are implemented by SPEC-005; this Specification
defines the rule and the contract, not the payment recording UI.

---

## 5. Business Rules

### BR-001 — Membership definition

A Membership is a client's enrollment in a Plan for a specific period (confirmed
decision C-05). Plan, Membership and Payment remain separate, persistent
concepts (C-07, ARCHITECTURE §14): creating, editing, renewing or cancelling a
membership never creates, modifies or deletes a Plan or Payment record.

### BR-002 — Membership references one client and one plan

A membership belongs to exactly one client and exactly one plan; both references
are mandatory at creation (FR-001). A membership is never created without both.

### BR-003 — Fixed-duration period from a start date

A membership covers a period defined by a start date and a duration in days
(D-03 Recommended option 1, AM-02). The end date is computed as
`end_date = start_date + duration_days - 1` (the membership is valid for
`duration_days` calendar days, inclusive; AM-07). The duration must be a
positive integer (ERR-003).

### BR-004 — State machine

A membership has exactly four states: `pending`, `active`, `expired`,
`cancelled` (D-04 Recommended option 2, AM-03). No other state exists in the MVP;
in particular, `frozen`/suspended (pausa) is NOT included (D-04 option 3
deferred; §12).

### BR-005 — Creation state

A new membership is created with status `pending` (awaiting the first cuota
payment) (D-04, AM-03). A membership is never created directly as `active`.

### BR-006 — Activation requires a confirmed first cuota payment

The `pending → active` transition occurs only when the first cuota of the
membership is confirmed as paid (D-16 Recommended option 1, AM-05). The
transition is triggered by SPEC-005 when it confirms the payment; it is never
performed manually by the ADMIN without a confirmed payment (FR-008). Once
`active`, the membership grants the access / booking rights defined by D-05
(SPEC-007 / SPEC-008), not by this Specification.

### BR-007 — Expiry

A membership whose end date has passed is never considered active or usable; it
is `expired` (AM-10). The `expired` state applies to both `pending` (never paid)
and `active` memberships whose period ended. Whether the `expired` status is
materialized by a scheduled job or computed on read is an Architect decision;
the business rule is that no membership is reported `active` after its end date.
`expired` is terminal in the MVP (BR-009).

### BR-008 — Manual cancellation

An ADMIN can cancel a membership in the `pending` or `active` state (FR-006).
Cancellation is terminal (BR-009). A cancelled membership is not considered
active even if its end date has not passed.

### BR-009 — Terminal states

`expired` and `cancelled` are terminal in the MVP: no reactivation, extension or
reopening operation exists (AM-10). Recovery from an expired or cancelled
membership happens by creating a new membership (renewal for `expired` active /
expired memberships per FR-005; see ERR-005 for `pending`/`cancelled`).

### BR-010 — Multiple memberships per client

A client may hold more than one membership at the same time, including several
`active` memberships (D-06 Recommended option 2, AM-04; confirmed C-08). Each
membership is independent: its own plan, period, status and cuotas (SPEC-005).
There is NO restriction preventing overlapping or concurrent memberships. Which
membership, if any, grants access / booking rights is defined by the access rule
D-05 (SPEC-007 / SPEC-008), not by this Specification.

### BR-011 — Manual renewal creates a new record

Renewal is a manual operation (D-03 Recommended option 1, AM-02) that creates a
NEW membership record for the same client and plan (FR-005, AM-08). Renewal
never modifies the original membership record (its period, status or state
history remain untouched).

### BR-012 — Inactive plans cannot be used

A membership cannot be created against an inactive plan (SPEC-003 BR-005
"inactive plan is no longer offered for new sales", consumed here; AM-09), and a
renewal cannot create a new membership against an inactive plan (AM-09). Only
`active` plans can be selected at membership creation and renewal.

### BR-013 — Plan edits and deactivation do not affect existing memberships

Editing a plan (e.g., changing its price) or deactivating it does not modify,
cancel or change the period or status of any existing membership (AM-09,
resolution of SPEC-003 OQ-06). The amount charged for a membership period is
determined when the corresponding cuota is generated by SPEC-005 (typically at
membership creation for the first period), so a later plan price change does not
alter already-generated cuotas or existing membership periods.

### BR-014 — No hard deletion of membership records

Membership records are never hard-deleted; historical enrollment data is
preserved (AGENTS.md §12; same pattern as SPEC-001 BR-007 / SPEC-002 BR-006 /
SPEC-003 BR-004; AM-06). No delete operation is provided.

### BR-015 — Membership management is ADMIN-only

Only ADMIN can create, list, search, view, renew and cancel memberships (AM-06).
TRAINER and CLIENT cannot.

### BR-016 — Access-grant boundary

This Specification defines what a membership IS and which state it is in; it does
NOT define what an active membership GRANTS (gym access, booking rights, grace
period). Those grants are decision D-05, a gate of SPEC-007 (Bookings) and
SPEC-008 (Attendance), and are deliberately out of scope here (§12, OQ-01).

---

## 6. Main Flow

1. An authenticated ADMIN opens the Memberships section of the admin panel
   (FR-001).
2. ADMIN creates a membership: selects an existing client (SPEC-002), an active
   plan (SPEC-003, BR-012), a start date, and a duration in days (FR-001).
3. The system validates: required fields present (ERR-002), client and plan exist
   (ERR-007), plan active (ERR-001), duration positive (ERR-003).
4. The system computes the end date (BR-003) and persists the membership with
   status `pending` (BR-005). The record appears in the membership list with its
   status shown (FR-002, FR-007).
5. Per the SPEC-005 contract (FR-008): when the first cuota of the membership is
   confirmed paid, the membership transitions `pending → active` (BR-006).
6. While `active`, the membership's validity is bounded by its period (BR-003);
   when the end date passes, the membership is `expired` and is never reported
   `active` again (BR-007).
7. ADMIN can renew an active/expired membership, creating a new `pending`
   membership record (FR-005, BR-011), or cancel a pending/active membership
   (FR-006, BR-008).
8. ADMIN can list, search and view memberships, including a client's full
   membership history (FR-002, FR-003, FR-004).

---

## 7. Alternative Flows

### AF-001 — Activation after the first cuota payment

A membership created as `pending` becomes `active` when SPEC-005 confirms the
first cuota as paid (FR-008, BR-006). The ADMIN does not activate memberships
manually. Until SPEC-005 is implemented, created memberships remain `pending`;
this Specification defines the state machine and the activation contract, not
the payment recording (see §13 Dependencies).

### AF-002 — Renewal while the current membership is still active

ADMIN renews an `active` membership before its end date (FR-005). The renewal
creates a new `pending` membership whose period typically starts after the
current one ends; during the transition window both records may exist and be
valid, which is permitted (BR-010, D-06). The original membership is unchanged
(BR-011).

### AF-003 — Renewal after expiry

ADMIN renews an `expired` membership (FR-005). The expired record remains
terminal (BR-009); the new membership is a fresh record with a new period and
status `pending` (BR-005, BR-011).

### AF-004 — Cancelling a pending membership

ADMIN cancels a `pending` membership (e.g., the client never paid the first
cuota) (FR-006). The membership becomes `cancelled` (terminal); it is never
reported `active` (BR-008, BR-009).

### AF-005 — Plan edited or deactivated after memberships exist

A plan used by existing memberships is edited or deactivated (SPEC-003 FR-004 /
FR-005). Existing memberships remain unchanged: same plan reference, period and
status (BR-013, AM-09). New memberships and renewals against the now inactive
plan are rejected (BR-012). The amount of any already-generated cuota is
unaffected (BR-013; SPEC-005).

### AF-006 — Membership expires while the client keeps attending

A membership's end date passes while the client still attends the gym
(analyst-pass-001 edge case E-01). The membership becomes `expired` (BR-007).
Whether the client is then denied gym access or booking rights is the access
rule D-05, enforced by SPEC-007 / SPEC-008; this Specification only guarantees
the membership is no longer reported `active` (BR-016).

---

## 8. Error Cases

### ERR-001 — Inactive plan

Condition: creating or renewing a membership with a plan whose status is
`inactive`.

Expected behavior: rejected with a validation error; only `active` plans can be
selected (BR-012, AM-09).

### ERR-002 — Missing required fields

Condition: creating/renewing a membership without a client, a plan, a start date
or a duration.

Expected behavior: rejected with a validation error (FR-001, FR-005, BR-002).

### ERR-003 — Invalid duration

Condition: the duration is zero, negative or not an integer number of days.

Expected behavior: rejected with a validation error (BR-003).

### ERR-004 — Cancelling a terminal membership

Condition: ADMIN attempts to cancel a membership whose status is `expired` or
`cancelled`.

Expected behavior: rejected; only `pending` and `active` memberships can be
cancelled (BR-008, BR-009).

### ERR-005 — Renewing a pending or cancelled membership

Condition: ADMIN attempts to renew a membership whose status is `pending` or
`cancelled`.

Expected behavior: rejected; renewal is available only for `active` and
`expired` memberships (FR-005, AM-08, BR-009).

### ERR-006 — Unauthorized access

Condition: a TRAINER or CLIENT attempts to create, list, search, view, renew or
cancel memberships.

Expected behavior: access denied (403 or hidden from navigation) (BR-015, AM-06).

### ERR-007 — Nonexistent client or plan

Condition: the selected client or plan does not exist (e.g., stale reference).

Expected behavior: rejected with a validation error; the membership references an
existing client and an existing plan (BR-002).

---

## 9. Authorization

| Operation | Anonymous | ADMIN | TRAINER | CLIENT |
| --- | --- | --- | --- | --- |
| Create membership | Denied | Allowed (BR-015) | Denied | Denied |
| List / search memberships | Denied | Allowed (BR-015) | Denied | Denied |
| View membership detail | Denied | Allowed (BR-015) | Denied | Denied |
| View a client's membership history | Denied | Allowed (BR-015) | Denied | Denied |
| Renew membership | Denied | Allowed (BR-015) | Denied | Denied |
| Cancel membership | Denied | Allowed (BR-015) | Denied | Denied |
| Trigger the pending → active transition | Denied | Denied (only via confirmed cuota payment, FR-008/BR-006) | Denied | Denied |
| Access another client's membership data | Denied | Per feature rules (later specs) | Per feature rules (later specs) | Denied always (BR-016, C-13) |

Notes:

- A User holding several roles receives the union of the column permissions
  (SPEC-001 BR-002); an ADMIN who is also CLIENT can manage memberships in the
  admin panel.
- The `pending → active` transition is NOT an ADMIN UI operation: it is invoked
  by the SPEC-005 payment-confirmation path (FR-008, BR-006, AM-05).
- Authorization is enforced server-side; frontend-only restrictions are never
  sufficient (AGENTS.md §17).
- TRAINER read access to memberships is deferred (OQ-06); client self-service
  access to their own memberships is SPEC-013 (client portal).

---

## 10. Data Changes

This Specification describes the information that must exist; the exact
persistence schema is defined by the Architect.

Created:

- Membership records:
  - `client_id` — foreign key to `clients.id`, required (BR-002).
  - `plan_id` — foreign key to `plans.id`, required (BR-002).
  - `start_date` — required (BR-003).
  - `end_date` — required, computed as `start_date + duration_days - 1`
    (BR-003, AM-07).
  - `duration_days` — required positive integer (BR-003); stored so the period
    length is explicit even after renewal history accumulates.
  - `status` — one of `pending` / `active` / `expired` / `cancelled`, default
    `pending` (BR-004, BR-005). Storage representation (string column vs. DB
    enum) is an Architect decision; the business rule is the four-state machine.
  - `created_at` / `updated_at` timestamps.
- No monetary column on memberships: amounts (price, enrollment fee) belong to
  cuotas and payments, which are created by SPEC-005 using the ADR-003
  `decimal(10,2)` / single-implicit-currency convention. The membership does not
  snapshot the plan price (BR-013, AM-09).

Modified:

- Membership `status` on transitions: `pending → active` (FR-008, BR-006),
  `pending`/`active → expired` (BR-007), `pending`/`active → cancelled`
  (BR-008). Whether the `expired` transition is persisted by a scheduled job or
  computed on read is an Architect decision (BR-007).
- No other membership field is modified by renewal, cancellation or plan edits
  (BR-011, BR-013).

Deleted:

- No hard deletion of membership records in the MVP (BR-014); no delete
  operation.

---

## 11. Acceptance Criteria

- [ ] AC-1: ADMIN can create a membership selecting an existing client and an
  `active` plan, with a start date and duration; the record is persisted with
  status `pending` and a computed end date, and appears in the membership list
  (FR-001, FR-002, BR-003, BR-005).
- [ ] AC-2: Creating a membership against an `inactive` plan is rejected with a
  validation error (ERR-001, BR-012).
- [ ] AC-3: Creating a membership with a zero, negative or non-integer duration
  is rejected (ERR-003, BR-003).
- [ ] AC-4: Creating a membership with a missing client, plan, start date or
  duration is rejected (ERR-002, BR-002).
- [ ] AC-5: ADMIN can list and search memberships by client (name/DNI), plan,
  status and period dates (FR-002).
- [ ] AC-6: ADMIN can view a membership's full detail including client, plan,
  period and status (FR-003, FR-007).
- [ ] AC-7: ADMIN can view a client's membership history, including past states,
  in chronological order (FR-004, C-08).
- [ ] AC-8: Renewing an `active` or `expired` membership creates a NEW membership
  record for the same client and plan with status `pending`; the original record
  is not modified (FR-005, BR-011).
- [ ] AC-9: Renewing a `pending` or `cancelled` membership is rejected (ERR-005,
  AM-08).
- [ ] AC-10: ADMIN can cancel a `pending` or `active` membership; it becomes
  `cancelled` and is never reported `active` (FR-006, BR-008).
- [ ] AC-11: Cancelling an `expired` or `cancelled` membership is rejected
  (ERR-004, BR-009).
- [ ] AC-12: A membership whose end date has passed is never reported `active`
  (BR-007, AM-10); the mechanism (scheduled job vs. computed on read) is
  verified per the Architect's design.
- [ ] AC-13: A client can hold several concurrent memberships, including multiple
  `active` ones; the system imposes no restriction (BR-010, D-06, C-08).
- [ ] AC-14: Editing or deactivating a plan does not modify the period, status
  or plan reference of any existing membership; a deactivated plan cannot be
  used for new memberships or renewals (BR-012, BR-013, AM-09).
- [ ] AC-15: The membership model exposes the `pending → active` transition
  (FR-008) and enforces: it can run only from `pending`, only while the end date
  has not passed, and it is not callable as an ADMIN UI action (BR-006, AM-05).
- [ ] AC-16: A TRAINER or CLIENT cannot create, list, search, view, renew or
  cancel memberships (403) (ERR-006, BR-015).
- [ ] AC-17: No delete operation exists for memberships; a created membership
  record persists (BR-014).
- [ ] AC-18: Creating, renewing or cancelling a membership never creates,
  modifies or deletes a Plan or Payment record (BR-001, C-07).

---

## 12. Out of Scope

The following are explicitly NOT part of this Specification:

- Cuota generation per membership period, cuota amounts, cuota edit, payment
  recording, payment allocation (partial / overpayment / multiple payments per
  cuota), payment statuses, the charging of the plan's enrollment fee
  (matrícula), and refunds — all deferred to SPEC-005 (Payments & Cuotas;
  gates D-02, D-15, D-16). This Specification only defines the activation
  contract (FR-008, BR-006).
- The access rule: what an active membership grants (gym access, booking rights)
  and any grace period after expiry — decision D-05, gates of SPEC-007
  (Bookings) and SPEC-008 (Attendance). See BR-016, OQ-01.
- Freeze / on-hold (pausa) and suspended states — D-04 option 3, deferred
  (§5.5 "Freeze / on-hold ... not mentioned at all").
- Automatic renewal and recurring / automatic billing — D-03 option 3, deferred
  (product-definition open question "billing automatic vs manual"; resolved as
  manual by AM-02).
- Client portal display of a client's own memberships — SPEC-013.
- Public registration and online membership purchase — SPEC-012 / SPEC-014.
- Plan versioning / price history — SPEC-003 §12; the effect of plan edits on
  existing memberships (OQ-06) is resolved only at the membership level here
  (BR-013, AM-09).
- Plan-level period/duration attribute — already PO-confirmed absent (SPEC-003
  AP-06); the period is a Membership attribute (BR-003).
- Plan categories / session packages — D-14 option 3, deferred.
- TRAINER read access to memberships (OQ-06) and TRAINER–client assignments.
- Reactivation or extension of `expired` / `cancelled` memberships (BR-009).
- Membership statuses beyond the four-state machine (BR-004).

---

## 13. Dependencies

- **SPEC-001 (Authentication & Roles) — COMPLETED** (`docs/sdd/state.yaml`):
  authentication and session foundation; fixed role catalog with `Role::ADMIN`;
  admin panel access (ADMIN | TRAINER); `User::hasRole` / `User::hasAnyRole`
  helpers; policy pattern (`UserPolicy`); no hard deletion. Membership
  management is implemented inside the admin panel and follows the same
  conventions.
- **SPEC-002 (Client Management) — COMPLETED** (`docs/sdd/state.yaml`): client
  records are the required subject of a membership (`client_id`, BR-002);
  DNI unique; ADMIN-only client management; Client ↔ User link is irrelevant
  to membership validity (a membership requires a Client record, not an
  account).
- **SPEC-003 (Plan Management) — COMPLETED** (`docs/sdd/state.yaml`): plan
  records are the required object of a membership (`plan_id`, BR-002); plan
  `is_active` gates membership creation and renewal (BR-012); ADR-003 pricing
  convention (decimal(10,2), single implicit currency, no plan-level period)
  is consumed by SPEC-005 when it generates cuotas; SPEC-003 OQ-06 (plan
  edit/deactivation effect on memberships) is resolved here (BR-013, AM-09).
- **SPEC-005 (Payments & Cuotas) — FUTURE, NOT required for this
  Specification's implementation**: implements cuota generation and payment
  confirmation, and invokes the `pending → active` activation transition
  defined here (FR-008, BR-006, AM-05). Until SPEC-005 exists, memberships
  created by this Specification remain `pending`; the state machine and
  activation contract are fully defined now (see AF-001).
- **SPEC-007 (Bookings) / SPEC-008 (Attendance) — FUTURE**: consume the
  membership status semantics defined here under the access rule D-05
  (BR-016, OQ-01). Not required for this Specification.
- Confirmed decisions used: C-01 (roles, multi-role), C-05 (membership
  definition), C-07 (Plan / Membership / Payment separate), C-08 (multiple
  membership records over time), C-13 (client isolation), C-15 (presentation
  contexts).
- Requirements analysis: `analyst-pass-001.md` §5.5 (Memberships), §5.6
  (Cuotas), D-02, D-03, D-04, D-05, D-06, D-16, tensions T-02/T-03, edge cases
  E-01/E-02/E-03/E-04.
- Flagged assumptions AM-01 to AM-10 require Product Owner confirmation before
  Implementation (see §14.1).

---

## 14. Open Questions

### 14.1 Assumed decisions requiring PO confirmation

These are flagged assumptions. They are needed to make the Specification
implementable, but they are NOT confirmed business rules. The prefix AM
distinguishes this Specification's assumptions from SPEC-001 (A-xx),
SPEC-002 (AD-xx) and SPEC-003 (AP-xx).

| ID | Assumption | Source | Blocks / Affects |
| --- | --- | --- | --- |
| AM-01 | Cuota model: the system auto-generates a cuota per membership period, and staff may edit the amount of a pending cuota (D-02 option 2). The cuota entity, generation and allocation mechanics are implemented by SPEC-005; this Specification only needs the consequence that a membership is created `pending` awaiting its first cuota. | Borrowed from analyst-pass-001 D-02 Recommended (option 2) | FR-001, BR-005, BR-006, §12, OQ-04 |
| AM-02 | Period and renewal model: fixed duration from start date (days), manual renewal; no automatic billing (D-03 option 1). Renewal = new membership record. | Borrowed from analyst-pass-001 D-03 Recommended (option 1) | BR-003, BR-011, FR-005, §12 |
| AM-03 | State machine: pending (awaiting first payment) / active / expired / cancelled (D-04 option 2). Freeze (pausa) and suspended are NOT in the MVP. | Borrowed from analyst-pass-001 D-04 Recommended (option 2) | BR-004, BR-005, BR-007, BR-008, §12 |
| AM-04 | Multiple active memberships allowed (D-06 option 2): a client may hold several concurrent memberships, each independent; the access rule (D-05) decides which membership governs access. | Borrowed from analyst-pass-001 D-06 Recommended (option 2) | BR-010, AC-13 |
| AM-05 | Activation rule: a membership activates only after a confirmed first cuota payment (D-16 option 1). The transition is triggered by SPEC-005, never manually by ADMIN. | Borrowed from analyst-pass-001 D-16 Recommended (option 1); gate of SPEC-005, needed here to define BR-006 | BR-006, FR-008, §9, AC-15 |
| AM-06 | Membership management is ADMIN-only, and membership records are never hard-deleted (no delete operation). | Not documented — analyst necessity, consistent with SPEC-002 AD-03 / SPEC-003 AP-03 and the preservation pattern (SPEC-001 BR-007 / SPEC-002 BR-006 / SPEC-003 BR-004) | BR-014, BR-015, ERR-006, AC-16, AC-17 |
| AM-07 | Period arithmetic: `end_date = start_date + duration_days - 1` (the membership is valid for `duration_days` calendar days, inclusive); duration is a positive integer. | Not documented — analyst necessity | BR-003, ERR-003, AC-1, AC-3 |
| AM-08 | Renewal details: renewal is available only for `active` and `expired` memberships; it pre-fills the same client and plan and creates a new `pending` record; the new start date defaults to the day after the previous end date (ADMIN may change it). | Not documented — analyst necessity; D-03 option 1 says "manual renewal" without mechanics | FR-005, BR-011, ERR-005, AC-8, AC-9, OQ-05 |
| AM-09 | OQ-06 resolution: editing or deactivating a plan does not modify existing memberships; an inactive plan cannot be used for new memberships or renewals; the amount charged for a membership period is fixed when the cuota is generated by SPEC-005 (so later price changes do not alter existing membership periods or already-generated cuotas). | Resolution of SPEC-003 OQ-06; not documented — analyst necessity, consistent with SPEC-003 BR-005 and ADR-003 (no price history) | BR-012, BR-013, ERR-001, AC-14, OQ-02 |
| AM-10 | `expired` and `cancelled` are terminal: no reactivation, extension or reopening; a late payment cannot reactivate an expired membership (edge case E-02 default); the recovery path is a new (renewed) membership. | Not documented — analyst necessity; edge case E-02 pending PO | BR-007, BR-009, AC-12, OQ-03 |

### 14.2 Open questions to be answered before Implementation (or at latest before Review)

- OQ-01 (decision D-05 — access rule): What does an active membership grant in
  the MVP — gym access, booking rights, or both — and is there a grace period
  after expiry? This is a gate of SPEC-007 / SPEC-008, NOT decided by this
  Specification (BR-016). It does not change the membership record or state
  machine defined here; it only changes how SPEC-007/008 consume the status.
- OQ-02 (AM-09 sub-question): For a membership whose first cuota has not yet
  been generated (e.g., renewal scheduled later), should the cuota amount use
  the plan price at generation time or a snapshot taken at membership creation?
  This Specification assumes generation-time (AM-09); the final rule is
  implemented by SPEC-005.
- OQ-03 (edge case E-02): A client pays late for an expired membership. Should a
  late payment be able to activate an `expired` (never-paid `pending`)
  membership retroactively, or must the client renew (new membership)? This
  Specification assumes the latter (AM-10); the payment mechanics are SPEC-005.
- OQ-04 (edge case E-03): A client prepays several periods in advance. Should
  this create one membership with a longer duration, several membership
  records, or several cuotas for future periods of one membership? This
  Specification leaves the multi-cuota allocation to SPEC-005; the number of
  membership records is a PO decision.
- OQ-05 (AM-08 sub-questions): For renewal, what should the default new start
  date be (day after previous end date vs. today), and should the duration
  default to the previous membership's duration? Should renewal be allowed for
  `pending` memberships (this Specification says no — ERR-005)?
- OQ-06: Should TRAINER be able to VIEW memberships (read-only) even though
  management is ADMIN-only? (Consistent with the analogous SPEC-003 OQ-02.)
- OQ-07: Are additional membership fields required (e.g., a membership number,
  an "enrolled since" date, internal notes, cancellation reason)?
- OQ-08: Is a calendar-month duration option needed ("1 month" as opposed to
  "30 days")? D-03 option 1 gives the 30-day example; this Specification models
  duration in days only (AM-07).
- OQ-09: Must the plan's one-time enrollment fee (matrícula, SPEC-003) be
  charged at membership creation? This is a SPEC-005 cuota decision; it does
  not affect the membership record defined here.
- OQ-10: Should memberships created before SPEC-005 (while memberships stay
  `pending`) expose any interim indication to the ADMIN that activation is
  pending implementation? (Operational note; no business rule implied.)

---

## 15. Related Documents

- Product documentation: `docs/product/product-definition-v0.1.md`
- Domain documentation: `docs/domain/domain-model-v0.1.md`
- Requirements analysis: `docs/requirements/analyst-pass-001.md` (§5.5
  Memberships, §5.6 Cuotas, C-05/C-07/C-08/C-17, T-02/T-03, E-01/E-02/E-03/E-04,
  D-02, D-03, D-04, D-05, D-06, D-16)
- Specifications: `docs/specs/SPEC-001.md`, `docs/specs/SPEC-002.md`,
  `docs/specs/SPEC-003.md`
- Architecture documentation: `docs/architecture/SPEC-001.md`,
  `docs/architecture/SPEC-002.md`, `docs/architecture/SPEC-003.md`,
  `ARCHITECTURE.md` (§13 Payments, §14 Memberships, §20 simplest correct
  architecture)
- Architecture decisions: `docs/adr/ADR-001.md`, `docs/adr/ADR-002.md`,
  `docs/adr/ADR-003.md`
- Development rules: `AGENTS.md`
- Workflow state: `docs/sdd/state.yaml`
