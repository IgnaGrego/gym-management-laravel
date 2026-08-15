# SPEC-005 — Payments & Cuotas

## Status

Ready (analysis complete).

This is the fifth Specification of the MVP. It depends on SPEC-001 (Authentication
& Roles), SPEC-002 (Client Management), SPEC-003 (Plan Management) and SPEC-004
(Membership Management), all COMPLETED and implemented in the repository
(`docs/sdd/state.yaml`).

**NIGHT MODE notice:** this Specification is produced under the PO pre-approval
("modo nocturno", `docs/sdd/state.yaml` `project.po_decisions`). The gates are
pre-approved as follows:

- **D-02 — option 2** (cuota model): the system auto-generates cuotas per
  membership period, and staff may edit the amount of a pending cuota.
- **D-15 — option 1** (payment methods): cash (efectivo) and bank transfer
  (transferencia), recorded manually by ADMIN/TRAINER. **SPEC-014 (Mercado
  Pago) is EXCLUDED from the backlog by PO decision**: this Specification
  defines NO Mercado Pago integration, no online checkout, no webhooks, no
  credentials and no payment-provider configuration of any kind.
- **D-16 — option 1** (payment lifecycle): statuses `pending` / `confirmed` /
  `failed`; a membership activates only after a confirmed payment; refunds are
  handled manually.

**Resolved decisions (NC-01 to NC-04):** the four previously-blocking business
decisions (§14.1) were resolved by the Product Owner on 2026-08-15 (recorded in
`docs/sdd/state.yaml`). This Specification is now fully implementable: payment
allocation is full-payment-only (NC-01), prepayment is not supported in the MVP
(NC-02), the enrollment fee is not charged (NC-03), and the late-payment /
cancelled-membership rules are defined (NC-04). The flagged assumptions
PY-01..PY-06 (§14.2) and the non-blocking open questions (§14.3) remain as
noted.

---

## 1. Objective

Provide payments and cuota (installment/due) management for the gym:

- the system auto-generates one cuota per membership period when a membership is
  created (including renewal, which creates a new membership record), with an
  amount defaulting to the plan's price at generation time; staff may edit the
  amount of a pending cuota (D-02 option 2);
- a cuota has a lifecycle: `pending` until it is satisfied by a confirmed
  payment, then `paid` (D-02; D-16);
- ADMIN/TRAINER register payments manually, in cash or by bank transfer only,
  and a manually registered payment is recorded directly as `confirmed` — there
  is no external payment provider in this Specification (D-15 option 1; SPEC-014
  excluded);
- when the first cuota of a membership is confirmed paid, the membership
  transitions `pending → active` — the contract defined by SPEC-004 FR-008 /
  BR-006 (D-16 option 1);
- a Payment remains a financial transaction related to a Membership (C-06),
  satisfied through the cuota; Plan, Membership, Cuota and Payment remain
  separate, persistent concepts (C-07, ARCHITECTURE §13-14).

SPEC-005 closes the "cuota" gap identified by `analyst-pass-001.md` §5.6 / T-03
(the missing link between Membership and Payment) and the payment lifecycle gap
T-04 (D-16).

---

## 2. Actors

Actors are defined by role, not by person (SPEC-001 §2). A single User may hold
one or more roles (confirmed decision C-01).

| Actor | Description |
| --- | --- |
| Anonymous visitor | No access to payments or cuotas. Payment/cuota data is never exposed on public pages. |
| ADMIN | Staff who administer the gym. Can register payments, view cuotas/payments and edit the amount of a pending cuota (D-02, D-15). |
| TRAINER | Staff who train clients. Can register payments, view cuotas/payments and edit the amount of a pending cuota (D-15 option 1: "ADMIN/TRAINER record cash/transfer"; D-02 "staff may edit"; assumption PY-01). |
| CLIENT | A gym member. Cannot register payments or manage cuotas; access to their own payment/cuota data is defined by SPEC-013 (client portal). Client isolation (C-13) always applies. |

Notes:

- A User holding ADMIN and/or TRAINER uses the admin panel; a User holding CLIENT
  uses the client portal (SPEC-001 FR-006, confirmed C-15).
- A User holding both a staff role and CLIENT is permitted by C-01; the
  mixed-role behavior is tracked as SPEC-001 OQ-04.
- There is no RECEPTIONIST role (confirmed SPEC-001 A-04); "staff" in this
  Specification means ADMIN and/or TRAINER (PY-01).

---

## 3. Preconditions

1. SPEC-001 is implemented and completed: authentication, fixed role catalog
   (ADMIN, TRAINER, CLIENT), admin panel access (ADMIN or TRAINER), role helpers
   (`hasRole` / `hasAnyRole`), `Role::ADMIN` constant, no hard deletion of User
   records (`docs/sdd/state.yaml`, ADR-001).
2. SPEC-002 is implemented and completed: client records exist, DNI unique,
   ADMIN-only client management (`docs/sdd/state.yaml`, ADR-002).
3. SPEC-003 is implemented and completed: plan records exist with `name`,
   `price`, optional `enrollment_fee` and `is_active` status; amounts use the
   ADR-003 convention `decimal(10,2)` with a single implicit currency
   (`docs/sdd/state.yaml`).
4. SPEC-004 is implemented and completed: membership records exist with
   `client_id`, `plan_id`, `start_date`, `end_date`, `duration_days` and the
   four-state machine (`pending` / `active` / `expired` / `cancelled`); the
   model exposes the `pending → active` transition (`Membership::activate()`,
   FR-008 / BR-006) that SPEC-005 triggers; the `expired` state is materialized
   by the daily command `memberships:expire` (ADR-004). The membership does NOT
   store any monetary column (SPEC-004 §10; BR-013).
5. An authenticated ADMIN or TRAINER exists and can access the admin panel
   (SPEC-001 FR-008).
6. No `cuotas` or `payments` table exists yet; the Payments & Cuotas module is
   greenfield on top of the SPEC-001..004 foundations.
7. Mercado Pago is EXCLUDED (SPEC-014 out of the backlog, PO decision): no
   provider integration, no online checkout, no webhooks, no credentials.
8. The gates D-02 (option 2), D-15 (option 1) and D-16 (option 1) are
   pre-approved (NIGHT MODE). The decisions NC-01..NC-04 (§14.1) are resolved by
   the PO (2026-08-15): full-payment-only, no prepayment, no matrícula, and the
   late-payment / cancelled-membership payment rules.

---

## 4. Functional Requirements

### FR-001 — Auto-generate cuota per membership period

When a membership is created (SPEC-004 FR-001) or renewed (SPEC-004 FR-005 —
renewal creates a NEW membership record), the system auto-generates one cuota
for that membership's single period (D-02 option 2, BR-001). The cuota's amount
defaults to the plan's `price` at generation time (SPEC-004 BR-013; BR-002 of
this Specification). The cuota is created with status `pending` (BR-003). Cuota
generation does NOT modify the plan, the membership, the client or the user
records (C-07; BR-010).

### FR-002 — Edit the amount of a pending cuota

ADMIN/TRAINER can edit the amount of a `pending` cuota (D-02 option 2). The
amount must remain a positive amount (BR-002). The amount of a cuota that is no
longer `pending` cannot be edited (BR-003, ERR-007).

### FR-003 — List, search and view cuotas

ADMIN/TRAINER can list cuotas and view a cuota's detail: membership, client,
plan, amount, status and payment history. Searching/filtering by client (name /
DNI), plan, membership, status and amount range is provided so staff can see
which cuotas are pending and which are paid.

### FR-004 — Register a manual payment

ADMIN/TRAINER register a payment against a `pending` cuota (BR-003, ERR-011).
The form records: the cuota (the payment's membership/cuota reference), the
amount received, the payment method (cash or bank transfer only — D-15 option
1), the payment date, and for bank transfers a `reference` (PY-04); optional
free-text `notes`. The amount received must equal the cuota amount — full
payment only, no partial, under- or over-payment (NC-01, BR-014, ERR-010). The
payment is persisted directly with status `confirmed` (D-16 option 1; BR-005,
PY-02). No external provider is involved: there is no checkout, no webhook and
no pending / failed intermediate state produced by the manual flow (BR-006).

### FR-005 — List, search and view payments

ADMIN/TRAINER can list payments and view a payment's detail: cuota, membership,
client, plan, amount, method, payment date, reference, notes, status and the
staff user who recorded it. Searching/filtering by client (name/DNI), membership,
method, payment date and status is provided (financial auditability).

### FR-006 — Satisfy the cuota and activate the membership

When a payment is registered and the cuota is satisfied, the cuota transitions
`pending → paid` (BR-003). A cuota is satisfied only by a single confirmed
payment whose amount equals the cuota amount — full payment only, no partial,
under- or over-payment and no multiple payments per cuota (NC-01, BR-014). If
the satisfied cuota is the first cuota of a `pending` membership, the system
invokes the SPEC-004 activation contract `Membership::activate()` (FR-008 /
BR-006 of SPEC-004), which sets the membership `active` — and only if the
membership is still `pending` and its end date has not passed (SPEC-004 AC-15).
The membership is never activated manually by staff (SPEC-004 §9).

### FR-007 — Display cuota and payment status

Cuota lists/detail always show the cuota's status (`pending` / `paid` /
`cancelled`), and payment lists/detail always show the payment's status
(`confirmed` in the manual flow), so staff know which dues are outstanding.

---

## 5. Business Rules

### BR-001 — Cuota definition

A Cuota is the fixed due amount generated for a membership period (D-02). The
system generates exactly one cuota per membership, at membership creation
(including renewal, which creates a new membership record — SPEC-004 BR-011);
the cuota's period is the membership's period (start/end dates, SPEC-004 BR-003).
A cuota belongs to exactly one membership (`membership_id`, required). A
membership carries exactly one cuota — prepayment of several periods is NOT
supported in the MVP and requires a new membership record per D-03 manual
renewal semantics (NC-02).

### BR-002 — Cuota amount

The cuota amount is a positive amount stored with the ADR-003 convention
(`decimal(10,2)`, single implicit currency). The default amount is the plan's
`price` at generation time (SPEC-004 BR-013: "the amount charged for a
membership period is determined when the corresponding cuota is generated");
later plan price edits or deactivation do NOT modify already-generated cuotas
(SPEC-004 BR-013; BR-010 of this Specification). Staff may edit the amount while
the cuota is `pending` (D-02 option 2; FR-002); the edited amount must remain a
positive amount (ERR-006). The plan's optional enrollment fee (matrícula,
SPEC-003 BR-001) is NOT charged in the MVP: the `enrollment_fee` field is not
used to generate any cuota or charge, and no matrícula cuota exists (NC-03).

### BR-003 — Cuota lifecycle

A cuota has exactly three states in this Specification: `pending`, `paid` and
`cancelled` ("pending cuota" is explicit in D-02 option 2; `paid` is reached
when the cuota is satisfied by a confirmed payment — the same wording used by
SPEC-004 BR-006 "first cuota ... confirmed as paid"; `cancelled` is the NC-04
consequence for pending cuotas of a cancelled membership). A new cuota is
created `pending` (FR-001). The `pending → paid` transition occurs only when a
single confirmed payment whose amount equals the cuota amount is registered
(FR-006; NC-01, BR-014). The `pending → cancelled` transition occurs when the
cuota's membership is cancelled: its still-pending cuotas become `cancelled`
(uncollectible, not payable) (NC-04, BR-015). No other cuota state exists in the
MVP; in particular there is no `overdue` / late state and no payment-side grace
period after the membership end date (NC-04).

### BR-004 — Payment methods

Only two payment methods exist in the MVP: `cash` (efectivo) and `transfer`
(transferencia bancaria) (D-15 option 1). No Mercado Pago, no other electronic
method, no online checkout (SPEC-014 EXCLUDED, PO decision).

### BR-005 — Manual registration records confirmed directly

A payment is registered manually by staff (ADMIN/TRAINER) and is persisted with
status `confirmed` immediately (D-16 option 1; PY-02). There is no external
provider: no payment is ever created `pending` or `failed` by a SPEC-005 flow
(FR-004). The `pending` and `failed` statuses exist only as the reserved D-16
lifecycle statuses for a future provider integration (SPEC-014, currently
excluded from the backlog); they are not writable by this Specification's
flows (BR-006).

### BR-006 — Payment status lifecycle

A Payment has exactly three statuses per D-16 option 1: `pending`, `confirmed`,
`failed`. In this Specification only `confirmed` is produced (BR-005). A
confirmed payment is immutable in the MVP: it cannot be edited, deleted or
transitioned (PY-05). Refunds are handled manually, outside the system (D-16
option 1); there is no refund/chargeback flow (see §12).

### BR-007 — Payment fields and references

A payment records: `cuota_id` (required — the payment's cuota reference; the
membership is reachable through the cuota, keeping Plan / Membership / Cuota /
Payment separate, C-07), `amount` (required, positive), `method` (required,
`cash` | `transfer`), `payment_date` (required, a valid date not in the future;
backdating is allowed — PY-03), `reference` (required when method is `transfer`;
optional for cash — PY-04), `notes` (optional free text), `status` (default
`confirmed`), and `recorded_by` (required — the staff User who recorded it;
PY-06).

### BR-008 — Membership activation after confirmed payment

A membership activates only after its first cuota is confirmed paid (D-16 option
1; SPEC-004 BR-006, AM-05). The transition is invoked by the payment-confirmation
path through `Membership::activate()`, which enforces: status is `pending` and
the end date has not passed (SPEC-004 AC-15). It is never performed manually by
staff (SPEC-004 §9). A late payment cannot activate an expired membership
(SPEC-004 AM-10, confirmed: `expired` and `cancelled` are terminal; recovery is
a new membership). Staff may record a payment against a pending cuota of a
membership that is `pending` (the first-payment activation flow), `active` or
`expired`; recording a payment never reactivates an `expired` membership
(AM-10). A `cancelled` membership's pending cuotas become `cancelled` and are
not payable (NC-04, BR-015, ERR-011).

### BR-009 — No hard deletion of cuotas or payments

Cuota and payment records are never hard-deleted; financial history is preserved
(AGENTS.md §12; same pattern as SPEC-001 BR-007 / SPEC-002 BR-006 / SPEC-003
BR-004 / SPEC-004 BR-014). No delete operation is provided (FR-005 note).

### BR-010 — Plan, Membership, Cuota and Payment remain separate

Creating, editing or deactivating a plan never creates, modifies or deletes a
cuota or payment, and registering a payment never modifies the plan or the
membership record except for the activation transition (C-07, ARCHITECTURE
§13-14; SPEC-003 BR-007, SPEC-004 BR-001/BR-013). The membership does not
snapshot prices; amounts live on cuotas/payments.

### BR-011 — Payment and cuota management is staff-only

Only ADMIN and TRAINER can register payments, view cuotas/payments and edit the
amount of a pending cuota (D-15 option 1; PY-01). CLIENT and Anonymous cannot
(§9).

### BR-012 — Cuota edit is amount-only and pending-only

The only edit operation on a cuota is changing the `amount` of a `pending`
cuota (D-02 option 2; FR-002). No other cuota field is editable in the MVP; in
particular the membership reference and the status are never edited directly.

### BR-013 — Activation is the only membership-side effect

The only membership record change caused by this module is the
`pending → active` transition (FR-006, BR-008). No other membership field is
modified by cuota or payment operations (SPEC-004 BR-011/BR-013).

### BR-014 — Full-payment allocation

Each cuota is covered by exactly ONE confirmed payment whose amount equals the
cuota amount (NC-01). Partial/underpayment, overpayment and multiple payments
per cuota are not supported: a payment amount that differs from the cuota amount
is rejected (ERR-010), and a cuota that is no longer `pending` cannot receive a
further payment (ERR-011). The cuota transitions `pending → paid` only when that
single matching confirmed payment is registered (BR-003, FR-006).

### BR-015 — Cuota cancellation on membership cancellation

When a membership is cancelled (SPEC-004 FR-006 / BR-008), all of its
still-`pending` cuotas transition to `cancelled` (uncollectible) and are no
longer payable (NC-04). This is triggered by the SPEC-004 membership-cancellation
path and is the only way a cuota reaches `cancelled`; a `cancelled` cuota cannot
be paid and cannot return to `pending` (BR-003).

---

## 6. Main Flow

1. An authenticated staff user (ADMIN/TRAINER) works in the Payments & Cuotas
   section of the admin panel (FR-003, FR-005).
2. **Cuota generation (FR-001):** a membership is created or renewed (SPEC-004).
   The system generates one cuota for the membership with amount = the plan's
   `price` at that time and status `pending` (BR-001, BR-002, BR-003). The
   membership remains `pending` awaiting its first cuota payment (SPEC-004
   BR-005).
3. **Cuota edit (FR-002, optional):** staff may edit the amount of the pending
   cuota (e.g., a negotiated price); the edited amount must be positive
   (BR-002, ERR-006).
4. **Payment registration (FR-004):** staff open a pending cuota and register a
   payment: cuota (pre-selected), amount (must equal the cuota amount — NC-01),
   method (`cash` | `transfer`), payment date, and for transfers a `reference`
   (BR-007, ERR-002, ERR-003, ERR-004, ERR-005, ERR-010). The system validates
   and persists the payment with status `confirmed` (BR-005).
5. **Cuota satisfaction (FR-006):** the single matching confirmed payment
   satisfies the cuota (full payment only — NC-01, BR-014) and the cuota
   becomes `paid` (BR-003).
6. **Activation (FR-006, BR-008):** if the paid cuota is the first cuota of a
   `pending` membership within its period, the system calls
   `Membership::activate()` and the membership becomes `active` (SPEC-004
   FR-008 / BR-006). If the membership is no longer `pending` or its end date
   has passed, activation is rejected by the model (SPEC-004 AC-15; AM-10).
7. **Viewing (FR-003, FR-005, FR-007):** staff can list/search/view cuotas and
   payments, including the payment history of a cuota and of a membership, and
   always see the current statuses.

---

## 7. Alternative Flows

### AF-001 — Bank transfer with reference

Staff register a bank transfer payment. The `reference` field is required
(ERR-005, PY-04) and captures the transfer identifier so the payment can be
traced. Notes are optional.

### AF-002 — Cuota amount edited before payment

Staff change the amount of a pending cuota (FR-002) after generation, before any
payment exists (e.g., a discount agreed verbally). The new amount becomes the
reference amount of the cuota; the later single payment must equal the updated
amount (NC-01, BR-014).

### AF-003 — Renewal generates a new cuota

A membership is renewed (SPEC-004 FR-005), which creates a NEW membership
record; the system generates a new pending cuota for the new membership's period
(BR-001). The original membership and its cuota are unchanged (SPEC-004 BR-011).

### AF-004 — Plan price changes or plan deactivation after cuota generation

A plan's price is edited or the plan is deactivated (SPEC-003) after a cuota was
generated. The generated cuota's amount is unaffected (BR-010, SPEC-004 BR-013);
only cuotas generated later use the new price. This resolves the SPEC-004 OQ-02
default (generation-time amount) as already assumed in SPEC-004 BR-013/AM-09.

### AF-005 — Activation rejected (late payment on an expired membership)

A payment is registered against the pending cuota of a membership whose end date
has passed (the membership is `expired`). The SPEC-004 activation contract
rejects the transition (`Membership::activate()` throws: not `pending`, or end
date passed — SPEC-004 AC-15/AM-10). The payment is still recorded `confirmed`
and the cuota becomes `paid`, but the expired membership is never reactivated
(AM-10, NC-04). Recovery is a new membership. (For a `cancelled` membership, the
pending cuota is already `cancelled` and not payable — BR-015, ERR-011.)

### AF-006 — Payment for a cuota of an already-active membership

With the full-payment (NC-01) and no-prepayment (NC-02) rules, a membership
carries exactly one cuota, and that cuota is `paid` as soon as the membership
activates. Therefore an `active` membership has no payable `pending` cuota; any
attempt to register a further payment against its (already `paid`) cuota is
rejected (ERR-011). Paying a future period requires a new membership record per
the D-03 manual renewal semantics (NC-02), not a payment on the current
membership.

---

## 8. Error Cases

### ERR-001 — Nonexistent cuota

Condition: registering a payment against a cuota id that does not exist (e.g.,
stale reference).

Expected behavior: rejected with a validation error; a payment always references
an existing cuota (BR-007).

### ERR-002 — Invalid payment amount

Condition: payment amount is zero, negative or not a valid amount.

Expected behavior: rejected with a validation error (BR-007).

### ERR-003 — Invalid payment method

Condition: payment method is not `cash` or `transfer` (e.g., a removed MP option,
or a typo).

Expected behavior: rejected with a validation error; only the two MVP methods
are accepted (BR-004).

### ERR-004 — Invalid payment date

Condition: payment date is missing, not a valid date, or in the future.

Expected behavior: rejected with a validation error (BR-007, PY-03).

### ERR-005 — Missing transfer reference

Condition: method is `transfer` and no `reference` is supplied.

Expected behavior: rejected with a validation error; a reference is required for
bank transfers (PY-04).

### ERR-006 — Invalid cuota amount edit

Condition: editing a cuota amount to zero, negative or not a valid amount.

Expected behavior: rejected with a validation error; the cuota amount must be
positive (BR-002).

### ERR-007 — Editing a non-pending cuota

Condition: attempting to edit the amount of a cuota that is not `pending`
(e.g., already `paid` or `cancelled`).

Expected behavior: rejected; only pending cuotas are editable (D-02 option 2,
BR-003, BR-012).

### ERR-008 — Unauthorized access

Condition: a CLIENT or Anonymous attempts to register payments, view cuotas/
payments or edit cuota amounts.

Expected behavior: access denied (403 or hidden from navigation) (BR-011, §9).

### ERR-009 — Activation rejection surfaced

Condition: the payment confirmation path attempts `Membership::activate()` on a
membership that is not `pending` or whose end date has passed (e.g., recording a
payment against the pending cuota of an `expired` membership).

Expected behavior: the activation transition fails per the SPEC-004 contract
(SPEC-004 AC-15) and the failure is surfaced to the staff without corrupting the
payment/cuota data. The payment is recorded `confirmed` and the cuota becomes
`paid`, but the expired membership is never reactivated (AM-10; NC-04).

### ERR-010 — Payment amount does not match the cuota amount

Condition: the registered payment amount differs from the cuota amount (partial,
under- or over-payment).

Expected behavior: rejected with a validation error; a cuota is satisfied only
by a single payment whose amount equals the cuota amount (NC-01, BR-014).

### ERR-011 — Payment against a non-payable cuota

Condition: registering a payment against a cuota that is not `pending` (already
`paid`, or `cancelled` because its membership was cancelled).

Expected behavior: rejected with a validation error; only `pending` cuotas of a
`pending` / `active` / `expired` membership are payable (BR-003, BR-008, BR-014,
BR-015; NC-01, NC-04).

---

## 9. Authorization

| Operation | Anonymous | ADMIN | TRAINER | CLIENT |
| --- | --- | --- | --- | --- |
| View cuotas / payments (list, search, detail) | Denied | Allowed (BR-011) | Allowed (BR-011) | Denied |
| Register a payment (cash / transfer) | Denied | Allowed (D-15 op1, BR-011) | Allowed (D-15 op1, BR-011) | Denied |
| Edit a pending cuota amount | Denied | Allowed (D-02 op2, BR-011) | Allowed (D-02 op2, BR-011) | Denied |
| Trigger the pending → active membership transition | Denied | Denied (only via confirmed cuota payment, FR-006 / BR-008 / SPEC-004 FR-008) | Denied | Denied |
| Access another client's payment data | Denied | Per feature rules (later specs) | Per feature rules (later specs) | Denied always (C-13) |

Notes:

- A User holding several roles receives the union of the column permissions
  (SPEC-001 BR-002); an ADMIN who is also CLIENT can manage payments in the
  admin panel.
- The `pending → active` transition is NOT an ADMIN/TRAINER UI operation: it is
  invoked by the payment-confirmation path via `Membership::activate()` (SPEC-004
  §9, BR-006).
- Authorization is enforced server-side; frontend-only restrictions are never
  sufficient (AGENTS.md §17).
- CLIENT-facing display of the client's own payments/cuotas belongs to SPEC-013
  (client portal); it is out of scope here (§12).

---

## 10. Data Changes

This Specification describes the information that must exist; the exact
persistence schema is defined by the Architect.

Created:

- **Cuota records** (`cuotas`):
  - `membership_id` — foreign key to `memberships.id`, required (BR-001).
  - `amount` — `decimal(10,2)`, NOT NULL, positive (BR-002; ADR-003
    convention).
  - `status` — one of `pending` / `paid` / `cancelled`, default `pending`
    (BR-003). Storage representation (string column vs. DB enum) is an Architect
    decision, consistent with SPEC-004 §10 (string + model constants preferred).
  - `paid_at` — nullable timestamp, set when the cuota becomes `paid`
    (FR-006/FR-007 display; Architect may choose to derive it from the
    satisfying payment instead — implementation detail).
  - `created_at` / `updated_at` timestamps.
  - No `due_date` column: the period semantics (start/end) belong to the
    membership (SPEC-004 BR-003); there is no separate cuota due date and no
    payment-side grace period after the membership end date (NC-04).
  - Exactly one cuota per membership (NC-02): a `membership_id` appears on at
    most one cuota. Whether this is enforced by a DB unique constraint or at the
    model level is an Architect decision.
- **Payment records** (`payments`):
  - `cuota_id` — foreign key to `cuotas.id`, required (BR-007; the membership
    is reached through the cuota — C-06 "related to a Membership").
  - `amount` — `decimal(10,2)`, NOT NULL, positive (BR-007).
  - `method` — one of `cash` / `transfer` (BR-004).
  - `payment_date` — date, NOT NULL, not in the future (BR-007, PY-03).
  - `reference` — nullable string; required when `method = transfer` (PY-04).
  - `notes` — nullable text; optional (BR-007).
  - `status` — one of `pending` / `confirmed` / `failed`, default `confirmed`
    (BR-005, BR-006; D-16 option 1). Manual flows only ever write `confirmed`.
  - `recorded_by` — foreign key to `users.id`, NOT NULL (PY-06): the staff
    User who registered the payment.
  - `created_at` / `updated_at` timestamps.
- No monetary column is added to `memberships` or `plans` (SPEC-004 §10,
  BR-010).

Modified:

- Cuota `status` on the `pending → paid` transition (BR-003, FR-006; NC-01,
  BR-014) and on the `pending → cancelled` transition when the membership is
  cancelled (BR-015; NC-04); cuota `amount` via the pending-cuota edit (FR-002,
  BR-012).
- Membership `status` on `pending → active` via the SPEC-004 activation contract
  (`Membership::activate()`, SPEC-004 FR-008 / BR-006; BR-008 of this
  Specification). No other membership field is modified (BR-013).

Deleted:

- No hard deletion of cuota or payment records in the MVP (BR-009); no delete
  operation.

---

## 11. Acceptance Criteria

- [ ] AC-1: Creating a membership (create or renewal) auto-generates exactly one
  cuota for the membership with amount = the plan's `price` at that time and
  status `pending`; creating/renewing a membership never creates a Payment
  record (FR-001, BR-001, BR-002, BR-003; C-07).
- [ ] AC-2: ADMIN/TRAINER can edit the amount of a pending cuota; the change
  persists (FR-002, BR-012).
- [ ] AC-3: Editing a cuota amount to zero, negative or invalid is rejected
  (ERR-006, BR-002).
- [ ] AC-4: Editing the amount of a cuota that is not `pending` is rejected
  (ERR-007, BR-003, BR-012).
- [ ] AC-5: ADMIN/TRAINER can register a cash payment against a pending cuota;
  the amount must equal the cuota amount (full payment); the payment is
  persisted with status `confirmed`, the recorded amount, method `cash`, payment
  date and `recorded_by`; the cuota becomes `paid` (FR-004, FR-006, BR-005,
  BR-007, BR-014).
- [ ] AC-6: ADMIN/TRAINER can register a bank transfer payment; `reference` is
  required; the payment is persisted `confirmed` (FR-004, ERR-005, PY-04).
- [ ] AC-7: Registering a payment with a nonexistent cuota, a zero/negative
  amount, an amount that does not equal the cuota amount, an invalid method, or
  a future/missing payment date is rejected (ERR-001, ERR-002, ERR-003, ERR-004,
  ERR-010).
- [ ] AC-8: Registering a payment against the first cuota of a `pending`
  membership within its period activates the membership (`Membership::activate()`
  invoked; status becomes `active`) (FR-006, BR-008, SPEC-004 BR-006).
- [ ] AC-9: Registering a payment never activates a membership that is not
  `pending` or whose end date has passed; the payment is recorded `confirmed`
  and the cuota becomes `paid`, but an `expired` membership is never
  reactivated (ERR-009, BR-008, SPEC-004 AC-15, AM-10; NC-04).
- [ ] AC-10: No payment is ever created with status `pending` or `failed` by a
  manual flow; every SPEC-005-created payment is `confirmed` (BR-005, BR-006).
- [ ] AC-11: Confirmed payments cannot be edited, deleted or transitioned; no
  delete operation exists for cuotas or payments (BR-009, PY-05).
- [ ] AC-12: ADMIN and TRAINER can list/search/view cuotas and payments,
  including a cuota's payment history; CLIENT and Anonymous cannot (403)
  (FR-003, FR-005, ERR-008, BR-011).
- [ ] AC-13: CLIENT cannot register payments or edit cuota amounts (403)
  (ERR-008, BR-011).
- [ ] AC-14: A later plan price change or plan deactivation does not modify
  already-generated cuota amounts (BR-010, SPEC-004 BR-013).
- [ ] AC-15: Registering a payment does not modify the plan, the client, the
  user or the membership record except for the `pending → active` activation
  transition (BR-010, BR-013; C-07).

Note: the resolved decisions NC-01..NC-04 (§14.1) are now covered by ACs
AC-16..AC-19 below; they were previously excluded while unresolved.

- [ ] AC-16: A payment amount that differs from the cuota amount (partial,
  under- or over-payment) is rejected; a cuota is satisfied only by a single
  full payment (ERR-010, BR-014, NC-01).
- [ ] AC-17: Each membership carries exactly one cuota; prepayment of several
  periods is not supported and requires a new membership record per D-03 manual
  renewal semantics (BR-001, NC-02).
- [ ] AC-18: The cuota default amount is the plan's `price` only; the plan's
  `enrollment_fee` is never added to any cuota or charge (BR-002, NC-03).
- [ ] AC-19: Cancelling a membership transitions its still-`pending` cuotas to
  `cancelled`; registering a payment against a `cancelled` cuota (or an
  already-`paid` cuota) is rejected (BR-015, ERR-011, NC-04).

**Test plan (to be executed at Implementation; aligned with the existing Pest
layout under `tests/`, `tests/Pest.php` helpers `role()` / `userWithRoles()`,
`RefreshDatabase`, Livewire component tests as used in
`MembershipManagementTest`):**

- `tests/Feature/Admin/CuotaManagementTest.php` (Livewire): cuota auto-generation
  on membership create and renewal (AC-1); edit pending cuota amount (AC-2);
  reject invalid amounts and non-pending edits (AC-3, AC-4); cuota list/detail
  with status (FR-003).
- `tests/Feature/Admin/PaymentManagementTest.php` (Livewire): register cash and
  transfer payments (AC-5, AC-6); validation errors incl. amount mismatch and
  non-payable cuota (AC-7, AC-16, AC-19); payments always `confirmed` (AC-10);
  payment list/detail (FR-005); no edit/delete (AC-11).
- `tests/Feature/Admin/PaymentPolicyTest.php`: ADMIN/TRAINER allowed to view and
  register; CLIENT denied; no delete ability (AC-12, AC-13, ERR-008, BR-011).
- `tests/Feature/Payment/ActivationFlowTest.php`: paying the first cuota of a
  pending membership activates it (AC-8); paying the pending cuota of an expired
  membership records the payment and the paid cuota but never reactivates the
  membership (AC-9); cancelling a membership cancels its pending cuotas and
  blocks payment (AC-19).
- `tests/Unit/CuotaTest.php`: constants (`pending`/`paid`/`cancelled`),
  `pending → paid` and `pending → cancelled` transitions, amount positivity,
  one-cuota-per-membership, relationship to membership, generation on membership
  creation.
- `tests/Unit/PaymentTest.php`: constants (`pending`/`confirmed`/`failed`),
  default `confirmed`, required transfer reference, relationships (cuota,
  recorded_by), immutability (no status-changing method).

---

## 12. Out of Scope

The following are explicitly NOT part of this Specification:

- **Mercado Pago and any external payment provider** — SPEC-014 is EXCLUDED
  from the backlog by PO decision: no integration, no online checkout, no
  webhooks, no credentials, no provider configuration, no `pending`/`failed`
  payment created by an external flow (the statuses are reserved only, BR-006).
- **Partial/underpayment, overpayment and multiple payments per cuota** — not
  supported (NC-01): each cuota is covered by exactly one full confirmed payment.
- **Prepayment of future periods** (E-03) — not supported in the MVP (NC-02):
  one payment covers exactly one cuota; paying several periods ahead requires a
  new membership record per D-03 manual renewal semantics.
- **Enrollment fee (matrícula) charging** — NOT charged in the MVP (NC-03): the
  plan's `enrollment_fee` field is not used to generate any cuota or charge.
- **Payment-side grace period after the membership end date** — none (NC-04).
  A payment may be recorded against a pending cuota of a `pending`, `active` or
  `expired` membership; recording never reactivates an `expired` membership
  (AM-10). A `cancelled` membership's pending cuotas become `cancelled` and are
  not payable. The access-side grace period is decided elsewhere (D-05 option 1
  for SPEC-007/008: active membership required, no grace period).
- **Refunds, chargebacks and payment reversals** — refunds are handled manually
  outside the system (D-16 option 1); no refund flow exists (BR-006, PY-05).
- **Invoices / receipts (comprobantes)** and payment reminders / overdue
  notifications.
- **Client-facing display of a client's own payments/cuotas** — SPEC-013
  (client portal); client isolation C-13 always applies.
- **Online membership purchase or payment on the public site** — SPEC-012 /
  SPEC-014 (excluded).
- **Automatic billing / recurring payments** — deferred (D-03 option 1, manual
  renewal; no recurring billing).
- **Cuota overdue/late state and cuota freezing** — no `overdue` state exists;
  the only cuota states are `pending` / `paid` / `cancelled` (BR-003). The
  `cancelled` state exists only as the NC-04 consequence (pending cuotas of a
  cancelled membership); it is not a staff-operated cancellation.

---

## 13. Dependencies

- **SPEC-001 (Authentication & Roles) — COMPLETED**: authentication, fixed role
  catalog (`Role::ADMIN`), admin panel access (ADMIN | TRAINER), `User::hasRole`
  helpers, policy pattern. `recorded_by` references `users` (PY-06).
- **SPEC-002 (Client Management) — COMPLETED**: client records; DNI unique;
  ADMIN-only client management. Payments/cuotas are navigated from clients via
  memberships (C-02/C-06).
- **SPEC-003 (Plan Management) — COMPLETED**: plan `price` is the source of the
  cuota default amount (BR-002; ADR-003 `decimal(10,2)` convention); plan
  `is_active` gates membership creation (SPEC-004 BR-012). The optional
  `enrollment_fee` is NOT charged in the MVP (NC-03).
- **SPEC-004 (Membership Management) — COMPLETED**: the membership record is
  the subject of cuotas (BR-001); the `pending → active` activation contract
  (`Membership::activate()`, SPEC-004 FR-008 / BR-006 / AC-15) is triggered by
  this Specification (FR-006, BR-008); `expired`/`cancelled` terminality and
  the no-reactivation rule (SPEC-004 AM-10) bound the late-payment scenario;
  cancelling a membership triggers the `pending → cancelled` cuota transition
  (BR-015, NC-04).
- **SPEC-013 (Client Portal) — FUTURE**: client-facing display of the client's
  own payments/cuotas; out of scope here (§12).
- **SPEC-007 (Bookings) / SPEC-008 (Attendance) — FUTURE**: consume the
  activated-membership status under the access rule D-05 (option 1: active
  membership required, no grace period); out of scope here.
- **SPEC-014 (Mercado Pago) — EXCLUDED** from the backlog by PO decision.
- Requirements analysis: `analyst-pass-001.md` §5.6 (Cuotas), §5.7 (Payments),
  D-02, D-15, D-16, T-03/T-04, E-02/E-03.
- Confirmed decisions used: C-01 (roles, multi-role), C-06 (Payment related to
  Membership), C-07 (Plan/Membership/Payment separate), C-13 (client
  isolation), C-15 (presentation contexts).
- Flagged assumptions PY-01..PY-06 (§14.2) remain analyst assumptions requiring
  Product Owner confirmation before Implementation. Decisions NC-01..NC-04 are
  resolved by the PO (2026-08-15, §14.1).

---

## 14. Open Questions

### 14.1 Resolved decisions (NC-01 to NC-04)

The following were blocking business decisions. They were resolved by the
Product Owner on 2026-08-15 (recorded in `docs/sdd/state.yaml`) and are now
business rules of this Specification. The previous "NOT COVERED" framing is
removed.

| ID | Decision (PO, 2026-08-15) | Applies to |
| --- | --- | --- |
| NC-01 | **Full payment only.** Each cuota is covered by exactly ONE confirmed payment whose amount equals the cuota amount. No partial/underpayment, no overpayment, no multiple payments per cuota. The cuota transitions `pending → paid` only when that single matching confirmed payment is registered. | BR-003, BR-014, FR-004, FR-006, ERR-010, ERR-011, §6 step 5, AC-5/AC-7/AC-16 |
| NC-02 | **No prepayment in MVP.** One payment covers exactly one cuota; a membership carries exactly one cuota. Paying several periods ahead is not supported — that requires a new membership per D-03 manual renewal semantics. | BR-001, FR-001, §10 (uniqueness), AF-006, AC-17 |
| NC-03 | **Enrollment fee (matrícula) NOT charged in MVP.** The Plan's `enrollment_fee` field (SPEC-003) is not used to generate any cuota or charge. No matrícula cuota. | BR-002, FR-001, §10, AC-1/AC-18, §12, §13 (SPEC-003) |
| NC-04 | **Late payment / cancelled memberships.** Staff may record a payment against a pending cuota of a membership that is `active` or `expired` (in addition to the covered `pending` first-payment flow); recording a payment never reactivates an `expired` membership (AM-10). When a membership is `cancelled`, its pending cuotas become `cancelled`/uncollectible (not payable). No payment-side grace period after `end_date`. | BR-003, BR-015, FR-004, ERR-009, ERR-011, AF-005, AC-9/AC-19, §10, §12 |

### 14.2 Assumptions requiring PO confirmation

These flagged assumptions are analyst necessities or derived consequences needed
to make the Specification implementable. They are NOT confirmed business rules
unless stated otherwise; prefix PY distinguishes this Specification's assumptions
from SPEC-001 (A-xx), SPEC-002 (AD-xx), SPEC-003 (AP-xx) and SPEC-004 (AM-xx).

| ID | Assumption | Source | Blocks / Affects |
| --- | --- | --- | --- |
| PY-01 | "Staff" for payment recording and pending-cuota editing = ADMIN and TRAINER (the MVP staff roles; no RECEPTIONIST — confirmed SPEC-001 A-04). Consistent with D-15 option 1 ("ADMIN/TRAINER record cash/transfer") and D-02 option 2 ("staff may edit"). | Derived from D-15 op1 + SPEC-001 A-04 | §2, BR-011, §9, ERR-008 |
| PY-02 | A manually registered payment is persisted directly with status `confirmed`; no `pending`/`failed` payment is ever produced by a SPEC-005 flow (D-16 option 1 read without an external provider). | Derived from D-16 op1 + PO exclusion of SPEC-014 | BR-005, BR-006, AC-10 |
| PY-03 | Payment date must be a valid date and must not be in the future; backdating is allowed so staff can record a payment received earlier. | Analyst necessity (validation) | BR-007, ERR-004, AC-7 |
| PY-04 | A bank transfer payment requires a `reference` (traceability); for cash it is optional; `notes` are optional for both. | Analyst necessity (traceability) | BR-007, ERR-005, AC-6, AF-001 |
| PY-05 | Confirmed payments are immutable in the MVP: no edit, no delete, no status transition; refunds are handled manually outside the system. | Derived from D-16 op1 ("refunds handled manually") + preservation pattern (AGENTS.md §12) | BR-006, BR-009, AC-11 |
| PY-06 | Every payment stores `recorded_by` (the staff User who registered it) for auditability. | Analyst necessity (audit) | BR-007, §10, tests |

### 14.3 Other open questions (non-blocking for this analysis)

- OQ-01: Should cuotas/payments generated before SPEC-005's deployment (i.e.,
  memberships currently `pending` with no cuota) be backfilled automatically, or
  generated only for memberships created after deployment? (Operational note;
  no business rule implied — analogous to SPEC-004 OQ-10.)
- OQ-02: Should the `paid_at` timestamp on a cuota be stored explicitly or
  derived from the satisfying payment? (Architect/implementation detail.)
- OQ-03: Does the admin UI need a client-side "payments" relation manager (like
  `MembershipsRelationManager`) in addition to the Payments/Cuotas resources?
  (Presentation choice; no business rule implied.)

---

## 15. Related Documents

- Product documentation: `docs/product/product-definition-v0.1.md`
- Domain documentation: `docs/domain/domain-model-v0.1.md` (Payment, C-06;
  Membership, C-05/C-08)
- Requirements analysis: `docs/requirements/analyst-pass-001.md` (§5.6 Cuotas,
  §5.7 Payments, D-02, D-15, D-16, T-03/T-04, E-02/E-03, R-01/R-02)
- Specifications: `docs/specs/SPEC-001.md`, `docs/specs/SPEC-002.md`,
  `docs/specs/SPEC-003.md`, `docs/specs/SPEC-004.md` (FR-008, BR-006, BR-013,
  AM-01/AM-05/AM-09/AM-10, OQ-02/OQ-04/OQ-09)
- Architecture documentation: `docs/architecture/SPEC-004.md`,
  `ARCHITECTURE.md` (§13 Payments, §14 Memberships, §20 simplest correct
  architecture)
- Architecture decisions: `docs/adr/ADR-001.md`, `docs/adr/ADR-002.md`,
  `docs/adr/ADR-003.md` (decimal(10,2), single implicit currency),
  `docs/adr/ADR-004.md` (expiry command)
- Development rules: `AGENTS.md`
- Workflow state: `docs/sdd/state.yaml` (NIGHT MODE pre-approvals; SPEC-014
  exclusion)
