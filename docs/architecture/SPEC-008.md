# Architecture — SPEC-008

## 1. Feature

Attendance for the gym management system:

- an **Attendance** record is an event record that a Client accessed the gym
  (domain-model §Attendance; D-09 option 3). In the MVP it records a
  gym-access event; the sessions dimension is represented conceptually through
  the optional turno link (D-07 option 1, SPEC-006 — no trainer-led sessions
  exist, no session entity);
- **recording mechanism**: staff — **ADMIN and TRAINER** — manually check in
  clients from the admin panel (D-09 option 3; D-19 option 1; no RECEPTIONIST
  role, front-desk tasks assigned to TRAINER);
- **access gate (D-05 option 1)**: only clients with at least one ACTIVE
  membership may be checked in; no grace period after expiry. The gate is
  evaluated **at check-in time** (BR-003, BR-004);
- each check-in record stores: the client, the access timestamp, the staff
  User who recorded it, and optionally the turno, plus optional notes. The
  record is minimal: client + timestamp + who recorded it + optional links;
- attendance records are an **immutable event log**: no edit, no delete, no
  status transitions, no status column (BR-001, BR-008, AT-07 — the same
  preservation stance as SPEC-005 PY-05 and SPEC-006 BR-009);
- attendance does **NOT** require a booking in the MVP: no bookings table
  exists (SPEC-007 BLOCKED); the booking link column is deferred (AT-04,
  BR-006); no `booking_id` column in this Specification (AC-14);
- client isolation is preserved: a CLIENT never accesses another client's
  attendance data (C-13); client self-view of their own attendance belongs to
  SPEC-013 (Client Portal) and is out of scope here (BR-010, AF-007).

This is the eighth Specification of the MVP. It builds on the SPEC-001/002/004
foundations already implemented (User / Role / Client / Membership models,
`User::hasRole` / `hasAnyRole` helpers, policy pattern, Filament admin panel,
ADR-001/002/003/004) and reads the Turno model (SPEC-006, completed) for the
optional session link. Attendance is a greenfield module: no attendance tables
exist yet. SPEC-007 (Bookings) is BLOCKED and is NOT a dependency of this
Specification (BR-006, §12 of SPEC-008).

---

## 2. Specification

Reference:

`docs/specs/SPEC-008.md`

Status note: SPEC-008 is approved for the architecture phase per
`docs/sdd/state.yaml` (status `spec_ready`, current phase `architecture`,
architect `in_progress`). The business gates are pre-approved under NIGHT MODE
(`docs/sdd/state.yaml` `project.po_decisions`): **D-05 option 1** (active
membership required for access, no grace period), **D-09 option 3** (both gym
access and sessions scope; recording is staff manual check-in in the MVP), and
**D-19 option 1** (no RECEPTIONIST role; front-desk tasks assigned to TRAINER;
confirmed in SPEC-001). The Specification explicitly flags assumptions
**AT-01 to AT-10** as NOT confirmed business rules; they require Product Owner
confirmation before Implementation (SPEC-008 §14.1). This design is written
against the assumptions as stated and remains valid under the documented
alternatives unless the PO changes them (see §12 Pending PO Confirmations).

Boundary note: bookings and the `confirmed → completed` transition (SPEC-007,
blocked), self check-in (PIN/QR), client self-view of own attendance
(SPEC-013), check-out / duration tracking, turno capacity enforcement, class /
group-session attendance, turno management (SPEC-006), membership management
(SPEC-004) and notifications are all explicitly OUT of scope (SPEC-008 §12).
This design introduces no booking, session, class, check-out or notification
concept of any kind.

---

## 3. Affected Modules

- **Attendance** (new module): the attendance entity (`attendances` table)
  with its fields (`client_id`, `attended_at`, `recorded_by`, optional
  `turno_id`, optional `notes`), the immutable event-log semantics (no
  status, no edit, no delete), the access-gate consumption (BR-003), and the
  ADMIN+TRAINER management UI. Attendance is a greenfield module on top of the
  SPEC-001/002/004 foundations.
- **Memberships** (existing module, additive changes only): the `Membership`
  model gains a query scope (`scopeQualifying`) that encodes the D-05 option 1
  access predicate (status `active` AND `end_date >= today`, BR-003) — the
  same rule SPEC-007 BR-005 would consume when unblocked. No schema change to
  the `memberships` table.
- **Clients** (existing module, additive changes only): the `Client` model
  gains an `attendances(): HasMany` relationship (C-02: a client aggregates
  attendance records; domain-model "Client ├── Attendance"), a
  `hasQualifyingMembership(): bool` predicate and an
  `accessDenialReason(): ?string` helper for the gate decision and its reason
  display (FR-005, ERR-003/ERR-004). No schema change to the `clients` table.
- **Cross-cutting authorization foundation** (no new module): a new
  `AttendancePolicy` extends the SPEC-001/002/003/004/006 pattern
  (ADMIN+TRAINER management like `TurnoPolicy`, no update/delete) and consumes
  the existing `User::hasAnyRole` helper (ADR-001).
- **Scheduling / Turnos** (existing module, read-only): the `turnos` table is
  referenced by the optional nullable `turno_id` link (AT-06). The `Turno`
  model is NOT modified: the reference direction is defined by the consuming
  module via `Attendance::turno()` — the same boundary discipline as SPEC-006
  §3/§6 (`turnos` staying standalone until SPEC-007 adds `bookings.turno_id`).

No changes are made to: auth scaffolding (login/logout/redirect), the
`EnsureUserHasRole` middleware, `AdminPanelProvider`, `RoleSeeder`,
`AdminUserSeeder`, the `role_user` pivot, the `users`/`roles`/`clients`/
`plans`/`memberships`/`turnos` tables, or the existing resources. No
`booking_id` column and no bookings concept are introduced (AT-04, BR-006,
AC-14).

The boundary with later Specifications is kept clean: the booking tie-in
(`confirmed → completed`, SPEC-007 BK-03/BK-13) is reserved, not implemented;
client self-view (SPEC-013) and self check-in (future) are explicitly OUT of
scope (SPEC-008 §12).

---

## 4. Application Flow

```text
Presentation (web)
    ↓
Admin panel: /admin — Filament panel (ADMIN | TRAINER gate via canAccessPanel)
    ↓
AttendanceResource (Filament): list / create / view attendance records
    ↓
Application
    ↓
AttendancePolicy (ADMIN | TRAINER; no update, no delete)
    ↓
Domain
    ↓
Attendance model (client_id, attended_at, recorded_by, turno_id?, notes?)
Client::hasQualifyingMembership() / Client::accessDenialReason()   (BR-003, FR-005)
Membership::scopeQualifying                                        (BR-003)
    ↓
Persistence
    ↓
PostgreSQL: attendances (new); clients / users / turnos / memberships
            (existing, untouched)
```

Concrete flows:

1. **Record check-in (FR-001, FR-005)**
   - ADMIN or TRAINER opens the Attendance section of the admin panel
     (`AttendanceResource`).
   - Create form: staff search and select an existing client by name or DNI
     (SPEC-002 fields). `attended_at` defaults to the current gym-local time
     (AT-05, form default `now()`). Optional: `turno_id` (the gym-access slot
     the client attended, AT-06) and `notes`.
   - The form shows the access decision for the selected client live:
     qualified, or the denial reason (no membership / no qualifying active
     membership / membership expired) (FR-005, ERR-003/ERR-004). Display only;
     enforcement is server-side (BR-003, AGENTS.md §17).
   - On save, the server validates (ERR-001, ERR-002, ERR-005, ERR-006) and
     enforces the access gate via a closure rule on `client_id` (BR-003): if
     the client qualifies, the record is persisted with `attended_at`,
     `recorded_by` = the authenticated staff User (injected in
     `mutateFormDataBeforeCreate`, BR-011), and the optional turno link and
     notes; if the client does NOT qualify, no record is created and the
     denial reason is shown (ERR-003, ERR-004, FR-005).
   - The record appears in the attendance list (FR-002) and in the client's
     attendance history (FR-004), with `recorded_by` shown (FR-006).
2. **List / filter (FR-002, FR-006)**
   - ADMIN or TRAINER lists attendance records; search by client name/DNI;
     filters by client, date range on `attended_at`, `recorded_by` and turno.
     Default order chronological by `attended_at` (asc — satisfies FR-004 /
     AC-11 "client's attendance history in chronological order"; the column is
     sortable so staff may re-sort the daily log).
3. **View detail (FR-003, FR-006)**
   - ADMIN or TRAINER opens the detail view: client (name/DNI), `attended_at`,
     `recorded_by` (which staff User recorded the check-in), optional turno
     link and notes. No header actions: records are immutable (BR-008).
4. **Backdated check-in (AF-001, BR-007)**
   - Staff record a check-in that happened earlier by setting `attended_at` to
     the past value; the rule `attended_at` not in the future (ERR-005) still
     applies; no minimum backdating limit exists (AT-05, OQ-07 open).
5. **Access gate evaluation at check-in time only (BR-004, AF-006)**
   - The gate is evaluated when the check-in is recorded against the
     then-current membership state. A membership that expires after the
     check-in does not retroactively invalidate the record (E-01 handled at
     the door); the next check-in is evaluated fresh (BR-004, AT-09).

---

## 5. Components

### Controllers

None new.

Attendance management lives entirely inside the Filament `AttendanceResource`
(the admin-side controller, same convention as `TurnoResource`,
`MembershipResource`, `ClientResource`). No web routes or HTTP controllers are
added.

### Actions / Use Cases

None required.

Recording a check-in is a single-record insert with framework validation
(client exists, `attended_at` not future, optional turno exists, access gate)
handled by the Filament resource — plain Eloquent CRUD matching the
SPEC-001/002/003/004/006 precedent (only genuinely multi-entity, transactional,
rule-bearing operations get explicit Actions — `ProvisionClientUser`,
`RenewMembership`). The access gate is a validation-time business check
(BR-003 is explicitly NOT an authorization rule, SPEC-008 §9), implemented as
model helpers + a form validation rule, not as an Action: it is not
multi-entity and has no transactional coordination beyond the single insert.
An explicit `RecordCheckIn` Action would be an unnecessary abstraction
(AGENTS.md §9-10, ARCHITECTURE §7, SPEC-006 §5 Actions rationale).

### Models

**`App\Models\Attendance`** (new)

- Table: `attendances`.
- Fillable: `client_id`, `attended_at`, `recorded_by`, `turno_id`, `notes`.
  (`recorded_by` is fillable so the create path, the factory and direct writes
  work, but it is never a form field — it is set to the authenticated staff
  User at creation, BR-011.)
- Casts:
  - `attended_at` → `'datetime'` (Carbon; BR-007 — the gym-local access
    timestamp; no timezone column, same local-time convention as SPEC-006
    BR-011).
  - `turno_id` / `client_id` / `recorded_by` → no cast (integer FKs, Eloquent
    handles them).
  - `notes` → plain string.
- No `$attributes` defaults: every required field is always supplied (the form
  prefills `attended_at` with `now()`, AT-05); `turno_id` / `notes` are
  nullable with no default needed. No status attribute exists (BR-001, AT-07).
- Relationships:
  - `client(): BelongsTo` → `Client` (FK `client_id`, BR-002).
  - `recordedBy(): BelongsTo` → `User` (FK `recorded_by`, BR-011, AT-08).
  - `turno(): BelongsTo` → `Turno` (FK `turno_id`, nullable — AT-06, BR-012).
- Scopes:
  - `scopeForClient(Builder $query, int $clientId): Builder` —
    `where('client_id', $clientId)` (FR-004: a client's attendance history;
    used by the client filter; ordering by `attended_at` is applied by the
    resource for FR-004 / AC-11).
- No domain behavior methods: the record is an immutable event-log entry
  (BR-008) — no `update`, no `delete`, no status transitions, no
  booking-related methods (AT-04, AC-14). There is intentionally no
  `scopeForTurno` / booking scope in the MVP.

**`App\Models\Membership`** (modified additively)

- New scope (the D-05 option 1 access predicate, BR-003):
  - `scopeQualifying(Builder $query): Builder` —
    `where('status', static::STATUS_ACTIVE)->where('end_date', '>=', today())`
    (status `active` AND `end_date >= today` — the defensive check against
    the `memberships:expire` command window, SPEC-004 BR-007 / ADR-004; no
    grace period — D-05 option 3 not chosen; multiple active memberships,
    D-06 option 2: at least one qualifying suffices — `exists()` semantics).
    This is the same rule SPEC-007 BR-005 describes; the scope lives on
    `Membership` so both SPEC-007 (when unblocked) and SPEC-008 share one
    predicate. No schema change, no change to `isActive()` or the state
    constants.

**`App\Models\Client`** (modified additively)

- New relationship (C-02, domain-model "Client ├── Attendance"):
  - `attendances(): HasMany` → `Attendance` (FR-004 navigation; display
    ordering is applied by the consuming UI).
- New access-gate helpers (BR-003, FR-005, ERR-003/ERR-004):
  - `hasQualifyingMembership(): bool` —
    `$this->memberships()->qualifying()->exists()` (BR-003: at least one
    qualifying membership — the D-05 option 1 predicate; the boolean gate
    reused by the create form's validation rule).
  - `accessDenialReason(): ?string` — the FR-005 decision with its reason.
    Returns `null` when the client qualifies, otherwise one of the constants:
    - `Client::ACCESS_DENIED_NO_MEMBERSHIP` — the client has no membership
      records (ERR-003);
    - `Client::ACCESS_DENIED_MEMBERSHIP_EXPIRED` — at least one membership is
      `active` but its end date has passed (E-01 at the door, no grace
      period — ERR-004);
    - `Client::ACCESS_DENIED_NO_ACTIVE_MEMBERSHIP` — memberships exist but all
      are `pending` / `expired` / `cancelled` (ERR-004).
    Evaluation order: (1) no membership records → NO_MEMBERSHIP; (2) a
    qualifying membership exists → `null` (qualified, D-06 option 2: one
    suffices); (3) an `active` membership with a passed end date exists →
    MEMBERSHIP_EXPIRED (the command-window case, SPEC-004 BR-007 / ADR-004);
    (4) otherwise → NO_ACTIVE_MEMBERSHIP.
- No new columns, no change to `$fillable`, casts, `user()` or
  `hasLinkedUser()`.

**No other model is modified.** `User`, `Role`, `Plan` and `Turno` are
untouched. `Turno` deliberately gains no `attendances()` relationship in this
Specification: the reference direction is defined by the consuming module
(`Attendance::turno()`), the same boundary discipline as SPEC-006 §3/§6 (a
turno stays standalone until its consumers define the reference).

### Policies

**`App\Policies\AttendancePolicy`** (new) — extends the `TurnoPolicy` /
`MembershipPolicy` / `UserPolicy` pattern:

- `viewAny` / `view`: ADMIN **or** TRAINER (BR-009, FR-002, FR-003, AT-01 —
  the same role set as `TurnoPolicy`, D-19 option 1: front-desk tasks assigned
  to TRAINER).
- `create`: ADMIN **or** TRAINER (BR-009, FR-001, AT-01) — covers recording a
  check-in.
- **No `update` and no `delete` policy is registered on purpose**: attendance
  records are immutable event-log entries; no edit or delete operation exists
  (BR-008, ERR-008, AT-07) — the same stance as `TurnoPolicy` (no delete) and
  `MembershipPolicy` (no delete), extended here to also omit `update` because
  no field of an attendance record is ever modified.
- All rules use `$user->hasAnyRole([Role::ADMIN, Role::TRAINER])` (ADR-001).
- The access gate (BR-003) is NOT an authorization rule: it is a business
  validation enforced at check-in time by the create form's validation rule
  (the same stance as SPEC-007's access gate and SPEC-004/006 state rules,
  SPEC-008 §9).

Authorization matrix (SPEC-008 §9):

| Operation | Anonymous | ADMIN | TRAINER | CLIENT |
| --- | --- | --- | --- | --- |
| Record check-in (create attendance) | Denied | Allowed (BR-009, AT-01) | Allowed (BR-009, AT-01) | Denied |
| List / filter attendance | Denied | Allowed (BR-009) | Allowed (BR-009) | Denied |
| View attendance detail | Denied | Allowed (BR-009) | Allowed (BR-009) | Denied |
| View a client's attendance history | Denied | Allowed (BR-009) | Allowed (BR-009) | Denied |
| Edit / delete attendance records | Denied | Denied (no such operation — BR-008) | Denied (no such operation — BR-008) | Denied (no such operation — BR-008) |
| Client self-view of own attendance | Out of scope (SPEC-013) | — | — | Out of scope at this stage (AT-01) |
| Access another client's attendance data | Denied | Allowed (staff duty) | Allowed (staff duty) | Denied always (BR-010, C-13) |

A multi-role user receives the union of permissions (SPEC-001 BR-002): an
ADMIN or TRAINER who also holds CLIENT can record and view attendance in the
admin panel (AF-005, AC-10) — including being checked in as a client
themselves, evaluated purely against their Client record's memberships
(AT-10, E-11). CLIENT-only users never reach the admin panel
(`canAccessPanel`, SPEC-001); anonymous visitors are redirected to `/login`.
Authorization is enforced server-side via the Policy; frontend hiding is never
the enforcement (AGENTS.md §17).

### Filament

**`App\Filament\Resources\AttendanceResource`** (new) with pages
`ListAttendances`, `CreateAttendance`, `ViewAttendance` (following the
`TurnoResource` / `MembershipResource` folder convention:
`app/Filament/Resources/AttendanceResource/Pages/*`).

There is NO `EditAttendance` page: the Specification defines no edit operation
and records are immutable (BR-008, ERR-008) — the same stance as
`MembershipResource` (no edit page).

- Form (create only — FR-001, FR-005):
  - `client_id` — `Select` with `->relationship('client', 'full_name')`
    (searchable/preload; staff search by name or DNI per FR-001), required
    (ERR-001, BR-002), server-side rule `exists:clients,id` (ERR-002), PLUS the
    access-gate closure rule (BR-003): when the selected client's
    `accessDenialReason()` is not `null`, validation fails with the denial
    reason (ERR-003/ERR-004) and no record is created. This is the server-side
    enforcement of the gate (AGENTS.md §17).
  - `attended_at` — `DateTimePicker` (24-hour, seconds off), required
    (BR-007), `->default(now())` (AT-05: defaults to the current gym-local
    time), server-side rule not in the future (ERR-005) — e.g. a
    `before_or_equal:now` rule or a closure comparing the parsed value against
    `now()` (the Developer verifies the exact rule behavior in the installed
    Filament version). No minimum date: backdating is allowed without an
    explicit limit (AT-05, AF-001, OQ-07 open).
  - `turno_id` — `Select` with `->relationship('turno', ...)` (display the
    turno's `date`/`label`; presentation choice), optional/nullable (AT-06),
    server-side rule `exists:turnos,id` (ERR-006). No turno status, time or
    capacity validation is applied to the link (AT-06 — the link is optional
    metadata; booking/capacity semantics belong to SPEC-007, blocked). The
    option list may include all turnos (active/inactive/cancelled) because no
    status rule applies to the link (AT-06).
  - `notes` — `TextArea`, optional, `maxLength(500)` (technical detail per
    SPEC-008 §10; no business rules on content).
  - `recorded_by` is NOT a form field (BR-011): the `CreateAttendance` page
    sets `data['recorded_by'] = auth()->id()` in
    `mutateFormDataBeforeCreate` — the staff User who recorded the check-in
    (the same injection pattern used for `user_id`-style server-set fields).
  - FR-005 display: a reactive hint/placeholder next to `client_id` that
    reflects `Client::accessDenialReason()` (or "qualified") for the selected
    client. Display only; the closure rule is the enforcement.
- Table (FR-002, FR-006):
  - Columns: `client.full_name` (searchable), `client.dni` (searchable),
    `attended_at` (sortable, datetime), `recordedBy.name` (label "Recorded
    by" — FR-006, BR-011, AT-08), `turno` (e.g. `turno.date`/`label`,
    placeholder '—'; presentation choice), `notes` (placeholder '—',
    toggleable/truncated).
  - Default sort: `attended_at` ascending (chronological — satisfies FR-004 /
    AC-11 "client's attendance history in chronological order"); the column is
    sortable so staff can re-sort the daily access log (FR-002).
  - Filters (FR-002): a client `SelectFilter` on `client_id` (searchable,
    name/DNI — also the FR-004 client-history entry point); a date-range
    `Filter` on `attended_at` with two `DatePicker`s (`attended_from` /
    `attended_until`) mirroring the `MembershipResource` date-range filter
    pattern; a `recorded_by` `SelectFilter` (staff Users); a `turno_id`
    `SelectFilter` (turnos). The Developer chooses the exact component API.
  - Row actions: `View` only. No `EditAction`, no `DeleteAction`, no
    `bulkActions([])` (BR-008, ERR-008).
- View page (`ViewAttendance`, FR-003, FR-006): infolist showing
  `client.full_name`, `client.dni`, `attended_at` (datetime), `recordedBy.name`
  (label "Recorded by"), `turno` (placeholder '—'), `notes` (placeholder '—').
  No header actions (immutable records, BR-008).
- Navigation: `navigationIcon` (e.g., `heroicon-o-clipboard-check`) and
  `navigationGroup = 'Attendance'` (a new group for the Attendance module; the
  Developer may adjust the cosmetic placement).

**No `AttendancesRelationManager` on `ClientResource` is added in this
design.** OQ-05 (SPEC-008 §14.2) asks whether the admin UI should also offer a
client-side attendance relation manager (like `MembershipsRelationManager`).
The Specification does not require it: FR-004 is satisfied by the attendance
list's client filter (search by name/DNI) with chronological ordering (AC-11),
the same way the list serves the daily access log. Adding a relation manager
would silently resolve an open question; the design instead carries OQ-05 to
§12 Pending PO Confirmations.

### Events

None required.

Recording a check-in has no defined secondary effect: it never creates,
modifies or deletes a Client, Membership, Turno, Plan or User record and never
touches a booking (BR-012, AC-12, AC-14); no notification is sent (SPEC-008
§12). No event is warranted (ARCHITECTURE §10). The `confirmed → completed`
booking transition reserved by SPEC-007 (BK-03/BK-13) is NOT implemented here
(AC-14).

### Jobs

None required.

No queued work exists in SPEC-008 (no notifications, email, or slow
operations); recording a check-in is a synchronous single-row insert
(ARCHITECTURE §11).

### Routes

No new routes. Filament auto-registers `/admin/attendances*` through the
panel's `discoverResources` (already configured in `AdminPanelProvider`).

### Seeders

None new. Attendance records are created by staff check-in in the admin panel
only (SPEC-008 §10: "No seeder is required").

---

## 6. Data Changes

### Migrations

1. **`create_attendances_table`** (new; next migration in the existing
   timestamp sequence: `2026_08_15_000007_create_attendances_table.php`):

   | Column | Type | Constraints |
   | --- | --- | --- |
   | `id` | bigint | PK |
   | `client_id` | foreignId | NOT NULL, FK → `clients.id`, `restrictOnDelete` (BR-002) |
   | `attended_at` | timestamp | NOT NULL (BR-007); gym-local access time, no timezone column (AT-05, ARCHITECTURE §17); no DB default — the value is always supplied (form default `now()`) |
   | `recorded_by` | foreignId | NOT NULL, FK → `users.id`, `restrictOnDelete` (BR-011, AT-08) |
   | `turno_id` | foreignId | nullable, FK → `turnos.id`, `restrictOnDelete` (AT-06, BR-012) |
   | `notes` | string(500) | nullable (FR-001; max 500 is a technical detail, SPEC-008 §10) |
   | `created_at` / `updated_at` | timestamp | timestamps |

   - `attended_at` as a `timestamp` column (the spec explicitly delegates the
     exact column type to the Architect, SPEC-008 §10): it records a point in
     time — "when the client accessed the gym" — in the gym's local time, so a
     `timestamp` (Laravel `timestamp()` maps to Postgres
     `timestamp(0) without time zone`; `dateTime()` is an alias for the same
     type) is the natural fit. No timezone column (single location, local
     time — C-14, ARCHITECTURE §17; same convention as SPEC-006 BR-011).
   - `restrictOnDelete` on all FKs is a defensive guard consistent with the
     preservation pattern: clients (SPEC-002 BR-006), users (SPEC-001 BR-007,
     ADR-001) and turnos (SPEC-006 BR-009) are never hard-deleted, and a
     deletion attempt should be blocked rather than cascade into historical
     attendance data (BR-008; same rationale as the `memberships` migration).
   - Indexes: on `attended_at` (FR-002 date-range filter) and on
     `(client_id, attended_at)` (FR-004 per-client history ordered by
     `attended_at`, mirroring the `memberships` `(client_id, start_date)`
     index). The FK columns (`client_id`, `recorded_by`, `turno_id`) receive
     their own indexes automatically via `constrained()`.
   - No `booking_id` column — DEFERRED (AT-04, BR-006, AC-14): the bookings
     table does not exist (SPEC-007 BLOCKED). When SPEC-007 is unblocked, a
     follow-up migration adds a nullable `booking_id` and the `confirmed →
     completed` tie-in is defined then (SPEC-007 BK-03/BK-13). Following the
     no-speculative-fields / module-boundary discipline (ADR-002; SPEC-006 §10
     alternative 5).
   - No `status` column: an attendance record is an event, not a stateful
     entity (BR-001, AT-07).
   - No DB CHECK constraints: `attended_at` not in the future, `client_id`
     existence and `turno_id` existence are enforced by framework validation
     (framework-validation-first convention, ADR-003; same as SPEC-004/006).
   - No uniqueness constraint on `(client_id, day)`: multiple check-ins per
     day are each independent records (AT-03, AF-004, AC-7).

No existing migration is modified. The `users`, `roles`, `role_user`,
`clients`, `plans`, `memberships` and `turnos` tables are reused as-is.

### Relationships

```text
clients 1 ──── * attendances * ──── 0..1 turnos
                       *
                       1
                      users (recorded_by)
```

```text
attendances.client_id    → clients.id (required, restrictOnDelete)
attendances.recorded_by  → users.id   (required, restrictOnDelete)
attendances.turno_id     → turnos.id  (optional, restrictOnDelete)
```

Eloquent relationships: `Attendance::client()`, `Attendance::recordedBy()`,
`Attendance::turno()` (new); `Client::attendances()` (new, additive);
`Membership::scopeQualifying()` (new, additive). `Turno` gains no relationship
in this Specification (see §5 Models — boundary discipline).

### Data lifecycle

- **Created:** attendance records on staff check-in with `attended_at`
  (default now or backdated), `recorded_by` = the authenticated staff User,
  and the optional turno link and notes (FR-001, BR-011). Creating an
  attendance record creates no other record (BR-012, AC-12) and no booking
  transition (AC-14).
- **Modified:** none. Attendance records are immutable: no field is modified
  by any operation (BR-008, AT-07).
- **Deleted:** none in the MVP. No delete operation (BR-008) and no hard
  deletion of attendance records; the event log is preserved.

---

## 7. External Integrations

None.

SPEC-008 touches no external service. No notification/email is sent by any
attendance operation (SPEC-008 §12); self check-in (PIN/QR) is future scope
(BR-005).

---

## 8. Testing Strategy

Pest (PHPUnit) feature tests following the existing conventions
(`tests/Pest.php` helpers `role()`, `userWithRoles()`; `RefreshDatabase`;
Livewire component testing as used in `TurnoManagementTest` /
`MembershipManagementTest`). A new `AttendanceFactory`
(`database/factories/`) is added: `client_id` → `Client::factory()`,
`recorded_by` → `User::factory()` (BR-011 — required), `attended_at` →
`now()` (AT-05 default), `turno_id` → `null`, `notes` → `null` (or
`fake()->sentence()`), mirroring the `MembershipFactory` / `TurnoFactory`
shape.

As SPEC-005 is BLOCKED, memberships created through the admin panel remain
`pending`; the gate and its tests therefore use `active` memberships created
directly via factories with an `end_date >= today`, exactly as SPEC-007 plans
to (SPEC-008 §12). Tests that need a specific period pass `end_date`
explicitly (the membership `creating` hook does not overwrite it).

**Check-in CRUD and gate through the UI (AC-1..AC-8, AC-11..AC-14; FR-001,
FR-002, FR-003, FR-005, FR-006)**
- `tests/Feature/Admin/AttendanceManagementTest.php` (Livewire component tests
  against `CreateAttendance`, `ListAttendances`, `ViewAttendance`):
  - ADMIN or TRAINER can record a check-in for a client with a qualifying
    active membership; the record is persisted with `attended_at` (default
    now), `recorded_by` = the current staff User, and appears in the list
    (AC-1, FR-001, FR-002, BR-002, BR-003, BR-011).
  - Recording for a client with no membership is rejected with the "no
    membership" reason and no record is created (AC-2, ERR-003, FR-005).
  - Recording for a client whose memberships are all
    `pending`/`expired`/`cancelled`, or whose only `active` membership has an
    end date before today, is rejected with the "no active membership" /
    "membership expired" reason and no record is created (AC-3, ERR-004, E-01,
    no grace period).
  - A client with several concurrent active memberships can be checked in
    (AC-4, D-06 option 2).
  - A check-in with `attended_at` in the future is rejected; a backdated
    check-in (past timestamp) is accepted (AC-5, ERR-005, AF-001).
  - A check-in referencing a nonexistent turno is rejected; a check-in with no
    turno or with an existing turno is accepted and the turno record is not
    modified (AC-6, ERR-006, AT-06, BR-012, AF-002).
  - Multiple check-ins for the same client on the same day are each recorded
    as independent records; none is rejected as a duplicate (AC-7, AT-03,
    AF-004).
  - No edit/delete path exists: a created record persists unchanged and no
    edit/delete action, page or route is available (AC-8, ERR-008, BR-008).
  - The list shows `recorded_by` and supports filtering by client, date range,
    `recorded_by` and turno; the client's history is in chronological order
    (AC-11, FR-002, FR-003, FR-004, FR-006).
  - Recording a check-in never creates, modifies or deletes a Client,
    Membership, Turno, Plan or User record (AC-12, BR-012 — assert only the
    `attendances` table gained a row).
  - No `booking_id` column and no booking transition exist (AC-14, AT-04 —
    assert the schema has no `booking_id` and no attendance operation touches
    a booking).

**Authorization / Policy (AC-9, AC-10; ERR-007, ERR-008)**
- `tests/Feature/Admin/AttendancePolicyTest.php`:
  - ADMIN and TRAINER can `viewAny`/`view`/`create` attendance records
    (AC-1/AC-9, BR-009, AT-01).
  - CLIENT and anonymous users cannot record, list, filter or view attendance
    — 403 on `/admin/attendances` routes and no navigation (AC-9, ERR-007,
    BR-009; asserted server-side, AGENTS.md §17).
  - A multi-role ADMIN + CLIENT or TRAINER + CLIENT user can manage attendance
    in the admin panel (AC-10, SPEC-001 BR-002, AF-005).
  - `AttendancePolicy` has no `update` and no `delete` ability for anyone
    (AC-8, ERR-008, BR-008).

**Access gate in isolation (AC-2..AC-4; BR-003, BR-004, D-05 option 1)**
- `tests/Feature/Attendance/AccessGateTest.php` (direct model/scope tests on
  `Client::hasQualifyingMembership()`, `Client::accessDenialReason()` and
  `Membership::scopeQualifying()`):
  - Reject: no membership; pending-only; expired-only; cancelled-only; an
    `active` membership whose end date has passed (AC-2, AC-3, ERR-003,
    ERR-004, E-01, no grace period).
  - Allow: one qualifying active membership; several concurrent active
    memberships (AC-4, D-06 option 2, AF-005).
  - The gate is evaluated at check-in time only: a membership that expires
    after the check-in does not retroactively invalidate the record (BR-004,
    AF-006).

**Unit**
- `tests/Unit/AttendanceTest.php`:
  - Relationships: `client()`, `recordedBy()`, `turno()` navigation;
    `Client::attendances()` (BR-002, BR-011, C-02).
  - Casts: `attended_at` is a Carbon datetime (BR-007).
  - `recorded_by` is required (BR-011, AT-08): a factory/user-created record
    without it fails the DB NOT NULL constraint.
  - Immutability shape: no status attribute/column; no update/delete methods;
    no booking link in the MVP shape (BR-001, BR-008, AT-04, AT-07, AC-14).
  - The boundary rule: creating an attendance record creates no other record
    (BR-012, AC-12).
  - Factory defaults: `attended_at` is now, `turno_id`/`notes` are null
    (FR-001, AT-05, AT-06).
  - `scopeForClient()` returns only the client's records (FR-004).

All authorization assertions are server-side (AGENTS.md §17); no test relies
on frontend visibility.

---

## 9. Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Assumptions AT-01..AT-10 are unconfirmed (SPEC-008 §14.1). | If the PO changes them, parts of the design change (e.g., AT-01 restricts TRAINER to a subset; AT-03 rejects duplicate same-day check-ins; AT-05 adds a backdating limit; AT-06 validates the turno link). | Keep implementation isolated: `AttendancePolicy` rules, the create-form rules, the `Client` gate helpers and the `attendances` schema are the only touch points. Block Implementation until the PO confirms the §12 items (per spec: "before Implementation (or at latest before Review)"). |
| OQ-05: client-side attendance relation manager. | If the PO wants a `AttendancesRelationManager` on `ClientResource`, FR-004 gains a second entry point. | Not added in this design (FR-004 is satisfied by the list's client filter, AC-11); additive if approved. |
| OQ-07: backdating limit. | If the PO imposes a backdating limit (e.g., no check-in older than N days), the `attended_at` rule changes. | Currently only "not in the future" (AT-05); a single form rule change if approved. |
| The access gate is enforced by a closure rule on `client_id` in the Filament form (not by the Policy). | If the rule is misplaced or bypassed, a check-in could be recorded for a non-qualifying client. | The rule is server-side framework validation (ADR-003 validation-first); `AttendanceManagementTest` covers the denial paths (AC-2/AC-3) and no other write path creates attendance records. The gate is deliberately NOT an authorization rule (SPEC-008 §9). |
| `memberships:expire` staleness window (ADR-004). | An `active` membership whose end date passed before the daily command runs could otherwise qualify for access. | The gate defensively checks `end_date >= today` in `scopeQualifying` (BR-003, SPEC-004 BR-007), closing the window; covered by `AccessGateTest` (AC-3, E-01). |
| SPEC-005 is BLOCKED: admin-created memberships remain `pending`. | Operationlly, newly created memberships cannot be checked in until activation exists. | Correct per SPEC-008 §12; the gate reads membership state as-is; gate tests use `active` memberships via factories, the same stance as SPEC-007. No design change needed. |
| `attended_at` "not in the future" rule and local time. | A client checked in exactly at the current minute could be rejected if the rule compares strictly after now with seconds precision. | Gym-local time convention (no timezone handling, AT-05); the Developer uses a `before_or_equal`-style comparison or a closure against `now()` and verifies the installed Filament/Livewire behavior; covered by AC-5. |
| Timezone handling of `today()` in the gate. | `end_date` is a date; `today()` uses the app timezone. | Use the application's configured timezone consistently (same note as SPEC-004 §9). |
| No DB CHECK on `attended_at` not-future / FK existence. | A raw write path could store invalid data. | All MVP write paths go through the Filament form (validated) plus the model/factory; FKs are DB-enforced; same trade-off as ADR-003/SPEC-004/006. |

---

## 10. Alternatives Considered

1. **Access gate as an explicit `RecordCheckIn` Action instead of model
   helpers + form validation rule** — Recording a check-in is a single-record
   insert with framework validation; there is no multi-entity coordination or
   transactional orchestration beyond the insert itself. An Action would be an
   unnecessary abstraction (AGENTS.md §9-10; the `ProvisionClientUser` /
   `RenewMembership` precedent shows Actions are reserved for genuinely
   multi-entity, rule-bearing operations). The gate predicate and reason live
   on the models (`Membership::scopeQualifying`, `Client` helpers) — the same
   simple-domain-behavior convention as `Membership::isActive()` and
   `Turno::scopeActive()` — and are invoked by the create form's closure rule.
   Chosen.
2. **Gate predicate on `Client` instead of a `Membership` scope** — A
   `Membership::scopeQualifying` scope keeps the D-05 predicate on the entity
   that owns the state (status + end_date) and is directly reusable by
   SPEC-007 when unblocked (same rule, SPEC-007 BR-005); `Client` then
   composes it (`hasQualifyingMembership()`, `accessDenialReason()`). Chosen.
3. **Single boolean check vs. a reason-returning helper** — FR-005 requires
   showing the denial reason (no membership vs. no qualifying active
   membership vs. membership expired, ERR-003/ERR-004). A single boolean would
   force the UI to re-derive the reason. `accessDenialReason(): ?string` with
   constants gives the gate decision AND the reason from one place. Chosen.
4. **`AttendancesRelationManager` on `ClientResource`** — OQ-05 is an open
   question, not a requirement; FR-004 is satisfied by the list's client
   filter with chronological ordering (AC-11), the same list that serves the
   daily log (FR-002). Adding the relation manager would silently resolve an
   open question. Not added; OQ-05 carried to §12.
5. **`booking_id` nullable column now** — Explicitly deferred by AT-04 /
   BR-006 / AC-14 following the no-speculative-fields and module-boundary
   discipline (ADR-002; SPEC-006 §10 alternative 5). Not added.
6. **`attended_at` as `dateTime` vs `timestamp`** — In Laravel/Postgres both
   map to the same `timestamp(0) without time zone` type; the spec delegates
   the choice to the Architect (SPEC-008 §10). `timestamp` chosen for clarity
   ("a point in time in gym-local time"); either spelling is equivalent. Not
   significant enough for an ADR.
7. **DB default `useCurrent()` on `attended_at`** — The spec says `attended_at`
   "defaults to the current time at check-in" (AT-05). A form default
   (`->default(now())`) on the `CreateAttendance` page provides the prefill
   and keeps the value explicit and backdatable; no DB default is needed since
   the field is required and always supplied (same convention as `start_date`
   on memberships). A DB `useCurrent()` default would add nothing for the MVP
   write paths. Chosen.
8. **`update` policy ability returning false (explicit) vs. not registering
   it** — Matching the repo convention (no `delete` ability is registered in
   `TurnoPolicy` / `MembershipPolicy`), both `update` and `delete` are simply
   not registered; Filament then offers no edit/delete UI and direct attempts
   fall back to denied. Chosen.
9. **`restrictOnDelete` vs `nullOnDelete` on `turno_id`** — Turnos are never
   hard-deleted (SPEC-006 BR-009), so the FK behavior is a defensive guard. A
   `nullOnDelete` would preserve the attendance row if a turno were ever
   deleted, but it would silently drop the historical link; `restrictOnDelete`
   blocks the deletion instead and matches the `memberships` precedent.
   Chosen.
10. **Form Request vs Filament form rules** — The repo has no HTTP controllers
    for admin CRUD; validation lives in the resource (SPEC-006 §11: "No
    separate Form Request: the repo convention is resource-level validation").
    Form rules + the closure gate rule chosen.

No new ADR is required for this Specification: every decision above is an
incremental application of the established ADRs (ADR-001 role/authorization
foundation, ADR-002 module boundary discipline, ADR-003 validation-first
stored representation, ADR-004 status-as-string / stored-state precedent) to a
greenfield module. No genuinely new architectural pattern is introduced.

---

## 11. Decision

Use the established SPEC-001/002/003/004/006 conventions throughout:

- **Persistence:** a new `attendances` table with required FKs to `clients`
  (`client_id`, BR-002) and `users` (`recorded_by`, BR-011), a NOT NULL
  `timestamp` `attended_at` (gym-local, BR-007), an optional FK to `turnos`
  (`turno_id`, AT-06), optional `notes` (max 500), and timestamps. Indexes on
  `attended_at` and `(client_id, attended_at)`; FK indexes via
  `constrained()`. No `booking_id` column (AT-04, deferred), no `status`
  column (BR-001), no uniqueness on `(client_id, day)` (AT-03), no DB CHECK
  constraints (ADR-003). The existing schema is untouched.
- **Access gate (BR-003, D-05 option 1):** `Membership::scopeQualifying()`
  (status `active` AND `end_date >= today`, reusable by SPEC-007) +
  `Client::hasQualifyingMembership(): bool` + `Client::accessDenialReason():
  ?string` (the FR-005 decision and its reason: no membership /
  membership expired / no active membership). Enforced server-side by a
  closure validation rule on `client_id` in the `CreateAttendance` form
  (validation-first, ADR-003); evaluated at check-in time only (BR-004).
- **Authorization:** `AttendancePolicy` (viewAny/view/create = ADMIN **or**
  TRAINER, no `update`, no `delete`) on top of the existing `User::hasAnyRole`
  helper (ADR-001) — the `TurnoPolicy` pattern (D-19 option 1, AT-01).
- **UI:** Filament `AttendanceResource` with list/create/view pages, no edit
  page; `recorded_by` injected in `mutateFormDataBeforeCreate` (BR-011);
  create form with searchable client select + gate rule + `attended_at`
  DateTimePicker default now + optional turno select + notes; table with
  client/date-range/recorded_by/turno filters, chronological default order,
  `View` row action only, no bulk actions; infolist view (FR-003, FR-006).
- **No Actions, no events, no jobs, no new routes, no new seeders, no
  external integrations, no ADR.**
- **Deferred (reserved for later Specifications):** the nullable `booking_id`
  column and the `confirmed → completed` tie-in (SPEC-007 BK-03/BK-13, AT-04,
  AC-14); client self-view of own attendance (SPEC-013).

---

## 12. Pending PO Confirmations

These items are carried from SPEC-008 and must be confirmed before
Implementation (or at latest before Review). This design does not silently
resolve them.

### Assumptions (SPEC-008 §14.1)

| ID | Assumption | Impact on this design |
| --- | --- | --- |
| AT-01 | Attendance staff = ADMIN and TRAINER (full set); CLIENT has no attendance access; no RECEPTIONIST role. | `AttendancePolicy` grants ADMIN and TRAINER for viewAny/view/create; CLIENT denied; no update/delete (BR-009, §9). If the PO restricts TRAINER (OQ-04), policy + navigation change only. |
| AT-02 | Attendance does not require a booking; gym access is the primary scope; the sessions dimension is conceptual via the optional turno link. | No booking column/transition; `turno_id` optional metadata (BR-001, BR-006, AC-14). |
| AT-03 | Multiple check-ins per day allowed; no uniqueness on (client, day). | No uniqueness constraint; each record independent (AF-004, AC-7). |
| AT-04 | `booking_id` column deferred to SPEC-007. | No `booking_id` column in this migration (BR-006, AC-14); follow-up migration when SPEC-007 is unblocked. |
| AT-05 | `attended_at` = gym-local timestamp; defaults to now; not in the future; backdating allowed without an explicit limit. | `timestamp` column, form default `now()`, not-future rule, no minimum-date rule (BR-007, ERR-005, AF-001). OQ-07 open: a backdating limit is a single rule change. |
| AT-06 | Optional `turno_id` link: turno must exist; no status/time/capacity validation on the link. | Nullable FK + `exists:turnos,id` rule only (ERR-006, BR-012, AF-002). |
| AT-07 | Attendance records are immutable event-log entries: no edit, no delete, no status. | No update/delete policy/UI, no status column (BR-001, BR-008, ERR-008, AC-8). |
| AT-08 | `recorded_by` required, set to the authenticated staff User at check-in. | NOT NULL FK to `users.id`; injected in `mutateFormDataBeforeCreate` (BR-011, FR-006). |
| AT-09 | Gate evaluated at check-in time only; post-check-in expiry does not invalidate the record. | Gate runs at create-time validation; no retroactive logic (BR-004, AF-006). |
| AT-10 | A staff User may check in any Client, including a Client linked to their own User when they also hold CLIENT. | Union of permissions (SPEC-001 BR-002); gate evaluated purely against the Client's memberships (AF-005, AC-10); broader mixed-role behavior tracked in SPEC-001 OQ-04. |

### Open questions (SPEC-008 §14.2)

| ID | Question | Impact on this design |
| --- | --- | --- |
| OQ-01 | Multiple check-ins per day allowed, or at most one? | Design assumes multiple allowed (AT-03, AF-004, AC-7); a uniqueness constraint would be a schema + test change. |
| OQ-02 | May a staff User record their OWN check-in (staff also CLIENT)? | Design allows any authorized staff member, evaluated purely against the Client's memberships (AT-10, AF-005, AC-10); SPEC-001 OQ-04 tracks the general behavior. |
| OQ-03 | When SPEC-007 is unblocked: nullable `booking_id` on attendance vs. completion tracked on the booking side only? | Deferred by design (AT-04, AC-14); revisit when SPEC-007 is specified. |
| OQ-04 | TRAINER full attendance set vs. restricted subset? | Design assumes full (AT-01), consistent with SPEC-006 AS-01 / SPEC-005 PY-01; restricting touches `AttendancePolicy` + navigation only. |
| OQ-05 | Client-side "attendance" relation manager on `ClientResource`? | Not added; FR-004 is satisfied by the list's client filter + chronological order (AC-11). Additive if the PO approves. |
| OQ-06 | Should `attended_at` be editable after creation? | Design assumes immutable records (AT-07) with manual correction outside the system; no edit path (BR-008, ERR-008). |
| OQ-07 | Backdating limit needed (e.g., no check-in older than N days)? | Design allows backdating without an explicit limit (AT-05), subject only to "not in the future"; a limit is a single form-rule change. |

### Additional design notes flagged for confirmation

- The access gate is a business validation (BR-003), not an authorization
  rule; it is enforced by the create form's closure rule on `client_id` and
  covered by feature tests (AC-2/AC-3) — consistent with the spec's explicit
  statement (SPEC-008 §9) and SPEC-007's access-gate stance.
- `recorded_by` is set server-side from the authenticated staff User; it is
  never a form field and is never modified (BR-011, BR-008).
- `attended_at` is stored as a `timestamp` column in gym-local time with no
  timezone column (AT-05, ARCHITECTURE §17); "not in the future" is enforced
  by validation, not a DB CHECK (ADR-003).
- Attendance records are immutable: this design registers no `update`/`delete`
  policy ability, exposes no edit/delete page/action, and stores no `status`
  (BR-001, BR-008, ERR-008).

---

## 13. Related Documents

- Specification: `docs/specs/SPEC-008.md`
- Architecture decisions: `docs/adr/ADR-001.md` (role/authorization
  foundation), `docs/adr/ADR-002.md` (module boundary discipline),
  `docs/adr/ADR-003.md` (validation-first representation),
  `docs/adr/ADR-004.md` (status-as-string / `memberships:expire` command)
- Architecture: `docs/architecture/SPEC-001.md`,
  `docs/architecture/SPEC-002.md`, `docs/architecture/SPEC-003.md`,
  `docs/architecture/SPEC-004.md`, `docs/architecture/SPEC-006.md`,
  `ARCHITECTURE.md` (§7 Actions, §8 Models, §10 Events, §12 Authorization,
  §14 Plans/Memberships/Payments separate, §15 Scheduling, §17
  Multi-location, §20 simplest correct architecture)
- Product: `docs/product/product-definition-v0.1.md`
- Domain: `docs/domain/domain-model-v0.1.md` (§Attendance; C-02)
- Requirements analysis: `docs/requirements/analyst-pass-001.md` (§5.10
  Attendance, D-05, D-09, D-19, C-18, E-01, R-03)
- Specifications: `docs/specs/SPEC-001.md` (roles, D-19 option 1, C-13/C-15),
  `docs/specs/SPEC-002.md` (client records, D-01 option 2),
  `docs/specs/SPEC-004.md` (membership state machine, BR-004/BR-007/BR-010),
  `docs/specs/SPEC-006.md` (turno model, D-07 option 1, AS-01/AS-02),
  `docs/specs/SPEC-007.md` (BK-03/BK-13 — the reserved `completed` tie-in;
  BLOCKED, not a dependency; BR-005 — the same access-gate rule)
- Workflow state: `docs/sdd/state.yaml` (SPEC-008 `spec_ready`, current phase
  `architecture`; NIGHT MODE pre-approvals D-05/D-09; SPEC-001/002/004/006
  completed; SPEC-007 blocked)
- Development rules: `AGENTS.md`
