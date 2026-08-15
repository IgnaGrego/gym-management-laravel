# Architecture — SPEC-004

## 1. Feature

Membership Management for the gym management system:

- an ADMIN can create, list, search, view, renew and cancel a client's
  membership records (SPEC-004 FR-001..FR-006);
- a membership is a client's enrollment in a Plan for a specific period
  (confirmed decision C-05): `client_id` + `plan_id` + a fixed-duration period
  from a start date (BR-002, BR-003, AM-02);
- a membership has exactly four states: `pending`, `active`, `expired`,
  `cancelled` (BR-004, AM-03); a new membership is always created `pending`
  (BR-005);
- a client may hold more than one membership at the same time, including
  several active ones (BR-010, AM-04, C-08); no restriction is imposed;
- renewal is manual and creates a NEW membership record; the original record
  is never modified (BR-011, AM-02/AM-08);
- the `pending → active` transition is a contract for SPEC-005 (Payments &
  Cuotas): it is invoked only when the first cuota of the membership is
  confirmed paid (BR-006, AM-05, FR-008). It is never an ADMIN UI action;
- the `expired` state is materialized by a daily scheduled command
  (`memberships:expire`); the mechanism is an Architect decision (BR-007,
  ADR-004);
- the access rule (what an active membership grants: attendance / booking) is
  decision D-05, a gate of SPEC-007 / SPEC-008, and is deliberately NOT
  decided here (BR-016, OQ-01);
- plan edits or deactivation do not modify existing memberships; an inactive
  plan cannot be used for new memberships or renewals (BR-012, BR-013, AM-09);
- membership records are never hard-deleted; no delete operation exists
  (BR-014).

This is the fourth Specification of the MVP. It builds on the SPEC-001/002/003
foundations already implemented in the repository (User / Role / Client / Plan
models, `role_user` pivot, UserPolicy / ClientPolicy / PlanPolicy, Filament
resources, `ProvisionClientUser`, seeders, `EnsureUserHasRole`; ADR-001/002/003).

---

## 2. Specification

Reference:

`docs/specs/SPEC-004.md`

Status note: SPEC-004 is approved for the architecture phase per
`docs/sdd/state.yaml` (status `architecture`). The Specification explicitly
flags assumptions **AM-01 to AM-10** as NOT confirmed business rules; they
require Product Owner confirmation before Implementation (SPEC-004 §14.1). This
design is written against the assumptions as stated and remains valid under the
documented alternatives unless the PO changes them (see §12 Pending PO
Confirmations).

Boundary note: cuota generation and payment allocation are deferred to
SPEC-005. This design defines only the membership record, its period semantics,
its state machine, manual renewal, the multiplicity rule, and the activation
contract that SPEC-005 triggers. No payment or cuota concept of any kind is
introduced here (BR-001, C-07, ARCHITECTURE §14).

---

## 3. Affected Modules

- **Memberships** (new module): the membership record (`memberships` table),
  the fixed-duration period (BR-003), the four-state machine (BR-004/BR-005),
  the activation contract for SPEC-005 (FR-008), manual renewal (FR-005), and
  cancellation (FR-006).
- **Clients** (existing module, additive changes only): the `Client` model
  gains a `memberships(): HasMany` relationship so a client's membership
  history is navigable (FR-004, C-08). No schema change to the `clients`
  table. A Filament RelationManager (`MembershipsRelationManager`) is added to
  `ClientResource` to display the client's membership history (FR-004).
- **Plans** (existing module, additive changes only): the `Plan` model gains a
  `memberships(): HasMany` relationship (the consumer relationship anticipated
  by SPEC-003 §3). No schema change to the `plans` table and no change to
  `PlanResource` in this Specification. `plans.is_active` gates membership
  creation and renewal (BR-012).
- **Cross-cutting authorization foundation** (no new module): a new
  `MembershipPolicy` extends the SPEC-001/002/003 pattern (ADMIN-only
  management, no delete) and consumes the existing `User::hasRole` helpers
  (ADR-001).
- **Scheduling infrastructure** (no new module): a scheduled console command
  (`memberships:expire`) materializes the `expired` state (BR-007, ADR-004).

No changes are made to: auth scaffolding (login/logout/redirect), the
`EnsureUserHasRole` middleware, `AdminPanelProvider`, `RoleSeeder`,
`AdminUserSeeder`, the `role_user` pivot, the `users`/`roles`/`clients`/`plans`
tables, `UserResource`, or `PlanResource`.

The boundary with later Specifications is kept clean: payments/cuotas
(SPEC-005), the access rule (SPEC-007/008, D-05), freeze states (deferred),
automatic renewal (deferred), and plan versioning (SPEC-003 §12) are
explicitly OUT of scope (SPEC-004 §12).

---

## 4. Application Flow

```text
Presentation (web)
    ↓
Admin panel: /admin — Filament panel (ADMIN | TRAINER gate via canAccessPanel)
    ↓
MembershipResource (Filament): list / create / view / cancel / renew memberships
ClientResource → MembershipsRelationManager: client membership history (FR-004)
    ↓
Application
    ↓
MembershipPolicy (ADMIN-only)            RenewMembership Action (renewal)
    ↓
Domain
    ↓
Membership model (period + four-state machine + activate()/cancel() contracts)
    ↓
Persistence
    ↓
PostgreSQL: memberships (new); clients / plans (existing, untouched)
    ↓
Scheduled command memberships:expire (daily) — materializes expired (BR-007, ADR-004)
```

Concrete flows:

1. **Create membership (FR-001)**
   - ADMIN opens the Memberships section of the admin panel (`MembershipResource`).
   - Create form: `client_id` (existing client — SPEC-002), `plan_id` (existing
     ACTIVE plan only — BR-012), `start_date`, `duration_days`.
   - Validation: required fields (ERR-002), client and plan exist (ERR-007),
     plan active (ERR-001, BR-012), duration positive integer (ERR-003,
     BR-003).
   - The record is persisted with `status = pending` (BR-005); `end_date` is
     computed as `start_date + duration_days - 1` by the model's `creating`
     hook (BR-003, AM-07). The record appears in the list with its status shown
     (FR-002, FR-007).
   - No payment or cuota record is created (BR-001, C-07; SPEC-005).
2. **List / search (FR-002, FR-007)**
   - ADMIN lists memberships; search by client (name/DNI), plan (name); filters
     by status and period dates (start/end).
   - Status is always displayed (badge column).
3. **View detail (FR-003)**
   - ADMIN opens the detail view: client, plan, period (start/end dates,
     duration), status.
4. **Client membership history (FR-004)**
   - From the client's record (ClientResource), the ADMIN opens the
     "Memberships" relation manager showing all memberships of that client,
     including past states, ordered by `start_date` (chronological, C-08).
5. **Renew membership (FR-005, BR-011)**
   - From a membership detail (or list row), ADMIN renews an `active` or
     `expired` membership (AM-08). The `RenewMembership` Action authorizes
     (ADMIN, via `create` ability), validates the source status (ERR-005), the
     plan is still active (ERR-001, BR-012) and the new duration (ERR-003),
     then creates a NEW membership record with the same `client_id` and
     `plan_id`, a new period (start date defaults to the day after the previous
     end date; duration defaults to the previous duration — AM-08, design
     default for OQ-05), and status `pending` (BR-005). The original record is
     never modified (BR-011).
6. **Cancel membership (FR-006, BR-008)**
   - ADMIN cancels a `pending` or `active` membership (confirmation modal).
     The Filament action authorizes via `MembershipPolicy::update` (ADMIN), then
     calls `$membership->cancel()`, which enforces the state rule (ERR-004) and
     sets status `cancelled` (terminal, BR-009).
7. **Activation transition — contract with SPEC-005 (FR-008, BR-006)**
   - SPEC-005 calls `$membership->activate()` when the first cuota of the
     membership is confirmed as paid. The method enforces: status is `pending`
     and the end date has not passed (AC-15); it sets status `active`.
   - Until SPEC-005 exists, created memberships remain `pending` (AF-001); this
     design defines the state machine and the contract, not the payment
     recording UI.
8. **Expiry (BR-007)**
   - The scheduled command `memberships:expire` runs daily and flips
     `pending`/`active` memberships whose `end_date < today` to `expired`
     (idempotent bulk update; ADR-004). `expired` is terminal (BR-009).
   - The business rule "no membership is reported `active` after its end date"
     is guaranteed by the command; `activate()` additionally rejects calls
     after the end date, covering the window before the next job run.

---

## 5. Components

### Controllers

None new.

Membership management lives entirely inside the Filament `MembershipResource`
(the admin-side controller, same convention as `UserResource`, `ClientResource`
and `PlanResource`). No web routes or HTTP controllers are added. The
`MembershipsRelationManager` under `ClientResource` is the Filament-side
controller for FR-004.

### Actions / Use Cases

**`App\Actions\RenewMembership`** (new — the only non-CRUD operation of this
Specification)

- Input: `Membership $membership` (the source record), `string $startDate`
  (new start date; defaults to `end_date + 1 day`), `int $durationDays` (new
  duration; defaults to the source record's `duration_days`) — AM-08, design
  default for OQ-05.
- Behavior:
  1. Authorize: `Gate::authorize('create', Membership::class)` — renewal
     creates a NEW membership record; the `create` ability covers it
     (BR-015). Server-side defense in depth (AGENTS.md §17) — the Filament
     action visibility already restricts the UI to ADMIN.
  2. Validate: the source membership's status is `active` or `expired`
     (ERR-005, AM-08, BR-009); the plan is still active (ERR-001, BR-012);
     `startDate` is a valid date; `durationDays` is a positive integer
     (ERR-003).
  3. Create: `Membership::create([...])` with the source record's
     `client_id` and `plan_id`, the new `start_date` and `duration_days`;
     status defaults to `pending` (BR-005); `end_date` is computed by the
     model's `creating` hook (BR-003).
  4. The source record is never modified (BR-011). No event, no payment, no
     cuota is created (BR-001, C-07).
- Returns the new `Membership`.

Membership create is plain Eloquent CRUD handled by the Filament resource with
form validation (the same precedent as `UserResource`/`ClientResource`/
`PlanResource`); the cancel transition is a model method invoked by a thin
Filament action; the activation transition is a model method invoked by
SPEC-005. An explicit `CreateMembership` / `CancelMembership` Action would be
an unnecessary abstraction (AGENTS.md §9-10, ARCHITECTURE §7).

### Models

**`App\Models\Membership`** (new)

- Table: `memberships`.
- Fillable: `client_id`, `plan_id`, `start_date`, `end_date`, `duration_days`,
  `status`. (`end_date` is fillable so the `creating` hook and explicit factory
  values work, but the standard create/renew paths never supply it.)
- Casts:
  - `start_date` → `'date'`;
  - `end_date` → `'date'`;
  - `duration_days` → `'integer'`;
  - `status` → plain string (no cast).
- Constants (single source of truth for the four-state machine, BR-004):
  - `Membership::STATUS_PENDING = 'pending'`
  - `Membership::STATUS_ACTIVE = 'active'`
  - `Membership::STATUS_EXPIRED = 'expired'`
  - `Membership::STATUS_CANCELLED = 'cancelled'`
- `booted()` creating hook: when `end_date` is null, compute it as
  `start_date + duration_days - 1` (BR-003, AM-07) via the static helper
  below. Single source of truth for the period invariant across create,
  renewal, factory and future SPEC-005 paths.
- Static helper: `computeEndDate(Carbon|string $startDate, int $durationDays): Carbon`
  → `Carbon::parse($startDate)->addDays($durationDays - 1)` (BR-003, AM-07).
- Relationships:
  - `client(): BelongsTo` → `Client` (FK `client_id`).
  - `plan(): BelongsTo` → `Plan` (FK `plan_id`).
- Simple domain behavior (ARCHITECTURE §8):
  - `activate(): void` — **the FR-008 contract for SPEC-005.** Throws
    `DomainException` unless `status === STATUS_PENDING` (AC-15; a non-pending
    membership cannot be activated) and `end_date >= today` (AC-15; only while
    within the validity period). Sets `status = STATUS_ACTIVE` and saves. No
    authorization check: this is a system-internal transition invoked by the
    SPEC-005 payment-confirmation path, never by a user request (SPEC-004 §9,
    BR-006).
  - `cancel(): void` — throws `DomainException` unless `status` is
    `STATUS_PENDING` or `STATUS_ACTIVE` (ERR-004, BR-008). Sets
    `status = STATUS_CANCELLED` and saves. Terminal (BR-009).
  - `isActive(): bool` — `status === STATUS_ACTIVE` (used by FR-007 display
    and, later, by SPEC-007/008 under D-05).
- No delete scope/method: deletion is not offered anywhere (BR-014).

**`App\Models\Client`** (modified additively)

- New relationship only:
  - `memberships(): HasMany` → `Membership` (ordered by `start_date` in the
    RelationManager, FR-004).
- No new columns, no change to `$fillable`, casts, `user()` or
  `hasLinkedUser()`.

**`App\Models\Plan`** (modified additively)

- New relationship only:
  - `memberships(): HasMany` → `Membership`.
- No new columns, no change to `$fillable`, casts or existing behavior. The
  relationship is not displayed in `PlanResource` in this Specification.

### Policies

**`App\Policies\MembershipPolicy`** (new) — mirrors the `UserPolicy` /
`ClientPolicy` / `PlanPolicy` pattern:

- `viewAny` / `view`: ADMIN only (BR-015, FR-002/003).
- `create`: ADMIN only (BR-015, FR-001) — also covers renewal, which creates a
  new membership record (FR-005).
- `update`: ADMIN only (BR-015) — covers the cancel transition (FR-006), the
  same way `PlanPolicy::update` covers activate/deactivate.
- No `delete` policy is registered: membership records are never hard-deleted
  (BR-014); there is no delete operation.
- No `activate` ability is registered: the `pending → active` transition is
  NOT an ADMIN UI operation; it is invoked by SPEC-005 through the model
  method (SPEC-004 §9, BR-006, FR-008).
- All rules use `$user->hasRole(Role::ADMIN)` (ADR-001).

Authorization matrix (SPEC-004 §9):

| Operation | Anonymous | ADMIN | TRAINER | CLIENT |
| --- | --- | --- | --- | --- |
| Create membership | Denied | Allowed (BR-015) | Denied | Denied |
| List / search memberships | Denied | Allowed (BR-015) | Denied | Denied |
| View membership detail | Denied | Allowed (BR-015) | Denied | Denied |
| View a client's membership history | Denied | Allowed (BR-015) | Denied | Denied |
| Renew membership | Denied | Allowed (BR-015) | Denied | Denied |
| Cancel membership | Denied | Allowed (BR-015) | Denied | Denied |
| Trigger the pending → active transition | Denied | Denied (only via confirmed cuota payment, FR-008/BR-006) | Denied | Denied |

A multi-role user receives the union of permissions (SPEC-001 BR-002): an ADMIN
who also holds CLIENT can manage memberships. TRAINER may access `/admin`
(`canAccessPanel`, SPEC-001) but sees no Memberships navigation (consequence of
`viewAny` returning false) and receives 403 on direct membership URLs
(ERR-006); CLIENT never reaches the admin panel. Authorization is enforced
server-side via the Policy; frontend hiding is never the enforcement
(AGENTS.md §17).

### Filament

**`App\Filament\Resources\MembershipResource`** (new) with pages
`ListMemberships`, `CreateMembership`, `ViewMembership` (following the
`ClientResource` folder convention:
`app/Filament/Resources/MembershipResource/Pages/*`).

There is NO `EditMembership` page: the Specification defines no edit operation.
Renewal creates a new record (BR-011) and cancellation is a terminal action
(BR-008); editing an existing membership's fields is not a requirement
(SPEC-004 §4).

- Form (create only — FR-001):
  - `client_id` — `Select` from existing clients (`Client::pluck('full_name', 'id')` or
    a searchable select), required (ERR-002, BR-002). Server-side rule:
    `exists:clients,id` (ERR-007).
  - `plan_id` — `Select` from ACTIVE plans only (`Plan::where('is_active', true)`),
    required (ERR-002, BR-002). Server-side rule: `exists:plans,id` plus a
    check that the plan is active (ERR-001, BR-012, AM-09). The option list is
    the UX restriction; the rule is the server-side enforcement (AGENTS.md §17).
  - `start_date` — `DatePicker`, required (ERR-002, BR-003).
  - `duration_days` — `TextInput` numeric, required, `integer`, `minValue(1)`
    (ERR-003, BR-003). Label: "Duration (days)".
  - `end_date` is NOT in the form; it is computed by the model (BR-003).
- Table (FR-002, FR-007):
  - Columns: `client.full_name` (searchable), `client.dni` (searchable),
    `plan.name` (searchable), `start_date`, `end_date`, `duration_days`,
    `status` (badge column — FR-007; colors e.g. pending=warning,
    active=success, expired=gray, cancelled=danger; presentation choice).
  - Filters: status `SelectFilter` with the four constants (FR-002); period
    date-range filters on `start_date` and `end_date` (FR-002) — implemented
    with Filament `Filter` components and DatePickers (Developer choice of the
    exact component API).
  - Row actions: `View`, `Cancel` (visible when status is `pending`/`active`,
    `requiresConfirmation()`, calls `$record->cancel()` after
    `authorize('update', $record)` — ERR-004), and `Renew` (visible when status
    is `active`/`expired`; opens a modal with `start_date` pre-filled to
    `end_date + 1 day` and `duration_days` pre-filled to the previous duration
    — AM-08/OQ-05 default; submits to `RenewMembership`).
  - No delete action; `bulkActions([])` (BR-014).
- View page (`ViewMembership`, FR-003): infolist showing `client.full_name`,
  `plan.name`, `start_date`, `end_date`, `duration_days`, and `status`
  (FR-007). Header actions: `Cancel` and `Renew` (same rules as the row
  actions). The `pending → active` transition is NOT offered anywhere in the
  UI (FR-008, BR-006).
- Navigation: `navigationIcon` (e.g., `heroicon-o-clipboard-document-list`) and
  `navigationGroup = 'Commercial'` (consistent with `PlanResource`); the
  Developer may adjust the cosmetic placement.

**`App\Filament\Resources\ClientResource\RelationManagers\MembershipsRelationManager`**
(new)

- Shows all memberships of the selected client, including past states, ordered
  by `start_date` (chronological, FR-004, C-08).
- Table: `plan.name`, `start_date`, `end_date`, `duration_days`, `status`
  (badge). Read-only: no create/attach actions (membership creation happens
  from `MembershipResource`; FR-001).
- Access: the relation manager inherits `ClientResource` access (ADMIN-only via
  `ClientPolicy`) and is additionally gated by `MembershipPolicy::viewAny`
  (ADMIN-only; Filament checks the related model's policy).
- Registered via `ClientResource::getRelations()`.

### Events

None required.

The activation transition is invoked synchronously by SPEC-005 through
`Membership::activate()`; there is no secondary effect with a defined consumer
today (ARCHITECTURE §10). If SPEC-007/008 later need to react to activation,
an event (e.g., `MembershipActivated`) can be introduced there. The expiry
command performs a bulk status update with no per-record side effects.

### Jobs

None queued.

The expiry operation is a fast, idempotent bulk `UPDATE`; it does not need a
queue (ARCHITECTURE §11). It is implemented as a scheduled console command.

### Scheduled command

**`App\Console\Commands\ExpireMemberships`** (new; signature
`memberships:expire`)

- Behavior: `Membership::whereIn('status', [STATUS_PENDING, STATUS_ACTIVE])
  ->where('end_date', '<', today())->update(['status' => STATUS_EXPIRED])`
  (BR-007, ADR-004). Idempotent; safe to run repeatedly.
- Registered in `routes/console.php` (Laravel 11 convention, already used by
  the scaffold's `inspire` command):
  `Schedule::command('memberships:expire')->dailyAt('00:05');`
  (early run minimizes the staleness window between midnight and the job; the
  exact time is a Developer/ops choice).
- Deployment note: requires the Laravel scheduler to run
  (`php artisan schedule:run` every minute via cron, or equivalent in Docker).
  Documented in ADR-004.

### Routes

No new routes. Filament auto-registers `/admin/memberships*` through the
panel's `discoverResources` (already configured in `AdminPanelProvider`). The
console command is registered in `routes/console.php`.

### Seeders

None new. Membership records are created by ADMIN in the admin panel only
(SPEC-004 §3 precondition 5). The existing `RoleSeeder` already provides the
ADMIN role required by management.

---

## 6. Data Changes

### Migrations

1. **`create_memberships_table`** (new; next migration in the existing
   timestamp sequence: `2026_08_15_000005_create_memberships_table.php`):

   | Column | Type | Constraints |
   | --- | --- | --- |
   | `id` | bigint | PK |
   | `client_id` | foreignId | NOT NULL, FK → `clients.id`, `restrictOnDelete` (BR-002) |
   | `plan_id` | foreignId | NOT NULL, FK → `plans.id`, `restrictOnDelete` (BR-002) |
   | `start_date` | date | NOT NULL (BR-003) |
   | `end_date` | date | NOT NULL, computed as `start_date + duration_days - 1` (BR-003, AM-07) |
   | `duration_days` | unsignedInteger | NOT NULL (BR-003; positivity enforced by form/action validation, ERR-003) |
   | `status` | string | NOT NULL, default `'pending'` (BR-004, BR-005); stored as string with model constants, NOT a DB enum (Architect decision, §10) |
   | `created_at` / `updated_at` | timestamp | timestamps |

   - `restrictOnDelete` on both FKs is a defensive guard consistent with the
     preservation pattern: clients (SPEC-002 BR-006) and plans (SPEC-003
     BR-004) are never hard-deleted, and a deletion attempt should be blocked
     rather than cascade into historical membership data (BR-014).
   - No monetary column: amounts belong to cuotas/payments (SPEC-005) using
     the ADR-003 `decimal(10,2)` convention; the membership does not snapshot
     the plan price (BR-013, AM-09).
   - Index on `(client_id, start_date)` to support the client membership
     history query ordered by `start_date` (FR-004, C-08). The FK columns get
     their own indexes automatically via `constrained()`.
   - No DB CHECK constraint for `end_date >= start_date` or
     `duration_days > 0`: enforced by the model hook and form/action validation
     (framework-validation-first convention, same as ADR-003).

No existing migration is modified. The `users`, `roles`, `role_user`,
`clients` and `plans` tables are reused as-is.

### Relationships

```text
clients 1 ──── * memberships * ──── 1 plans
```

```text
memberships.client_id → clients.id (required, restrictOnDelete)
memberships.plan_id   → plans.id   (required, restrictOnDelete)
```

### Data lifecycle

- **Created:** membership records with status `pending` and a computed
  `end_date` (FR-001, BR-005); new membership records on renewal (FR-005,
  BR-011).
- **Modified:** `status` on transitions — `pending → active` (FR-008/BR-006,
  via SPEC-005 calling `activate()`), `pending`/`active → expired` (BR-007,
  via the daily command), `pending`/`active → cancelled` (BR-008, via
  `cancel()`). No other membership field is modified by renewal, cancellation
  or plan edits (BR-011, BR-013).
- **Deleted:** none in the MVP. No delete operation (BR-014) and no hard
  deletion of membership records.

---

## 7. External Integrations

None.

SPEC-004 touches no external service. Mercado Pago remains out of scope
(SPEC-014). No notification/email is sent by any membership operation.

---

## 8. Testing Strategy

Pest (PHPUnit) feature tests following the existing conventions
(`tests/Pest.php` helpers `role()`, `userWithRoles()`; `RefreshDatabase`;
Livewire component testing as used in `PlanManagementTest`). A new
`MembershipFactory` (`database/factories/`) is added: `client_id` →
`Client::factory()`, `plan_id` → `Plan::factory()`, `start_date` (e.g.,
`today()->toDateString()`), `duration_days` (e.g., `30`), `status` →
`Membership::STATUS_PENDING`; `end_date` is either set explicitly in the
factory or computed by the model's `creating` hook (the hook fires on factory
creation; tests that need a past/future `end_date` pass it explicitly).

**Membership CRUD and lifecycle (AC-1..AC-6, AC-10, AC-11, AC-13, AC-14,
AC-17, AC-18; FR-007)**
- `tests/Feature/Admin/MembershipManagementTest.php` (Livewire component tests
  against `CreateMembership`, `ListMemberships`, `ViewMembership`):
  - ADMIN can create a membership selecting an existing client and an active
    plan with a start date and duration; the record is persisted with status
    `pending` and `end_date = start_date + duration_days - 1`, and appears in
    the list (AC-1, FR-001, FR-002, BR-003, BR-005).
  - Creating a membership does NOT create any payment/cuota record and does
    not modify the client, plan or user records (AC-18, BR-001, C-07 — assert
    only the `memberships` table gained a row).
  - Creating with an inactive plan is rejected (AC-2, ERR-001, BR-012).
  - Creating with a zero, negative or non-integer duration is rejected
    (AC-3, ERR-003, BR-003).
  - Creating with a missing client, plan, start date or duration is rejected
    (AC-4, ERR-002, BR-002).
  - Creating with a nonexistent client or plan id is rejected (ERR-007).
  - Search by client name/DNI and plan name returns matching memberships;
    status and period-date filters work (AC-5, FR-002).
  - ADMIN can view the full detail including client, plan, period and status
    (AC-6, FR-003, FR-007).
  - ADMIN can cancel a `pending` or `active` membership; it becomes
    `cancelled` (AC-10, FR-006, BR-008).
  - Cancelling an `expired` or `cancelled` membership is rejected (AC-11,
    ERR-004, BR-009).
  - A client can hold several concurrent memberships, including multiple
    active ones; no restriction is imposed (AC-13, BR-010).
  - Deactivating/editing a plan does not modify existing memberships; a
    deactivated plan cannot be used for new memberships or renewals (AC-14,
    BR-012, BR-013, AM-09).
  - No delete operation exists: a created membership persists and no delete
    action/route is available (AC-17, BR-014).

**Client membership history (AC-7, FR-004)**
- In `MembershipManagementTest` or a dedicated test: the
  `MembershipsRelationManager` on the client's record shows all memberships of
  the client, including past states, in chronological order by `start_date`
  (AC-7, FR-004, C-08).

**Renewal (AC-8, AC-9, FR-005, BR-011)**
- Renewing an `active` or `expired` membership creates a NEW membership record
  for the same client and plan with status `pending` and the new period; the
  original record is not modified (AC-8, FR-005, BR-011). Defaults: start date
  = day after previous end date; duration = previous duration (AM-08, OQ-05
  design default).
- Renewing a `pending` or `cancelled` membership is rejected (AC-9, ERR-005,
  AM-08, BR-009).
- Renewing against a now-inactive plan is rejected (ERR-001, BR-012).

**Authorization / Policy (AC-16, AC-17)**
- `tests/Feature/Admin/MembershipPolicyTest.php`:
  - ADMIN can `viewAny`/`view`/`create`/`update` memberships; TRAINER and
    CLIENT cannot (AC-16, ERR-006, BR-015).
  - A multi-role ADMIN + CLIENT user can manage memberships (SPEC-001 BR-002).
  - `MembershipPolicy` has no `delete` ability for anyone (AC-17, BR-014).
  - TRAINER/CLIENT receive 403 on `/admin/memberships` routes; the Memberships
    navigation is not visible to them (asserted server-side, AGENTS.md §17).

**Activation contract (AC-15, FR-008, BR-006)**
- `tests/Feature/Membership/ActivationContractTest.php` (direct model tests):
  - `activate()` on a `pending` membership within its period sets status
    `active` (FR-008, BR-006).
  - `activate()` on an `active`, `expired` or `cancelled` membership throws
    (AC-15: only from `pending`).
  - `activate()` on a `pending` membership whose end date has passed throws
    (AC-15: only while within the validity period).
  - The transition is not exposed as any Filament action and the policy has no
    `activate` ability (AC-15: not callable as an ADMIN UI action).

**Expiry (AC-12, BR-007, ADR-004)**
- `tests/Feature/Membership/ExpiryCommandTest.php`:
  - Running `php artisan memberships:expire` (or `Artisan::call`) marks
    `pending`/`active` memberships with `end_date < today` as `expired`
    (AC-12, BR-007).
  - Memberships with `end_date >= today` are unchanged; `expired`/`cancelled`
    memberships are untouched; the command is idempotent (ADR-004).

**Unit**
- `tests/Unit/MembershipTest.php`:
  - `computeEndDate` and the `creating` hook: `end_date` equals
    `start_date + duration_days - 1` (BR-003, AM-07); the hook does not
    overwrite an explicit `end_date`.
  - Status constants match the four states (BR-004).
  - `activate()`/`cancel()` rules as above.
  - Relationships: `client()`, `plan()` navigation; `Client::memberships()`
    and `Plan::memberships()`.
  - The DB FK constraints reject a membership with a nonexistent client/plan
    (BR-002, ERR-007).

All authorization assertions are server-side (AGENTS.md §17); no test relies on
frontend visibility.

---

## 9. Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Assumptions AM-01..AM-10 are unconfirmed (SPEC-004 §14.1). | If PO changes them, parts of the design change (e.g., AM-04 adds a uniqueness restriction; AM-08 changes renewal defaults; AM-10 allows reactivation). | Keep implementation isolated: the state constants, `activate()`/`cancel()` guards, the renewal Action defaults and the schema are the only touch points. Block Implementation until PO confirms §12 items. |
| Expiry relies on the Laravel scheduler (cron) running daily. | If the scheduler is not deployed, memberships with a passed end date stay `active` until the next run; `activate()` still rejects post-period activations (AC-15), but list filters may briefly show stale `active`. | ADR-004 documents the deployment requirement; the command is idempotent and the daily run at 00:05 keeps the window minimal. The business rule is day-granular (end date inclusive), so a daily run is sufficient. |
| OQ-01 (access rule D-05) is undefined. | What an active membership grants (attendance/booking) is out of scope; consumers (SPEC-007/008) must not be built on assumptions. | This design provides only `status` + `isActive()` as the contract consumed later; no access logic is introduced (BR-016). |
| OQ-05 (renewal defaults) is unconfirmed. | The renewal modal defaults (start = day after previous end date; duration = previous duration) are design defaults. | Defaults live in the `RenewMembership` Action as parameter defaults, trivially changeable once the PO decides. |
| Status stored as string (no DB enum). | A typo could store an invalid status via a non-validated write path. | All write paths set status via the model constants or the DB default; form/action validation restricts the create path; no raw SQL enum (consistent with ADR-003's validation-first stance). |
| No DB CHECK on `duration_days > 0` / `end_date >= start_date`. | A raw write path could store invalid data. | All MVP write paths go through the Filament form / `RenewMembership` (validated) and the model hook; same trade-off as ADR-003. |
| `restrictOnDelete` FKs to clients/plans. | If a future requirement ever hard-deletes a client/plan, the FK blocks it. | Clients/plans are never hard-deleted (SPEC-002 BR-006, SPEC-003 BR-004); the restrict guard enforces the preservation pattern. |
| Timezone handling of `today()` in the expiry command. | `end_date` is a date; `today()` uses the app timezone. | Use the application's configured timezone consistently in the command and in `activate()`'s period check. |

---

## 10. Alternatives Considered

1. **Expiry computed on read vs. materialized by a scheduled command**
   — Computing `expired` on read (e.g., an accessor that treats past-end_date
   `pending`/`active` as expired) would always be current without a scheduler,
   but makes every status query/filter more complex (FR-002 status filter,
   SPEC-005 cuota generation, SPEC-007/008 access rules would each re-derive
   the effective status) and leaves the stored status ambiguous. Materializing
   via a daily idempotent command keeps the status column authoritative,
   matches the spec's data-changes framing ("`pending`/`active → expired`" as a
   status modification) and gives every consumer a plain queryable value. See
   ADR-004.
2. **Status as a PostgreSQL native enum vs. string column with model
   constants** — A native enum adds raw SQL and makes future state changes
   (e.g., adding `frozen`) migration-heavy; the project avoids raw SQL
   constraints (ADR-003) and uses string constants for roles (`Role::ADMIN`,
   ADR-001). String + constants chosen.
3. **`CreateMembership` / `CancelMembership` Actions** — plain CRUD plus a
   simple state transition on a single record needs no explicit use case;
   matching the SPEC-001/002/003 precedent (`UserResource`/`ClientResource`/
   `PlanResource` perform CRUD directly; only multi-entity transactional
   operations get Actions — `ProvisionClientUser`). Renewal (creates a new
   record with defaults from the source) is the only operation that gets an
   explicit Action.
4. **Dedicated `renew` / `activate` policy abilities** — renewal creates a new
   record, so the existing `create` ability authorizes it (identical role
   set); activation is a system-internal contract with no user authorization
   (SPEC-004 §9). Extra abilities would add noise without enforcement value.
5. **Edit membership page** — the Specification defines no edit operation;
   renewal creates new records and cancellation is terminal. An edit page would
   invent functionality (AGENTS.md §6: never invent business rules).
6. **Client history as a filter inside `MembershipResource` vs. a
   RelationManager on `ClientResource`** — a RelationManager matches the
   spec's FR-004 framing ("view all memberships of a client") and the Filament
   convention of displaying related records from the parent record.
7. **Events (e.g., `MembershipActivated`)** — no consumer exists yet
   (ARCHITECTURE §10); the activation contract is a synchronous model method
   that SPEC-005 calls directly. Events can be introduced when SPEC-007/008
   define consumers.

---

## 11. Decision

Use the established SPEC-001/002/003 conventions throughout:

- **Persistence:** a new `memberships` table with required FKs to `clients`
  and `plans`, `start_date`, `end_date`, `duration_days` and a string `status`
  defaulting to `pending`. No monetary columns (SPEC-005 owns amounts via
  ADR-003). The existing schema is untouched.
- **Period invariant (BR-003):** `end_date = start_date + duration_days - 1`
  computed by the model's `creating` hook via a static `computeEndDate()`
  helper — a single source of truth for every write path.
- **State machine (BR-004/005/008/009):** four string constants on the model;
  `activate()` (contract for SPEC-005, FR-008) and `cancel()` (FR-006) are
  model methods that enforce their state rules and throw `DomainException` on
  violation.
- **Expiry (BR-007):** a daily scheduled command `memberships:expire`
  materializes `expired` for `pending`/`active` memberships whose end date has
  passed (ADR-004).
- **Authorization:** `MembershipPolicy` (viewAny/view/create/update = ADMIN
  only, no delete, no `activate` ability) on top of the existing
  `User::hasRole` helpers (ADR-001).
- **UI:** Filament `MembershipResource` with list/create/view pages, no edit
  page, status badge (FR-007), search/filters per FR-002, `Cancel` and `Renew`
  actions; a `MembershipsRelationManager` on `ClientResource` for FR-004.
- **Renewal:** explicit `App\Actions\RenewMembership` (ADMIN via `create`
  ability; validates source status, plan activity and duration; creates a new
  `pending` record with the same client/plan and a new period; original record
  untouched).
- **No events, no queued jobs, no new routes, no new seeders, no external
  integrations.**

---

## 12. Pending PO Confirmations

These items are carried from SPEC-004 and must be confirmed before
Implementation (or at latest before Review). This design does not silently
resolve them.

### Assumptions (SPEC-004 §14.1)

| ID | Assumption | Impact on this design |
| --- | --- | --- |
| AM-01 | Cuota model: auto-generated cuota per period, staff may edit pending cuota amount (SPEC-005 implements it). | No cuota concept here; only the consequence that a membership starts `pending` (BR-005). |
| AM-02 | Period = fixed duration in days from start date; manual renewal; renewal = new record. | `start_date` + `duration_days` fields; `RenewMembership` creates a new record (BR-003, BR-011). |
| AM-03 | Four states: pending/active/expired/cancelled; no freeze. | Four constants; no extra states (BR-004). |
| AM-04 | Multiple concurrent memberships allowed, including several active. | No uniqueness/overlap restriction (BR-010). |
| AM-05 | Activation only after a confirmed first cuota payment (SPEC-005 triggers). | `Membership::activate()` model contract; not a UI action (BR-006, FR-008). |
| AM-06 | Membership management is ADMIN-only; records never hard-deleted. | `MembershipPolicy` rules; no delete policy/action (BR-014, BR-015). |
| AM-07 | Period arithmetic: `end_date = start_date + duration_days - 1`; positive integer duration. | `computeEndDate()` + creating hook; `minValue(1)` validation (BR-003, ERR-003). |
| AM-08 | Renewal available only for active/expired; pre-fills same client/plan; new start date defaults to day after previous end date. | `RenewMembership` guards and defaults (FR-005, ERR-005). Duration default (previous duration) is an OQ-05 design default. |
| AM-09 | Plan edits/deactivation don't affect existing memberships; inactive plans unusable for new/renewals; cuota amount fixed at generation (SPEC-005). | `plans.is_active` gates create/renew; no price snapshot on membership (BR-012, BR-013). |
| AM-10 | `expired`/`cancelled` are terminal; late payment cannot reactivate; recovery = new membership. | `activate()` only from `pending`; no reactivation path (BR-009). |

### Open questions (SPEC-004 §14.2)

| ID | Question | Impact on this design |
| --- | --- | --- |
| OQ-01 | Access rule (D-05): what does an active membership grant, grace period? | Out of scope; this design exposes only `status`/`isActive()` for SPEC-007/008 (BR-016). |
| OQ-02 | Cuota amount: plan price at generation vs. snapshot at creation? | SPEC-005 decision; this design stores no price on the membership (BR-013). |
| OQ-03 | Late payment for an expired membership: retroactive activation vs. new membership? | Design assumes terminal (AM-10): `activate()` only from `pending`; SPEC-005 mechanics decide payment handling. |
| OQ-04 | Prepayment of several periods: one longer membership, several records, or several cuotas? | SPEC-005/multi-cuota decision; this design imposes no restriction (BR-010). |
| OQ-05 | Renewal defaults: new start date (day-after-end vs. today) and duration default? | Design default: start = day after previous end date; duration = previous duration (AM-08); both ADMIN-editable in the modal. |
| OQ-06 | TRAINER read access to memberships? | Design default: denied (BR-015/AM-06); single `MembershipPolicy` touch point if PO grants it. |
| OQ-07 | Additional membership fields (membership number, enrolled-since, notes, cancellation reason)? | Not modeled (SPEC-004 §10); additive columns/forms later. |
| OQ-08 | Calendar-month duration option needed? | Not modeled; days only (AM-07). |
| OQ-09 | Enrollment fee (matrícula) charged at membership creation? | SPEC-005 cuota decision; not stored on membership (BR-013). |
| OQ-10 | Interim indication that activation is pending SPEC-005 implementation? | Operational note only; status badge already shows `pending` (FR-007); no extra UI designed. |

### Additional design notes flagged for confirmation

- The `expired` state is materialized by a daily scheduled command rather than
  computed on read (Architect decision per BR-007; recorded in ADR-004). The
  scheduler must run in every environment.
- Status is stored as a string column with model constants, not a PostgreSQL
  enum (Architect decision per SPEC-004 §10).
- The `pending → active` transition is a model method (`activate()`) invoked by
  SPEC-005; it is not exposed in any UI and has no policy ability.
- Renewal reuses the `create` policy ability rather than introducing a
  dedicated `renew` ability (identical ADMIN-only authorization).

---

## 13. Related Documents

- Specification: `docs/specs/SPEC-004.md`
- Architecture decision: `docs/adr/ADR-001.md`, `docs/adr/ADR-002.md`,
  `docs/adr/ADR-003.md`, `docs/adr/ADR-004.md`
- Architecture: `docs/architecture/SPEC-001.md`, `docs/architecture/SPEC-002.md`,
  `docs/architecture/SPEC-003.md`, `ARCHITECTURE.md` (§7 Actions, §8 Models,
  §10 Events, §14 Plans/Memberships/Payments separate, §20 simplest correct
  architecture)
- Product: `docs/product/product-definition-v0.1.md`
- Domain: `docs/domain/domain-model-v0.1.md` (Membership, C-05/C-08)
- Requirements analysis: `docs/requirements/analyst-pass-001.md` (§5.5
  Memberships, §5.6 Cuotas, D-02, D-03, D-04, D-05, D-06, D-16, T-02/T-03,
  E-01/E-02/E-03/E-04)
- Development rules: `AGENTS.md`
- Workflow state: `docs/sdd/state.yaml`
