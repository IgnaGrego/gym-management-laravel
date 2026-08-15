# Architecture — SPEC-001

## 1. Feature

Authentication & Roles foundation for the gym management system:

- a User can log in and log out;
- every authenticated request carries the User's identity and roles;
- access to the two authenticated presentation contexts (Filament admin panel and
  client portal) is controlled by roles;
- the authorization foundation (identity + roles exposed to policies) is the base
  that all later Specifications build on;
- ADMIN can manage staff user accounts and role assignments.

This is the first Specification of the MVP. The repository contains an empty Laravel
scaffold (no application code exists yet), so this design is greenfield.

---

## 2. Specification

Reference:

`docs/specs/SPEC-001.md`

Status note: SPEC-001 is approved for the architecture phase per
`docs/sdd/state.yaml` (status `architecture`). The Specification explicitly flags
assumptions **A-01 to A-05** as NOT confirmed business rules; they require Product
Owner confirmation before Implementation (SPEC-001 §14.1). This design is written
against the assumptions as stated and remains valid under the documented
alternatives unless the PO changes them (see §12 Pending Confirmations).

---

## 3. Affected Modules

- **Users** (new module): authentication (login/logout/session), the role catalog
  (ADMIN, TRAINER, CLIENT), multi-role assignment, staff user management by ADMIN,
  initial ADMIN provisioning.
- **Cross-cutting authorization foundation** (no new module, contract for all later
  modules): the authenticated `User` and its roles are available to every module's
  Policies. Modules such as Clients, Plans, Memberships, Payments, Scheduling,
  Bookings, Attendance, Exercises and Routines will enforce their own rules on top
  of this foundation in later Specifications (FR-009, AC-12).

No other module is implemented in this Specification. The client portal context is
created as a gate only; portal features are deferred to SPEC-013. Client account
provisioning and the Client ↔ User relationship are deferred to SPEC-002
(assumption A-01).

---

## 4. Application Flow

```text
Presentation (web)
    ↓
Public website: landing (/) and login (/login) — Laravel web routes, Blade views
Admin panel:   /admin — Filament panel (ADMIN | TRAINER)
Client portal: /portal — Laravel web route (CLIENT)
    ↓
Application
    ↓
Auth scaffolding (Laravel Breeze-style) → AuthenticatedSessionController
Role checks: User::canAccessPanel (Filament), EnsureUserHasRole middleware (portal),
             UserPolicy (staff user management)
    ↓
Domain
    ↓
User model (identity + credentials + active status), Role model + role_user pivot
    ↓
Persistence
    ↓
PostgreSQL: users, roles, role_user, framework sessions
```

Concrete flows:

1. **Login (FR-001)**
   - `GET /login` shows the login form (guest-only).
   - `POST /login` validates email + password, then attempts authentication with
     `email`, `password` and `is_active = true` (BR-007, ERR-002). The framework's
     default hashing is used (FR-010, BR-008).
   - On failure: generic "invalid credentials" message (ERR-001, A-05). The message
     never reveals whether the email exists or whether the account is deactivated.
   - On success: a session is created (FR-003) and the user is redirected based on
     roles (see 5. Redirect rule).
2. **Context access (FR-006, BR-004)**
   - Admin panel: Filament checks `User::canAccessPanel()` on every panel request;
     a user without ADMIN or TRAINER receives 403 (ERR-004).
   - Client portal: the `web` + `auth` + `role:CLIENT` middleware group guards
     `/portal`; guests are redirected to `/login` (ERR-003), authenticated users
     without CLIENT receive 403.
   - Public pages (`/`, `/login`) are the only anonymous entry points (BR-003).
3. **Logout (FR-002)**
   - `POST /logout` (authenticated) terminates the session and regenerates the CSRF
     token (framework default); protected pages require login again (AC-3).
4. **Staff user management (FR-007)**
   - ADMIN opens the Filament `UserResource`, creates staff users (name, email,
     password, roles ADMIN/TRAINER), edits role assignments, and toggles the active
     status. Every mutation is authorized by `UserPolicy` (BR-006).
   - A deactivated user cannot log in (BR-007, ERR-002, AC-8).

---

## 5. Components

### Controllers

| Controller | Route(s) | Responsibility |
| --- | --- | --- |
| `App\Http\Controllers\Auth\AuthenticatedSessionController` | `GET/POST /login`, `POST /logout` | Provided by the Laravel auth scaffolding (Breeze-style). Customized: (a) registration and profile routes are removed (out of scope: SPEC-012, §12 of SPEC-001); (b) `store()` authenticates with `is_active = true` (via `Auth::attemptWhen` or equivalent) so deactivated users are rejected with the generic message; (c) after successful login, redirect by role. |
| `App\Http\Controllers\ClientPortalController` | `GET /portal` | Minimal placeholder page proving the CLIENT context gate works (identity + role display). Real portal features are SPEC-013. |

The Filament `UserResource` acts as the admin-side controller for staff user
management (FR-007); no separate HTTP controller is needed.

### Actions / Use Cases

None required.

Staff user creation, role assignment and deactivation are standard Eloquent CRUD
performed through the Filament `UserResource` with Form Request validation and
`UserPolicy` authorization. Introducing an explicit Action would be an unnecessary
abstraction at this stage (AGENTS.md §9-10, ARCHITECTURE §7). A dedicated Action
may be introduced later if a non-CRUD operation (e.g., password generation) becomes
a requirement.

### Models

**`App\Models\User`** (framework default, extended)

- Fields: `id`, `name`, `email` (unique), `password`, `is_active` (bool, default
  true), timestamps. The framework's `remember_token` column remains as scaffold
  default but is unused (remember-me is out of scope, AF-004).
- Casts: `password` hashed; `is_active` boolean.
- Relationships:
  - `roles(): BelongsToMany` → `Role` via `role_user`.
- Helpers (simple domain behavior, ARCHITECTURE §8):
  - `hasRole(string $role): bool`
  - `hasAnyRole(array $roles): bool`
- Implements `FilamentUser`:
  - `canAccessPanel(Panel $panel): bool` → `$this->hasAnyRole([Role::ADMIN, Role::TRAINER])` (FR-006, BR-004).

**`App\Models\Role`** (new)

- Fields: `id`, `name` (string, unique), timestamps.
- Constants (single source of truth for the fixed catalog, FR-004, BR-001):
  - `Role::ADMIN = 'ADMIN'`
  - `Role::TRAINER = 'TRAINER'`
  - `Role::CLIENT = 'CLIENT'`
- Relationship: `users(): BelongsToMany` → `User`.

The catalog is fixed; there is no dynamic role creation in the MVP (FR-004, A-04).
The CLIENT role exists in the catalog and is seeded, but the `UserResource` UI only
offers ADMIN/TRAINER because client account provisioning is deferred to SPEC-002
(A-01).

### Policies

**`App\Policies\UserPolicy`**

- `viewAny` / `view`: ADMIN only.
- `create`: ADMIN only (BR-006, FR-007).
- `update`: ADMIN only (BR-006) — covers role changes and deactivation.
- No `delete` policy is registered: user records are never hard-deleted (BR-007);
  deactivation is an `update`.
- Technical safeguard (not a business rule): an ADMIN cannot deactivate their own
  account, preventing accidental lockout. Flagged for PO confirmation (§12).

Other modules will receive their own Policies in later Specifications; this
Specification only establishes the mechanism (FR-009, AC-12).

### Middleware

**`App\Http\Middleware\EnsureUserHasRole`** (new, alias `role`)

- Parameters: one or more role names.
- Behavior: if the authenticated user lacks any of the required roles → 403.
- Registered in the framework middleware alias list (`bootstrap/app.php` on
  Laravel 11+; `app/Http/Kernel.php` on Laravel 10).
- Used by the client portal route group (`role:CLIENT`).

Framework middlewares used as-is: `auth` (guest redirect to `route('login')` with
intended URL, satisfying ERR-003 "optionally with a return URL"), `guest` (login
page), `throttle` (login rate limiting, framework default security hardening).

### Events

None required.

No operation in SPEC-001 has a defined secondary effect that needs decoupling
(ARCHITECTURE §10). If a later Specification needs e.g. `UserDeactivated`, it will
be introduced there.

### Jobs

None required.

No queued work exists in SPEC-001 (no notifications, email, or slow operations).

### Routes

| Method | URI | Middleware | Controller / Target | Notes |
| --- | --- | --- | --- | --- |
| GET | `/` | `web` | Public landing (Blade) | Public website, anonymous. |
| GET | `/login` | `web`, `guest` | `AuthenticatedSessionController@create` | FR-001. |
| POST | `/login` | `web`, `guest`, `throttle` | `AuthenticatedSessionController@store` | FR-001, ERR-001/002. |
| POST | `/logout` | `web`, `auth` | `AuthenticatedSessionController@destroy` | FR-002, AC-3. |
| GET | `/portal` | `web`, `auth`, `role:CLIENT` | `ClientPortalController@index` | Client portal gate (FR-006, BR-004). |
| - | `/admin/*` | Filament panel (id `admin`) | Filament resources | Admin panel, `canAccessPanel` gate (FR-006). |

Registration route is NOT registered (public registration deferred to SPEC-012).

### Filament panel

**`App\Providers\Filament\AdminPanelProvider`** (new)

- Panel id: `admin`; path: `/admin`; auth guard: `web` (default).
- Contains the `UserResource` (staff user management).
- Access is governed by `User::canAccessPanel()` (ADMIN or TRAINER).

### Seeders

**`Database\Seeders\RoleSeeder`** (new)

- Idempotent: inserts `Role::ADMIN`, `Role::TRAINER`, `Role::CLIENT` if absent
  (FR-004, BR-001). Runs before `AdminUserSeeder`.

**`Database\Seeders\AdminUserSeeder`** (new)

- Creates the first ADMIN user so the system is usable (FR-008, AC-11, A-03).
- Credentials source (pending OQ-06): environment variables
  `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`; if any are missing the seeder
  aborts with a clear message (no hardcoded credentials committed).
- `.env.example` documents the variables. Local-dev defaults may be documented for
  convenience; production must set real values.

---

## 6. Data Changes

### Migrations

1. **`create_users_table`** (framework default, extended): `id`, `name`, `email`
   (unique index — also satisfies ERR-005 duplicate email validation), `password`,
   `remember_token` (unused, scaffold default), `is_active` (boolean, default
   `true`), timestamps.
2. **`create_roles_table`** (new): `id`, `name` (string, unique), timestamps.
3. **`create_role_user_table`** (new): `role_id` (FK → roles, cascade delete),
   `user_id` (FK → users, cascade delete), composite primary key
   `(role_id, user_id)`, timestamps. Enforces multi-role integrity (FR-005).
4. Framework default `cache` and `jobs` migrations remain (scaffold).

### Relationships

```text
users 1 ──── * role_user * ──── 1 roles
```

### Data lifecycle

- **Created:** user records (identity, hashed credential, active status); role
  assignment records; session records (framework-managed); seeded ADMIN user and
  fixed roles.
- **Modified:** `users.is_active` when deactivated (FR-007, BR-007); role
  assignments via the pivot (FR-007).
- **Deleted:** no hard deletion in the MVP (BR-007). Cascade delete on the pivot
  only cleans up assignments when a record is removed, which does not occur in the
  MVP business flow.

---

## 7. External Integrations

None.

SPEC-001 touches no external service. Mercado Pago integration is out of scope
(SPEC-014). Redis is available per ARCHITECTURE §2 for sessions/cache/queues; the
concrete session driver choice is configuration, not a business rule (OQ-05).

---

## 8. Testing Strategy

Pest (PHPUnit) feature tests covering the acceptance criteria of SPEC-001 §11:

**Authentication (AC-1, AC-2, AC-3, AC-4, AC-8, AC-10)**
- `tests/Feature/Auth/LoginTest.php`
  - a user with valid credentials can log in (AC-1);
  - an ADMIN/TRAINER is redirected to `/admin` after login (AF-001);
  - a CLIENT-only user is redirected to `/portal` after login (AF-002);
  - a multi-role user is redirected per the default rule (AF-003, AC-9);
  - invalid email or wrong password is rejected with the generic message (AC-2,
    ERR-001); the message is identical whether the email exists or not (A-05);
  - a deactivated user cannot log in (AC-8, ERR-002, BR-007) with the same generic
    message;
  - the stored password is hashed and `Hash::check` passes (AC-10, FR-010).
- `tests/Feature/Auth/LogoutTest.php` — after logout the session is terminated and
  protected pages are inaccessible (AC-3).
- `tests/Feature/Auth/AccessControlTest.php`
  - guests are redirected to `/login` for `/admin` and `/portal` (AC-4, ERR-003);
  - a CLIENT-only user receives 403 on `/admin` (AC-5, ERR-004, BR-004);
  - a TRAINER can access `/admin` (AC-6);
  - a user with no roles can authenticate but has no context access (edge case,
    documented behavior).

**Staff user management (AC-6, AC-7)**
- `tests/Feature/Admin/UserManagementTest.php`
  - ADMIN can create a staff user, assign roles, change assignments and deactivate
    (AC-7);
  - role/status changes take effect on the user's next request (AC-7);
  - duplicate email is rejected (ERR-005);
  - TRAINER and CLIENT cannot access user management (AC-6, BR-006).

**Seeder (AC-11)**
- `tests/Feature/Seed/AdminUserSeederTest.php` — after seeding, the initial ADMIN
  exists and can log in (AC-11, FR-008).

**Unit**
- `tests/Unit/UserRoleTest.php` — `hasRole`, `hasAnyRole`, multi-role union
  behavior (AC-9, BR-002).

**Authorization / Policy**
- `tests/Feature/Auth/UserPolicyTest.php` or inline policy tests — only ADMIN may
  create/update users (BR-006); self-deactivation safeguard behavior.

All authorization assertions are server-side (AGENTS.md §17); no test relies on
frontend visibility.

---

## 9. Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Assumptions A-01..A-05 are unconfirmed (SPEC-001 §14.1). | If PO changes them, parts of the design change (e.g., A-02 extends user management to another role; A-04 adds a role). | Keep implementation isolated: role constants, policy rules and seeder inputs are the only touch points. Block Implementation until PO confirms §12 items. |
| OQ-04: multi-role landing/navigation undefined (E-11). | Default redirect behavior may not match PO intent. | Design uses a documented default (staff context preferred). PO confirmation needed before Review. |
| OQ-06: initial ADMIN credential delivery mechanism undefined. | Seeder mechanism may be replaced (artisan command vs env). | Env-based seeder is the least surprising default; swappable without schema change. |
| OQ-02/OQ-05: password policy and session lifetime undefined. | Framework defaults used; may differ from PO expectations. | No custom code beyond framework default; easy to tighten later. |
| Empty scaffold: Laravel version differences (middleware registration, Breeze structure, Filament version). | Developer must adapt naming/registration details. | Design is mechanism-level and version-agnostic; developer follows the installed framework conventions. |
| A user with zero roles authenticates but has no context. | Not covered by SPEC-001; login redirect must handle it. | Documented default: redirect to `/` with a notice. Flagged in §12. |

---

## 10. Alternatives Considered

1. **Permission package (Spatie Laravel Permission)** — provides roles,
   permissions, gates and Filament integration out of the box. Rejected for the
   MVP: the catalog is fixed at three roles with no dynamic permission matrix
   (FR-004), which makes the package's main value unused, and AGENTS.md §14
   requires justification for dependencies. Recorded in ADR-001.
2. **Single `role` column on `users`** — simplest schema, but violates FR-005
   (multi-role users). Rejected.
3. **JSON array of roles on `users`** — no referential integrity, awkward queries.
   Rejected in favor of a normalized pivot.
4. **Manual authentication (no scaffold)** — fully custom login controller/views.
   Rejected in favor of the Laravel auth scaffolding (Blade + Tailwind + Alpine),
   which matches the documented stack; only the out-of-scope routes are removed.

---

## 11. Decision

Use native Laravel mechanisms throughout:

- **Authentication:** Laravel auth scaffolding (Breeze-style) with registration and
  profile routes removed; login customized to reject deactivated users with a
  generic message; role-based post-login redirect.
- **Roles:** normalized `roles` + `role_user` pivot with Eloquent
  `belongsToMany`, fixed `Role` constants, and small `hasRole`/`hasAnyRole` helpers
  on `User` (ADR-001). No permission package.
- **Authorization:** Filament `canAccessPanel` for the admin panel,
  `EnsureUserHasRole` middleware for the client portal, and `UserPolicy` for staff
  user management (ADMIN only). Identity + roles are exposed to all module Policies
  (FR-009, AC-12).
- **Provisioning:** `RoleSeeder` (fixed catalog) + env-based `AdminUserSeeder`
  (initial ADMIN).
- **No hard deletion:** `is_active` deactivation only (BR-007).

---

## 12. Pending PO Confirmations

These items are carried from SPEC-001 and must be confirmed before Implementation
(or at latest before Review). This design does not silently resolve them.

### Assumptions (SPEC-001 §14.1)

| ID | Assumption | Impact on this design |
| --- | --- | --- |
| A-01 | Client record is standalone; linked User optional; client user provisioning deferred to SPEC-002. | `UserResource` offers only ADMIN/TRAINER roles; CLIENT role seeded but not exposed in the UI. |
| A-02 | Only ADMIN creates/manages staff users; deactivation over deletion. | `UserPolicy` rules; `is_active` field; no `delete`. |
| A-03 | Initial ADMIN via seeder. | `AdminUserSeeder`. |
| A-04 | Role catalog stays at ADMIN/TRAINER/CLIENT. | `Role` constants; no RECEPTIONIST. If PO adds a role, catalog/constants/policies ripple. |
| A-05 | Framework default password policy (min length 8) + generic login failure message. | Framework validation; generic `auth.failed` message; no enumeration. |

### Open questions (SPEC-001 §14.2)

| ID | Question | Impact on this design |
| --- | --- | --- |
| OQ-01 | RECEPTIONIST role needed? | If yes, SPEC-001 and this design must be updated (role catalog, panel access, user management permissions). |
| OQ-02 | Exact password policy / recovery? | Framework default used now; tightening is configuration-only. |
| OQ-03 | Can deactivated users be reactivated, and who decides? | `is_active` toggle already supports reactivation by ADMIN if approved. |
| OQ-04 | Multi-role landing context and navigation? | Default used: staff role present → `/admin`, else CLIENT → `/portal`. Confirmation required. |
| OQ-05 | Session lifetime / expiry / concurrency? | Framework defaults used; configuration-only change later. |
| OQ-06 | Initial ADMIN credential delivery? | Env-based seeder used; artisan command is a drop-in alternative. |

### Additional design note flagged for confirmation

- A user with zero roles can authenticate but has no context access; default
  behavior redirects to `/` with a notice. Not specified in SPEC-001.
- Self-deactivation of an ADMIN is blocked as a technical safeguard.

---

## 13. Related Documents

- Specification: `docs/specs/SPEC-001.md`
- Architecture decision: `docs/adr/ADR-001.md`
- Architecture: `ARCHITECTURE.md`
- Product: `docs/product/product-definition-v0.1.md`
- Domain: `docs/domain/domain-model-v0.1.md`
- Requirements analysis: `docs/requirements/analyst-pass-001.md`
- Development rules: `AGENTS.md`
- Workflow state: `docs/sdd/state.yaml`
