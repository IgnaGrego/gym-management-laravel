# Architecture — SPEC-007

## 1. Feature

Bookings for the gym management system:

- a **Booking** is a reservation made by a client for a **turno** (the bookable
  entity under gate D-07 option 1; the domain's `Schedule → Session → Booking`
  hierarchy is NOT implemented in the MVP, SPEC-006 AS-02). It reserves ONE
  spot in ONE turno for ONE client (BR-001, BR-002);
- staff — **ADMIN and TRAINER** — create, list/filter, view and cancel bookings
  on behalf of clients from the admin panel (FR-001..FR-004, BR-012, BK-01,
  BK-02). Client-facing self-booking / self-cancellation / own-booking view
  belong to SPEC-013 (Client Portal) and are NOT implemented here (BK-01,
  BK-07, AF-006);
- **capacity enforcement**: a booking counts toward the turno's
  `capacity_limit`; a booking is rejected when the turno is full, and the check
  is enforced **atomically** so concurrent bookings can never oversell a turno
  (BR-008, ERR-007, ERR-011, D-07 option 1, SPEC-006 FR-009 deferred);
- **access gate (D-05 option 1)**: only clients with at least one ACTIVE
  membership (`end_date >= today`) may be booked; no grace period. The gate is
  evaluated **at booking time only** (BR-005, BK-08, BK-09);
- **booking lifecycle (D-08 package; NC-01 resolved)**: a booking is created
  `confirmed`; cancellation is without penalty and terminal; a cancelled spot
  reopens; there is NO waitlist (BR-003, BR-004, BR-010, BR-013). When a turno
  is cancelled or deactivated, its `confirmed` bookings are AUTOMATICALLY
  cancelled and their spots freed (FR-007, BR-014, NC-01). Lowering a turno's
  `capacity_limit` below its number of `confirmed` bookings is NOT allowed
  (BR-014, ERR-012, NC-01);
- a booking references the **Client record** (not the User account), so clients
  without a linked User can be booked by staff (BR-002, BK-01, AF-001);
- booking records are never hard-deleted (BR-011).

This is the seventh Specification of the MVP. It builds on SPEC-001 (roles,
policy pattern), SPEC-002 (client records), SPEC-004 (membership state machine
+ the D-05 predicate already materialized as `Membership::scopeQualifying()` /
`Client::hasQualifyingMembership()` in SPEC-008) and SPEC-006 (turno model).
Bookings is a greenfield module: no `bookings` table exists yet.

---

## 2. Specification

Reference:

`docs/specs/SPEC-007.md`

Status note: SPEC-007 is approved for the architecture phase per
`docs/sdd/state.yaml` (status `spec_ready`, current phase `architecture`). Gates
**D-05 option 1**, **D-08 (Recommended MVP package)** and **D-07 option 1** are
pre-approved under NIGHT MODE (`docs/sdd/state.yaml` `project.po_decisions`).
Decision **NC-01** (turno status changes vs. existing bookings) is RESOLVED by
the PO (2026-08-15, `docs/sdd/state.yaml` `SPEC-007.po_decisions`). The
Specification flags assumptions **BK-01..BK-15** as NOT confirmed business
rules; they require Product Owner confirmation before Implementation (or at
latest before Review). This design is written against the assumptions as stated
(see §12 Pending PO Confirmations).

Boundary note: client self-service (SPEC-013), attendance and the `completed`
status (SPEC-008), waitlists, penalties, per-client booking limits and
notifications are all explicitly OUT of scope (SPEC-007 §12).

---

## 3. Affected Modules

- **Bookings** (new module): the booking entity (`bookings` table) with its
  fields (`client_id`, `turno_id`, `status`, `booked_at`, `booked_by`, `notes`),
  the two-state lifecycle (`confirmed` / `cancelled`), the capacity + duplicate
  invariants, the access-gate consumption, and the ADMIN+TRAINER management UI.
- **Scheduling / Turnos** (existing module, additive changes only): the `Turno`
  model gains a `bookings()` relationship, a `confirmedBookingsCount()` helper
  and an `assertCapacityLimitNotBelowConfirmed()` guard; `Turno::cancel()` and
  `Turno::deactivate()` gain the NC-01 auto-cancel (wrapped in a transaction);
  `TurnoResource` gains the FR-006 occupancy display and the ERR-012
  capacity-lowering guard. No change to the `turnos` schema (SPEC-007 §10).
- **Clients** (existing module, additive changes only): the `Client` model gains
  a `bookings(): HasMany` relationship (C-02: a client aggregates bookings; the
  same pattern as `Client::attendances()`). No schema change.
- **Memberships** (existing module, NO change): the D-05 predicate is already
  `Membership::scopeQualifying()` + `Client::hasQualifyingMembership()` +
  `Client::accessDenialReason()` (SPEC-008). SPEC-007 reuses them verbatim.
- **Cross-cutting authorization foundation** (no new module): a new
  `BookingPolicy` extends the `TurnoPolicy` / `AttendancePolicy` pattern
  (ADMIN+TRAINER management, no delete) on the existing
  `User::hasAnyRole` helper (ADR-001).

No changes are made to: auth scaffolding, `EnsureUserHasRole`,
`AdminPanelProvider`, the `role_user` pivot, the `users`/`roles`/`clients`/
`plans`/`memberships`/`turnos` tables (schema), or the Users, Clients, Plans,
Memberships and Attendance resources. No `booking_id` column is added to
`attendances` in this Specification (that link belongs to the SPEC-008
`confirmed → completed` tie-in and stays deferred; SPEC-008 §6).

---

## 4. Application Flow

```text
Presentation (web)
    ↓
Admin panel: /admin — Filament panel (ADMIN | TRAINER gate via canAccessPanel)
    ↓
BookingResource (Filament): list / create / view / cancel bookings
    ↓
Application
    ↓
BookingPolicy (ADMIN | TRAINER)          CreateBooking Action (authorize →
                                         validate → transaction + row lock)
    ↓
Domain
    ↓
Booking model (client_id, turno_id, status, booked_at, booked_by, notes)
Client::hasQualifyingMembership() / Membership::scopeQualifying   (BR-005)
Turno::bookings() / Turno::cancel() / Turno::deactivate()        (BR-014, NC-01)
    ↓
Persistence
    ↓
PostgreSQL: bookings (new); clients / users / turnos / memberships
            (existing, untouched schema)
```

Concrete flows:

1. **Create booking (FR-001)**
   - ADMIN or TRAINER opens the Bookings section (`BookingResource`) and opens
     the create form: selects an existing client (searchable by name/DNI,
     SPEC-002) and an existing turno, optionally enters `notes`, and saves.
   - `booked_by` is NOT a form field: it is set to the authenticated staff User
     inside the Action (BR-012, BK-12 — the `RegisterPayment.recorded_by`
     precedent).
   - The `CreateBooking` page delegates to the `CreateBooking` Action
     (`handleRecordCreation`, the `CreatePayment` precedent). The Action
     authorizes, validates all booking rules, then in ONE transaction locks the
     turno row, re-checks capacity and the duplicate invariant, and inserts the
     booking with `status = 'confirmed'` and `booked_at = now` (FR-001, BR-003).
   - On success the booking appears in the list with its status shown (FR-002,
     FR-005). On failure a `ValidationException` surfaces the specific error
     (ERR-001..ERR-008, ERR-011).
2. **List / filter (FR-002)**
   - ADMIN or TRAINER lists bookings; filters by client (name/DNI), turno date,
     and status (confirmed / cancelled). Status is always displayed (FR-005).
3. **View detail (FR-003)**
   - ADMIN or TRAINER opens the detail view: client, turno (date, start, end),
     status, `booked_at`, `booked_by` and `notes`.
4. **Cancel booking (FR-004)**
   - ADMIN or TRAINER cancels a `confirmed` booking (row/header action); the
     model's `Booking::cancel()` enforces the state rule (`confirmed →
     cancelled`, terminal) and the spot reopens (BR-010).
5. **Occupancy display (FR-006)**
   - The turno detail view shows `confirmed bookings / capacity_limit` (e.g.,
     "3/10") so staff see remaining spots (BK-14; display only).
6. **Turno cancel / deactivate with bookings (FR-007, BR-014, NC-01)**
   - When staff cancel or deactivate a turno (SPEC-006 FR-007 / FR-005), the
     turno's lifecycle method auto-cancels every `confirmed` booking of that
     turno in the same transaction and frees their spots (AF-007). No client
     notification is sent. Reactivating an `inactive` turno does NOT restore
     auto-cancelled bookings (AF-007, BR-004).
7. **Turno capacity edit guard (BR-014, ERR-012)**
   - Staff edit a turno's `capacity_limit` (SPEC-006 FR-004): the edit is
     rejected if the new value is below the current `confirmed` bookings count,
     until the excess bookings are cancelled first.

---

## 5. Components

### Controllers

None new.

Booking management lives entirely inside the Filament `BookingResource` (the
admin-side controller, same convention as `TurnoResource`, `AttendanceResource`,
`MembershipResource`). No web routes or HTTP controllers are added.

### Actions / Use Cases

**`App\Actions\CreateBooking`** (new) — the single enforcement point for the
booking create path.

`handle(int $clientId, int $turnoId, ?string $notes = null): Booking`:

1. `authorize()` — `Gate::authorize('create', Booking::class)` (BR-012; defense
   in depth so the Action stays safe outside the UI — the `RegisterPayment` /
   `AssignRoutine` precedent).
2. `validate()` — framework validation (`Validator` / `ValidationException`)
   for the non-atomic rules:
   - client exists (ERR-002), turno exists (ERR-002);
   - access gate: `Client::hasQualifyingMembership()` is true, otherwise fail
     with the specific `Client::accessDenialReason()` message (ERR-006, BR-005);
   - turno is `active` (ERR-003, BR-006);
   - turno date is within `today .. today + 7 days` inclusive and, for a
     same-day turno, the start time has not passed (ERR-004, ERR-005, BR-007,
     BK-04).
3. `DB::transaction()` — the atomic capacity + duplicate guard (BR-008, BR-009,
   ERR-011):
   - lock the turno row: `Turno::query()->lockForUpdate()->findOrFail($turnoId)`
     (pessimistic row lock; see ADR-006);
   - re-check capacity: `confirmed bookings count >= capacity_limit` → throw
     `ValidationException` "turno full" (ERR-007, ERR-011);
   - re-check the duplicate invariant: a `confirmed` booking for the same
     `(client_id, turno_id)` already exists → throw (ERR-008, BR-009);
   - insert the booking with `status = 'confirmed'`, `booked_at = now()` and
     `booked_by = auth()->id()`; return it.

An explicit Action is warranted here (not speculative): booking creation is a
multi-rule, transactional, race-safe operation — the "BookSession" use case
named in ARCHITECTURE §7 — and it must be directly invocable in tests for the
concurrency acceptance criterion (AC-9). This is the same bar that justified
`RegisterPayment` and `AssignRoutine`.

Cancellation does NOT need an Action: it is a single-record, non-transactional
state transition implemented as a model method (see below).

### Models

**`App\Models\Booking`** (new)

- Table: `bookings`.
- Fillable: `client_id`, `turno_id`, `status`, `booked_at`, `notes`,
  `booked_by`. (`booked_by` is fillable so the Action and the factory work, but
  it is never a form field — the Action sets it to the authenticated staff
  User, BK-12.)
- Casts: `booked_at` → `'datetime'` (Carbon; the gym-local reservation
  timestamp, no timezone column — same local-time convention as SPEC-006
  BR-011). `status` stays a plain string validated against the model constants.
- Constants (single source of truth for the two-state machine, BR-003):
  - `Booking::STATUS_CONFIRMED = 'confirmed'`
  - `Booking::STATUS_CANCELLED = 'cancelled'`
  - (`completed` is RESERVED for the SPEC-008 tie-in and is intentionally NOT
    added as a constant or reachable state in this Specification — BK-03,
    BK-13.)
- Default attributes: `status` defaults to `STATUS_CONFIRMED` (FR-001, BR-003);
  the DB column carries the same default (mirrors `Turno` / `Membership`).
- Relationships:
  - `client(): BelongsTo` → `Client` (BR-002).
  - `turno(): BelongsTo` → `Turno` (BR-002).
  - `bookedBy(): BelongsTo` → `User` (FK `booked_by`, nullable — BK-12).
- Scopes:
  - `scopeConfirmed(Builder): Builder` — `where('status', STATUS_CONFIRMED)`
    (the capacity-counting predicate, BR-008; and the duplicate check, BR-009).
  - (Optional `scopeForClient` — `where('client_id', ...)` for per-client
    lists, FR-002; the Developer may add it if the list filter needs it.)
- Domain behavior (ARCHITECTURE §8):
  - `cancel(): void` — throws `DomainException` unless `status ===
    STATUS_CONFIRMED` (ERR-009, BR-004); sets `status = STATUS_CANCELLED` and
    saves (FR-004). Terminal: no un-cancel (BR-004, BK-06).
  - `static cancelForTurno(Turno $turno): int` — bulk transitions every
    `confirmed` booking of the turno to `cancelled` (FR-007, BR-014, NC-01);
    returns the affected count. Called from the turno lifecycle methods inside
    their transaction (see below). Idempotent: already-`cancelled` rows are
    untouched.
  - `static confirmedCountForTurno(int $turnoId): int` — the BR-008 count over
    `confirmed` bookings only (BK-11); used by the Action, the Turno model
    guard and the occupancy display.
- No delete scope/method and no `completed` transition: deletion is not offered
  (BR-011) and completion is SPEC-008 (BK-03, BK-13).

**`App\Models\Turno`** (modified additively)

- New relationship `bookings(): HasMany` → `Booking` (the inverse of
  `bookings.turno_id`; now that SPEC-007 defines the reference direction, the
  SPEC-006 §3/§6 boundary note is satisfied).
- New helpers:
  - `confirmedBookingsCount(): int` — `$this->bookings()->confirmed()->count()`
    (FR-006, BR-008, BR-014).
  - `assertCapacityLimitNotBelowConfirmed(int $newLimit): void` — throws
    `DomainException` when `$newLimit < confirmedBookingsCount()` (ERR-012,
    BR-014, NC-01). The business rule lives on the model (AGENTS.md §9); the
    Filament edit path calls it.
- Modified lifecycle methods (NC-01, FR-007, BR-014):
  - `deactivate(): void` — wrap the existing `active → inactive` transition in
    `DB::transaction()` and, after saving, call `Booking::cancelForTurno($this)`
    so the status change and the auto-cancel commit or roll back together.
  - `cancel(): void` — wrap the existing `active/inactive → cancelled`
    transition in `DB::transaction()` and, after saving, call
    `Booking::cancelForTurno($this)`.
  - `reactivate(): void` — UNCHANGED: reactivating an `inactive` turno does NOT
    restore auto-cancelled bookings (AF-007, BR-004).

**`App\Models\Client`** (modified additively)

- New relationship `bookings(): HasMany` → `Booking` (C-02; the
  `attendances()` pattern; supports the FR-002 client list/filter and any
  future client-side relation manager).

**No other model is modified.** `Membership` already exposes
`scopeQualifying()` and `Client` already exposes `hasQualifyingMembership()` /
`accessDenialReason()` (SPEC-008); SPEC-007 reuses them unchanged (BR-005).

### Policies

**`App\Policies\BookingPolicy`** (new) — extends the `TurnoPolicy` /
`AttendancePolicy` pattern (ADMIN+TRAINER, no delete):

- `viewAny` / `view`: ADMIN **or** TRAINER (BR-012, FR-002, FR-003, BK-02).
- `create`: ADMIN **or** TRAINER (BR-012, FR-001, BK-02).
- `update`: ADMIN **or** TRAINER (BR-012) — covers the cancel action, the same
  way `TurnoPolicy::update` covers the deactivate/reactivate/cancel transitions
  and `MembershipPolicy::update` covers cancel.
- **No `delete` policy is registered on purpose**: booking records are never
  hard-deleted (BR-011); there is no delete operation.
- All rules use `$user->hasAnyRole([Role::ADMIN, Role::TRAINER])` (ADR-001).

Authorization matrix (SPEC-007 §9):

| Operation | Anonymous | ADMIN | TRAINER | CLIENT |
| --- | --- | --- | --- | --- |
| Create booking (on behalf) | Denied | Allowed (BR-012, BK-02) | Allowed (BR-012, BK-02) | Denied (SPEC-013) |
| List / filter bookings | Denied | Allowed (BR-012) | Allowed (BR-012) | Denied (SPEC-013) |
| View booking detail | Denied | Allowed (BR-012) | Allowed (BR-012) | Denied (SPEC-013) |
| Cancel booking | Denied | Allowed (BR-012, BK-07) | Allowed (BR-012, BK-07) | Denied (SPEC-013) |
| Delete booking | Denied (no operation, BR-011) | Denied (no operation) | Denied (no operation) | Denied (no operation) |
| Access another client's booking data | Denied | Allowed (staff duty) | Allowed (staff duty) | Denied always (BR-012, C-13) |

A multi-role user receives the union of permissions (SPEC-001 BR-002). CLIENT-
only users never reach the admin panel (`canAccessPanel`); anonymous visitors
are redirected to `/login`. Authorization is enforced server-side via the
Policy and the Action's `authorize()`; frontend hiding is never the enforcement
(AGENTS.md §17). The state rules (BR-003, BR-004 — e.g. a `cancelled` booking
cannot be cancelled again) and the business rules (BR-005 gate, BR-008
capacity, BR-009 duplicate) are NOT authorization rules: they are enforced by
the model lifecycle / the Action, the same way SPEC-006/008 enforce their state
and gate rules.

### Filament

**`App\Filament\Resources\BookingResource`** (new) with pages `ListBookings`,
`CreateBooking`, `ViewBooking` (following the `TurnoResource` folder
convention: `app/Filament/Resources/BookingResource/Pages/*`).

There is NO `EditBooking` page: the Specification defines no field edit; a
booking is created, viewed and cancelled (FR-001..FR-004) — the same stance as
`AttendanceResource` (no edit page). Cancellation is a row/header action, not an
edit.

- Form (create only — FR-001):
  - `client_id` — `Select` with `->relationship('client', 'full_name')`
    (searchable `['full_name', 'dni']`, preload), required (ERR-001, BR-002),
    server-side rule `exists:clients,id` (ERR-002). Optionally a reactive
    placeholder showing the gate decision via `Client::accessDenialReason()`
    (display only — the `AttendanceResource` FR-005 pattern; the enforcement is
    the Action). The client does not need a linked User (AF-001, BK-01).
  - `turno_id` — `Select` with `->relationship('turno', ...)`; option label
    "date start-end label" (the `AttendanceResource::turnoLabel` pattern);
    `modifyQueryUsing` restricts the list to `active` turnos ordered by date/time
    (UX); required (ERR-001), server-side rule `exists:turnos,id` (ERR-002).
    The turno status / time-validity / lead-time rules are enforced by the
    Action (ERR-003..ERR-005), not by the option list (a stale option list can
    never be the enforcement, AGENTS.md §17).
  - `notes` — `TextArea`, optional, `maxLength(500)` (technical detail, no
    business rules).
  - `status` and `booked_by` are NOT form fields: `status` is set by the model
    default (`confirmed`, BR-003) and `booked_by` is set by the Action (BK-12).
- The `CreateBooking` page overrides `handleRecordCreation(array $data): Booking`
  to call `app(CreateBooking::class)->handle((int) $data['client_id'], (int)
  $data['turno_id'], $data['notes'] ?? null)` — the exact `CreatePayment`
  pattern. `ValidationException` messages surface as form errors.
- Table (FR-002, FR-005):
  - Columns: `client.full_name` (searchable), `client.dni` (searchable),
    `turno` (a label like "Y-m-d H:i-H:i", placeholder '—'), `status` (badge:
    confirmed=success, cancelled=gray — presentation), `booked_at` (datetime,
    sortable), `bookedBy.name` (label "Booked by", placeholder '—'), `notes`
    (placeholder '—', truncated/toggleable).
  - Filters (FR-002): a `SelectFilter` on `status` (confirmed / cancelled); a
    client `SelectFilter` on `client_id` (searchable name/DNI); a turno-date
    `Filter` with a `DatePicker` (or `date_from`/`date_until` pair) using
    `whereHas('turno', fn ($q) => $q->whereDate('date', ...))` (mirroring the
    `MembershipResource` / `AttendanceResource` date-range filter pattern).
  - Row actions: `View`; `Cancel` (visible when `record->status ===
    STATUS_CONFIRMED`, `requiresConfirmation()`, `authorize('update', $record)`,
    action `$record->cancel()` — the `TurnoResource::cancel` action pattern).
    No `EditAction`, no `DeleteAction`, `bulkActions([])` (BR-011).
- View page (`ViewBooking`, FR-003): infolist showing `client.full_name`,
  `client.dni`, `turno` (date + start + end), `status` (badge), `booked_at`
  (datetime), `bookedBy.name` (placeholder '—'), `notes` (placeholder '—').
  Header action: `Cancel` with the same visibility/authorization rules.
- Navigation: `navigationIcon` (e.g., `heroicon-o-calendar`) and
  `navigationGroup = 'Bookings'` (a new group for the Bookings module; cosmetic
  placement is a Developer choice).

**`App\Filament\Resources\TurnoResource`** (modified additively, FR-006,
BR-014, ERR-012):

- Occupancy display (FR-006, BK-14): add an entry to `TurnoResource::infolist()`
  (the `ViewTurno` page) showing `confirmedBookingsCount() / capacity_limit`
  (e.g. via a `TextEntry` with a `formatStateUsing` closure reading
  `$record->confirmedBookingsCount().' / '.$record->capacity_limit`, label
  "Occupancy"). Display only; the enforcement is BR-008/BR-014.
- Capacity-lowering guard (BR-014, ERR-012, NC-01): the `EditTurno` page
  overrides `mutateFormDataBeforeSave(array $data): array` to call
  `$this->record->assertCapacityLimitNotBelowConfirmed((int)
  $data['capacity_limit'])` and, on `DomainException`, halt with a validation
  error on `capacity_limit` (the Developer chooses the exact Filament mechanism
  — `addError('data.capacity_limit', ...)` + `halt()`, or rethrow as
  `ValidationException`). The business rule lives on `Turno` (AGENTS.md §9);
  the page is thin glue.

**No relation managers are added in this design.** SPEC-007 OQ-05 asks whether
the admin UI should also offer a client-side "bookings" relation manager (like
`MembershipsRelationManager`) and/or a turno-side one. FR-002 is satisfied by
the Bookings list filters (client, turno date, status), and FR-006 by the turno
detail occupancy entry. Adding relation managers would silently resolve an open
question; the design carries OQ-05 to §12 Pending PO Confirmations (the same
stance SPEC-008 took for its OQ-05).

### Events

None required.

No operation in SPEC-007 has a secondary effect that needs decoupling
(ARCHITECTURE §10). The NC-01 auto-cancel is a mandatory invariant of the turno
lifecycle, executed synchronously inside the turno's transaction — a direct
call (`Booking::cancelForTurno()`), not an event, because the effect must
commit atomically with the status change and no notification is sent (NC-01).
`BookingCreated` / `BookingCancelled` events are not needed until a consumer
(notifications, SPEC-013) exists.

### Jobs

None required.

No queued work exists (no notifications, email, or slow operations). Capacity
enforcement must NOT be queued — it needs immediate, transactional consistency
(ARCHITECTURE §11).

### Routes

No new routes. Filament auto-registers `/admin/bookings*` through the panel's
`discoverResources` (already configured in `AdminPanelProvider`).

### Seeders

None new. Bookings are created by staff in the admin panel only (SPEC-007 §10:
"No seeder is required").

---

## 6. Data Changes

### Migrations

1. **`create_bookings_table`** (new; next migration in the existing timestamp
   sequence: `2026_08_15_000017_create_bookings_table.php`):

   | Column | Type | Constraints |
   | --- | --- | --- |
   | `id` | bigint | PK |
   | `client_id` | foreignId | NOT NULL, FK → `clients.id`, `restrictOnDelete` (BR-002) |
   | `turno_id` | foreignId | NOT NULL, FK → `turnos.id`, `restrictOnDelete` (BR-002) |
   | `status` | string | NOT NULL, default `'confirmed'` (BR-003); string + model constants, NOT a DB enum (ADR-004) |
   | `booked_at` | timestamp | NOT NULL, no DB default — the Action always supplies `now()` (FR-001; the business meaning "when the reservation was made", BK-12) |
   | `booked_by` | foreignId | nullable, FK → `users.id`, `restrictOnDelete` (BK-12 audit; null reserved for SPEC-013 self-service) |
   | `notes` | string(500) | nullable (FR-001; max 500 is a technical detail, SPEC-007 §10) |
   | `created_at` / `updated_at` | timestamp | timestamps |

   - `restrictOnDelete` on all FKs is the defensive guard consistent with the
     preservation pattern: clients (SPEC-002 BR-006), users (SPEC-001 BR-007)
     and turnos (SPEC-006 BR-009) are never hard-deleted, and a deletion
     attempt must be blocked rather than cascade into historical booking data
     (BR-011; same rationale as the `memberships` / `attendances` migrations).
   - Indexes:
     - composite `index(['turno_id', 'status'])` — supports the BR-008 capacity
       count (`WHERE turno_id = ? AND status = 'confirmed'`) and booking lists
       by turno (FR-002). (The `turno_id` FK also gets its own index via
       `constrained()`; the composite index is the one the count query uses.)
     - `client_id` — index for per-client lists (FR-002); provided automatically
       by the FK `constrained()`.
     - **partial unique index** (BR-009) via a single raw statement:
       `CREATE UNIQUE INDEX bookings_confirmed_client_turno_unique ON bookings
       (client_id, turno_id) WHERE status = 'confirmed'` — enforces "at most one
       `confirmed` booking per (client_id, turno_id)" at the database level,
       while still allowing any number of `cancelled` rows for the same pair
       (BR-009, AF-003). This is the one intentional raw-SQL statement in the
       migration: Laravel's `->unique()` does not support a partial `WHERE`
       predicate, and Postgres does (the `whereNull`-style partial-index helpers
       are not applicable to a `status` predicate). Dropped with the table in
       `down()`.
   - No DB CHECK constraints for capacity, duplicate, or lead time: BR-008 /
     BR-009 are enforced by the Action inside a transaction + the partial index;
     the time/gate rules are framework validation (framework-validation-first
     convention, ADR-003). The `capacity_limit` itself lives on `turnos` and is
     not duplicated here (SPEC-007 §10: "no change to the `turnos` table").

No existing migration is modified. The `turnos`, `clients`, `users`,
`memberships` tables are reused as-is (no schema change to `turnos`).

### Relationships

```text
clients 1 ──── * bookings * ──── 1 turnos
                    *
                    0..1
                   users (booked_by)
```

```text
bookings.client_id  → clients.id (required, restrictOnDelete)
bookings.turno_id   → turnos.id  (required, restrictOnDelete)
bookings.booked_by  → users.id   (optional, restrictOnDelete)
```

Eloquent relationships: `Booking::client()`, `Booking::turno()`,
`Booking::bookedBy()` (new); `Client::bookings()` and `Turno::bookings()`
(new, additive). `Membership` and `Client`'s gate helpers are reused unchanged.

### Data lifecycle

- **Created:** booking records with `status = 'confirmed'` (BR-003) and
  `booked_at = now` (FR-001), `booked_by` = the authenticated staff User
  (BK-12). Creating a booking never creates/modifies/deletes a Client, Turno,
  Membership or Payment record (BR-001, BR-002, C-07, AC-14).
- **Modified:** `status` only, on cancel (`confirmed → cancelled`, FR-004) and
  on the NC-01 auto-cancel (FR-007). No other field is modified by any
  operation in this Specification (SPEC-007 §10).
- **Deleted:** none. No delete operation (BR-011); historical booking data is
  preserved.

---

## 7. External Integrations

None.

SPEC-007 touches no external service. No notification/email is sent by any
booking operation (NC-01; SPEC-007 §12). Payments remain out of scope (a
booking is a reservation with no payment attached).

---

## 8. Testing Strategy

Pest (PHPUnit) feature tests following the existing conventions
(`tests/Pest.php` helpers `role()`, `userWithRoles()`; `RefreshDatabase`;
Livewire component testing as used in `TurnoManagementTest` /
`AttendanceManagementTest`). A new `BookingFactory` (`database/factories/`) is
added: `client_id` → `Client::factory()`, `turno_id` → `Turno::factory()`,
`status` → `Booking::STATUS_CONFIRMED`, `booked_at` → `now()`, `booked_by` →
`User::factory()`, `notes` → `null` (mirroring the `AttendanceFactory` shape).

The race test (AC-9) drives the `CreateBooking` Action directly with two
transactions (see `CapacityTest` below), because a Livewire HTTP round-trip
cannot exercise true interleaving. The validation/authorization paths are
covered through the Livewire pages.

**Booking CRUD, validation and lifecycle through the UI (AC-1..AC-5, AC-7,
AC-8, AC-10, AC-11, AC-13, AC-14)**
- `tests/Feature/Admin/BookingManagementTest.php` (Livewire component tests
  against `CreateBooking`, `ListBookings`, `ViewBooking`):
  - ADMIN or TRAINER creates a booking for a client with a qualifying
    membership on an `active`, time-valid turno; the record is persisted
    `confirmed` with `booked_at` set and `booked_by` = the current staff User,
    and appears in the list (AC-1, FR-001, FR-002, BR-002, BR-003, BK-12).
  - Missing client or turno is rejected (AC-2, ERR-001, BR-002).
  - Nonexistent client or turno is rejected (AC-2, ERR-002).
  - Booking an `inactive` or `cancelled` turno is rejected (AC-3, ERR-003,
    BR-006).
  - Booking a turno whose date is in the past, or whose date is today and start
    time has passed, is rejected (AC-4, ERR-004, BR-007).
  - Booking a turno more than 7 days out is rejected (AC-5, ERR-005, BK-04).
  - Booking a full turno is rejected with "turno full" (AC-7, ERR-007, BR-008);
    after a cancellation the spot is re-bookable (AC-7, AF-004).
  - A duplicate `confirmed` booking for the same client+turno is rejected
    (AC-8, ERR-008, BR-009); after cancelling, the same client can book again
    (AC-8, AF-003).
  - ADMIN or TRAINER cancels a `confirmed` booking; it becomes `cancelled`,
    displays as such, and is terminal (AC-10, FR-004, FR-005, BR-004).
  - Cancelling a `cancelled` booking is rejected (AC-11, ERR-009, BR-004).
  - No delete operation exists (AC-13, BR-011).
  - Creating/cancelling a booking never touches client/turno/membership/
    payment rows (AC-14, BR-002, C-07).
  - The booking list supports filtering by client, turno date and status
    (FR-002).

**Authorization / Policy (AC-12, BR-012)**
- `tests/Feature/Admin/BookingPolicyTest.php`:
  - ADMIN and TRAINER can `viewAny`/`view`/`create`/`update` bookings (AC-12,
    BR-012).
  - CLIENT and anonymous users cannot create, list, view or cancel bookings —
    403 and no navigation (AC-12, ERR-010, BR-012; asserted server-side,
    AGENTS.md §17).
  - A multi-role ADMIN + CLIENT or TRAINER + CLIENT user can manage bookings in
    the admin panel (SPEC-001 BR-002).
  - `BookingPolicy` has no `delete` ability for anyone (AC-13, BR-011).

**Access gate in isolation (AC-6, BR-005, D-05 option 1, D-06 option 2)**
- `tests/Feature/Bookings/AccessGateTest.php` (direct tests on the Action /
  model helpers): reject no membership, pending-only, expired-only,
  cancelled-only, and active-but-end-date-passed; allow one qualifying
  membership and several concurrent active memberships; the gate is evaluated
  at booking time only (a membership expiring after booking does not cancel the
  booking, AF-005, BK-09). Reuses `Client::hasQualifyingMembership()` /
  `Membership::scopeQualifying()`.

**Capacity and race safety (AC-7, AC-9, ERR-011, BR-008, BR-010, BK-11)**
- `tests/Feature/Bookings/CapacityTest.php`:
  - rejected when full (AC-7, ERR-007).
  - cancelled bookings do not count toward capacity (BK-11).
  - the race: two concurrent `CreateBooking` invocations (two transactions with
    `lockForUpdate` on the same turno) for the last spot produce exactly one
    success and one "turno full" (AC-9, ERR-011). The test drives the Action
    directly (e.g., with two `DB::transaction` closures orchestrated so one
    blocks on the row lock) and asserts `confirmed` count never exceeds
    `capacity_limit`.

**Turno interplay / NC-01 (AC-16, AC-17, FR-007, BR-014, ERR-012)**
- `tests/Feature/Bookings/TurnoInterplayTest.php`:
  - cancelling a turno with `confirmed` bookings auto-cancels them and frees
    their spots (AC-16, FR-007, BR-014, AF-007, NC-01).
  - deactivating a turno with `confirmed` bookings auto-cancels them (AC-16,
    BR-014, NC-01).
  - reactivating an `inactive` turno does NOT restore auto-cancelled bookings
    (AF-007, BR-004).
  - lowering a turno's `capacity_limit` below its confirmed count is rejected
    (AC-17, ERR-012, NC-01); the edit is accepted once the excess bookings are
    cancelled.

**Unit**
- `tests/Unit/BookingTest.php`:
  - status constants match the two states; no `completed` constant exists
    (BR-003, BK-03).
  - factory/default: `status` defaults to `confirmed`, `booked_at` defaults to
    now (FR-001, BR-003).
  - relationships: `client()`, `turno()`, `bookedBy()`; `Client::bookings()`,
    `Turno::bookings()` (BR-002, C-02).
  - casts: `booked_at` is a Carbon datetime.
  - `scopeConfirmed()` returns only confirmed bookings (BR-008).
  - `cancel()` only from `confirmed`; throws `DomainException` on a `cancelled`
    booking; terminal (BR-004, ERR-009).
  - `cancelForTurno()` cancels exactly the turno's `confirmed` bookings and is
    idempotent (FR-007, BR-014).
  - `confirmedCountForTurno()` counts `confirmed` only (BK-11, BR-008).
  - `Turno::assertCapacityLimitNotBelowConfirmed()` throws when the new limit is
    below the confirmed count (ERR-012, BR-014).
  - the partial unique index exists and enforces BR-009 at the DB level (a
    second `confirmed` booking for the same client+turno raises a unique
    violation) (BR-009).

All authorization assertions are server-side (AGENTS.md §17); no test relies on
frontend visibility.

---

## 9. Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Assumptions BK-01..BK-15 are unconfirmed (SPEC-007 §14.2). | If the PO changes them (e.g., BK-04 lead time, BK-02 TRAINER scope, BK-03 status set), parts of the design change. | Keep implementation isolated: `BookingPolicy`, `CreateBooking` validation, the `bookings` schema, `Booking::cancel()`, and the turno guard are the only touch points. Block Implementation until the PO confirms §12 items (per spec: "before Implementation (or at latest before Review)"). |
| Capacity check must be atomic; a form-only check is racy (TOCTOU). | Two concurrent bookings could oversell the last spot (violates ERR-011, AC-9). | Capacity check lives in the `CreateBooking` Action inside a transaction with `lockForUpdate` on the turno row (ADR-006); never as a form rule. Covered by `CapacityTest`. |
| The partial unique index is raw SQL. | A typo or a non-Postgres run would break the migration. | The DB is PostgreSQL (AGENTS.md §12). The statement is isolated and documented (ADR-006); `BookingTest` asserts the invariant behavior. |
| The row lock serializes concurrent bookings for the SAME turno. | Under extreme concurrency on one turno, later requests wait on the lock. | The lock is held for a short single-insert transaction; gym-scale traffic is trivial. Acceptable (ADR-006 consequences). |
| `Turno::deactivate()` / `cancel()` now coordinate with bookings. | A regression could leave a turno status change without the auto-cancel (NC-01). | Both wrapped in `DB::transaction()` with `Booking::cancelForTurno()`; covered by `TurnoInterplayTest` and the existing `TurnoManagementTest` (which must still pass). |
| Lead-time / same-day "start not passed" comparison on `time` strings. | The `time` column has no date component (SPEC-006); a naive comparison could mis-handle the day boundary. | The Action compares a `today . ' ' . start_time` datetime against `now()` using the app timezone; covered by AC-4/AC-5. |
| `booked_by` nullable (reserved for SPEC-013). | A missing `booked_by` would break the audit display. | All MVP write paths set `booked_by = auth()->id()` in the Action; the factory supplies a User; `restrictOnDelete` guards the FK. |
| SPEC-005 is completed but bookings do not depend on payments. | None — the gate reads membership state as-is (SPEC-007 §13). | The gate reuses `Client::hasQualifyingMembership()`; gate tests use `active` memberships via factories (the SPEC-008 stance). |
| OQ-05: client-side bookings relation manager. | If the PO wants one, FR-002 gains a second entry point. | Not added in this design (FR-002 is satisfied by the list filters); additive if approved. |

---

## 10. Alternatives Considered

1. **Capacity as a form validation rule only (no transaction)** — Matches the
   `AttendanceResource` gate-rule style but is racy: the count-check and the
   insert are separate statements, so two concurrent attempts can both see "one
   spot left" and both insert (violates BR-008, ERR-011, AC-9). Rejected; the
   atomic check must live in a transaction with a row lock (ADR-006).
2. **Capacity via `lockForUpdate` on the turno row (chosen)** vs. **PostgreSQL
   advisory lock** (`pg_advisory_xact_lock(turno_id)`) — Both serialize
   concurrent bookings for a turno. The row lock is chosen: it reuses the
   existing turno row (no lock-namespace discipline), is idiomatic Laravel
   (`lockForUpdate()`), and self-documents the protected resource. See ADR-006.
3. **Constraint/trigger-based enforcement (DB CHECK + counting function, or a
   capacity "slot" table)** — Hard DB guarantees but raw SQL/complex DDL,
   contrary to the validation-first convention (ADR-003) and over-engineered
   for the MVP. Rejected; the partial unique index (BR-009) is the only raw-SQL
   piece, kept because Laravel has no partial-index API and it is a genuine
   data invariant.
4. **`booked_at` as `created_at` reuse** — The spec explicitly permits reusing
   `created_at` (SPEC-007 §10). An explicit `booked_at` is chosen: "when the
   reservation was made" is a business fact that must stay stable even if the
   row is later updated (e.g., by the SPEC-008 `completed` tie-in), and the
   spec/AC-1/tests reference `booked_at` by name (BK-12).
5. **`status` as a Postgres enum** — Rejected for the same reasons as SPEC-004 /
   SPEC-006 (ADR-004 §10): string + model constants is the repo precedent and
   keeps future state changes additive.
6. **`CreateBooking` Action vs. model static creator vs. Filament-only** — An
   explicit Action is chosen: creation is multi-rule, transactional and
   race-safe (the `RegisterPayment` / `AssignRoutine` bar), and it must be
   directly invocable in the concurrency test (AC-9). A bare model `create()`
   would silently drop the atomicity; a Filament-only implementation would be
   untestable for the race and would put business rules in the UI (AGENTS.md
   §9).
7. **Auto-cancel as an Eloquent event listener vs. a direct call** — The NC-01
   effect is a mandatory invariant that must commit atomically with the turno
   status change (no notification, no queue). A direct `Booking::cancelForTurno()`
   call inside the turno's transaction is chosen over an event (events imply
   decoupling and are for optional secondary effects, ARCHITECTURE §10).
8. **`bookings` relation managers on `ClientResource` / `TurnoResource`** — OQ-05
   is open; FR-002 (filters) and FR-006 (occupancy entry) satisfy the spec.
   Not added (avoids silently resolving an open question; SPEC-008 §10
   alternative 4 precedent).
9. **`completed` status constant now** — Reserved for the SPEC-008 tie-in
   (BK-03, BK-13); adding it would imply a reachable state this Specification
   never enters. Rejected (no speculative state).

The capacity race-safety mechanism (alternative 2) is the one genuinely new
architectural pattern in the repo and is documented in **ADR-006**. All other
decisions are incremental applications of ADR-001 (authorization), ADR-002
(module boundary), ADR-003 (validation-first) and ADR-004 (status-as-string).

---

## 11. Decision

Use the established SPEC-001/002/004/006/008 conventions throughout:

- **Persistence:** a new `bookings` table with required FKs to `clients`
  (`client_id`) and `turnos` (`turno_id`) — both `restrictOnDelete` — a string
  `status` default `'confirmed'`, a NOT NULL `timestamp` `booked_at` (no DB
  default), a nullable audit FK `booked_by` → `users.id`, nullable `notes`
  (max 500), and timestamps. Composite index on `(turno_id, status)` for the
  capacity count; `client_id` indexed via the FK; a **partial unique index**
  `(client_id, turno_id) WHERE status = 'confirmed'` for BR-009. No change to
  the `turnos` schema (SPEC-007 §10).
- **Capacity + duplicate enforcement (BR-008, BR-009, ERR-007, ERR-008,
  ERR-011):** inside the `CreateBooking` Action — a `DB::transaction()` that
  locks the turno row (`lockForUpdate`), re-checks the confirmed count against
  `capacity_limit`, re-checks the duplicate invariant, then inserts (ADR-006).
  The partial unique index is the DB-level backstop for BR-009.
- **Access gate (BR-005, D-05 option 1):** reuse `Client::hasQualifyingMembership()`
  and `Client::accessDenialReason()` (already built by SPEC-008); enforced in
  the Action, evaluated at booking time only (BR-004).
- **State machine (BR-003/004):** two string constants on `Booking`;
  `Booking::cancel()` enforces `confirmed → cancelled` (terminal, ERR-009);
  `Booking::cancelForTurno()` performs the NC-01 bulk auto-cancel.
- **Turno interplay (BR-014, NC-01):** `Turno::cancel()` and
  `Turno::deactivate()` wrap their transition + `Booking::cancelForTurno()` in a
  transaction; `Turno::assertCapacityLimitNotBelowConfirmed()` guards the
  `capacity_limit` edit (ERR-012), called from the `EditTurno` page.
- **Authorization:** `BookingPolicy` (viewAny/view/create/update = ADMIN **or**
  TRAINER, no delete) on the existing `User::hasAnyRole` helper (ADR-001); the
  `CreateBooking` Action re-authorizes via `Gate::authorize('create', ...)`.
- **UI:** Filament `BookingResource` with list/create/view pages (no edit);
  `CreateBooking` delegates to the Action (`handleRecordCreation`); cancel as a
  row/header action calling `Booking::cancel()`; filters by client, turno date
  and status; `TurnoResource` gains the FR-006 occupancy entry and the ERR-012
  edit guard.
- **No events, no jobs, no new routes, no new seeders, no external
  integrations.** One new ADR (ADR-006) for the capacity race-safety mechanism.

---

## 12. Pending PO Confirmations

These items are carried from SPEC-007 and must be confirmed before
Implementation (or at latest before Review). This design does not silently
resolve them. They are NOT blocking for the architecture phase (the spec states
"There are no remaining blocking items for this Specification").

### Assumptions (SPEC-007 §14.2)

| ID | Assumption | Impact on this design |
| --- | --- | --- |
| BK-01 | Admin-panel booking only; client self-service deferred to SPEC-013. | `BookingPolicy` denies CLIENT; `booked_by` nullable reserved for SPEC-013 (BK-12). |
| BK-02 | Booking staff = ADMIN and TRAINER (full set). | `BookingPolicy` grants ADMIN+TRAINER; if the PO restricts TRAINER, policy + navigation change only. |
| BK-03 | Status set = `confirmed`/`cancelled`; `completed` reserved for SPEC-008. | Two constants; no `completed` constant or transition (BK-13). |
| BK-04 | Lead time = today .. today+7 inclusive; same-day allowed until start; no minimum notice. | Action lead-time + start-passed rules (ERR-004, ERR-005, BR-007). |
| BK-05 | No per-client booking limit. | No per-client cap; only BR-009 (one confirmed per client+turno). |
| BK-06 | Cancellation boundary = before turno start; terminal (no un-cancel). | `Booking::cancel()` terminal; no reactivation of a cancelled booking (AF-003). |
| BK-07 | Who cancels = ADMIN/TRAINER; client self-cancel is SPEC-013. | `BookingPolicy::update` = ADMIN+TRAINER; cancel action visible to staff. |
| BK-08 | Gate = at least one `active` membership with `end_date >= today`; no "primary". | Reuse `Client::hasQualifyingMembership()` (exists() semantics). |
| BK-09 | Membership expiring after booking does not cancel the booking. | Gate evaluated only in the Action at booking time; no retroactive logic (AF-005). |
| BK-10 | One `confirmed` booking per client per turno. | Partial unique index + Action duplicate check (BR-009, ERR-008). |
| BK-11 | Only `confirmed` bookings count toward capacity. | `scopeConfirmed()` / `confirmedCountForTurno()` (BR-008, BR-010). |
| BK-12 | `booked_by` optional audit field. | Nullable `booked_by` FK; set to `auth()->id()` by the Action; never a form field. |
| BK-13 | No-show consequence: a passed `confirmed` booking stays `confirmed` (no transition). | No `completed` transition; SPEC-008 handles it later. |
| BK-14 | Turno detail shows occupancy (placement is presentation). | `TurnoResource::infolist()` occupancy entry (FR-006). |
| BK-15 | No hard deletion of booking records. | No delete policy/action; `bulkActions([])` (BR-011). |

### Open questions (SPEC-007 §14.3, non-blocking)

| ID | Question | Impact on this design |
| --- | --- | --- |
| OQ-01 | Should auto-cancelled bookings record a reason / keep the historical `confirmed` state visible? | Not modeled: the transition is a plain `confirmed → cancelled` (NC-01). Additive audit column if the PO wants it. |
| OQ-02 | Should `cancelled_by` be recorded? | Not modeled (only `booked_by`, BK-12). Additive if approved. |
| OQ-03 | Is the 7-day lead time the right default; can staff override it? | Design applies the rule to all bookings (BK-04). An override would be an Action change. |
| OQ-04 | Per-client booking limit before SPEC-013? | None imposed (BK-05). |
| OQ-05 | Client-side `bookings` relation manager (and/or turno-side)? | Not added; FR-002/FR-006 satisfy the spec (see §5 Filament). Additive if approved. |
| OQ-06 | Interim indication that `completed` is pending? | Not modeled; presentation note only. |

---

## 13. Related Documents

- Specification: `docs/specs/SPEC-007.md`
- Architecture decisions: `docs/adr/ADR-001.md` (role/authorization
  foundation), `docs/adr/ADR-002.md` (module boundary discipline),
  `docs/adr/ADR-003.md` (validation-first representation), `docs/adr/ADR-004.md`
  (status-as-string / `memberships:expire`), **`docs/adr/ADR-006.md`** (atomic
  capacity enforcement for bookings — new, this Specification)
- Architecture: `docs/architecture/SPEC-004.md`, `docs/architecture/SPEC-006.md`
  (§5 Models — bookable-friendly turno; §6 Data Changes — `bookings.turno_id`
  reference direction), `docs/architecture/SPEC-008.md` (§5 — the D-05 gate
  helpers reused here), `ARCHITECTURE.md` (§7 Actions, §8 Models, §12
  Authorization, §15 Scheduling, §20 simplest correct architecture)
- Product: `docs/product/product-definition-v0.1.md`
- Domain: `docs/domain/domain-model-v0.1.md` (§Booking; C-02)
- Requirements analysis: `docs/requirements/analyst-pass-001.md` (§5.9 Bookings,
  D-05, D-07, D-08, C-18, E-01, R-03)
- Specifications: `docs/specs/SPEC-001.md`, `docs/specs/SPEC-002.md`,
  `docs/specs/SPEC-004.md`, `docs/specs/SPEC-006.md`
- Workflow state: `docs/sdd/state.yaml` (SPEC-007 `spec_ready`, NIGHT MODE
  pre-approvals D-05/D-08/D-07, NC-01 resolved)
- Development rules: `AGENTS.md`
