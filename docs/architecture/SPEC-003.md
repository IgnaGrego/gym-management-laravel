# Architecture — SPEC-003

## 1. Feature

Plan Management (the Plan catalog) for the gym management system:

- an ADMIN can create, list, search, view, edit, activate and deactivate the plans
  the gym offers (SPEC-003 FR-001..FR-006);
- a Plan defines commercial characteristics: a name, a description, a fixed price
  per period, and an optional one-time enrollment fee (matrícula) (AP-01, D-14
  Recommended option 2);
- Plan, Membership and Payment remain separate, persistent concepts (C-07,
  ARCHITECTURE §14): this Specification defines only the Plan catalog; SPEC-004
  (Memberships) and SPEC-005 (Payments) consume it;
- plan records are never hard-deleted; deactivation is used instead (BR-004, AP-02,
  the same preservation pattern as SPEC-001 BR-007 / SPEC-002 BR-006).

This is the third Specification of the MVP. It builds on the SPEC-001 foundation
already implemented in the repository (User, Role, `role_user`, `UserPolicy`,
`UserResource`, `RoleSeeder`, `AdminUserSeeder`, `EnsureUserHasRole`, Filament
admin panel) and follows the SPEC-002 conventions (module-level Policy, Filament
Resource, no hard deletion). Plan management does not depend on client records
(`docs/sdd/state.yaml` records `depends_on: []` for SPEC-003).

---

## 2. Specification

Reference:

`docs/specs/SPEC-003.md`

Status note: SPEC-003 is approved for the architecture phase per
`docs/sdd/state.yaml` (status `architecture`). The Specification explicitly flags
assumptions **AP-01 to AP-06** as NOT confirmed business rules; they require
Product Owner confirmation before Implementation (SPEC-003 §14.1). This design is
written against the assumptions as stated and remains valid under the documented
alternatives unless the PO changes them (see §12 Pending PO Confirmations).

---

## 3. Affected Modules

- **Plans** (new module): the plan catalog — `name`, `description`, `price`,
  `enrollment_fee`, `is_active` (active/inactive) — ADMIN-only management, and the
  lifecycle transitions (activate/deactivate). No hard deletion.
- **Cross-cutting authorization foundation** (no new module): a new `PlanPolicy`
  extends the SPEC-001/SPEC-002 pattern (ADMIN-only management, no delete) and
  consumes the existing `User::hasRole` helpers (ADR-001).

No changes are made to: auth scaffolding (login/logout/redirect), the
`EnsureUserHasRole` middleware, `AdminPanelProvider`, `RoleSeeder`,
`AdminUserSeeder`, the `role_user` pivot, the `users`/`roles`/`clients` tables, or
the Users and Clients modules. The Users module is not touched because plan
management does not create or modify user records; the Clients module is not
touched because plan management does not read or write client records.

The boundary with later Specifications is kept clean: memberships (SPEC-004),
payments and cuotas (SPEC-005), plan-period semantics (D-03 / SPEC-004), and the
effect of editing/deactivating a plan on existing memberships (OQ-06) are
explicitly OUT of scope (SPEC-003 §12). This design introduces no membership or
payment concept of any kind.

---

## 4. Application Flow

```text
Presentation (web)
    ↓
Admin panel: /admin — Filament panel (ADMIN | TRAINER gate via canAccessPanel)
    ↓
PlanResource (Filament): list / create / view / edit / activate / deactivate plans
    ↓
Application
    ↓
PlanPolicy (ADMIN-only)
    ↓
Domain
    ↓
Plan model (name, description, price, enrollment_fee, is_active)
    ↓
Persistence
    ↓
PostgreSQL: plans (new); users / roles / role_user / clients (existing, untouched)
```

Concrete flows:

1. **Plan CRUD (FR-001..FR-004)**
   - ADMIN opens the Plans section of the admin panel (`PlanResource`).
   - Create (FR-001): fills required `name` and `price` plus optional
     `description` and `enrollment_fee`, saves. Validation: required fields
     (ERR-001), unique name (ERR-002, BR-003), positive price and non-negative fee
     (ERR-003, BR-002).
   - The record is persisted with `is_active = true` by default (FR-001, AP-02)
     and appears in the list (FR-002); search is by `name` and `description`
     (FR-002).
   - ADMIN opens the detail view (FR-003) showing the full record including
     status, or edits fields (FR-004). Changes persist.
   - No delete operation exists (BR-004).
2. **Activate / deactivate (FR-005, AF-001, AF-002)**
   - From the list (or the edit form), ADMIN toggles the status of a plan. The
     transition is an `update` of `is_active` and is authorized by the
     `PlanPolicy::update` ability (ADMIN-only), the same precedent as SPEC-001
     user deactivation.
   - Deactivating an active plan leaves the record in the system, marked inactive
     in the list and detail (FR-006, BR-005); it is no longer offered for new
     sales (enforced by SPEC-004, out of scope here — OQ-06).
   - Reactivating an inactive plan makes it active again (AF-002).
3. **No side effects (BR-007)**
   - Creating, editing or deactivating a plan never creates, modifies or deletes a
     membership or payment record. In this Specification no membership/payment
     table exists yet; the contract is that every Plan operation touches only the
     `plans` table.

---

## 5. Components

### Controllers

None new.

Plan management lives entirely inside the Filament `PlanResource` (the admin-side
controller, same convention as `UserResource` in SPEC-001 and `ClientResource` in
SPEC-002). No web routes or HTTP controllers are added.

### Actions / Use Cases

None required.

Plan create/edit is plain Eloquent CRUD handled by the Filament resource with form
validation, matching the SPEC-001/SPEC-002 precedent (`UserResource`,
`ClientResource` perform CRUD directly). The activate/deactivate transition is a
single-field `update` of `is_active`, covered by the `update` policy ability; it
does not deserve an Action class (AGENTS.md §9-10, ARCHITECTURE §7). The
SPEC-002 `ProvisionClientUser` precedent shows that an explicit Action is only
introduced for genuinely multi-entity, transactional, rule-bearing operations;
no such operation exists in this Specification.

### Models

**`App\Models\Plan`** (new)

- Table: `plans`.
- Fillable: `name`, `description`, `price`, `enrollment_fee`, `is_active`.
- Casts:
  - `price` → `'decimal:2'` (ADR-003);
  - `enrollment_fee` → `'decimal:2'` (ADR-003);
  - `is_active` → `'boolean'` (AP-02).
- Relationships: none. Plan, Membership and Payment are separate modules (BR-007,
  C-07, ARCHITECTURE §14); SPEC-004/005 add the consumer relationships. No
  `memberships()` or `payments()` relation is introduced here.
- No delete scope/method: deletion is not offered anywhere (BR-004).

Note for the Developer: Eloquent `decimal:2` casts return string values; use the
`numeric` validation rules and format display columns accordingly (see §8, §9).

### Policies

**`App\Policies\PlanPolicy`** (new) — mirrors the `UserPolicy` / `ClientPolicy`
pattern:

- `viewAny` / `view`: ADMIN only (BR-006, FR-002, FR-003).
- `create`: ADMIN only (BR-006, FR-001).
- `update`: ADMIN only (BR-006) — covers field edits (FR-004) AND the
  activate/deactivate transitions (FR-005), the same way `UserPolicy::update`
  covers user deactivation.
- No `delete` policy is registered: plan records are never hard-deleted (BR-004);
  there is no delete operation.
- All rules use `$user->hasRole(Role::ADMIN)` (ADR-001).

Authorization matrix (SPEC-003 §9):

| Operation | Anonymous | ADMIN | TRAINER | CLIENT |
| --- | --- | --- | --- | --- |
| Create plan | Denied | Allowed (BR-006) | Denied | Denied |
| List / search plans | Denied | Allowed (BR-006) | Denied | Denied |
| View plan detail | Denied | Allowed (BR-006) | Denied | Denied |
| Edit plan | Denied | Allowed (BR-006) | Denied | Denied |
| Activate / deactivate plan | Denied | Allowed (BR-006) | Denied | Denied |

A multi-role user receives the union of permissions (SPEC-001 BR-002): an ADMIN
who also holds CLIENT can manage plans. TRAINER may access `/admin`
(`canAccessPanel`, SPEC-001) but sees no Plans navigation (consequence of
`viewAny` returning false) and receives 403 on direct plan URLs (ERR-004);
CLIENT never reaches the admin panel. Authorization is enforced server-side via
the Policy; frontend hiding is never the enforcement (AGENTS.md §17).

### Filament

**`App\Filament\Resources\PlanResource`** (new) with pages `ListPlans`,
`CreatePlan`, `ViewPlan`, `EditPlan` (following the `ClientResource` folder
convention: `app/Filament/Resources/PlanResource/Pages/*`).

- Form (create/edit):
  - `name` — required, maxLength 255, `unique(ignoreRecord: true)` (BR-003,
    ERR-002).
  - `description` — nullable, Textarea (FR-001).
  - `price` — required, `numeric`, `min:0.01` (positive amount, BR-002, ERR-003).
    Display without a currency symbol (single implicit currency — AP-05, OQ-07;
    ADR-003).
  - `enrollment_fee` — nullable, `numeric`, `min:0` (zero or positive when
    present, BR-002, ERR-003); empty input is stored as `null` (absent fee), not
    as 0.
  - `is_active` — Toggle, `default(true)` (FR-001, AP-02); visible on create and
    edit so status can also be changed during an edit (FR-005 path).
- Table (FR-002, FR-006):
  - Columns: `name` (searchable), `description` (searchable), `price`
    (numeric, 2 decimals), `enrollment_fee` (numeric, 2 decimals, placeholder
    '—'), `is_active` (`IconColumn` boolean — status display, FR-006).
  - Row actions: `View`, `Edit`, and the lifecycle actions — `Deactivate`
    (visible when `record->is_active` is true, confirmation modal) and `Activate`
    (visible when false) — or a single action with dynamic label/color (Developer
    choice). The action updates `is_active` on the record; authorization is the
    resource's `update` policy (ADMIN-only), enforced server-side.
  - No delete action; `bulkActions([])` (BR-004).
- View page (`ViewPlan`): infolist showing `name`, `description`, `price`,
  `enrollment_fee` and status (FR-003, FR-006).
- Navigation: `navigationIcon` (e.g., `heroicon-o-tag`) and
  `navigationGroup = 'Commercial'` — presentation-only, consistent with the
  `'Administration'` group used by `UserResource`; the Developer may adjust the
  cosmetic placement.

### Events

None required.

No operation in SPEC-003 has a defined secondary effect that needs decoupling
(ARCHITECTURE §10). The activate/deactivate transition is a synchronous
single-field update; `PlanDeactivated`-style events are not needed until a later
Specification defines consumers (e.g., SPEC-004 membership behavior on
deactivation, OQ-06).

### Jobs

None required.

No queued work exists in SPEC-003 (no notifications, email, or slow operations).

### Routes

No new routes. Filament auto-registers `/admin/plans*` through the panel's
`discoverResources` (already configured in `AdminPanelProvider`).

### Seeders

None new. Plan records are created by ADMIN in the admin panel only (SPEC-003 §3
precondition 5). The existing `RoleSeeder` already provides the ADMIN role
required by management.

---

## 6. Data Changes

### Migrations

1. **`create_plans_table`** (new; next migration in the existing timestamp
   sequence: `2026_08_15_000004_create_plans_table.php`):

   | Column | Type | Constraints |
   | --- | --- | --- |
   | `id` | bigint | PK |
   | `name` | string | NOT NULL, UNIQUE (BR-003, ERR-002) |
   | `description` | text | nullable (FR-001) |
   | `price` | decimal(10,2) | NOT NULL (BR-001, BR-002; ADR-003) |
   | `enrollment_fee` | decimal(10,2) | nullable (BR-001, BR-002; ADR-003) |
   | `is_active` | boolean | NOT NULL, default `true` (FR-001, AP-02) |
   | `created_at` / `updated_at` | timestamp | timestamps |

   - The UNIQUE index on `name` enforces BR-003 at the database level.
   - Monetary amounts are stored as plain `decimal(10, 2)` amounts in the MVP's
     single implicit currency; there is NO currency column and NO period/duration
     column (AP-05, AP-06; ADR-003). `decimal(10,2)` supports amounts up to
     99,999,999.99 — far beyond any MVP plan price.
   - No foreign keys: `plans` is standalone in this Specification. Memberships
     (SPEC-004) will reference `plans.id`; the `plans` table is created without
     consumers so that the reference direction is defined by the consuming module
     (same boundary discipline as `clients.user_id`, ADR-002).
   - No DB check constraints for positivity: BR-002 is enforced by form
     validation (framework-first, AGENTS.md §10; ADR-003 §10 alternatives).

No existing migration is modified. The `users`, `roles`, `role_user` and
`clients` tables are reused as-is.

### Relationships

```text
plans (standalone in this Specification)
    ↑ consumed later by SPEC-004 (memberships) / SPEC-005 (payments)
```

No Eloquent relationship is defined in this Specification (BR-007).

### Data lifecycle

- **Created:** plan records, active by default (FR-001, AP-02).
- **Modified:** `name`, `description`, `price`, `enrollment_fee` via edit
  (FR-004); `is_active` via deactivate/reactivate (FR-005).
- **Deleted:** none in the MVP. No delete operation (BR-004) and no hard deletion
  of any kind.

---

## 7. External Integrations

None.

SPEC-003 touches no external service. Mercado Pago remains out of scope
(SPEC-014). No notification/email is sent by any plan operation.

---

## 8. Testing Strategy

Pest (PHPUnit) feature tests following the existing conventions (`tests/Pest.php`
helpers `role()`, `userWithRoles()`; `RefreshDatabase`; Livewire component testing
as used in `ClientManagementTest`). A new `PlanFactory` (`database/factories/`) is
added: `name` (unique), `description` (nullable), `price` (positive decimal),
`enrollment_fee` (nullable), `is_active` (default `true`) — mirroring the
`ClientFactory` shape.

**Plan CRUD and lifecycle (AC-1..AC-8, AC-10, AC-11)**
- `tests/Feature/Admin/PlanManagementTest.php` (Livewire component tests against
  `CreatePlan`, `EditPlan`, `ListPlans`, `ViewPlan`):
  - ADMIN can create a plan with required name + price and optional description +
    enrollment fee; the record is persisted with `is_active = true` and listed
    (AC-1, FR-001, FR-002, FR-006).
  - Creating a plan does NOT create any membership or payment record — assert the
    operation touches only the `plans` table (AC-11, BR-007; the membership/payment
    tables do not exist yet, so the assertion is that no other table gains rows).
  - Creating/editing with a duplicate name is rejected with a validation error
    (AC-2, ERR-002, BR-003).
  - Missing required fields are rejected (ERR-001).
  - Creating/editing with a non-positive price or a negative enrollment fee is
    rejected (AC-3, ERR-003, BR-002); a zero fee is accepted; an absent fee is
    stored as `null`.
  - Search by name and description returns matching plans (AC-4, FR-002).
  - ADMIN can view the full detail including status (AC-5, FR-003, FR-006).
  - ADMIN can edit fields; changes persist (AC-6, FR-004).
  - ADMIN can deactivate an active plan: the record remains in the system and is
    displayed as inactive (AC-7, FR-005, FR-006, BR-005).
  - ADMIN can reactivate an inactive plan (AC-8, FR-005, AF-002).
  - No delete operation exists: a created plan record persists and no delete
    action/route is available (AC-10, BR-004).

**Authorization / Policy (AC-9, AC-10)**
- `tests/Feature/Admin/PlanPolicyTest.php`:
  - ADMIN can `viewAny`/`view`/`create`/`update` plans; TRAINER and CLIENT cannot
    (AC-9, ERR-004, BR-006).
  - A multi-role ADMIN + CLIENT user can manage plans (SPEC-001 BR-002).
  - `PlanPolicy` has no `delete` ability for anyone (AC-10, BR-004).
  - TRAINER/CLIENT receive 403 on `/admin/plans` routes; the Plans navigation is
    not visible to them (asserted server-side, AGENTS.md §17).

**Unit**
- `tests/Unit/PlanTest.php`:
  - `PlanFactory` defaults: `is_active` is `true`, `enrollment_fee` is `null`
    (FR-001, AP-02).
  - Casts: `price`/`enrollment_fee` are decimal(2) values, `is_active` is boolean
    (ADR-003).
  - The DB unique constraint on `plans.name` rejects duplicates (BR-003).

All authorization assertions are server-side (AGENTS.md §17); no test relies on
frontend visibility.

---

## 9. Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Assumptions AP-01..AP-06 are unconfirmed (SPEC-003 §14.1). | If PO changes them, parts of the design change (e.g., AP-01 adds a different pricing shape; AP-03 extends management to another role; AP-06 adds a period attribute). | Keep implementation isolated: `PlanPolicy` rules, the `plans` schema and the form rules are the only touch points. Block Implementation until PO confirms §12 items. |
| OQ-01: plan-level period/duration attribute (D-03 sub-question). | If PO wants a plan-level period, the schema and FR-001 must change. | Design assumes period is a Membership attribute (AP-06); the `plans` table has no period column. Adding one later is an additive migration. |
| OQ-02: TRAINER read access to plans. | Policy and navigation may need to change. | `PlanPolicy` is the single enforcement point; granting TRAINER `view`/`viewAny` touches policy + navigation only. |
| OQ-03: public website / client portal display of plans. | New presentation concern in later Specifications. | No schema impact; a public read path can be added without touching the catalog. |
| OQ-04: plan category/type field. | Additive column + form field if approved. | Not modeled; no migration risk. |
| OQ-05: trial periods/discounts on plans. | Additive fields or a later Specification. | Not modeled; no migration risk. |
| OQ-06: effect of editing/deactivating a plan on existing memberships. | Speculative behavior would be invented. | Deliberately NOT defined (SPEC-003 boundary); SPEC-004 must answer it before membership behavior is built. |
| OQ-07: currency and amount precision (AP-05). | Changing precision requires a migration; adding a currency column is additive. | `decimal(10,2)` + single implicit currency assumed (ADR-003). PO confirmation required before Review. |
| Eloquent `decimal:2` casts return strings. | Formatting/comparison bugs if treated as floats. | Use `numeric` rules in forms and `->numeric()`/formatting on table/infolist columns; unit tests assert cast behavior. |
| No DB check constraint on amounts. | A non-validated write path could store a negative price. | All write paths go through the Filament form (validated). If a raw-write path appears later, an optional check constraint is documented in ADR-003. |

---

## 10. Alternatives Considered

1. **Monetary amounts as integer cents** — avoids floating-point/decimal-string
   concerns, but introduces a conversion layer everywhere and is not a documented
   project pattern. Rejected in favor of `decimal(10,2)` columns with Eloquent
   `decimal:2` casts (ADR-003).
2. **DB check constraints for positive amounts** — stronger data integrity, but
   requires raw SQL in migrations and is inconsistent with the project's
   framework-validation-first convention (AGENTS.md §10; no existing migration
   uses check constraints). Rejected for the MVP; documented as optional hardening
   in ADR-003.
3. **Status as an enum-like string column (`active` / `inactive`)** — more
   extensible for future states, but overkill for a two-state flag. A boolean
   `is_active` matches the SPEC-001 `users.is_active` precedent (AP-02).
4. **Plan versioning / price-history table** — would preserve price changes for
   membership comparison, but is explicitly out of scope (SPEC-003 §12) and
   belongs to SPEC-004 (OQ-06). Rejected for this Specification.
5. **Explicit `CreatePlan` / `UpdatePlan` / `DeactivatePlan` Actions** — plain
   CRUD plus a single-field toggle needs no explicit use case, matching the
   SPEC-001/SPEC-002 precedent where `UserResource`/`ClientResource` perform CRUD
   directly and only multi-entity transactional operations get an Action
   (`ProvisionClientUser`).
6. **TRAINER read access to plans** — plausible future need (OQ-02), but AP-03
   (ADMIN-only) is the stated assumption; the policy makes granting it a
   one-line change later.
7. **A `category`/`type` column (e.g., "membership" vs "personal training")** —
   D-14 option 3 (multiple pricing models) is deferred (OQ-04); a display-only
   category is not documented. Not modeled.

---

## 11. Decision

Use the established SPEC-001/SPEC-002 conventions throughout:

- **Persistence:** a new `plans` table with required unique `name`, nullable
  `description`, required `price` `decimal(10,2)`, nullable `enrollment_fee`
  `decimal(10,2)`, and boolean `is_active` default `true` (ADR-003). No currency
  column (AP-05), no period column (AP-06), no FKs to memberships/payments
  (BR-007). The existing schema is untouched.
- **Authorization:** `PlanPolicy` (viewAny/view/create/update = ADMIN only, no
  delete) on top of the existing `User::hasRole` helpers (ADR-001). The `update`
  ability covers edits AND the activate/deactivate transitions (FR-004, FR-005).
- **CRUD + lifecycle:** Filament `PlanResource` with list/create/view/edit pages;
  search by name and description; status shown via a boolean column; `Deactivate`
  / `Activate` row actions (or one dynamic action); a `Toggle` in the form with
  default `true`; no delete action (BR-004).
- **No Actions, no events, no jobs, no new routes, no new seeders, no external
  integrations.**

---

## 12. Pending PO Confirmations

These items are carried from SPEC-003 and must be confirmed before Implementation
(or at latest before Review). This design does not silently resolve them.

### Assumptions (SPEC-003 §14.1)

| ID | Assumption | Impact on this design |
| --- | --- | --- |
| AP-01 | Pricing model = fixed price per period + optional one-time enrollment fee; price positive, fee zero-or-positive. | `plans` columns (`price`, `enrollment_fee`) + form rules (ERR-003) + ADR-003. |
| AP-02 | Lifecycle = active/inactive flag; deactivation over deletion; ADMIN-only transitions; deactivated plans can be reactivated. | `is_active` boolean default `true`; no delete policy/action; row actions for activate/deactivate. |
| AP-03 | Only ADMIN can create, list, search, view, edit, activate and deactivate plans. | `PlanPolicy` rules (viewAny/view/create/update ADMIN-only). |
| AP-04 | Plan name unique among plans; duplicates rejected. | Unique index on `plans.name` + `unique(ignoreRecord: true)` (ERR-002). |
| AP-05 | Monetary amounts stored as plain numeric amounts; single implicit currency; no currency field. | `decimal(10,2)` columns, no currency column (ADR-003). |
| AP-06 | Plan does NOT carry a period/duration attribute; `price` is the per-period amount; period semantics belong to D-03 / SPEC-004. | No period column; minimal plan schema. |

### Open questions (SPEC-003 §14.2)

| ID | Question | Impact on this design |
| --- | --- | --- |
| OQ-01 | Does a Plan carry a period/duration attribute (e.g., "Mensual" = 30 days)? | Design assumes no (AP-06). If PO wants a plan-level period, the schema and FR-001 must be updated. |
| OQ-02 | Should TRAINER be able to VIEW plans (read-only)? | Design assumes ADMIN-only (AP-03). Granting TRAINER read access touches `PlanPolicy` + navigation only. |
| OQ-03 | Public website and/or client portal display of plans? | Out of scope (SPEC-013, public website). No schema impact; requires its own scope decision. |
| OQ-04 | Plan category / type field needed? | Not modeled (D-14 option 3 deferred). Additive column + form field if approved. |
| OQ-05 | Trial periods or discounts on plans required? | Assumed out of scope. Additive fields or a later Specification. |
| OQ-06 | What happens to existing memberships when a plan is edited (e.g., price change) or deactivated? | SPEC-004 boundary; deliberately NOT defined here. Must be answered before SPEC-004 is specified. |
| OQ-07 | What is the MVP currency and amount precision? | Design default: `decimal(10,2)` + single implicit currency (AP-05, ADR-003). Precision change = migration; currency column = additive. |

### Additional design notes flagged for confirmation

- Deactivation/reactivation are modeled as `is_active` updates covered by the
  `update` policy ability — the same precedent as SPEC-001 user deactivation; no
  separate "deactivate" ability is registered.
- No DB check constraints are added for amount positivity (BR-002); enforcement is
  framework validation only (ADR-003).
- `price` is required and strictly positive (`min:0.01`); `enrollment_fee` is
  optional and `null` when absent, `>= 0` when present.
- No TRAINER read access (AP-03) and no public/client-facing plan visibility
  (OQ-03) are implemented in this Specification.

---

## 13. Related Documents

- Specification: `docs/specs/SPEC-003.md`
- Architecture decision: `docs/adr/ADR-001.md` (multi-role model; this design
  builds on it), `docs/adr/ADR-003.md` (plan pricing representation)
- Architecture: `docs/architecture/SPEC-001.md`, `docs/architecture/SPEC-002.md`,
  `ARCHITECTURE.md` (§14 Plans/Memberships/Payments separate, §20 simplest correct
  architecture)
- Product: `docs/product/product-definition-v0.1.md`
- Domain: `docs/domain/domain-model-v0.1.md`
- Requirements analysis: `docs/requirements/analyst-pass-001.md` (C-04, C-07,
  D-14, §5.4)
- Development rules: `AGENTS.md`
- Workflow state: `docs/sdd/state.yaml`
