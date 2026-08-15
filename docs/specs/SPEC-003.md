# SPEC-003 — Plan Management

## Status

Draft (analysis phase).

This is the third Specification of the MVP. It builds on the SPEC-001 and SPEC-002
foundations, both COMPLETED and implemented in the repository (`docs/sdd/state.yaml`),
and does not depend on any later Specification.

**Assumption notice:** this specification contains explicitly flagged assumptions
(AP-01 to AP-06, see §14.1) that borrow the documented "Recommended" option of
`analyst-pass-001.md` §8 (D-14), derive from the documented plan analysis (§5.4),
or fill gaps required to make the specification implementable. **None of them is a
confirmed business rule** unless stated otherwise. Each requires Product Owner
confirmation before Implementation.

---

## 1. Objective

Provide plan (offer) management for the gym:

- an ADMIN can create, list, search, view, edit, activate and deactivate the plans
  the gym offers (C-04: "A Plan is a product/service offered by the gym");
- a Plan defines the commercial characteristics of an offer: a name, a description,
  a fixed price per period, and an optional one-time enrollment fee (matrícula)
  (D-14 Recommended option 2, borrowed as AP-01);
- Plan, Membership and Payment remain separate, persistent concepts (C-07,
  ARCHITECTURE §14): this Specification defines only the Plan catalog; memberships
  (SPEC-004) and payments (SPEC-005) consume it;
- plan records are never hard-deleted; deactivation is used instead (preservation
  pattern, AGENTS.md §12; AP-02).

The Plan catalog is the base for the Membership module (SPEC-004), which creates a
client's enrollment in a Plan for a period (C-05). This Specification deliberately
does NOT define membership behavior, period semantics, or how plan edits affect
existing memberships (see §12 Out of Scope, AP-06, OQ-01, OQ-06).

---

## 2. Actors

Actors are defined by role, not by person (SPEC-001 §2). A single User may hold one
or more roles (confirmed decision C-01).

| Actor | Description |
| --- | --- |
| Anonymous visitor | No access to plan management. Public display of plans (e.g., a public "Plans" page) is out of scope of this Specification (OQ-03). |
| ADMIN | Staff who administer the gym. In this Specification, the only actor that can manage plans (assumption AP-03). |
| TRAINER | Staff who train clients. No plan management capability in this Specification; TRAINER read access to plans is an open question (OQ-02). |
| CLIENT | A gym member. Cannot manage plans; client-facing plan visibility is defined by later Specifications (client portal SPEC-013, public website). |

---

## 3. Preconditions

1. SPEC-001 is implemented and completed: authentication, fixed role catalog
   (ADMIN, TRAINER, CLIENT), admin panel access (ADMIN or TRAINER), role helpers
   (`hasRole` / `hasAnyRole`), `Role::ADMIN` constant (`docs/sdd/state.yaml`,
   ADR-001).
2. SPEC-002 is implemented and completed (context only: plan management does not
   depend on client records; `docs/sdd/state.yaml` records `depends_on: []` for
   SPEC-003).
3. An authenticated ADMIN exists and can access the admin panel (SPEC-001 FR-008).
4. The role catalog stays at ADMIN / TRAINER / CLIENT; no RECEPTIONIST role
   (confirmed SPEC-001 A-04).
5. No plans table exists yet; the Plans module is greenfield on top of the SPEC-001
   foundation.
6. Decision D-14 (plan pricing model) is assumed per the documented Recommended
   option 2 (AP-01) and requires Product Owner confirmation before Implementation.

---

## 4. Functional Requirements

### FR-001 — Create plan

An ADMIN can create a plan. Required fields: name, price (the fixed price charged
per period). Optional fields: description, enrollment fee (one-time matrícula). A
new plan is created as active by default (AP-02). Creating a plan does NOT create
any membership or payment (C-07, BR-007).

### FR-002 — List and search plans

An ADMIN can list plans and search them by name and description.

### FR-003 — View plan detail

An ADMIN can view a plan's full detail, including description, price, enrollment
fee and current status (active/inactive).

### FR-004 — Edit plan

An ADMIN can update a plan's name, description, price and enrollment fee.

### FR-005 — Activate / deactivate plan

An ADMIN can deactivate a plan (it is no longer offered for new sales) and
reactivate it (AP-02). Deactivation is the only lifecycle transition in this
Specification; there is no delete operation (BR-004).

### FR-006 — Display plan status

Plan lists and detail show the plan's status (active/inactive), so the ADMIN knows
which plans are currently offered.

---

## 5. Business Rules

### BR-001 — Pricing model

A Plan is priced as a fixed price per period plus an optional one-time enrollment
fee (matrícula) (D-14 Recommended option 2, AP-01). Session packages and other
pricing models are out of scope (D-14 option 3 deferred; §12).

### BR-002 — Amount validation

The price must be a positive amount. The enrollment fee, when present, must be zero
or a positive amount. Negative amounts are rejected (AP-01).

### BR-003 — Unique plan name

The plan name is unique among plans; a duplicate name is rejected (AP-04).

### BR-004 — No hard deletion of plans

Plan records are never hard-deleted; historical offer data is preserved (AGENTS.md
§12; same pattern as SPEC-001 BR-007 / SPEC-002 BR-006; AP-02). Deactivation is
used instead.

### BR-005 — Plan lifecycle

A plan is either active or inactive (AP-02). An inactive plan is no longer offered
for new sales; the concrete effect on membership creation is consumed and enforced
by SPEC-004. The effect of deactivating or editing a plan on existing memberships
is NOT defined by this Specification (boundary; OQ-06, gates D-03/D-04/D-06).

### BR-006 — Plan management is ADMIN-only

Only ADMIN can create, list, search, view, edit, activate and deactivate plans
(AP-03). TRAINER and CLIENT cannot.

### BR-007 — Plan, Membership and Payment are separate

Plan records exist independently of memberships and payments (C-07, ARCHITECTURE
§14). Creating, editing or deactivating a plan never creates, modifies or deletes a
membership or payment record.

---

## 6. Main Flow

1. An authenticated ADMIN opens the Plans section of the admin panel (FR-001).
2. ADMIN creates a plan: fills the required name and price, and optionally the
   description and enrollment fee, and saves.
3. The system validates: required fields present (ERR-001), name unique (ERR-002),
   price positive and fee non-negative (ERR-003).
4. The plan is persisted as active (FR-001, AP-02) and appears in the plan list
   (FR-002).
5. ADMIN can open the plan detail view (FR-003), edit fields (FR-004), or
   deactivate/reactivate the plan (FR-005).
6. The plan list and detail always show the plan's status (FR-006).

---

## 7. Alternative Flows

### AF-001 — Deactivating a plan

ADMIN deactivates an active plan (FR-005). The plan remains in the system and in
the list, marked inactive (BR-005); it is no longer offered for new sales. The
effect on any existing memberships is a SPEC-004 concern and is not defined here
(OQ-06).

### AF-002 — Reactivating a plan

ADMIN reactivates an inactive plan (FR-005); it becomes active again and may be
offered for new sales (AP-02).

### AF-003 — Editing a plan after it is in use

A plan may be edited at any time, including after memberships have been created
against it (FR-004). Whether a price change affects existing memberships is NOT
defined by this Specification (boundary; OQ-06, gates D-03/D-04/D-06). This
Specification provides no versioning or price-history mechanism (see §12).

---

## 8. Error Cases

### ERR-001 — Missing required fields

Condition: creating/editing a plan without the name or price.

Expected behavior: rejected with a validation error (FR-001, FR-004).

### ERR-002 — Duplicate plan name

Condition: creating/editing a plan with a name already used by another plan.

Expected behavior: rejected with a validation error (BR-003, AP-04).

### ERR-003 — Invalid amounts

Condition: price is zero or negative, or enrollment fee is negative.

Expected behavior: rejected with a validation error (BR-002, AP-01).

### ERR-004 — Unauthorized access

Condition: a TRAINER or CLIENT attempts to manage plans (create, view, search,
edit, activate/deactivate).

Expected behavior: access denied (403 or hidden from navigation) (BR-006, AP-03).

---

## 9. Authorization

| Operation | Anonymous | ADMIN | TRAINER | CLIENT |
| --- | --- | --- | --- | --- |
| Create plan | Denied | Allowed (BR-006) | Denied | Denied |
| List / search plans | Denied | Allowed (BR-006) | Denied | Denied |
| View plan detail | Denied | Allowed (BR-006) | Denied | Denied |
| Edit plan | Denied | Allowed (BR-006) | Denied | Denied |
| Activate / deactivate plan | Denied | Allowed (BR-006, AP-02) | Denied | Denied |
| Public display of plans | Out of scope (OQ-03) | — | — | — |

Notes:

- A User holding several roles receives the union of the column permissions
  (SPEC-001 BR-002); an ADMIN who is also CLIENT can manage plans in the admin
  panel.
- Authorization is enforced server-side; frontend-only restrictions are never
  sufficient (AGENTS.md §17).
- TRAINER read access to plans is deferred (OQ-02); client-facing plan visibility
  is SPEC-013 / public website (OQ-03).

---

## 10. Data Changes

This Specification describes the information that must exist; the exact persistence
schema is defined by the Architect.

Created:

- Plan records: name (unique — BR-003), description (optional), price (the fixed
  price per period — BR-001, BR-002), enrollment fee (optional one-time matrícula —
  BR-001, BR-002), status (active/inactive, active by default — AP-02). Monetary
  amounts are stored as plain numeric amounts in the MVP's single implicit currency
  (AP-05); the exact column precision is an Architect decision.

Modified:

- Plan name, description, price and enrollment fee via edit (FR-004).
- Plan status when deactivated/reactivated (FR-005, AP-02).

Deleted:

- No hard deletion of plan records in the MVP (BR-004); no delete operation.

---

## 11. Acceptance Criteria

- [ ] AC-1: ADMIN can create a plan with name and price (required) plus optional
  description and enrollment fee; the record is persisted as active and listed
  (FR-001, FR-002, FR-006).
- [ ] AC-2: Creating or editing a plan with a name already used by another plan is
  rejected with a validation error (ERR-002, BR-003).
- [ ] AC-3: Creating/editing a plan with a non-positive price or a negative
  enrollment fee is rejected with a validation error (ERR-003, BR-002).
- [ ] AC-4: ADMIN can search plans by name and description (FR-002).
- [ ] AC-5: ADMIN can view a plan's full detail including status (FR-003, FR-006).
- [ ] AC-6: ADMIN can edit a plan's fields; changes persist (FR-004).
- [ ] AC-7: ADMIN can deactivate an active plan; the plan remains in the system and
  is displayed as inactive (FR-005, FR-006, BR-005).
- [ ] AC-8: ADMIN can reactivate an inactive plan (FR-005, AF-002).
- [ ] AC-9: A TRAINER or CLIENT cannot create, view, search, edit or
  activate/deactivate plans (403) (ERR-004, BR-006).
- [ ] AC-10: No delete operation exists for plans; a created plan record persists
  (BR-004).
- [ ] AC-11: Creating, editing or deactivating a plan never creates, modifies or
  deletes membership or payment records (BR-007, C-07).

---

## 12. Out of Scope

The following are explicitly NOT part of this Specification:

- Memberships: creating a client's enrollment in a plan, periods, renewal, states,
  multiple active memberships (SPEC-004; gates D-02, D-03, D-04, D-06).
- Payments and cuotas (SPEC-005; gates D-02, D-15, D-16).
- The effect of editing or deactivating a plan on existing memberships (boundary;
  OQ-06; gates D-03/D-04/D-06).
- Whether a plan carries a period/duration attribute (e.g., "Mensual" = 30 days):
  period semantics belong to D-03 (gate of SPEC-004); this Specification stores
  price as a plain amount (AP-06, OQ-01).
- Plan categories / multiple pricing models (subscription vs session packages):
  D-14 option 3 deferred; session packages later (OQ-04).
- Trial periods and discounts on plans (§5.4 "may be out of MVP"; OQ-05).
- Public website display of plans ("Complete public website content" is out of
  scope per product-definition; OQ-03).
- Client portal display of plans (SPEC-013).
- Trainer–client assignment or any trainer-specific plan features.
- Bulk import/export of plans.
- Plan versioning or price history.

---

## 13. Dependencies

- **SPEC-001 (Authentication & Roles) — COMPLETED** (`docs/sdd/state.yaml`):
  authentication and session foundation; fixed role catalog with `Role::ADMIN`;
  admin panel access (ADMIN | TRAINER); `User::hasRole` / `User::hasAnyRole`
  helpers; policy pattern (`UserPolicy`, `ClientPolicy`). Plan management is
  implemented inside the admin panel and follows the same conventions.
- **SPEC-002 (Client Management) — COMPLETED** (`docs/sdd/state.yaml`): not
  functionally required by this Specification (plan management does not touch
  client records); referenced for context and conventions only. `docs/sdd/state.yaml`
  records `depends_on: []` for SPEC-003.
- Confirmed decisions used: C-04 (Plan is an offer), C-07 (Plan / Membership /
  Payment separate), C-01 (roles, multi-role), C-15 (presentation contexts).
- Requirements analysis: `analyst-pass-001.md` §5.4 (Plans), D-14 (plan pricing
  model; Recommended option 2 borrowed as AP-01).
- Architecture constraints used: ARCHITECTURE §14 (Plans/Memberships/Payments
  separate), §20 (simplest correct architecture).
- Flagged assumptions AP-01 to AP-06 require Product Owner confirmation before
  Implementation (see §14.1).

---

## 14. Open Questions

### 14.1 Assumed decisions requiring PO confirmation

These are flagged assumptions. They are needed to make the Specification
implementable, but they are NOT confirmed business rules. The prefix AP distinguishes
this Specification's assumptions from SPEC-001 (A-xx) and SPEC-002 (AD-xx).

| ID | Assumption | Source | Blocks / Affects |
| --- | --- | --- | --- |
| AP-01 | Plan pricing model = fixed price per period plus an optional one-time enrollment fee (matrícula). The price must be a positive amount; the fee, when present, zero or positive. | Borrowed from analyst-pass-001 D-14 Recommended (option 2) | FR-001, BR-001, BR-002, ERR-003 |
| AP-02 | Plan lifecycle = active / inactive via a status flag. Deactivation over deletion (no delete operation); ADMIN-only transitions; deactivated plans are no longer offered for new sales; deactivated plans can be reactivated. | Not documented — analyst necessity, consistent with SPEC-001 BR-007 / SPEC-002 BR-006 preservation pattern | FR-001, FR-005, BR-004, BR-005 |
| AP-03 | Only ADMIN can create, list, search, view, edit, activate and deactivate plans. | Not documented — analyst necessity, consistent with SPEC-002 AD-03 (ADMIN-only client management) | FR-001..FR-006, BR-006, ERR-004 |
| AP-04 | Plan name is unique among plans; duplicates rejected. | Not documented — analyst necessity, consistent with SPEC-002 AD-02 (unique DNI) | BR-003, ERR-002 |
| AP-05 | Monetary amounts are stored as plain numeric amounts; the MVP uses a single implicit currency (context suggests ARS) and the Plan has no currency field. | Not documented — analyst necessity | FR-001, §10, OQ-07 |
| AP-06 | The Plan does NOT carry a period/duration attribute in this Specification; `price` is the amount charged per period, where "period" semantics (length, start, renewal) are defined by D-03 / SPEC-004. | Boundary decision per guidance (D-03 is the gate of SPEC-004) | FR-001, BR-001, §12, OQ-01 |

### 14.2 Open questions to be answered before Implementation (or at latest before Review)

- OQ-01 (D-03 sub-question): Does a Plan carry a period/duration attribute (e.g.,
  "Mensual" = 30 days, "Trimestral"), or is the period purely a Membership
  attribute? This Specification assumes the latter (AP-06). If the PO wants a
  plan-level period, the plan schema and FR-001 must be updated.
- OQ-02: Should TRAINER be able to VIEW plans (read-only) even though management is
  ADMIN-only?
- OQ-03: Should active plans be displayed on the public website (ARCHITECTURE §5
  lists "plans" in the public site) and/or in the client portal (SPEC-013)?
  Product-definition says "Complete public website content" is out of scope; if
  public display is wanted for the MVP, it needs its own scope decision.
- OQ-04: Is a plan category / type field needed (e.g., to distinguish monthly
  membership from personal training)? D-14 option 3 (multiple pricing models) is
  deferred; a display-only category is possible but not documented.
- OQ-05: Are trial periods or discounts on plans required in the MVP? (Assumed out
  of scope, §12.)
- OQ-06 (gates D-03/D-04/D-06): What happens to existing memberships when a plan is
  edited (e.g., price change) or deactivated? This is a SPEC-004 concern and is
  deliberately NOT defined here; it must be answered before SPEC-004 is specified.
- OQ-07: What is the MVP currency and amount precision? (Assumed single implicit
  currency, no currency field — AP-05.)

---

## 15. Related Documents

- Product documentation: `docs/product/product-definition-v0.1.md`
- Domain documentation: `docs/domain/domain-model-v0.1.md`
- Requirements analysis: `docs/requirements/analyst-pass-001.md` (§5.4 Plans, D-14,
  C-04, C-07)
- Specifications: `docs/specs/SPEC-001.md`, `docs/specs/SPEC-002.md`
- Architecture documentation: `docs/architecture/SPEC-001.md`,
  `docs/architecture/SPEC-002.md`, `ARCHITECTURE.md`
- Architecture decisions: `docs/adr/ADR-001.md`, `docs/adr/ADR-002.md`
- Development rules: `AGENTS.md`
- Workflow state: `docs/sdd/state.yaml`
