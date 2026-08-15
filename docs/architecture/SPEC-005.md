# Architecture — SPEC-005

## 1. Feature

Payments & Cuotas for the gym management system:

- the system auto-generates exactly one cuota per membership when a membership
  is created or renewed (FR-001, BR-001, NC-02), with `amount = plans.price` at
  generation time (BR-002) and status `pending` (BR-003);
- staff (ADMIN/TRAINER) may edit the amount of a `pending` cuota (FR-002,
  D-02 option 2, BR-012);
- staff register manual payments (cash or bank transfer only — D-15 option 1,
  BR-004) against a `pending` cuota; the payment is persisted directly
  `confirmed` (BR-005, D-16 option 1), the amount must equal the cuota amount
  (full payment only — NC-01, BR-014), and the cuota becomes `paid` (BR-003);
- when the first (and only) cuota of a `pending` membership is paid, the
  SPEC-004 activation contract `Membership::activate()` is invoked and the
  membership becomes `active` (FR-006, BR-008, SPEC-004 FR-008/BR-006); a late
  payment never reactivates an `expired` membership (AM-10, NC-04);
- cancelling a membership cascades to its still-`pending` cuotas, which become
  `cancelled` (BR-015, NC-04);
- cuotas and payments are never hard-deleted; a confirmed payment is immutable
  (BR-006, BR-009, PY-05).

This is the fifth Specification of the MVP. It builds on SPEC-001/002/003/004
(User/Role/Client/Plan/Membership models, `role_user` pivot, policies, Filament
resources, `RenewMembership`/`ProvisionClientUser`, `Membership::activate()` /
`cancel()`, `memberships:expire`; ADR-001/002/003/004). Mercado Pago is EXCLUDED
from the backlog by PO decision (SPEC-014): this design defines no payment
provider, no checkout, no webhooks, no credentials and no `pending`/`failed`
payment produced by any SPEC-005 flow.

---

## 2. Specification

Reference:

`docs/specs/SPEC-005.md`

Status note: SPEC-005 is approved for the architecture phase
(`docs/sdd/state.yaml`, status `spec_ready`, architect `in_progress`). The gates
D-02 (option 2), D-15 (option 1) and D-16 (option 1) are PO pre-approved (NIGHT
MODE); the business decisions NC-01..NC-04 are resolved by the PO on 2026-08-15.
The flagged analyst assumptions PY-01..PY-06 (§14.2) and the non-blocking open
questions OQ-01..OQ-03 (§14.3) remain and are carried to §12.

Boundary note: this design closes the "cuota gap" (T-03) and the payment
lifecycle gap (T-04) identified in SPEC-004. The client-facing display of a
client's own payments/cuotas is SPEC-013 (out of scope, §12 of the spec).

---

## 3. Affected Modules

- **Payments & Cuotas** (new module): the `cuotas` and `payments` tables, the
  `Cuota` and `Payment` models, `CuotaPolicy` and `PaymentPolicy`, the
  `CuotaResource` and `PaymentResource` Filament resources, and the
  `RegisterPayment` Action.
- **Memberships** (existing module, additive changes only): the `Membership`
  model gains a `cuota(): HasOne` relationship, a `created` model hook that
  auto-generates the membership's single cuota (FR-001, ADR-005), and an
  extended `cancel()` that cascades `pending → cancelled` to the membership's
  pending cuota (BR-015). The `CreateMembership` Filament page gains a small
  transaction wrapper so membership + cuota commit atomically (ADR-005). No
  schema change to the `memberships` table.
- **Cross-cutting authorization foundation** (no new module): two new policies
  extend the existing `User::hasRole`/`hasAnyRole` pattern (ADR-001). They are
  the first policies to grant TRAINER access to a commercial record (BR-011,
  PY-01).
- **Clients / Plans** (no change): payments/cuotas are reached through
  membership → cuota; no new relationship, column or resource change is
  required on `Client` or `Plan` (C-07, BR-010). (An optional `ClientResource`
  relation manager for payment history is a presentation choice — OQ-03, §5.)

No changes are made to: auth scaffolding, `EnsureUserHasRole`,
`AdminPanelProvider`, seeders, the `role_user` pivot, the
`users`/`roles`/`clients`/`plans`/`memberships` tables, `UserResource`,
`ClientResource`, `PlanResource`, or the `memberships:expire` command (its
expiry behavior is unchanged; SPEC-005 only adds cuota generation on creation
and cuota cancellation on membership cancellation).

---

## 4. Application Flow

```text
Presentation (web)
    ↓
Admin panel: /admin — Filament panel (ADMIN | TRAINER gate via canAccessPanel)
    ↓
CuotaResource (list/view; edit-amount row action)
PaymentResource (list/create/view)
    ↓
Application
    ↓
CuotaPolicy / PaymentPolicy (ADMIN | TRAINER)      RegisterPayment Action
    ↓
Domain
    ↓
Cuota model (pending/paid/cancelled; markPaid()/cancel()/updateAmount())
Payment model (confirmed; immutable)      Membership model (activate()/cancel() + cuota()/created hook)
    ↓
Persistence
    ↓
PostgreSQL: cuotas (new), payments (new); memberships/plans/clients/users (existing)
```

Concrete flows:

1. **Cuota generation (FR-001, ADR-005)** — when a membership is created
   (Filament `CreateMembership`) or renewed (`RenewMembership`), the
   `Membership` `created` hook generates one cuota with `amount = plan->price`
   and status `pending`. The membership stays `pending` awaiting that first
   cuota payment (SPEC-004 BR-005). Both write paths run inside a `DB::transaction`.
2. **Cuota amount edit (FR-002, optional)** — staff edit the amount of a
   `pending` cuota (row action "Edit amount"). The amount must remain positive
   (ERR-006); a non-`pending` cuota is not editable (ERR-007, BR-012).
3. **Payment registration (FR-004)** — staff open a pending cuota (from
   `CuotaResource` or `PaymentResource` create) and register a payment: cuota
   (pre-selected), amount (pre-filled with the cuota amount; must equal it —
   NC-01), method (`cash` | `transfer`), payment date (not in the future), and
   for transfers a `reference` (BR-007). `RegisterPayment` validates, then in a
   transaction persists the payment `confirmed` with `recorded_by = auth()->id()`.
4. **Cuota satisfaction (FR-006)** — the single matching confirmed payment
   satisfies the cuota; `Cuota::markPaid()` sets status `paid` and `paid_at`.
5. **Activation (FR-006, BR-008)** — if the paid cuota's membership is
   `pending`, `RegisterPayment` calls `Membership::activate()` inside the same
   transaction. If the membership's end date has passed, `activate()` throws
   `DomainException`, which the Action catches (the payment and paid cuota
   stand; the membership is never reactivated — AF-005, ERR-009).
6. **Membership cancellation cascade (BR-015)** — `Membership::cancel()` sets
   the membership `cancelled` and, if its cuota is still `pending`, calls
   `Cuota::cancel()` (status `cancelled`, uncollectible).
7. **Viewing (FR-003, FR-005, FR-007)** — staff list/search/view cuotas and
   payments, always seeing the current statuses; a cuota's detail shows its
   payment history.

---

## 5. Components

### Controllers

None new.

Cuota and payment management lives entirely inside Filament resources (the
admin-side controller, same convention as `MembershipResource`). No web routes
or HTTP controllers are added. Relation managers are the Filament-side
controllers for related-record display.

### Actions / Use Cases

**`App\Actions\RegisterPayment`** (new — the single non-CRUD operation of this
Specification)

- Input: `int $cuotaId`, `string $amount`, `string $method`,
  `string $paymentDate`, `?string $reference = null`, `?string $notes = null`.
- Behavior:
  1. Authorize: `Gate::authorize('create', Payment::class)` — ADMIN | TRAINER
     (BR-011). Server-side defense in depth (AGENTS.md §17).
  2. Validate (ERR-001..ERR-005, ERR-010, ERR-011):
     - the cuota exists (`ERR-001` when `Cuota::find($cuotaId)` is null);
     - the cuota is `pending` (`ERR-011`; a `paid` or `cancelled` cuota is not
       payable — BR-003, BR-014, BR-015);
     - `amount` is `required|numeric|min:0.01` (ERR-002) and equals the cuota
       amount (`ERR-010`; compare normalized two-decimal strings, e.g.
       `number_format(..., 2, '.', '')`, to avoid floating-point drift);
     - `method` is `required|in:cash,transfer` (ERR-003, BR-004);
     - `reference` is `required_if:method,transfer` (ERR-005, PY-04);
     - `paymentDate` is `required|date|before_or_equal:today` (ERR-004, PY-03);
     - `notes` is nullable string.
  3. Persist (transactional):
     - create the `Payment` with `amount`, `method`, `payment_date`,
       `reference`, `notes` and `recorded_by = auth()->id()`; `status` defaults
       to `confirmed` (BR-005, model default);
     - `$cuota->markPaid()` (status `paid`, `paid_at = now()`);
     - if `$cuota->membership->status === Membership::STATUS_PENDING`, attempt
       `$cuota->membership->activate()` inside a `try/catch (DomainException)`
       that swallows the end-date-passed failure (AF-005, ERR-009); a payment on
       an `expired`/`cancelled` membership never reactivates it (AM-10, NC-04).
- Returns the created `Payment`.

Cuota generation is a model hook, not an Action (ADR-005). Cuota amount edit is
a model method invoked by a thin Filament action (same pattern as
`Membership::cancel()`). An explicit `CreateCuota`/`EditCuotaAmount` Action
would be an unnecessary abstraction (AGENTS.md §9-10, ARCHITECTURE §7).

### Models

**`App\Models\Cuota`** (new)

- Table: `cuotas`.
- Fillable: `membership_id`, `amount`, `status`, `paid_at`. (Standard paths set
  status via `markPaid()`/`cancel()`; the factory and those methods use
  `status`/`paid_at`.)
- Defaults: `$attributes['status'] = self::STATUS_PENDING`.
- Casts:
  - `amount` → `'decimal:2'` (ADR-003);
  - `paid_at` → `'datetime'`;
  - `status` → plain string (no cast), validated against the model constants.
- Constants (single source of truth, BR-003):
  - `Cuota::STATUS_PENDING = 'pending'`
  - `Cuota::STATUS_PAID = 'paid'`
  - `Cuota::STATUS_CANCELLED = 'cancelled'`
- Relationships:
  - `membership(): BelongsTo` → `Membership` (FK `membership_id`);
  - `payments(): HasMany` → `Payment` (ordered by `payment_date` for history).
- Simple domain behavior (ARCHITECTURE §8):
  - `markPaid(): void` — throws `DomainException` unless `pending` (BR-014,
    ERR-011); sets `status = paid`, `paid_at = now()` and saves.
  - `cancel(): void` — throws `DomainException` unless `pending` (BR-003,
    BR-015); sets `status = cancelled` and saves. Called by
    `Membership::cancel()` (which guards on pending first).
  - `updateAmount(string $amount): void` — throws `DomainException` unless
    `pending` (ERR-007, BR-012); sets `amount` and saves. Positivity is
    enforced by the Filament form (ERR-006).
  - `isPending() / isPaid() / isCancelled(): bool` helpers (FR-007 display).
  - `scope pending()` for listing payable cuotas.
- No delete scope/method: deletion is not offered (BR-009).

**`App\Models\Payment`** (new)

- Table: `payments`.
- Fillable: `cuota_id`, `amount`, `method`, `payment_date`, `reference`,
  `notes`. (`status` is NOT fillable: it always defaults to `confirmed` in the
  manual flow; `recorded_by` is written by the Action, not mass-assigned.)
- Defaults: `$attributes['status'] = self::STATUS_CONFIRMED`.
- Casts:
  - `amount` → `'decimal:2'` (ADR-003);
  - `payment_date` → `'date'`;
  - `status` → plain string.
- Constants:
  - `Payment::STATUS_PENDING = 'pending'` (reserved, never written by SPEC-005)
  - `Payment::STATUS_CONFIRMED = 'confirmed'`
  - `Payment::STATUS_FAILED = 'failed'` (reserved)
  - `Payment::METHOD_CASH = 'cash'`
  - `Payment::METHOD_TRANSFER = 'transfer'`
- Relationships:
  - `cuota(): BelongsTo` → `Cuota` (FK `cuota_id`; the membership is reached
    through `$payment->cuota->membership` — C-06/C-07);
  - `recordedBy(): BelongsTo` → `User` (FK `recorded_by`; PY-06).
- No transition/`update`/`delete` methods: a confirmed payment is immutable in
  the MVP (BR-006, PY-05). `isConfirmed(): bool` helper for display.

**`App\Models\Membership`** (modified additively)

- New relationship:
  - `cuota(): HasOne` → `Cuota` (exactly one cuota per membership, NC-02).
- New `booted()` hook: a `static::created` closure that creates the cuota with
  `amount = $this->plan->price` and `status = pending` (FR-001, BR-001,
  BR-002, ADR-005). Runs inside whichever transaction is active.
- Extended `cancel()`: wraps the transition in `DB::transaction`; after setting
  `status = cancelled`, if `$this->cuota !== null && $this->cuota->status ===
  Cuota::STATUS_PENDING`, calls `$this->cuota->cancel()` (BR-015, NC-04).
- No other change to `activate()`, `isActive()`, `scopeQualifying()`,
  `computeEndDate()`, `client()`, `plan()` or the fillable/casts.

### Policies

**`App\Policies\CuotaPolicy`** (new) — mirrors the `UserPolicy`/`PlanPolicy`/
`MembershipPolicy` pattern, but grants ADMIN **and** TRAINER (BR-011, PY-01):

- `viewAny` / `view`: `$user->hasAnyRole([Role::ADMIN, Role::TRAINER])`
  (FR-003, BR-011).
- `update`: same check (covers the pending-amount edit, FR-002, D-02 option 2).
- No `create`: cuotas are never created manually — they are auto-generated by
  the `Membership` `created` hook (FR-001, ADR-005).
- No `delete`: cuotas are never hard-deleted (BR-009).

**`App\Policies\PaymentPolicy`** (new):

- `viewAny` / `view`: `$user->hasAnyRole([Role::ADMIN, Role::TRAINER])`
  (FR-005, BR-011).
- `create`: same check (register a payment, FR-004, D-15 option 1).
- No `update` / `delete`: a confirmed payment is immutable and never hard-deleted
  (BR-006, BR-009, PY-05).

Authorization matrix (SPEC-005 §9):

| Operation | Anonymous | ADMIN | TRAINER | CLIENT |
| --- | --- | --- | --- | --- |
| View cuotas / payments (list, search, detail) | Denied | Allowed | Allowed | Denied |
| Register a payment (cash / transfer) | Denied | Allowed | Allowed | Denied |
| Edit a pending cuota amount | Denied | Allowed | Allowed | Denied |
| Trigger pending → active membership transition | Denied | Denied (only via confirmed cuota payment) | Denied | Denied |

A multi-role user receives the union of permissions (SPEC-001 BR-002). CLIENT and
Anonymous never reach the admin panel (`canAccessPanel` = ADMIN | TRAINER), and
the policies return false for them anyway (403 on any direct URL — ERR-008).
Authorization is enforced server-side; frontend hiding is never the enforcement
(AGENTS.md §17).

### Filament

**`App\Filament\Resources\CuotaResource`** (new; pages `ListCuotas`,
`ViewCuota`) — no `CreateCuota` (auto-generated) and no `EditCuota` page (the
only edit is the pending amount, via a row action).

- Table (FR-003, FR-007):
  - Columns: `membership.client.full_name` (searchable, label "Client"),
    `membership.client.dni` (searchable, "DNI"), `membership.plan.name`
    (searchable, "Plan"), `membership.start_date` / `membership.end_date`
    ("Period"), `amount` (money), `status` (badge — pending=warning,
    paid=success, cancelled=danger), `paid_at`.
  - Filters: `status` `SelectFilter` (three constants); `amount` range `Filter`
    (two `TextInput`s → `whereBetween('amount', ...)`, FR-003).
  - Row actions: `View`; `EditAmount` (label "Edit amount", visible when
    `status === pending`, modal with a single positive `amount` input; authorizes
    `update`; calls `$record->updateAmount($amount)` after the form validates
    `numeric|min:0.01` — ERR-006, ERR-007). No delete; `bulkActions([])` (BR-009).
- Infolist (`ViewCuota`, FR-003): `membership.client.full_name`, `membership.client.dni`,
  `membership.plan.name`, `membership.start_date`/`end_date`, `amount`, `status`
  (badge), `paid_at`; plus a `PaymentsRelationManager` (payment history).
- Navigation: `navigationIcon` (e.g. `heroicon-o-banknotes`),
  `navigationGroup = 'Commercial'` (consistent with `MembershipResource`).

**`App\Filament\Resources\PaymentResource`** (new; pages `ListPayments`,
`CreatePayment`, `ViewPayment`) — no `EditPayment` (immutable, BR-006, PY-05).

- Table (FR-005, FR-007):
  - Columns: `cuota.membership.client.full_name` (searchable, "Client"),
    `cuota.membership.client.dni` (searchable, "DNI"), `cuota.membership.plan.name`
    ("Plan"), `amount` (money), `method` (badge), `payment_date`, `reference`
    (placeholder "—"), `status` (badge), `recordedBy.name` ("Recorded by").
  - Filters: `method` `SelectFilter` (`cash`/`transfer`); `status` `SelectFilter`
    (three constants incl. reserved); `payment_date` range `Filter`.
  - Row actions: `View` only. No edit/delete; `bulkActions([])` (AC-11).
- Form (`CreatePayment`, FR-004):
  - `cuota_id` — `Select` of **pending** cuotas only (options built from
    `Cuota::where('status', Cuota::STATUS_PENDING)` with labels like
    "Client — Plan — $amount"); required; `exists:cuotas,id` (ERR-001). Reactive
    (`->live()`).
  - `amount` — `TextInput` numeric, required, `min:0.01` (ERR-002), pre-filled
    with the selected cuota's amount (reactive default). Editable so a mismatch
    can be attempted and rejected (ERR-010, AC-7); `RegisterPayment` re-validates
    equality server-side (defense in depth).
  - `method` — `Select` `cash`/`transfer`, required (ERR-003, BR-004).
  - `reference` — `TextInput`, `required_if:method,transfer` (ERR-005, PY-04).
  - `payment_date` — `DatePicker`, required, `before_or_equal:today`, default
    today (ERR-004, PY-03).
  - `notes` — `Textarea`, optional (BR-007).
  - Submit: `app(RegisterPayment::class)->handle($data['cuota_id'],
    $data['amount'], $data['method'], $data['payment_date'],
    $data['reference'] ?? null, $data['notes'] ?? null)`.
- Infolist (`ViewPayment`, FR-005): `cuota`/`membership`/`client`/`plan` context,
  `amount`, `method`, `payment_date`, `reference`, `notes`, `status`,
  `recordedBy.name`, `created_at`.

**Relation managers** (presentation choice, OQ-03):

- `CuotaResource` gets a `PaymentsRelationManager` (FR-003 "payment history";
  read-only).
- `MembershipResource` `ViewMembership` MAY show a read-only payments/cuota
  relation manager; this is optional and not required by any AC. The Client-side
  "payments" relation manager (OQ-03) is deferred unless the PO asks for it;
  payments remain reachable via `CuotaResource`/`PaymentResource`.

### Events

None required.

Payment registration, cuota satisfaction and activation are a single
synchronous transactional operation (ARCHITECTURE §10: events should not replace
straightforward synchronous logic). If SPEC-007/008 later need to react to
activation, an event can be introduced there. The `ARCHITECTURE §10` example
`PaymentRegistered`/`MembershipPaid` is deliberately NOT introduced until a
consumer exists.

### Jobs

None queued.

No slow or external operation exists (no provider, no email, no notification —
ARCHITECTURE §11). The `memberships:expire` scheduled command is unchanged.

### Routes

No new routes. Filament auto-registers `/admin/cuotas*` and `/admin/payments*`
via `discoverResources`.

### Seeders

None new. Cuotas are generated by the membership `created` hook; payments are
registered by staff. The existing `RoleSeeder`/`AdminUserSeeder` are unchanged.

### Factories

`database/factories/CuotaFactory.php` and `PaymentFactory.php` (new), mirroring
`MembershipFactory`. Because `Membership` auto-generates its cuota (ADR-005),
SPEC-005 tests typically obtain the cuota via `$membership->cuota` rather than
creating a second cuota for the same membership (the `membership_id` UNIQUE
constraint forbids duplicates). `CuotaFactory` is used in unit tests for the
model in isolation (creating the cuota directly, without relying on the
membership hook).

---

## 6. Data Changes

### Migrations

1. **`create_cuotas_table`** (new; next in sequence:
   `2026_08_15_000015_create_cuotas_table.php`):

   | Column | Type | Constraints |
   | --- | --- | --- |
   | `id` | bigint | PK |
   | `membership_id` | foreignId | NOT NULL, UNIQUE, FK → `memberships.id`, `restrictOnDelete` (BR-001, NC-02) |
   | `amount` | decimal(10,2) | NOT NULL, positive (BR-002, ADR-003) |
   | `status` | string | NOT NULL, default `'pending'` (BR-003); string + model constants, not a DB enum |
   | `paid_at` | timestamp | nullable, set when the cuota becomes `paid` (FR-006/FR-007, OQ-02) |
   | `created_at` / `updated_at` | timestamp | timestamps |

   - No `due_date` column: the period semantics (start/end) belong to the
     membership (SPEC-004 BR-003); there is no separate cuota due date and no
     payment-side grace period (SPEC-005 §10, NC-04).
   - The `UNIQUE` constraint on `membership_id` enforces NC-02 ("exactly one
     cuota per membership") at the database level; the `created` hook guarantees
     it in practice (ADR-005).
   - `restrictOnDelete` on `membership_id` is a defensive guard consistent with
     the preservation pattern (memberships are never hard-deleted; SPEC-004
     BR-014).

2. **`create_payments_table`** (new; `2026_08_15_000016_create_payments_table.php`):

   | Column | Type | Constraints |
   | --- | --- | --- |
   | `id` | bigint | PK |
   | `cuota_id` | foreignId | NOT NULL, FK → `cuotas.id`, `restrictOnDelete` (BR-007) |
   | `amount` | decimal(10,2) | NOT NULL, positive (BR-007, ADR-003) |
   | `method` | string | NOT NULL, `cash` \| `transfer` (BR-004); string + model constants |
   | `payment_date` | date | NOT NULL, not in the future (BR-007, PY-03) |
   | `reference` | string | nullable; required when `method = transfer` (PY-04) |
   | `notes` | text | nullable (BR-007) |
   | `status` | string | NOT NULL, default `'confirmed'` (BR-005, BR-006); `pending`/`failed` reserved for a future provider (SPEC-014, excluded) |
   | `recorded_by` | foreignId | NOT NULL, FK → `users.id`, `restrictOnDelete` (PY-06) |
   | `created_at` / `updated_at` | timestamp | timestamps |

   - `restrictOnDelete` on `cuota_id` and `recorded_by` preserves financial
     history (users and cuotas are never hard-deleted).
   - Index on `payment_date` to support the FR-005 date-range filter. The FK
     columns get their own indexes via `constrained()`.

No existing migration is modified. The `memberships`/`plans`/`clients`/`users`
tables are reused as-is; no monetary column is added to `memberships` or `plans`
(SPEC-004 §10, BR-010).

### Relationships

```text
clients 1 ── * memberships 1 ── 1 cuotas 1 ── * payments * ── 1 users (recorded_by)
                                   │
                                   * ── 1 plans
```

```text
cuotas.membership_id   → memberships.id (required, UNIQUE, restrictOnDelete)
payments.cuota_id      → cuotas.id      (required, restrictOnDelete)
payments.recorded_by   → users.id       (required, restrictOnDelete)
```

The membership is reached from a payment through its cuota
(`payment → cuota → membership`), keeping Plan / Membership / Cuota / Payment
separate (C-06, C-07, BR-010). `Membership::cuota()` is the inverse `hasOne`.

### Data lifecycle

- **Created:** one cuota per membership at membership creation/renewal
  (FR-001, ADR-005); one confirmed payment per satisfied cuota (FR-004).
- **Modified:** cuota `status` `pending → paid` via `markPaid()` (BR-003) and
  `pending → cancelled` via the membership-cancellation cascade (BR-015); cuota
  `amount` via `updateAmount()` on a pending cuota (FR-002); membership `status`
  `pending → active` via the SPEC-004 `activate()` contract (FR-006, BR-008).
  No other membership field is modified (BR-013).
- **Deleted:** none. No hard deletion of cuotas or payments (BR-009); no delete
  operation exists.

---

## 7. External Integrations

None.

SPEC-005 touches no external service. Mercado Pago is EXCLUDED (SPEC-014, PO
decision): no integration, no checkout, no webhooks, no credentials, no provider
configuration. No notification/email is sent by any payment or cuota operation.

---

## 8. Testing Strategy

Pest (PHPUnit) feature tests following the existing conventions (`tests/Pest.php`
helpers `role()`, `userWithRoles()`, `clientWithUser()`; `RefreshDatabase`;
Livewire component testing). New `CuotaFactory` and `PaymentFactory`.

**Cuota generation and amount edit (AC-1..AC-4, AC-14, AC-17, AC-18, FR-003)**
- `tests/Feature/Admin/CuotaManagementTest.php` (Livewire):
  - creating a membership auto-generates exactly one `pending` cuota with
    `amount = plan->price` (AC-1, AC-18 — no enrollment fee, NC-03); renewal
    auto-generates a new cuota for the new membership (AC-1, AC-17).
  - ADMIN/TRAINER edit a pending cuota amount; the change persists (AC-2).
  - zero/negative/invalid amount is rejected (AC-3, ERR-006).
  - editing a `paid`/`cancelled` cuota is rejected (AC-4, ERR-007).
  - a later plan price change/deactivation does not change an existing cuota
    amount (AC-14, BR-010).
  - cuota list/detail show status and payment history (FR-003).

**Payment registration (AC-5..AC-7, AC-10, AC-11, AC-16, AC-19, FR-005)**
- `tests/Feature/Admin/PaymentManagementTest.php` (Livewire):
  - register a cash payment (amount = cuota amount): payment persisted
    `confirmed` with recorded amount/method/date/`recorded_by`; cuota `paid`
    (AC-5).
  - register a transfer payment with required `reference` (AC-6).
  - reject nonexistent cuota, zero/negative amount, amount mismatch, invalid
    method, future/missing date, missing transfer reference (AC-7, AC-16).
  - every SPEC-005-created payment is `confirmed` (AC-10).
  - no edit/delete exists for payments (AC-11).
  - reject payment against a `cancelled` or already-`paid` cuota (AC-19,
    ERR-011).
  - payment list/detail show method/date/reference/notes/status/`recorded_by`
    (FR-005).

**Authorization (AC-12, AC-13, ERR-008, BR-011)**
- `tests/Feature/Admin/CuotaPolicyTest.php` and
  `tests/Feature/Admin/PaymentPolicyTest.php`:
  - ADMIN and TRAINER can `viewAny`/`view` (and cuota `update`, payment
    `create`); CLIENT cannot; no `delete` (and no cuota `create`, no payment
    `update`) ability exists for anyone (AC-12, AC-13, BR-011).
  - CLIENT/Anonymous receive 403 on `/admin/cuotas` and `/admin/payments`.

**Activation and cancellation cascade (AC-8, AC-9, AC-15, AC-19, ERR-009)**
- `tests/Feature/Payments/ActivationTest.php` (direct `RegisterMembership`-style
  Action/model tests, plus Livewire where UI is exercised):
  - paying the cuota of a `pending` membership within its period activates it
    (AC-8, FR-006, BR-008).
  - paying the pending cuota of an `expired` membership records the payment and
    the `paid` cuota but never reactivates the membership (AC-9, ERR-009).
  - paying the pending cuota of a `pending` membership whose end date has passed
    (the pre-expiry-command window) records the payment and the `paid` cuota but
    `activate()`'s `DomainException` is swallowed (AC-9, ERR-009, AM-10).
  - cancelling a membership transitions its pending cuota to `cancelled` and
    blocks further payment (AC-19, BR-015).
  - registering a payment does not modify the plan/client/user/membership record
    except the `pending → active` transition (AC-15, BR-010, BR-013).

**Unit**
- `tests/Unit/CuotaTest.php`: status constants; `markPaid()` (`pending → paid`
  only, throws otherwise); `cancel()` (`pending → cancelled` only, throws
  otherwise); `updateAmount()` (pending-only); `amount` `decimal:2` cast; the
  `membership_id` UNIQUE/one-cuota rule; relationships (`membership()`,
  `payments()`); generation via the `Membership` `created` hook.
- `tests/Unit/PaymentTest.php`: status constants incl. reserved `pending`/`failed`;
  default `confirmed`; method constants; `amount` `decimal:2` and
  `payment_date` casts; relationships (`cuota()`, `recordedBy()`); immutability
  (no status-transition/update/delete method exists).

### Existing tests to update (expected, not a regression)

- `tests/Feature/Admin/MembershipManagementTest.php` — the case "does not create
  any payment or cuota record … when creating a membership" is superseded by
  SPEC-005 AC-1: creating a membership now DOES create one cuota. Update its
  comment and assertions (assert `Cuota::count()` is 1 with
  `amount = plan->price`, no `Payment` created) while keeping the "no
  client/plan/user modification" assertions.
- `app/Actions/RenewMembership.php` docblock ("No event, payment or cuota is
  created") becomes stale: renewal now auto-generates a cuota via the model hook.

All authorization assertions are server-side (AGENTS.md §17); no test relies on
frontend visibility.

---

## 9. Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Assumptions PY-01..PY-06 are unconfirmed (SPEC-005 §14.2). | If the PO changes "staff = ADMIN+TRAINER" (PY-01), transfer-reference requirement (PY-04), immutability (PY-05) or audit field (PY-06), the policies/schema/Action change. | Isolate: the two policies, the `RegisterPayment` validation, and the `payments` columns are the only touch points. Carry the assumptions to §12 and do not silently resolve them. |
| Cuota auto-generation via the `created` hook adds a side effect to every membership creation (ADR-005). | Factory-created memberships in existing tests gain a cuota row; a stale SPEC-004 assertion remains. | Documented test update (§8); the hook is the single source of truth so no write path can forget the cuota. |
| Full-payment equality on decimal strings. | Float comparison could reject/miss mismatches. | Compare normalized two-decimal strings (`number_format(..., 2, '.', '')`) in `RegisterPayment`; amounts are `decimal:2` (ADR-003). |
| Payment immutability enforced only by convention (no edit/delete UI + no policy abilities). | A future code path could mutate a confirmed payment. | The model exposes no transition method; `status` is not fillable; policies register no `update`/`delete`; `restrictOnDelete` FKs preserve history. Documented as the immutability contract (BR-006, PY-05). |
| `recorded_by` depends on `auth()->id()`. | The Action must run in an authenticated staff context; CLI/tinker would have no user. | `RegisterPayment` is invoked only from the Filament form (authenticated). Tests act as a user. |
| TRAINER granted access to commercial records (first time). | TRAINER can see client names/DNIs and payment data; an ADMIN-only expectation elsewhere could be violated. | This is the PO-approved D-15/D-02 decision (PY-01); scoped to cuotas/payments only via the two new policies. No change to `MembershipPolicy`/`ClientPolicy`. |

---

## 10. Alternatives Considered

1. **Cuota generation: model `created` hook vs. explicit per-path** — see
   ADR-005. Model hook chosen (single source of truth, consistent with the
   `end_date` `creating` hook).
2. **`paid_at` stored vs. derived from the satisfying payment (OQ-02)** —
   deriving avoids a column but every consumer (FR-007 display, future reports)
   would re-join the payment; storing it (set by `markPaid()`) keeps `paid_at`
   a plain queryable value, consistent with the stored-state/materialization
   philosophy of ADR-004. Explicit column chosen; the payment's own
   `payment_date` still preserves the (possibly backdated) received date.
3. **One-cuota-per-membership: DB UNIQUE vs. model-level only (NC-02)** — a DB
   `UNIQUE` constraint is a strong, zero-cost backstop; the `created` hook
   guarantees at most one in practice. Both are used (constraint + hook).
4. **`Cuota::cancel()` strict vs. lenient** — strict (throws unless `pending`,
   mirroring `Membership::cancel()`) so misuse is caught; the `Membership::cancel()`
   cascade checks `pending` first so a paid cuota is never touched (BR-015).
5. **Amount field auto-filled read-only vs. editable** — read-only auto-fill is
   the nicer UX, but AC-7 requires the mismatch-rejection path to be exercisable;
   the amount field is editable (pre-filled) and `RegisterPayment` re-validates
   equality server-side (ERR-010). (A Developer may render it read-only if the
   server-side equality check is preserved and the AC-7 path is tested at the
   Action level.)
6. **`CreateCuota`/`EditCuotaAmount`/`CancelMembershipCascade` Actions** — cuota
   generation is a model hook, amount edit is a thin model method, and the
   cancellation cascade is part of `Membership::cancel()`; extra Action classes
   would be unnecessary abstraction (AGENTS.md §9-10, ARCHITECTURE §7). Only
   `RegisterPayment` (multi-entity transactional) gets an Action.
7. **Payment status as a writable lifecycle (`pending`/`failed`)** — the D-16
   statuses are reserved for a future provider (SPEC-014, excluded); no SPEC-005
   flow writes them (BR-005, BR-006). They exist only as model constants for
   forward-compatibility.
8. **Events (`PaymentRegistered`, `MembershipPaid`)** — no consumer exists
   (ARCHITECTURE §10); the flow is synchronous. Events can be introduced when
   SPEC-007/008 define consumers.

---

## 11. Decision

Use the established SPEC-001..004 conventions throughout:

- **Persistence:** new `cuotas` (membership FK UNIQUE, `amount` decimal(10,2),
  string `status` default `pending`, nullable `paid_at`; no `due_date`) and
  `payments` (cuota FK + `recorded_by` user FK, `amount`, `method`, `payment_date`,
  `reference`, `notes`, string `status` default `confirmed`) tables with
  `restrictOnDelete` FKs. No monetary column on `memberships`/`plans` (BR-010).
- **Cuota generation (ADR-005):** a `Membership` `created` hook generates the
  single cuota with `amount = plan->price`; the Filament create path gains a
  transaction wrapper so membership + cuota commit atomically.
- **State machines:** `Cuota` (pending/paid/cancelled) with `markPaid()` /
  `cancel()` / `updateAmount()` model methods throwing `DomainException` on
  violation; `Payment` confirmed-immutable with no transition method.
- **Payment registration:** explicit `App\Actions\RegisterPayment` (authorizes
  `create`, validates full-payment-only and the BR-007 field rules, persists the
  confirmed payment with `recorded_by`, marks the cuota paid, and invokes
  `Membership::activate()` — swallowing the end-date-passed `DomainException` so
  a late payment never reactivates).
- **Cancellation cascade (BR-015):** `Membership::cancel()` cancels a pending
  cuota within its transaction.
- **Authorization:** `CuotaPolicy` (viewAny/view/update = ADMIN|TRAINER; no
  create/delete) and `PaymentPolicy` (viewAny/view/create = ADMIN|TRAINER; no
  update/delete) on top of `hasAnyRole` (ADR-001).
- **UI:** Filament `CuotaResource` (list/view + edit-amount row action +
  payment-history relation manager) and `PaymentResource` (list/create/view, no
  edit) with status badges, search/filters per FR-003/FR-005.
- **No events, no queued jobs, no new routes, no new seeders, no external
  integrations.**

---

## 12. Pending PO Confirmations

Carried from SPEC-005 §14; not silently resolved.

### Assumptions (SPEC-005 §14.2)

| ID | Assumption | Impact on this design |
| --- | --- | --- |
| PY-01 | "Staff" = ADMIN and TRAINER (no RECEPTIONIST). | `CuotaPolicy`/`PaymentPolicy` grant ADMIN\|TRAINER (first TRAINER access to commercial records). |
| PY-02 | Manually registered payment persisted directly `confirmed`. | `Payment::$attributes['status'] = confirmed`; no pending/failed written. |
| PY-03 | Payment date valid and not in the future; backdating allowed. | `before_or_equal:today` rule (ERR-004). |
| PY-04 | Transfer requires a `reference`; cash optional; notes optional. | `required_if:method,transfer` (ERR-005); `reference`/`notes` nullable columns. |
| PY-05 | Confirmed payments immutable; refunds manual/out of system. | No `update`/`delete` policy; no edit page; model has no transition method. |
| PY-06 | Every payment stores `recorded_by` (audit). | `payments.recorded_by` NOT NULL FK; set by the Action from `auth()->id()`. |

### Open questions (SPEC-005 §14.3)

| ID | Question | Impact on this design |
| --- | --- | --- |
| OQ-01 | Backfill cuotas for memberships created before SPEC-005? | Not performed; cuotas are generated only for memberships created after deployment. Operational note; no data migration written. |
| OQ-02 | `paid_at` stored vs. derived? | Stored nullable timestamp, set by `markPaid()` (see §10 alt 2). |
| OQ-03 | Client-side payments relation manager? | Deferred (presentation choice); payments are reachable via `CuotaResource`/`PaymentResource`. |

### Additional design notes flagged for confirmation

- Statuses are stored as string columns with model constants (not DB enums),
  consistent with SPEC-004 §10 and ADR-003.
- No `due_date` on `cuotas` (SPEC-005 §10): the period lives on the membership.
- Cuota generation is a `Membership` `created` model hook (ADR-005), not an
  explicit Action; this changes the SPEC-004 "no cuota on create" expectation.
- The activation transition remains a model method invoked only by the payment
  path (SPEC-004 §9); no policy ability or UI exposes it.

---

## 13. Related Documents

- Specification: `docs/specs/SPEC-005.md`
- Architecture decision: `docs/adr/ADR-001.md`, `docs/adr/ADR-003.md`,
  `docs/adr/ADR-004.md`, `docs/adr/ADR-005.md`
- Architecture: `docs/architecture/SPEC-001.md`, `SPEC-002.md`, `SPEC-003.md`,
  `SPEC-004.md`, `ARCHITECTURE.md` (§7 Actions, §8 Models, §10 Events, §13-14
  Payments/Memberships separate, §20 simplest correct architecture)
- Product: `docs/product/product-definition-v0.1.md`
- Domain: `docs/domain/domain-model-v0.1.md` (Payment C-06, Membership C-05/C-08)
- Requirements analysis: `docs/requirements/analyst-pass-001.md` (§5.6 Cuotas,
  §5.7 Payments, D-02, D-15, D-16, T-03/T-04, E-02/E-03)
- Development rules: `AGENTS.md`
- Workflow state: `docs/sdd/state.yaml`
