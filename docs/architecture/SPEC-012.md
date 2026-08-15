# Architecture — SPEC-012

## 1. Feature

Public Registration for the gym management system:

- an anonymous visitor can submit a registration from the public website
  (C-15), providing identity (full name, DNI), one email used as both contact
  and login (AS-02), a password, and optionally contact and health information
  (D-13 option 2 field set, reused from SPEC-002);
- a successful registration creates, in one transaction (BR-008), a **pending
  Client record** awaiting staff approval (D-17 option 2, pre-approved) **and**
  a linked **deactivated User** with the CLIENT role (AS-03, the design decision
  requested for the SPEC-013 portal need);
- the applicant is NOT logged in (BR-003) and sees a success screen
  ("registration received; staff will review it; you will be able to log in once
  approved");
- an ADMIN reviews pending registrations in the Filament admin panel and
  **approves** (Client `pending → active` + linked User activation, FR-004) or
  **rejects** (Client `pending → rejected`, terminal, linked User stays
  deactivated, FR-005);
- duplicate detection: DNI unique among clients regardless of status (SPEC-002
  BR-005) and email unique among users (SPEC-001 ERR-005);
- the registration does NOT include plan selection, purchase or payment
  (pre-approved decision on C-18; BR-012) and never creates a membership;
- the submission route is rate-limited per IP as spam hardening (BR-013,
  AS-07).

This is the public-side entry point that feeds the existing Client module
(SPEC-002): the records created here are ordinary Client records that later
Specifications (memberships, bookings, attendance, routines, portal) consume
without knowing their origin.

---

## 2. Specification

Reference:

`docs/specs/SPEC-012.md`

Status note: SPEC-012 is approved for the architecture phase per
`docs/sdd/state.yaml` (status `spec_ready`, current phase `architecture`,
architect `in_progress`). The business gates are pre-approved under NIGHT MODE
(`docs/sdd/state.yaml` `project.po_decisions`): **D-17 option 2** (registration
creates a pending Client awaiting staff approval) and the **C-18 scope note**
(no plan selection / purchase / payment at registration). Decision **D-01
option 2** (Client standalone, linked User optional) is PO-confirmed. The
Specification explicitly flags assumptions **AS-01 to AS-10** as NOT confirmed
business rules; they require Product Owner confirmation before Implementation
(SPEC-012 §14.1). This design is written against the assumptions as stated and
remains valid under the documented alternatives unless the PO changes them (see
§12 Pending PO Confirmations).

---

## 3. Affected Modules

- **Clients** (existing module, additive changes only): the `Client` model and
  `clients` table gain the registration-lifecycle `status` (pending / active /
  rejected, AS-01) with guarded transition methods; the `ClientResource` gains
  a status column, a pending filter, and Approve/Reject actions; `ClientPolicy`
  gains `approve` / `reject` abilities (ADMIN-only, AS-06). No existing
  migration is modified; the new column is additive with default `active` so
  existing and staff-created clients keep their behavior (AC-13).
- **Users** (existing module, additive usage only): the `User` model and the
  `users` / `roles` / `role_user` schema are UNCHANGED. Registration writes a
  new User (CLIENT role, `is_active = false`) through the same primitives as
  SPEC-002 provisioning; the existing `AuthenticatedSessionController` already
  rejects deactivated users with the generic message, which implements the
  pre-approval login gate (ERR-009) with no change.
- **Public website / Authentication** (existing presentation context, extended):
  two new guest-only web routes (`GET/POST /register`) and a success screen,
  implemented with minimal Blade views following the existing `auth/login`
  convention. The login flow (SPEC-001) is untouched.
- **Cross-cutting authorization foundation** (no new module): the new
  `approve` / `reject` Policy abilities extend the SPEC-002 pattern
  (ADMIN-only client management) and consume the existing `User::hasRole`
  helper (ADR-001).

No changes are made to: the `users` / `roles` / `role_user` / `plans` /
`memberships` tables, `AuthenticatedSessionController`, `ClientPortalController`,
`EnsureUserHasRole`, `AdminPanelProvider`, `ProvisionClientUser`, `RoleSeeder`,
`AdminUserSeeder`, or the `role_user` pivot. SPEC-002's rule "client CRUD never
creates users implicitly" (BR-002) is preserved: the staff-side `ClientResource`
create/edit flow stays as-is; registration is a distinct self-service public
flow (BR-002 of SPEC-012).

---

## 4. Application Flow

```text
Presentation (web)
    ↓
Public website: GET/POST /register (guest-only, Blade views, no layout)
                GET /register/complete (success screen)
Admin panel:   /admin — Filament ClientResource (ADMIN gate via canAccessPanel)
    ↓
Application
    ↓
Registration: RegisterRequest (validation) → RegisterClient Action (transaction)
Approval:     ClientResource Approve/Reject actions → ClientPolicy → Client::approve()/reject()
    ↓
Domain
    ↓
Client model (status + guarded transitions), User model (CLIENT role, is_active)
    ↓
Persistence
    ↓
PostgreSQL: clients (additive status column), users / roles / role_user (reused)
```

Concrete flows:

1. **Public registration (FR-001, FR-002, FR-006, FR-008)**
   - An anonymous visitor opens `GET /register` (guest-only; an authenticated
     user is redirected away — ERR-008, BR-011). The Blade form collects the
     SPEC-002 field set plus login credentials: required `full_name`, `dni`,
     `email`, `password` (+ confirmation); optional `phone`,
     `emergency_contact`, `injuries_notes`, `medical_conditions_notes`
     (FR-001). No plan/pricing/payment fields exist (BR-012, AC-14).
   - `POST /register` (guest + `throttle:registration`) is validated by
     `RegisterRequest`: required fields, email format, password policy
     (min 8, confirmed), DNI unique among clients (ERR-001), email unique among
     users (ERR-002). On failure the form is re-rendered with validation
     errors (ERR-003, ERR-004); no record is created.
   - On success the `RegisterClient` Action runs in one DB transaction
     (BR-008): creates a Client with `status = pending` (BR-001, BR-004),
     creates a User with the CLIENT role and `is_active = false` (BR-002,
     AS-03), links the User via `clients.user_id` (1:1, SPEC-002 BR-003), and
     writes the same email to both records (AS-02). The user is NOT
     authenticated (BR-003, AS-04).
   - The visitor is redirected (PRG) to `GET /register/complete`, a success
     screen with the generic "registration received" message that reveals no
     internal detail (FR-002, AS-11).
2. **Pre-approval login attempt (ERR-009, AF-008)**
   - The applicant tries `POST /login` with the chosen credentials. The
     existing SPEC-001 login (`Auth::attemptWhen` with `is_active`) rejects the
     deactivated user with the generic "invalid credentials" message — the
     response never reveals that the account exists or that it is pending
     approval. No login change is required.
3. **Admin review (FR-003, FR-007)**
   - The ADMIN opens the Clients section (`ClientResource`). A status column
     with a badge and a pending filter make the approval queue visible at a
     glance (FR-003, AC-6). The ADMIN opens the detail view, which shows the
     status, the full record including the health data (FR-007, SPEC-002
     BR-007 — never in lists or search), and the linked-account status
     (SPEC-002 FR-006).
   - The ADMIN may edit the pending client's identity/contact/health fields
     first (AF-003); editing never changes the status (no status field in the
     form, AS-06).
4. **Approve (FR-004, AC-7)**
   - The ADMIN triggers the Approve action on a `pending` row. `ClientPolicy`
     authorizes (ADMIN-only, BR-009), the transition guard requires `pending`
     (ERR-007), and `Client::approve()` runs in a transaction: Client
     `pending → active` and, when a linked User exists, `is_active` false →
     true. No membership is created (BR-012); the applicant can now log in and
     is redirected to the client portal context by the existing SPEC-001
     redirect rule (FR-004, SPEC-001 FR-006).
5. **Reject (FR-005, AC-8)**
   - The ADMIN triggers the Reject action on a `pending` row (ADMIN-only,
     guard `pending`). `Client::reject()` sets Client `pending → rejected`
     (terminal, BR-006); the linked User stays deactivated, so the applicant
     cannot log in and cannot re-register with the same DNI (BR-006, AF-005).
6. **Never-reviewed registration (AF-004, AC-16)**
   - A pending registration that staff never act on remains `pending`
     indefinitely; the linked User stays deactivated. No expiry, cleanup or
     job exists (BR-010, AS-09). No delete operation is provided for any
     client record (BR-010).

---

## 5. Components

### Controllers

**`App\Http\Controllers\Auth\RegistrationController`** (new, Breeze-style
placement under the `Auth` namespace, mirroring
`AuthenticatedSessionController`):

| Method | Route(s) | Responsibility |
| --- | --- | --- |
| `create()` | `GET /register` | Renders `auth/register` (the public registration form). Guest-only (BR-011). |
| `store()` | `POST /register` | Validates via `RegisterRequest`, invokes `RegisterClient`, redirects to `register.complete` (PRG). Guest-only + `throttle:registration` (BR-013). Never authenticates the applicant (BR-003). |
| `complete()` | `GET /register/complete` | Renders `auth/registration-complete` (the success screen, FR-002). Guest-only. |

The controller stays thin: validation lives in the Form Request, business
behavior in the Action, no business rules in the controller (ARCHITECTURE §6).

### Actions / Use Cases

**`App\Actions\RegisterClient`** (new — the only non-CRUD public operation of
this Specification)

- Input: validated registration data array (from `RegisterRequest`):
  `full_name`, `dni`, `email`, `password`, `phone`, `emergency_contact`,
  `injuries_notes`, `medical_conditions_notes`.
- Behavior (one DB transaction, BR-008):
  1. `Client::create([...])` with `status = Client::STATUS_PENDING` (BR-001,
     BR-004); `email` is the single submitted email (AS-02).
  2. `User::create([...])` with `name = full_name` (snapshot, per the SPEC-002
     provisioning convention), `email` = the same email, `password` (hashed by
     the `User` cast), `is_active = false` (BR-002, AS-03).
  3. `$user->roles()->attach(Role::firstOrCreate(['name' => Role::CLIENT]))`
     (same primitive as `ProvisionClientUser`; SPEC-001 BR-006 stays intact:
     this is the self-service registration flow, not staff user management).
  4. `$client->user()->associate($user); $client->save();` (sets
     `clients.user_id`, 1:1 — SPEC-002 BR-003).
  5. No event, no notification, no email is dispatched (welcome email out of
     scope, SPEC-012 §12). Returns the created pending Client.
- No authorization check inside the Action: this is the anonymous public entry
  point; the route-level `guest` middleware is the gate (defense in depth is
  not applicable without an authenticated actor). Uniqueness is enforced
  earlier by `RegisterRequest` and backed by the DB unique indexes
  (spec §9 Risks on races).
- `ProvisionClientUser` is NOT reused: it requires ADMIN authorization, creates
  an active user, and takes a separate login email — the opposite of the
  registration contract. Keeping the two Actions separate preserves SPEC-002
  BR-002 (staff CRUD never creates users) and SPEC-001 BR-006 (staff user
  management stays ADMIN-only).

Client create/edit stays plain Eloquent CRUD in `ClientResource` (SPEC-002
precedent); approval/rejection is modeled as guarded model transitions (see
Models), the same convention as `Membership::activate()/cancel()` and
`Turno::deactivate()/reactivate()/cancel()` — no new Action class is needed for
a two-field update (ARCHITECTURE §7, AGENTS.md §9).

### Models

**`App\Models\Client`** (modified additively)

- New status constants — single source of truth for the three registration-flow
  values (BR-004, AS-01; the string-with-constants convention of
  `Membership::STATUS_*`, `Turno::STATUS_*`, `Routine::STATUS_*`, ADR-004):
  - `Client::STATUS_PENDING = 'pending'`
  - `Client::STATUS_ACTIVE = 'active'`
  - `Client::STATUS_REJECTED = 'rejected'`
- `$fillable` gains `status` (written by the registration Action as `pending`;
  the Filament create form never supplies it — AS-06).
- New default attribute `$attributes = ['status' => self::STATUS_ACTIVE]`
  (mirrors the DB default; staff-created and pre-existing clients default to
  `active` — BR-004, AC-13). The status remains a plain string cast (like
  Membership), validated against the constants.
- New transition methods with `DomainException` guards (ERR-007, BR-005; the
  `Membership::activate()` pattern):
  - `approve(): void` — throws unless `status === STATUS_PENDING`; in one DB
    transaction sets `status = STATUS_ACTIVE` and, when a linked User exists
    (`$this->user`), sets `is_active = true` (FR-004, BR-005). The Client owns
    the user link (`user()`), so the coupled update lives here, not in the
    Filament action.
  - `reject(): void` — throws unless `status === STATUS_PENDING`; sets
    `status = STATUS_REJECTED`. The linked User is left untouched (stays
    deactivated, FR-005, AS-05). Terminal (BR-006).
- New predicates: `isPending(): bool`, `isActive(): bool`,
  `isRejected(): bool` (used by the resource for badges and action
  visibility). New query scope `scopePending(Builder)` →
  `where('status', static::STATUS_PENDING)` (FR-003 filter).
- Unchanged: `full_name`/`dni`/contact/health columns, `user()` /
  `hasLinkedUser()`, memberships/attendances/routines/workout-log relations,
  `hasQualifyingMembership()` / `accessDenialReason()` (SPEC-008).
  No delete scope/method (BR-010).

**`App\Models\User`** (no change)

Reused as-is: `is_active` boolean, `hasRole` / `hasAnyRole`, CLIENT role
attachment, `client(): HasOne`. No new column, fillable entry, cast or helper.

### Policies

**`App\Policies\ClientPolicy`** (modified additively)

- Existing abilities unchanged: `viewAny` / `view` / `create` / `update` —
  ADMIN only (SPEC-002 BR-004). No `delete` (BR-010).
- New abilities (BR-009, AS-06, ERR-006):
  - `approve(User $user, Client $client): bool` — ADMIN only.
  - `reject(User $user, Client $client): bool` — ADMIN only.
- All rules use `$user->hasRole(Role::ADMIN)` (ADR-001). The ability checks
  authorization (who may act); the model transition guards enforce the state
  rule (when acting is legal — ERR-007). TRAINER and CLIENT receive 403 / no
  navigation (ERR-006, AC-10).

Authorization matrix (SPEC-012 §9):

| Operation | Anonymous | ADMIN | TRAINER | CLIENT |
| --- | --- | --- | --- | --- |
| View / submit registration | Allowed (guest) | Redirected away (ERR-008) | Redirected away (ERR-008) | Redirected away (ERR-008) |
| Log in before approval | Denied, generic message (ERR-009) | — | — | — |
| Review / filter pending clients | Denied | Allowed | Denied | Denied |
| Approve / reject a pending registration | Denied | Allowed | Denied | Denied |
| Edit a pending client's data | Denied | Allowed (SPEC-002 FR-004) | Denied | Denied |
| View health data | Denied | Allowed (detail view only) | Denied | Denied |

### Events

None required.

Registration has no defined secondary effect (no welcome/approval/rejection
email, SPEC-012 §12); approval is a short synchronous transaction
(ARCHITECTURE §10-11). If SPEC-013 later needs e.g. `RegistrationApproved`, it
will be introduced there.

### Jobs

None required.

No queued work exists in SPEC-012 (no notifications, no cleanup/expiry of
pending registrations — BR-010, AS-09).

### Routes

| Method | URI | Middleware | Controller / Target | Notes |
| --- | --- | --- | --- | --- |
| GET | `/register` | `web`, `guest` | `RegistrationController@create` | FR-001, BR-011, ERR-008. |
| POST | `/register` | `web`, `guest`, `throttle:registration` | `RegistrationController@store` | FR-002, BR-013, ERR-005. |
| GET | `/register/complete` | `web`, `guest` | `RegistrationController@complete` | Success screen, FR-002, AS-11. |

Registered by extending the existing `guest` group in `routes/web.php` (the
SPEC-001 comment "registration deferred to SPEC-012" is updated). The
`throttle:registration` limiter is registered in `AppServiceProvider` (see
Rate limiting). No Filament routes: `/admin/clients*` auto-registers.

### Filament

**`App\Filament\Resources\ClientResource`** (modified additively — approval
lives in the existing resource per AS-06)

- Table (`table()`):
  - New `status` `TextColumn` with `->badge()` and a color per value
    (pending = warning, active = success, rejected = danger) — FR-003, AC-6.
  - New `SelectFilter::make('status')` with the three options (pending /
    active / rejected), so the approval queue is filterable (FR-003, AC-6).
  - Existing columns (name, DNI, email, phone, linked account) unchanged.
    Health fields never appear in the list or search (FR-007, SPEC-002 BR-007).
- Row actions (`actions()`), following the `TurnoResource`/`PlanResource`
  model-method pattern:
  - `Approve` — `visible(fn (Client $record) => $record->isPending())`;
    `->requiresConfirmation()` (recommended UI for a state-changing action);
    action closure authorizes (`Gate::authorize('approve', $record)` — BR-009,
    AGENTS.md §17) then calls `$record->approve()`.
  - `Reject` — `visible(fn (Client $record) => $record->isPending())`;
    `->requiresConfirmation()` (terminal, destructive — BR-006); closure
    authorizes (`Gate::authorize('reject', $record)`) then calls
    `$record->reject()`.
  - Both are hidden on non-pending rows for UX (ERR-007), but the guard is
    enforced server-side by the model transition (ERR-007) and the policy
    (BR-009). No bulk approve/reject is introduced (not specified).
- Form (`form()`): NOT modified in behavior. No `status` field is added:
  status is set by the flow — default `active` on staff create, `pending` on
  registration, transitions via Approve/Reject only (AS-06, AF-003). Editing a
  pending client therefore never changes the status.
- Infolist (`infolist()`): add a `status` `TextEntry` with a badge in a
  "Registration status" section (FR-003 detail view, AC-6). The existing
  "Linked account" section already shows the linked user and its active status
  (FR-003, SPEC-002 FR-006), which is the approval-relevant information.
- No delete action / bulk actions remain empty (BR-010).

### Views

- **`resources/views/auth/register.blade.php`** (new): the public registration
  form, following the inline-styled markup convention of the existing
  `auth/login.blade.php` (no shared layout yet — SPEC-015 is a soft dependency
  and the route/behavior contract does not depend on it). Posts to
  `route('register.store')`, renders server-side validation errors, and links
  to the login page. Contains ONLY the FR-001 field set — no plan, pricing or
  payment fields (BR-012, AC-14).
- **`resources/views/auth/registration-complete.blade.php`** (new): the
  success screen with the generic message (FR-002, AS-11). Renders no
  submitted data (no identity/contact/health values, AC-15) and no internal
  detail.
- The landing page `/` (`welcome`) is NOT modified: public website page
  content and navigation links are SPEC-015 concerns (SPEC-012 §12).

### Rate limiting

**`App\Providers\AppServiceProvider`** (modified additively)

- New `RateLimiter::for('registration', function (Request $request) { return
  Limit::perMinute(5)->by($request->ip()); })` — a conservative per-IP limit
  mirroring the existing `throttle:login` pattern (FR-008, BR-013, AS-07).
  Per-IP only (no email component) so the limiter cannot be used to probe
  which emails have registered. CAPTCHA / anti-bot are out of scope (§12 of
  SPEC-012).

---

## 6. Data Changes

### Migrations

1. **`2026_08_15_000014_add_status_to_clients_table.php`** (new; next
   migration in the existing timestamp sequence after
   `..._000013_create_workout_logs_table.php`):

   | Column | Type | Constraints |
   | --- | --- | --- |
   | `status` | string | NOT NULL, default `'active'` |

   - Additive: no existing migration is modified; existing rows receive the
     default `active` (no destructive migration, AGENTS.md §12; AC-13).
   - String column with the three flow values `pending` / `active` /
     `rejected` (BR-004, AS-01). No DB enum and no CHECK constraint: the
     project convention is framework validation plus model constants
     (ADR-003 rejected DB CHECK constraints; ADR-004 documented the
     string-with-constants convention for `memberships.status`,
     `turnos.status`, `routines.status`). The column default matches the
     model default attribute on `Client` (`STATUS_ACTIVE`).
   - `down()`: `Schema::table('clients', fn (Blueprint $table) =>
     $table->dropColumn('status'))`.

No other schema change: `users`, `roles`, `role_user`, `plans`, `memberships`
are reused as-is.

### Relationships

```text
users 1 ──── 0..1 clients        (clients.user_id nullable unique FK, unchanged)
```

Registration writes the same 1:1 link that SPEC-002 provisioning writes
(`clients.user_id`); the unique index continues to enforce the 1:1 rule
(SPEC-002 BR-003).

### Data lifecycle

- **Created:** Client records with `status = pending` (public registration);
  User records with the CLIENT role and `is_active = false`; their `role_user`
  assignments; the link value `clients.user_id`. No membership, plan, booking
  or payment record (BR-012).
- **Modified:** `clients.status` (`pending → active` on approve, `pending →
  rejected` on reject — BR-005); `users.is_active` false → true on approve
  (FR-004); client identity/contact/health fields via the existing SPEC-002
  edit flow (AF-003), which never changes status.
- **Deleted:** none. No delete operation for pending/rejected records
  (BR-010); no user hard deletion (SPEC-001 BR-007). A registration that is
  never reviewed remains `pending`; no cleanup job (AF-004, AS-09).

---

## 7. External Integrations

None.

SPEC-012 touches no external service: no email/notification provider, no
CAPTCHA/anti-bot service, no payment provider (Mercado Pago excluded by PO
decision; SPEC-014 out of backlog). Redis/queues are not used (no jobs).

---

## 8. Testing Strategy

Pest (PHPUnit) feature tests following the existing conventions
(`tests/Pest.php` helpers `role()`, `userWithRoles()`; `RefreshDatabase`;
`Client::factory()`). Extend `ClientFactory` with a `pending()` state
(`status => Client::STATUS_PENDING`) so approval tests can create pending
registrations directly. All authorization assertions are server-side
(AGENTS.md §17).

**Public registration (AC-1..AC-5, AC-11, AC-12, AC-14, AC-15)**
- `tests/Feature/Public/RegistrationTest.php` (new directory `Public`)
  - An anonymous visitor can open `/register` and submit a valid registration;
    one Client with status `pending`, one User with the CLIENT role and
    `is_active = false`, and the 1:1 link are created in one transaction
    (AC-1, FR-001, FR-002, BR-001, BR-002, BR-008); the submitted email is
    written to both records (AS-02).
  - The applicant is not authenticated after registration and sees the success
    screen (AC-2, BR-003, AS-04); the success screen does not echo submitted
    health/contact data (AC-15).
  - A login attempt with the chosen credentials before approval is rejected
    with the same generic message as invalid credentials and the applicant
    stays a guest (AC-2, ERR-009, AF-008); the message is identical to an
    unknown-email attempt (SPEC-001 A-05). After the ADMIN approves, the same
    credentials log in and redirect to `/portal` (AC-7, SPEC-001 FR-006).
  - Duplicate DNI (pending, active or rejected) is rejected with a validation
    error and no record is created (AC-3, ERR-001, BR-007).
  - Duplicate email (any user) is rejected with a validation error and no
    record is created (AC-4, ERR-002, BR-007).
  - Missing required fields, malformed email, short password or mismatched
    confirmation are rejected (AC-5, ERR-003, ERR-004).
  - The submission route is rate-limited: exceeding the per-IP limit yields
    the framework's standard 429 rate-limit response (AC-11, ERR-005, BR-013);
    the test isolates the limiter (unique IP / clear the `registration`
    limiter) so it does not leak into other tests.
  - An authenticated user (any role) is redirected away from `/register` and
    `/register/complete` (AC-12, ERR-008, BR-011).
  - The submitted payload never includes plan/pricing/payment fields and no
    membership row is created (AC-14, BR-012).

**Admin approval workflow (AC-6..AC-10, AC-13, AC-15, AC-16)**
- `tests/Feature/Admin/ClientApprovalTest.php` (new file; the SPEC-002
  `ClientManagementTest` CRUD coverage stays untouched)
  - A pending registration appears in the admin Clients list with a `pending`
    badge and is returned by the pending filter (AC-6, FR-003).
  - ADMIN can open the detail view of a pending client including the health
    data; health fields never appear in the list or search results
    (AC-6/AC-15, FR-007, SPEC-002 BR-007).
  - ADMIN can approve a `pending` client: Client becomes `active`, the linked
    User becomes `is_active = true`, and the applicant can log in and is
    redirected to `/portal` (AC-7, FR-004, BR-005).
  - ADMIN can reject a `pending` client: Client becomes `rejected` (terminal),
    the linked User stays deactivated, the applicant cannot log in, and a
    second registration with the same DNI is rejected (AC-8, FR-005, BR-005,
    BR-006).
  - Approving or rejecting a client whose status is `active` or `rejected` is
    rejected (AC-9, ERR-007, BR-005, AF-007).
  - TRAINER and CLIENT cannot list clients or approve/reject (403 or hidden
    navigation) (AC-10, ERR-006, BR-009).
  - A staff-created client (through `ClientResource` create) defaults to
    `active` and the SPEC-002 CRUD flows keep working (AC-13, BR-004).
  - No delete operation exists for pending/rejected records; a registration
    that is never reviewed remains `pending` and its linked User stays
    deactivated (AC-16, BR-010, AF-004).

**Unit**
- `tests/Unit/ClientStatusTest.php` (new)
  - The status constants are the three flow values (BR-004).
  - A model-created client defaults to `active` (AC-13); the `scopePending`
    query returns only pending clients (FR-003).
  - `approve()` only succeeds from `pending` and activates the linked User;
    `reject()` only succeeds from `pending` and leaves the User deactivated;
    illegal transitions throw (ERR-007, BR-005, AF-007).
  - The DB column default is `active` and the DB unique constraints on
    `clients.dni` and `clients.user_id` still reject duplicates (BR-007,
    SPEC-002 BR-003).

**Login regression**
- Existing `tests/Feature/Auth/LoginTest.php` coverage of deactivated-user
  rejection remains the baseline for ERR-009; the RegistrationTest flow
  exercises it end-to-end for a registration-created account.

---

## 9. Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Assumptions AS-01..AS-10 are unconfirmed (SPEC-012 §14.1); AS-03 (User creation at registration) is the explicitly requested design decision. | If the PO changes them, parts of the design change (e.g., AS-03 dropped → no User/link at registration; AS-01 different representation → column/constants change). | Keep implementation isolated: the `clients.status` column, the Client constants/transitions, the `RegisterClient` Action and the Resource actions are the only touch points. Block Implementation until PO confirms §12 items (NIGHT MODE pre-approval covers D-17 op2 / D-01 op2 / C-18 only). |
| Concurrent duplicate registrations (same DNI or email pass validation, then collide at insert). | One request fails with a QueryException/500 instead of a friendly validation error. | The DB unique indexes are the backstop (BR-007 is DB-enforced). Not specified by the spec; the failure surfaces safely inside the transaction and no partial record persists (BR-008). Documented, not handled with retry logic (out of scope). |
| Rate limiter state shared across tests / environments. | Tests may spuriously 429 or leak limits. | Isolate the rate-limit test (unique IP or `RateLimiter::clear('registration')`); the per-IP limit is conservative by design (BR-013). |
| `guest` middleware default redirect target for authenticated users. | ERR-008 redirect may land somewhere unexpected. | Reuse the exact `guest` middleware already applied to `/login` (SPEC-001 tests assert the framework default redirect `/`); no new redirect logic is introduced. |
| Approval touches two entities (Client + User). | Partial update if the second write fails. | `Client::approve()` wraps both writes in one transaction (FR-004); `reject()` touches only the Client (FR-005). |
| The status column is string-typed with no DB enum/CHECK. | Application bugs could write an invalid status on a future write path. | The column default + model default (`active`) and the guarded transition methods constrain every current write path; consistent with ADR-003/ADR-004 precedent. |
| SPEC-015 (presentation foundation) is a soft dependency. | Register views may be restyled later. | Views follow the existing inline-style `auth/login` convention and expose no business logic; SPEC-015 restyle is presentation-only. |
| Health data submitted at registration is stored in `clients` (ADR-002 in-table columns). | Data exposure if a future role gains client access. | Access stays ADMIN-only via `ClientPolicy`; health fields never appear in lists/search (FR-007, SPEC-002 BR-007) — unchanged from SPEC-002. |

---

## 10. Alternatives Considered

1. **Install Laravel Breeze / Jetstream for the registration scaffolding.** —
   Rejected: AGENTS.md §14 requires justification for dependencies, the
   project already implements a custom Breeze-style login
   (`AuthenticatedSessionController` + Blade views), and SPEC-012 needs only
   two routes, one form and one action. A minimal controller + Blade views +
   Form Request matches the installed auth convention exactly.
2. **Reuse `ProvisionClientUser` for the registration-created User.** —
   Rejected: the Action requires ADMIN authorization, creates an `active` user,
   and takes a separate login email — the opposite of the anonymous,
   deactivated, single-email registration contract (BR-002, AS-02, AS-03).
   A dedicated `RegisterClient` Action keeps the two flows' rules separate and
   preserves SPEC-002 BR-002 / SPEC-001 BR-006.
3. **Model approve/reject as dedicated Action classes
   (`ApproveClientRegistration` / `RejectClientRegistration`).** — Considered
   and rejected: approval/rejection updates existing records with a guard; the
   established project pattern for user-initiated transitions is guarded model
   methods + thin Filament action closures (`Membership::activate/cancel`,
   `Turno::deactivate/reactivate/cancel`, `PlanResource` activate/deactivate).
   An Action class would be an unnecessary abstraction (AGENTS.md §9). The
   explicit Action pattern remains reserved for the registration write path,
   which creates two entities transactionally (the `ProvisionClientUser`
   precedent).
4. **Success message via session flash on the `/register` page instead of a
   dedicated success screen.** — Considered: simpler (one fewer route/view),
   but the spec requires a success screen (FR-002) that must not expose the
   form again; a dedicated guest-only `GET /register/complete` with PRG is
   cleaner, directly testable (`assertSee`), and prevents double-submission on
   refresh. Rejected alternative documented for the Developer.
5. **DB enum / CHECK constraint for `clients.status`.** — Rejected: the project
   convention is plain string columns with model constants and framework
   validation (ADR-003 rejected CHECK constraints; ADR-004 documented the
   string-with-constants pattern for every existing status column). The spec
   explicitly delegates the representation to the Architect (SPEC-012 §10).
6. **Gate pre-approval login by client status (new middleware / login check).** —
   Rejected as unnecessary: the registration-created User is deactivated
   (`is_active = false`) and SPEC-001 already rejects deactivated users with
   the generic message. ERR-009 falls out of the existing flow with zero
   changes; adding a client-status check would couple the auth module to the
   clients module without adding protection.

---

## 11. Decision

Use the established project conventions throughout:

- **Persistence:** additive migration `2026_08_15_000014_add_status_to_clients_table.php`
  adding a NOT NULL `status` string column defaulting to `'active'`; no enum,
  no CHECK (ADR-003/ADR-004 precedent); existing rows unaffected (AC-13).
- **Domain state:** `Client::STATUS_PENDING / STATUS_ACTIVE / STATUS_REJECTED`
  constants, `status` fillable with model default `active`, `scopePending`,
  `isPending/isActive/isRejected` predicates, and guarded `approve()` /
  `reject()` transition methods (DomainException on illegal transitions,
  approve activating the linked User in a transaction) — the
  `Membership`/`Turno` state-machine pattern.
- **Registration flow:** `RegistrationController` (create/store/complete) +
  `RegisterRequest` (FR-001 field set, DNI unique among clients, email unique
  among users, framework password policy, confirmed password) +
  `App\Actions\RegisterClient` (one transaction: pending Client + deactivated
  CLIENT User + 1:1 link; no auto-login; no membership) + two Blade views
  following the `auth/login` convention + success screen (PRG).
- **Approval workflow:** the existing `ClientResource` gains a status badge
  column, a pending filter, and Approve/Reject row actions that authorize via
  the new `ClientPolicy::approve`/`reject` abilities (ADMIN-only, BR-009) and
  call the model transitions. Status is never a staff-editable form field
  (AS-06).
- **Login gate:** no change — the SPEC-001 deactivated-user rejection
  implements ERR-009; verified by tests.
- **Spam hardening:** `RateLimiter::for('registration', per-minute per-IP)` in
  `AppServiceProvider`, applied to `POST /register` (BR-013).
- **No events, no jobs, no new seeders, no external integrations, no ADR** —
  the design reuses existing documented decisions (ADR-001, ADR-002, ADR-003,
  ADR-004) and introduces no new significant architectural decision.

---

## 12. Pending PO Confirmations

These items are carried from SPEC-012 and must be confirmed before
Implementation (or at latest before Review). This design does not silently
resolve them.

### Assumptions (SPEC-012 §14.1)

| ID | Assumption | Impact on this design |
| --- | --- | --- |
| AS-01 | "Pending" is a new `clients.status` with exactly `pending`/`active`/`rejected`, default `active`. | New column + constants + transitions. |
| AS-02 | Single email used as both client contact and user login; email required at registration. | `RegisterRequest` (email required) + `RegisterClient` writes the same email to both records. |
| AS-03 | Registration creates BOTH a pending Client AND a linked deactivated CLIENT User, in one transaction; approval activates the User. | `RegisterClient` Action; `Client::approve()` user activation. (The explicitly requested design decision for D-01/D-17 + SPEC-013.) |
| AS-04 | No auto-login after registration. | Controller never calls `Auth::login`; success redirect only. |
| AS-05 | Rejection is the terminal `rejected` status; linked User stays deactivated. | `Client::reject()`; no reactivation path. |
| AS-06 | Approval UI lives in the existing `ClientResource` (badge, filter, Approve/Reject actions, ADMIN-only); status is not a staff-editable form field. | Resource changes; no status form field. |
| AS-07 | Registration submission route is rate-limited per IP (throttle). | `RateLimiter::for('registration')` + `throttle:registration`. |
| AS-08 | Registration is guest-only; authenticated users redirected away. | `guest` middleware on all three routes. |
| AS-09 | No expiry/SLA/cleanup of pending registrations; no cleanup job. | No job, no schedule; pending stays pending. |
| AS-10 | No restriction on other modules referencing pending/rejected clients (OQ-06). | No change to membership/attendance/other module rules. |

### Open questions (SPEC-012 §14.2)

| ID | Question | Impact on this design |
| --- | --- | --- |
| OQ-01 | Additional guidance on the success screen (reference number, review time)? | Copy-only change in `registration-complete`; SPEC-015 presentation concern. |
| OQ-02 | Applicant-facing "check status" page before SPEC-013? | Not built; out of scope (SPEC-012 §12). |
| OQ-03 | Notification emails on approval/rejection? | Not built; out of scope (SPEC-012 §12). |
| OQ-04 | Automatic expiry/reminder for pending registrations? | Not built (AS-09). |
| OQ-05 | Registration link from a public Plans page? | Presentation concern of SPEC-015; not required by ACs. |
| OQ-06 | Exclude pending/rejected clients from membership creation (SPEC-004)? | Not introduced (AS-10); if wanted, a change to the consuming module's rules. |
| OQ-07 | DNI format validation? | None imposed, consistent with SPEC-002 (presence + uniqueness only). |
| OQ-08 | Split pending→active from User activation? | Coupled in `Client::approve()` per AS-03/FR-004; a split would be an additive change. |

### Additional design notes flagged for confirmation

- The registration-created User's `name` is a snapshot of the submitted
  `full_name` (the SPEC-002 provisioning convention); no later sync with the
  Client's name (same default as SPEC-002 OQ-05).
- Concurrent duplicate submissions rely on the DB unique indexes as the
  backstop; the failing request surfaces the DB error rather than a friendly
  validation message (documented risk, not specified behavior).
- The `guest` middleware redirect target for authenticated visitors reuses the
  framework default already in use for `/login` (SPEC-001 tests assert `/`).

---

## 13. Related Documents

- Specification: `docs/specs/SPEC-012.md`
- Architecture decisions: `docs/adr/ADR-001.md` (roles/authorization),
  `docs/adr/ADR-002.md` (clients table / link / health columns),
  `docs/adr/ADR-003.md` (validation-first precedent),
  `docs/adr/ADR-004.md` (string-with-constants status precedent)
- Architecture: `docs/architecture/SPEC-001.md`, `docs/architecture/SPEC-002.md`,
  `ARCHITECTURE.md` (§5 presentation contexts, §7 Actions, §12 authentication)
- Product: `docs/product/product-definition-v0.1.md`
- Domain: `docs/domain/domain-model-v0.1.md` (User, Client)
- Requirements analysis: `docs/requirements/analyst-pass-001.md` (§5.15 Public
  Registration, D-01, D-13, D-17, C-15, C-18, E-08, E-10)
- Workflow state: `docs/sdd/state.yaml` (SPEC-012 entry, NIGHT MODE
  `project.po_decisions`)
- Development rules: `AGENTS.md`
