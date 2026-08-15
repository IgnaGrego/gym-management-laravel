# Architecture — SPEC-006

## 1. Feature

Scheduling & Turnos for the gym management system:

- a **turno** is a bookable time slot for gym access, capacity-limited
  (gate D-07 option 1, pre-approved under NIGHT MODE); it is NOT a
  trainer-led session and not a class/group session (BR-001, C-16);
- staff — **ADMIN and TRAINER** — can create, list/filter, view, edit,
  deactivate, reactivate and cancel turnos from the admin panel
  (FR-001..FR-008, BR-012, AS-01);
- each turno records: `date`, `start_time`, `end_time`, `capacity_limit`,
  `status` (`active` / `inactive` / `cancelled`) and an optional `label`
  (FR-001, FR-003, BR-002, AS-07);
- the MVP has one location: turnos implicitly belong to the gym's single
  location; no location field (BR-010, C-14, ARCHITECTURE §17);
- the turno model is **bookable-friendly**: SPEC-007 (Bookings) will reference
  turnos and enforce capacity against bookings; this Specification stores the
  capacity field but deliberately does NOT enforce it (FR-009, D-07, C-16);
- the access rule (D-05) is explicitly NOT decided here (SPEC-007/008
  concern), and the consequences of turno status transitions for existing
  bookings are explicitly deferred to SPEC-007 (BR-013);
- turno records are never hard-deleted; no delete operation exists
  (BR-009, AS-08, the same preservation pattern as SPEC-001 BR-007 /
  SPEC-002 BR-006 / SPEC-003 BR-004 / SPEC-004 BR-014/BR-015).

This is the sixth Specification of the MVP. It builds on the SPEC-001/002
foundations already implemented in the repository (User / Role / Client
models, `role_user` pivot, `User::hasRole` / `hasAnyRole` helpers,
`Role::ADMIN` / `Role::TRAINER` constants, policy pattern, Filament admin
panel, `EnsureUserHasRole`; ADR-001/002/003/004). Scheduling is a greenfield
module: no scheduling tables exist yet. SPEC-007 (Bookings) will depend on
this Specification.

---

## 2. Specification

Reference:

`docs/specs/SPEC-006.md`

Status note: SPEC-006 is approved for the architecture phase per
`docs/sdd/state.yaml` (status `spec_ready`, current phase `architecture`).
Gate **D-07 option 1** (turno = bookable gym-access slot, capacity-limited) is
pre-approved under NIGHT MODE (`docs/sdd/state.yaml`
`project.po_decisions`). Decision **D-05** (access rule) is intentionally NOT
a gate of this Specification. The Specification explicitly flags assumptions
**AS-01 to AS-09** as NOT confirmed business rules; they require Product Owner
confirmation before Implementation (SPEC-006 §14.1). This design is written
against the assumptions as stated and remains valid under the documented
alternatives unless the PO changes them (see §12 Pending PO Confirmations).

Boundary note: capacity enforcement, the Booking entity, client-facing turno
visibility, class/session management, the Schedule → Session → Booking
hierarchy, operating-hours validation, recurring templates, multi-location,
timezone support and cross-midnight turnos are all explicitly OUT of scope
(SPEC-006 §12). This design introduces no booking, class, session or location
concept of any kind.

---

## 3. Affected Modules

- **Scheduling** (new module): the turno entity (`turnos` table) with its
  fields (`date`, `start_time`, `end_time`, `capacity_limit`, `status`,
  `label`), the three-state lifecycle (`active` / `inactive` / `cancelled`),
  the status transitions (deactivate / reactivate / cancel), ADMIN+TRAINER
  management, and the bookable-friendly shape that SPEC-007 will consume.
- **Cross-cutting authorization foundation** (no new module): a new
  `TurnoPolicy` extends the SPEC-001/002/003/004 pattern and consumes the
  existing `User::hasRole` / `hasAnyRole` helpers (ADR-001). For the first
  time in the repo, a module Policy grants management to **ADMIN and TRAINER**
  (BR-012, AS-01), not ADMIN only — see §5 Policies.

No changes are made to: auth scaffolding (login/logout/redirect), the
`EnsureUserHasRole` middleware, `AdminPanelProvider`, `RoleSeeder`,
`AdminUserSeeder`, the `role_user` pivot, the `users`/`roles`/`clients`/
`plans`/`memberships` tables, or the Users, Clients, Plans and Memberships
modules. Turnos reference nothing: no relationship to Client, Membership,
Plan or User exists in this Specification (SPEC-006 §10; the Booking entity
that will reference turnos is SPEC-007).

The boundary with later Specifications is kept clean: capacity checking
against bookings (SPEC-007), booking consequences of turno status transitions
(SPEC-007, BR-013), client-facing turno visibility (SPEC-007/013) and the
access rule (SPEC-007/008, D-05) are explicitly OUT of scope (SPEC-006 §12).
This design introduces no booking or client-visibility concept of any kind.

---

## 4. Application Flow

```text
Presentation (web)
    ↓
Admin panel: /admin — Filament panel (ADMIN | TRAINER gate via canAccessPanel)
    ↓
TurnoResource (Filament): list / create / view / edit /
                         deactivate / reactivate / cancel turnos
    ↓
Application
    ↓
TurnoPolicy (ADMIN | TRAINER)          Turno model lifecycle methods
                                       (deactivate / reactivate / cancel)
    ↓
Domain
    ↓
Turno model (date, start_time, end_time, capacity_limit, status, label)
    ↓
Persistence
    ↓
PostgreSQL: turnos (new); users / roles / role_user / clients / plans /
            memberships (existing, untouched)
```

Concrete flows:

1. **Create turno (FR-001)**
   - ADMIN or TRAINER opens the Scheduling section of the admin panel
     (`TurnoResource`).
   - Create form: fills required `date`, `start_time`, `end_time`,
     `capacity_limit`, and optionally `label`. Saves.
   - Validation: required fields present (ERR-001), interval invariant
     end > start on the same date (ERR-002, ERR-007, BR-005), date not in the
     past (ERR-003, BR-006), capacity positive integer (ERR-004, BR-007).
   - The record is persisted with `status = 'active'` (FR-001, BR-002, AS-07)
     and appears in the list with its status shown (FR-002, FR-008).
   - No booking, attendance or membership record is created (AC-14, D-07;
     the Booking entity is SPEC-007).
2. **List / filter (FR-002, FR-008)**
   - ADMIN or TRAINER lists turnos; filters by date range and by status
     (active / inactive / cancelled). Status is always displayed (badge).
3. **View detail (FR-003)**
   - ADMIN or TRAINER opens the detail view: date, start time, end time,
     capacity limit, status and label.
4. **Edit (FR-004, AF-003)**
   - ADMIN or TRAINER edits `date`, `start_time`, `end_time`,
     `capacity_limit` and `label` while the turno is `active` or `inactive`.
     Editing re-applies the same validations as creation (ERR-002, ERR-003,
     ERR-004, BR-006). A `cancelled` turno cannot be edited (ERR-006,
     BR-004) — enforced server-side via `TurnoResource::canEdit` (§5
     Filament).
5. **Deactivate (FR-005, BR-003)**
   - ADMIN or TRAINER deactivates an `active` turno (row action); it becomes
     `inactive` (no longer bookable, can be reactivated). The transition is an
     `update` of `status` via the model's `deactivate()` method, authorized by
     the `TurnoPolicy::update` ability.
6. **Reactivate (FR-006, AF-001, BR-003)**
   - ADMIN or TRAINER reactivates an `inactive` turno (row action); it becomes
     `active` again via the model's `reactivate()` method.
7. **Cancel (FR-007, AF-002, BR-003, BR-004)**
   - ADMIN or TRAINER cancels an `active` or `inactive` turno (row action with
     confirmation); it becomes `cancelled` — terminal: cannot be edited,
     reactivated or cancelled again (ERR-006). The model's `cancel()` method
     enforces the state rule. Consequences for existing bookings are a
     SPEC-007 concern (BR-013) and impose no restriction here.
8. **Operational cleanup of past turnos (AF-004, BR-003)**
   - A turno whose date has passed remains in the system (BR-009) and can
     still be deactivated, reactivated or cancelled; status transitions are
     date-independent (AS-04). Only editing date/time into the past is
     rejected (BR-006).

---

## 5. Components

### Controllers

None new.

Turno management lives entirely inside the Filament `TurnoResource` (the
admin-side controller, same convention as `UserResource`, `ClientResource`,
`PlanResource` and `MembershipResource`). No web routes or HTTP controllers
are added.

### Actions / Use Cases

None required.

Turno create/edit is plain Eloquent CRUD handled by the Filament resource
with form validation, matching the SPEC-001/002/003/004 precedent. The
status transitions (deactivate / reactivate / cancel) are single-field
`status` updates with state rules (BR-003, BR-004) — they are implemented as
**model methods** (`Turno::deactivate()`, `Turno::reactivate()`,
`Turno::cancel()`) invoked by thin Filament actions, the same precedent as
`Membership::cancel()` in SPEC-004. No explicit Action class is warranted:
none of these operations is multi-entity or transactional (AGENTS.md §9-10,
ARCHITECTURE §7; the `ProvisionClientUser` / `RenewMembership` precedent
shows Actions are reserved for genuinely multi-entity, transactional,
rule-bearing operations).

### Models

**`App\Models\Turno`** (new)

- Table: `turnos`.
- Fillable: `date`, `start_time`, `end_time`, `capacity_limit`, `status`,
  `label`.
- Casts:
  - `date` → `'date'` (Carbon; BR-006);
  - `start_time` / `end_time` → no cast (plain `time` strings from the
    driver, e.g. `"08:00:00"`); formatted for display in Filament via
    `->time('H:i')`. Rationale: a `time` column has no date component, so a
    datetime cast would be misleading; the interval invariant is validated
    with Laravel's `after` rule on the raw time strings (§6, §8).
  - `capacity_limit` → `'integer'` (BR-007);
  - `status` → plain string (no cast), validated against the model constants
    (BR-002).
- Constants (single source of truth for the three-state machine, BR-002,
  AS-07):
  - `Turno::STATUS_ACTIVE = 'active'`
  - `Turno::STATUS_INACTIVE = 'inactive'`
  - `Turno::STATUS_CANCELLED = 'cancelled'`
- Default attributes: `status` defaults to `STATUS_ACTIVE` (FR-001, BR-002);
  the DB column carries the same default (mirrors `Membership`).
- Relationships: **none**. The turno is standalone in this Specification
  (BR-013, SPEC-006 §10). No `bookings()` relationship is introduced:
  SPEC-007 defines the Booking entity and the reference direction
  (`bookings.turno_id`), the same boundary discipline as `plans` waiting for
  its consumers (SPEC-003 §6) and `clients.user_id` (ADR-002).
- Simple domain behavior (ARCHITECTURE §8):
  - `deactivate(): void` — throws `DomainException` unless `status ===
    STATUS_ACTIVE` (BR-003); sets `status = STATUS_INACTIVE` and saves
    (FR-005).
  - `reactivate(): void` — throws `DomainException` unless `status ===
    STATUS_INACTIVE` (BR-003, ERR-006); sets `status = STATUS_ACTIVE` and
    saves (FR-006).
  - `cancel(): void` — throws `DomainException` unless `status` is
    `STATUS_ACTIVE` or `STATUS_INACTIVE` (BR-003, ERR-006); sets
    `status = STATUS_CANCELLED` and saves (FR-007). Terminal (BR-004).
  - `isActive(): bool` — `status === STATUS_ACTIVE` (FR-008 display; the
    "currently bookable" notion that SPEC-007 will consume).
  - `isInactive(): bool`, `isCancelled(): bool` — counterparts for display
    and action visibility.
- Scopes:
  - `scopeActive(Builder): Builder` — `where('status', STATUS_ACTIVE)`
    (FR-008: the slots currently bookable; directly reusable by SPEC-007).
    Optional symmetric `scopeInactive` / `scopeCancelled` may be added if the
    Developer finds them useful, but they are not required by any flow.
- No delete scope/method: deletion is not offered anywhere (BR-009).

**No existing model is modified.** `User`, `Role`, `Client`, `Plan` and
`Membership` are untouched (turnos reference none of them).

### Policies

**`App\Policies\TurnoPolicy`** (new) — extends the `UserPolicy` /
`ClientPolicy` / `PlanPolicy` / `MembershipPolicy` pattern, with the
ADMIN+TRAINER difference required by BR-012 / AS-01:

- `viewAny` / `view`: ADMIN **or** TRAINER (BR-012, FR-002, FR-003).
- `create`: ADMIN **or** TRAINER (BR-012, FR-001).
- `update`: ADMIN **or** TRAINER (BR-012) — covers field edits (FR-004) AND
  the deactivate / reactivate / cancel transitions (FR-005..FR-007), the same
  way `PlanPolicy::update` covers activate/deactivate and
  `MembershipPolicy::update` covers cancel.
- No `delete` policy is registered: turno records are never hard-deleted
  (BR-009); there is no delete operation.
- All rules use `$user->hasAnyRole([Role::ADMIN, Role::TRAINER])` (ADR-001).

Authorization matrix (SPEC-006 §9):

| Operation | Anonymous | ADMIN | TRAINER | CLIENT |
| --- | --- | --- | --- | --- |
| Create turno | Denied | Allowed (BR-012) | Allowed (BR-012) | Denied |
| List / filter turnos | Denied | Allowed (BR-012) | Allowed (BR-012) | Denied |
| View turno detail | Denied | Allowed (BR-012) | Allowed (BR-012) | Denied |
| Edit turno | Denied | Allowed (BR-012) | Allowed (BR-012) | Denied |
| Deactivate / reactivate turno | Denied | Allowed (BR-012) | Allowed (BR-012) | Denied |
| Cancel turno | Denied | Allowed (BR-012) | Allowed (BR-012) | Denied |
| Client-facing turno visibility / booking | Out of scope (SPEC-007, SPEC-013) | — | — | Out of scope (AS-01) |

A multi-role user receives the union of permissions (SPEC-001 BR-002): an
ADMIN who also holds CLIENT can manage turnos; a TRAINER who also holds
CLIENT can manage turnos. CLIENT-only users never reach the admin panel
(`canAccessPanel`, SPEC-001); anonymous visitors are redirected to `/login`.
Authorization is enforced server-side via the Policy; frontend hiding is
never the enforcement (AGENTS.md §17). The state rules (BR-003, BR-004) are
NOT authorization rules: they are enforced by the model methods and by the
Filament action/`canEdit` guards (§5 Filament), so a record in a wrong state
cannot be transitioned even by an authorized user.

### Filament

**`App\Filament\Resources\TurnoResource`** (new) with pages `ListTurnos`,
`CreateTurno`, `ViewTurno`, `EditTurno` (following the `PlanResource` folder
convention: `app/Filament/Resources/TurnoResource/Pages/*`).

- Form (create/edit — FR-001, FR-004):
  - `date` — `DatePicker`, required (ERR-001), rule `after_or_equal:today`
    (BR-006, ERR-003; on edit the same rule keeps BR-006). Filament
    `->minDate(today())` may be used for UX; the server rule is the
    enforcement (AGENTS.md §17).
  - `start_time` — `TimePicker` (24-hour, seconds off), required (ERR-001,
    ERR-002). Rule `date_format:H:i`.
  - `end_time` — `TimePicker` (24-hour, seconds off), required (ERR-001),
    rules `date_format:H:i` and `after:start_time` (BR-005, ERR-002,
    ERR-007). Laravel's `after` rule parses the bare time strings as dates on
    the same day, so `end ≤ start` is rejected and a cross-midnight interval
    (e.g., 23:00–01:00) is rejected because 01:00 is not after 23:00 — this
    implements ERR-007 without custom logic. If the installed Filament
    version offers a TimePicker `after`-style helper the Developer may use
    it, but the server rule must remain.
  - `capacity_limit` — `TextInput` numeric, required, `integer`, `minValue(1)`
    (ERR-004, BR-007).
  - `label` — `TextInput`, nullable, `maxLength(255)` (FR-001; content is free
    text, no business rules — SPEC-006 §10).
  - `status` is NOT a form field: status is changed exclusively through the
    lifecycle actions (FR-005..FR-007), matching the SPEC-004 precedent where
    the membership status is action-driven, not form-driven.
- Table (FR-002, FR-008):
  - Columns: `date` (sortable, `->date('Y-m-d')`), `start_time`
    (`->time('H:i')`), `end_time` (`->time('H:i')`), `capacity_limit`,
    `label` (placeholder '—', optional searchable), `status` (`badge`
    column — FR-008; colors e.g. active=success, inactive=warning,
    cancelled=danger; presentation choice).
  - Filters (FR-002): `SelectFilter` on `status` with the three constants;
    a date-range `Filter` with two `DatePicker`s (`date_from` / `date_until`)
    mirroring the `MembershipResource` date-range filter pattern (Developer
    chooses the exact component API).
  - Row actions: `View`, `Edit` (auto-hidden on `cancelled` via
    `canEdit`, see below), `Deactivate` (visible when `record->status ===
    STATUS_ACTIVE`, `requiresConfirmation()`), `Reactivate` (visible when
    `STATUS_INACTIVE`), `Cancel` (visible when `STATUS_ACTIVE` or
    `STATUS_INACTIVE`, `requiresConfirmation()`). Each lifecycle action
    authorizes via `authorize('update', $record)` and calls the
    corresponding model method (`$record->deactivate()` / `->reactivate()` /
    `->cancel()`); the model methods are the final state-rule enforcement
    (BR-003, ERR-006). No delete action; `bulkActions([])` (BR-009).
  - `canEdit(Turno $record): bool` — overridden to return
    `parent::canEdit($record) && $record->status !== Turno::STATUS_CANCELLED`
    (BR-004, ERR-006). This single override gates both the `Edit` row action
    (hidden on cancelled turnos) and direct URL access to the `EditTurno`
    page (abort/403) — server-side enforcement that a `cancelled` turno
    cannot be edited (FR-004, ERR-006), consistent with Filament's own
    authorization hook (`can` → policy `update`).
- View page (`ViewTurno`, FR-003): infolist showing `date`, `start_time`
  (`->time('H:i')`), `end_time` (`->time('H:i')`), `capacity_limit`, `status`
  (badge, FR-008) and `label` (placeholder '—'). Header actions: `Edit`,
  `Deactivate`, `Reactivate`, `Cancel` with the same visibility/authorization
  rules as the row actions.
- Navigation: `navigationIcon` (e.g., `heroicon-o-calendar-days`) and
  `navigationGroup = 'Scheduling'` (a new group for the Scheduling module;
  the Developer may adjust the cosmetic placement).

### Events

None required.

No operation in SPEC-006 has a defined secondary effect that needs
decoupling (ARCHITECTURE §10). Turno status transitions do not notify
clients (notifications depend on SPEC-007 bookings data and are out of scope,
SPEC-006 §12) and do not touch bookings (BR-013). `TurnoDeactivated` /
`TurnoCancelled`-style events are not needed until SPEC-007 defines
consumers.

### Jobs

None required.

No queued work exists in SPEC-006 (no notifications, email, or slow
operations). Status transitions are synchronous single-row updates.

### Routes

No new routes. Filament auto-registers `/admin/turnos*` through the panel's
`discoverResources` (already configured in `AdminPanelProvider`).

### Seeders

None new. Turnos are created by staff in the admin panel only (SPEC-006 §10:
"No seeder is required"). The existing `RoleSeeder` already provides the
ADMIN and TRAINER roles required by management.

---

## 6. Data Changes

### Migrations

1. **`create_turnos_table`** (new; next migration in the existing timestamp
   sequence: `2026_08_15_000006_create_turnos_table.php`):

   | Column | Type | Constraints |
   | --- | --- | --- |
   | `id` | bigint | PK |
   | `date` | date | NOT NULL (FR-001, BR-006) |
   | `start_time` | time | NOT NULL (FR-001, BR-005) |
   | `end_time` | time | NOT NULL (FR-001, BR-005) |
   | `capacity_limit` | unsignedInteger | NOT NULL (FR-009, BR-007; positivity enforced by form validation, ERR-004) |
   | `status` | string | NOT NULL, default `'active'` (BR-002, FR-001, AS-07); stored as string with model constants, NOT a DB enum (Architect decision, §10) |
   | `label` | string | nullable (FR-001; max 255 is a technical detail) |
   | `created_at` / `updated_at` | timestamp | timestamps |

   - Time columns (`time`) chosen over datetime columns or a duration field:
     the turno is a same-day interval in the gym's local time (BR-005,
     BR-011, AS-03/AS-09); `start_time` + `end_time` matches the spec's field
     list exactly, and Laravel's `after` rule on the raw time strings enforces
     both ERR-002 (end ≤ start rejected) and ERR-007 (cross-midnight
     rejected) without custom comparison logic. The spec explicitly delegates
     the interval representation to the Architect with no business difference
     (SPEC-006 §10). See §10 Alternatives.
   - Index on `date` to support the FR-002 date-range filter (and future
     SPEC-007 lookups by day). No other indexes needed: the table is small
     and the status filter benefits from the date index plus a table scan.
   - No foreign keys: the turno is standalone in this Specification
     (BR-013, SPEC-006 §10). SPEC-007 will add `bookings.turno_id` →
     `turnos.id` from the consuming module (same boundary discipline as
     `plans` in SPEC-003 §6).
   - No DB CHECK constraints for `end_time > start_time`,
     `date >= today` or `capacity_limit >= 1`: BR-005/BR-006/BR-007 are
     enforced by form validation (framework-validation-first convention,
     ADR-003 §10; same as SPEC-003/004). No row-level timezone column:
     BR-011 (AS-09) — times are the gym's local time.
   - No `location` column: single location (BR-010, C-14, ARCHITECTURE §17).
   - No `type`/`category` column: no speculative fields for future
     classes/sessions (OQ-03, C-16).

No existing migration is modified. The `users`, `roles`, `role_user`,
`clients`, `plans` and `memberships` tables are reused as-is.

### Relationships

```text
turnos (standalone in this Specification)
    ↑ referenced later by SPEC-007 (bookings.turno_id)
```

No Eloquent relationship is defined in this Specification (BR-013).

### Data lifecycle

- **Created:** turno records with status `active` (FR-001, BR-002). Creating a
  turno never creates a booking, attendance or membership record (AC-14,
  D-07).
- **Modified:** `date`, `start_time`, `end_time`, `capacity_limit`, `label`
  via edit (FR-004); `status` on deactivate / reactivate / cancel
  (FR-005..FR-007). Status transitions are date-independent (BR-003, AS-04).
- **Deleted:** none in the MVP. No delete operation (BR-009, AS-08) and no
  hard deletion of turno records; historical schedule data is preserved
  (AF-004).

---

## 7. External Integrations

None.

SPEC-006 touches no external service. Mercado Pago remains out of scope
(SPEC-014, excluded by PO decision). No notification/email is sent by any
turno operation (client notifications depend on SPEC-007 bookings data,
SPEC-006 §12).

---

## 8. Testing Strategy

Pest (PHPUnit) feature tests following the existing conventions
(`tests/Pest.php` helpers `role()`, `userWithRoles()`; `RefreshDatabase`;
Livewire component testing as used in `PlanManagementTest` /
`MembershipManagementTest`). A new `TurnoFactory` (`database/factories/`) is
added: `date` → `today()->toDateString()`, `start_time` → `'08:00:00'`,
`end_time` → `'10:00:00'`, `capacity_limit` → `10`, `status` →
`Turno::STATUS_ACTIVE`, `label` → `null` (or `fake()->sentence()`), mirroring
the `MembershipFactory` shape.

**Turno CRUD and lifecycle (AC-1..AC-10, AC-12..AC-14; FR-007, FR-008)**
- `tests/Feature/Admin/TurnoManagementTest.php` (Livewire component tests
  against `CreateTurno`, `ListTurnos`, `ViewTurno`, `EditTurno`):
  - ADMIN or TRAINER can create a turno with date, start time, end time and
    capacity limit plus an optional label; the record is persisted with
    status `active` and listed (AC-1, FR-001, FR-002, BR-002).
  - Creating a turno does NOT create any booking, attendance or membership
    record and does not modify user/client/plan/membership records (AC-14,
    D-07 — assert only the `turnos` table gained a row).
  - Creating/editing with end time ≤ start time is rejected (AC-2, ERR-002,
    BR-005); a cross-midnight interval (23:00–01:00) is rejected (ERR-007).
  - Creating/editing with a past date is rejected (AC-3, ERR-003, BR-006).
  - Creating/editing with a missing, non-integer or < 1 capacity limit is
    rejected (AC-4, ERR-004, BR-007).
  - Missing required fields are rejected (ERR-001).
  - ADMIN or TRAINER can list turnos and filter by date range and by status
    (AC-5, FR-002).
  - ADMIN or TRAINER can view the full detail including status (AC-6, FR-003,
    FR-008).
  - ADMIN or TRAINER can edit an active/inactive turno's fields; changes
    persist (AC-7, FR-004).
  - ADMIN or TRAINER can deactivate an active turno; it becomes `inactive`
    and is displayed as such (AC-8, FR-005, FR-008, BR-003).
  - ADMIN or TRAINER can reactivate an inactive turno; it becomes `active`
    again (AC-9, FR-006, AF-001, BR-003).
  - ADMIN or TRAINER can cancel an active or inactive turno; it becomes
    `cancelled` and cannot be edited, reactivated or cancelled again
    (AC-10, FR-007, ERR-006, BR-004). The edit page is inaccessible for a
    cancelled turno (server-side via `canEdit`).
  - Overlapping turnos on the same date can both be created and exist
    independently, including identical date/start/end records (AC-12,
    BR-008, AF-005).
  - No delete operation exists: a created turno record persists even after
    its date passes (AC-13, BR-009, AF-004) and past turnos can still be
    deactivated/reactivated/cancelled (BR-003, AS-04).

**Authorization / Policy (AC-11)**
- `tests/Feature/Admin/TurnoPolicyTest.php`:
  - ADMIN and TRAINER can `viewAny`/`view`/`create`/`update` turnos
    (AC-11, BR-012).
  - CLIENT and anonymous users cannot create, view, list, edit, deactivate,
    reactivate or cancel turnos — 403 on `/admin/turnos` routes and no
    navigation (AC-11, ERR-005, BR-012; asserted server-side, AGENTS.md §17).
  - A multi-role ADMIN + CLIENT or TRAINER + CLIENT user can manage turnos
    (SPEC-001 BR-002).
  - `TurnoPolicy` has no `delete` ability for anyone (AC-13, BR-009).

**Unit**
- `tests/Unit/TurnoTest.php`:
  - Status constants match the three states (BR-002).
  - `deactivate()` only from `active`; `reactivate()` only from `inactive`;
    `cancel()` only from `active`/`inactive`; each throws `DomainException`
    otherwise, including all operations on a `cancelled` turno (BR-003,
    BR-004, ERR-006).
  - Factory defaults: `status` is `active`, `label` is `null` (FR-001,
    BR-002).
  - Casts: `date` is a Carbon date; `capacity_limit` is an integer; times are
    plain strings (BR-005).
  - `scopeActive()` returns only `active` turnos (FR-008).
  - The DB default on `status` is `'active'` and the `date` index/columns
    exist (FR-001, BR-002).

All authorization assertions are server-side (AGENTS.md §17); no test relies
on frontend visibility.

---

## 9. Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Assumptions AS-01..AS-09 are unconfirmed (SPEC-006 §14.1). | If PO changes them, parts of the design change (e.g., AS-01 restricts TRAINER to a subset; AS-07 changes the state set; AS-03 allows cross-midnight intervals). | Keep implementation isolated: `TurnoPolicy` rules, the model lifecycle methods, the `turnos` schema and the form rules are the only touch points. Block Implementation until PO confirms §12 items. |
| OQ-01: TRAINER full management vs restricted subset (AS-01). | If PO restricts TRAINER (e.g., view-only), `TurnoPolicy` and the resource navigation change. | The policy is the single enforcement point; granting/restricting TRAINER touches policy + navigation only. |
| OQ-06: past dates allowed on create (backfilling) vs today+future (AS-04). | If PO allows backfilling, the `after_or_equal:today` rule changes. | The rule is a single form rule; trivially removable. |
| Time-only values and the `after` validation rule. | Laravel's `after:start_time` parses bare time strings as dates on the same day; if a future Laravel/Filament version changes this parsing, ERR-002/ERR-007 could regress. | The form rules are covered by feature tests (AC-2, ERR-007); the Developer should verify the rule behavior in the installed version and fall back to a closure comparison if needed. |
| Status stored as string (no DB enum). | A typo could store an invalid status via a non-validated write path. | All write paths set status via the model constants or the DB default; form/action validation restricts the create path; no raw SQL enum (consistent with ADR-004 §10 and ADR-003's validation-first stance). |
| No DB CHECK on interval/date/capacity. | A raw write path could store invalid data. | All MVP write paths go through the Filament form (validated); same trade-off as ADR-003/SPEC-004. |
| SPEC-007 depends on this model. | If SPEC-007 needs a different turno shape (e.g., bookings reference), the schema is already bookable-friendly by design. | The turno is standalone with a stable shape (date, interval, capacity, status); SPEC-007 adds the booking table and reference direction. |
| OQ-03: slot type/category for future classes. | If PO wants a type field now, it is additive later. | Not modeled (no speculative fields); adding a nullable `type` column later is an additive migration with no restructuring. |

---

## 10. Alternatives Considered

1. **Interval as `start_time` + `duration` instead of `start_time` +
   `end_time`** — The spec explicitly allows both representations with no
   business difference (SPEC-006 §10). `start_time` + `end_time` was chosen:
   it matches the spec's field list and FR-002's "daily schedule at a glance"
   framing (staff see the exact end), it maps directly to the
   `after:start_time` validation (ERR-002/ERR-007), and it avoids deriving a
   display end from a stored duration. Not significant enough for an ADR.
2. **Datetime columns (`start_at` / `end_at`) with a date component** —
   More natural for generic scheduling, but the turno is a same-day interval
   in the gym's local time (BR-005, BR-011, AS-03/AS-09): a datetime would
   duplicate the date, invite timezone interpretation, and complicate
   cross-midnight rejection. Rejected in favor of `date` + `time` columns.
3. **Status as a PostgreSQL native enum** — A native enum adds raw SQL and
   makes future state changes migration-heavy; the project avoids raw SQL
   constraints (ADR-003) and uses string constants for roles (`Role::ADMIN`,
   ADR-001) and membership status (ADR-004 §10). String + constants chosen.
4. **Status as boolean `is_active` + separate terminal flag** — Two boolean
   flags would not cleanly represent the three states (active / inactive /
   cancelled) or the terminal rule (BR-004); a three-valued string column
   with constants is the simplest correct representation (BR-002).
5. **`Turno::bookings()` relationship now** — The Booking entity does not
   exist yet (SPEC-007); adding the relationship would be speculative and
   would define a reference direction before the consuming module exists.
   Rejected (BR-013; same discipline as SPEC-003 §6 `plans`).
6. **Explicit `CreateTurno` / `CancelTurno` Actions** — Plain CRUD plus
   single-record state transitions need no explicit use case; matching the
   SPEC-001/002/003/004 precedent (only multi-entity transactional operations
   get Actions — `ProvisionClientUser`, `RenewMembership`). Status
   transitions are model methods (`Membership::cancel()` precedent).
7. **`location` column / timezone field** — Explicitly rejected by BR-010 /
   BR-011 (single location, local time, no timezone handling). Additive later
   per ARCHITECTURE §17 without restructuring the turno.
8. **`type`/`category` column for future sessions/classes** — C-16 keeps
   class management future scope and OQ-03 assumes no speculative field;
   adding a nullable column later is additive (ARCHITECTURE §20).
9. **Model name `TimeSlot` instead of `Turno`** — The spec explicitly permits
   `Turno` (with `TimeSlot` as an acceptable equivalent). `Turno` was chosen:
   "turno" is the established product/domain vocabulary (spec, analyst-pass,
   product docs use it), the spec names it as the primary example, and
   SPEC-007 will reference "turnos" naturally. The class follows the repo's
   PascalCase single-word model convention; the table follows Laravel's
   snake_case plural convention (`turnos`).

No new ADR is required for this Specification: every decision above is an
incremental application of the established ADRs (ADR-001 role/authorization
foundation, ADR-002 module boundary discipline, ADR-003 validation-first
stored representation, ADR-004 status-as-string precedent) to a greenfield
module. No genuinely new architectural pattern is introduced.

---

## 11. Decision

Use the established SPEC-001/002/003/004 conventions throughout:

- **Persistence:** a new `turnos` table with `date`, `start_time`, `end_time`
  (all NOT NULL), `capacity_limit` (unsigned integer, NOT NULL), `status`
  (string default `'active'`), nullable `label`, timestamps, and an index on
  `date`. No foreign keys, no location column, no type column, no timezone
  column (BR-010, BR-011). The existing schema is untouched.
- **Interval representation:** `start_time` + `end_time` as `time` columns;
  the invariant end > start on the same date (BR-005) is enforced by the
  `after:start_time` validation rule, which also rejects cross-midnight
  intervals (ERR-007).
- **State machine (BR-002/003/004):** three string constants on the model;
  `deactivate()`, `reactivate()` and `cancel()` are model methods that enforce
  their state rules and throw `DomainException` on violation (the
  `Membership::cancel()` precedent). `cancelled` is terminal.
- **Authorization:** `TurnoPolicy` (viewAny/view/create/update = ADMIN **or**
  TRAINER, no delete) on top of the existing `User::hasRole` /
  `hasAnyRole` helpers (ADR-001). This is the first module Policy granting
  management to TRAINER, per BR-012 / AS-01 (pending PO confirmation, OQ-01).
- **UI:** Filament `TurnoResource` with list/create/view/edit pages; status
  badge (FR-008); date-range and status filters (FR-002); `Deactivate` /
  `Reactivate` / `Cancel` row actions calling the model methods;
  `canEdit` gated on `status !== STATUS_CANCELLED` (ERR-006); no delete
  action (BR-009).
- **Validation:** Filament form rules — `after_or_equal:today` (BR-006),
  `after:start_time` (BR-005, ERR-002/007), `integer` + `min:1` (BR-007),
  required fields (ERR-001), `maxLength(255)` on `label`. No separate Form
  Request: the repo convention is resource-level validation (no HTTP
  controllers exist for admin CRUD).
- **No Actions, no events, no jobs, no new routes, no new seeders, no
  external integrations.**

---

## 12. Pending PO Confirmations

These items are carried from SPEC-006 and must be confirmed before
Implementation (or at latest before Review). This design does not silently
resolve them.

### Assumptions (SPEC-006 §14.1)

| ID | Assumption | Impact on this design |
| --- | --- | --- |
| AS-01 | Scheduling management (create/view/edit/deactivate/reactivate/cancel) is performed by ADMIN and TRAINER; CLIENT has no scheduling access. | `TurnoPolicy` grants ADMIN and TRAINER; CLIENT denied (BR-012, §9). If PO restricts TRAINER (OQ-01), policy + navigation change only. |
| AS-02 | Turno is a standalone scheduling entity; Schedule → Session → Booking hierarchy NOT implemented in the MVP. | Standalone `turnos` table, no Schedule/Session entities (BR-001, §12). |
| AS-03 | Turno is date + start/end on a single date; does not cross midnight. | `date` + `time` columns; `after:start_time` rejects cross-midnight (ERR-007). |
| AS-04 | Date must be today/future on create/edit; status transitions are date-independent. | `after_or_equal:today` form rule; lifecycle actions not date-gated (BR-003, AF-004). |
| AS-05 | Overlapping (and identical) turnos allowed; no overlap/uniqueness constraint. | No uniqueness/overlap constraint on the `turnos` table (BR-008, AC-12). |
| AS-06 | Capacity limit required, integer ≥ 1; no maximum. | `unsignedInteger` column + `integer`/`min:1` rule (BR-007, FR-009). |
| AS-07 | Status semantics: created `active`; active ↔ inactive reversible; active/inactive → cancelled terminal. | Three constants, default `active`, model lifecycle methods (BR-002/003/004). |
| AS-08 | No hard deletion of turno records; deactivation/cancellation instead. | No delete policy/action; `bulkActions([])` (BR-009, AC-13). |
| AS-09 | Single location: no location field, no timezone handling; times are local. | No location/timezone columns; `time` columns (BR-010, BR-011). |

### Open questions (SPEC-006 §14.2)

| ID | Question | Impact on this design |
| --- | --- | --- |
| OQ-01 | TRAINER full management vs restricted subset (e.g., view-only)? | Design assumes full (AS-01). Restricting touches `TurnoPolicy` + navigation only. |
| OQ-02 | Duplicate date/start/end turnos rejected or allowed? | Design allows them (AS-05, BR-008, AC-12). |
| OQ-03 | Slot type/category field for future sessions/classes? | Not modeled (no speculative fields); additive nullable column later if PO approves. |
| OQ-04 | SPEC-007 restriction on editing/deactivating/cancelling a turno with bookings? | Deferred to SPEC-007 by design (BR-013); must be answered when SPEC-007 is specified. |
| OQ-05 | Maximum capacity / min-max duration? | None imposed (AS-06, BR-005); additive validation if PO approves. |
| OQ-06 | Create turnos only for today+future, or any date (backfilling)? | Design assumes today+future (AS-04); removing the `after_or_equal:today` rule is a single change. |
| OQ-07 | Gym operating hours and recurring/weekly templates needed in MVP? | Assumed out of scope (§12); if approved, additive to a later Specification. |

### Additional design notes flagged for confirmation

- `start_time` / `end_time` are stored as `time` columns (plain strings in
  Eloquent); the interval invariant is enforced by Laravel's `after` rule on
  the raw time strings (BR-005, ERR-002, ERR-007). No timezone handling
  (BR-011).
- Status is stored as a string column with model constants, not a PostgreSQL
  enum (Architect decision per SPEC-006 §10; ADR-004 precedent).
- The `cancelled`-is-terminal rule (BR-004) is enforced server-side via
  `TurnoResource::canEdit` and the model lifecycle methods, not via the
  Policy's `update` ability (which is purely role-based: ADMIN|TRAINER).
- This is the first module Policy granting management to TRAINER; the
  previous Policies (User/Client/Plan/Membership) are ADMIN-only. This
  follows BR-012 / AS-01 and does not change the existing policies.

---

## 13. Related Documents

- Specification: `docs/specs/SPEC-006.md`
- Architecture decision: `docs/adr/ADR-001.md` (role/authorization
  foundation), `docs/adr/ADR-002.md` (module boundary discipline),
  `docs/adr/ADR-003.md` (validation-first representation),
  `docs/adr/ADR-004.md` (status-as-string / stored-state precedent)
- Architecture: `docs/architecture/SPEC-001.md`,
  `docs/architecture/SPEC-002.md`, `docs/architecture/SPEC-003.md`,
  `docs/architecture/SPEC-004.md`, `ARCHITECTURE.md` (§7 Actions, §8 Models,
  §10 Events, §15 Scheduling, §17 Multi-location, §20 simplest correct
  architecture)
- Product: `docs/product/product-definition-v0.1.md`
- Domain: `docs/domain/domain-model-v0.1.md` (Schedule/Session/Booking —
  explicitly NOT implemented in the MVP, AS-02)
- Requirements analysis: `docs/requirements/analyst-pass-001.md` (§5.8
  Scheduling, D-07, C-16, T-01, R-04)
- Development rules: `AGENTS.md`
- Workflow state: `docs/sdd/state.yaml` (SPEC-006 `spec_ready`, D-07
  pre-approved under NIGHT MODE)
