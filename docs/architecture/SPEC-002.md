# Architecture — SPEC-002

## 1. Feature

Client Management for the gym management system:

- an ADMIN can create, list, search, view and edit client records (identity,
  contact and basic health information);
- client records are standalone: a linked User account is optional and can be
  created later (PO-confirmed decision D-01 / SPEC-001 assumption A-01);
- an ADMIN can provision a User account (CLIENT role) for an existing client so
  the client can later access their own portal context (portal features are
  SPEC-013);
- health notes are treated as sensitive data: visible only to ADMIN, never in
  lists or search results, never exposed to other clients (C-13);
- client records are never hard-deleted.

This is the second Specification of the MVP. It builds directly on the SPEC-001
foundation already implemented in the repository (User, Role, `role_user`,
`UserPolicy`, `UserResource`, `RoleSeeder`, `AdminUserSeeder`,
`EnsureUserHasRole`, Filament admin panel).

---

## 2. Specification

Reference:

`docs/specs/SPEC-002.md`

Status note: SPEC-002 is approved for the architecture phase per
`docs/sdd/state.yaml` (status `architecture`). The PO-confirmed decision D-01
(Client is a standalone record; linked User optional, created later) is recorded
in `docs/sdd/state.yaml` as the confirmation of SPEC-001 assumption A-01. The
Specification explicitly flags assumptions **AD-01 to AD-07** as NOT confirmed
business rules; they require Product Owner confirmation before Implementation
(SPEC-002 §14.1). This design is written against the assumptions as stated and
remains valid under the documented alternatives unless the PO changes them (see
§12 Pending PO Confirmations).

---

## 3. Affected Modules

- **Clients** (new module): client records (identity, DNI, contact, health
  notes), the optional 1:1 Client ↔ User link, ADMIN-only management, and the
  explicit provisioning operation.
- **Users** (existing module, additive changes only): the `User` model gains a
  `client()` relationship so the link is navigable from both sides. No schema
  change to the `users` table and no change to authentication, `UserPolicy` or
  `UserResource`. Provisioning reuses the SPEC-001 infrastructure: `User::create`
  + `roles()->attach(Role::CLIENT)`.
- **Cross-cutting authorization foundation** (no new module): a new
  `ClientPolicy` extends the SPEC-001 pattern (ADMIN-only management, no hard
  delete) and consumes the existing `User::hasRole` helpers (ADR-001).

No changes are made to: auth scaffolding (login/logout/redirect), the
`EnsureUserHasRole` middleware, `AdminPanelProvider`, `RoleSeeder`,
`AdminUserSeeder`, or the `role_user` pivot.

---

## 4. Application Flow

```text
Presentation (web)
    ↓
Admin panel: /admin — Filament panel (ADMIN | TRAINER gate via canAccessPanel)
    ↓
ClientResource (Filament): list / create / view / edit clients  (ADMIN-only)
    ↓
Application
    ↓
ClientPolicy (ADMIN-only)            ProvisionClientUser Action (transaction)
    ↓
Domain
    ↓
Client model (standalone record + optional user link), User model (CLIENT role)
    ↓
Persistence
    ↓
PostgreSQL: clients (new), users / roles / role_user (existing, reused)
```

Concrete flows:

1. **Client CRUD (FR-001..FR-004)**
   - ADMIN opens the Clients section of the admin panel (`ClientResource`).
   - Create: fills required identity data (`full_name`, `dni`) plus optional
     contact and health fields, saves. Validation: required fields (ERR-002),
     unique DNI (ERR-001), email/phone formats (ERR-006).
   - The record is persisted and appears in the list (FR-002); search is by
     `full_name`, `dni` and `email` only.
   - ADMIN opens the detail view (FR-003) showing the full record including
     health notes, or edits fields (FR-004). Changes persist.
   - No delete operation exists (BR-006).
2. **Provisioning (FR-005, FR-006)**
   - From the client detail view, ADMIN opens the "Provision user account"
     header action (modal: login email + password).
   - The `ProvisionClientUser` Action authorizes (ADMIN), validates (email
     unique among users — ERR-003; framework default password policy), rejects a
     second link (ERR-004), then in one DB transaction: creates a User with
     `is_active = true`, attaches the CLIENT role, and links the client via
     `clients.user_id`.
   - The client record now displays the linked account and its active status
     (FR-006, AC-14).
3. **Client login after provisioning (AF-001/AF-002, SPEC-001)**
   - The provisioned CLIENT-only user authenticates at `/login` and is
     redirected to `/portal` by the existing SPEC-001 role-based redirect.
   - If the linked account is later deactivated (SPEC-001 FR-007), the Client
     record is unaffected and the user cannot log in (BR-008, AF-004).

---

## 5. Components

### Controllers

None new.

Client management lives entirely inside the Filament `ClientResource` (the
admin-side controller, same convention as `UserResource` in SPEC-001). No web
routes or HTTP controllers are added.

### Actions / Use Cases

**`App\Actions\ProvisionClientUser`** (new — the only non-CRUD operation of this
Specification)

- Input: `Client $client`, `string $loginEmail`, `string $password`.
- Behavior:
  1. Authorize: the acting user must hold ADMIN. It checks
     `can('update', $client)` (via `ClientPolicy`, ADMIN-only) and
     `can('create', User::class)` (via `UserPolicy`, ADMIN-only). Server-side
     defense in depth (AGENTS.md §17) — the Filament page gate already restricts
     the route to ADMIN.
  2. Validate: `loginEmail` must be a valid email and unique among `users`
     (ERR-003, SPEC-001 ERR-005); `password` must satisfy the framework default
     policy (min length 8, SPEC-001 A-05).
  3. Reject if `$client->user_id` is already set (ERR-004, BR-003): throw a
     validation exception — a client may have at most one linked account.
  4. Transaction: `DB::transaction` →
     - `User::create([...])` with `name = $client->full_name` (snapshot at
       provisioning time; see §12 design default for OQ-05), `email =
       $loginEmail`, `password = $password` (hashed by the `User` cast),
       `is_active = true`;
     - `$user->roles()->attach(Role::CLIENT)` (BR-003);
     - `$client->user()->associate($user); $client->save();` (sets
       `clients.user_id`).
  5. No event, no notification, no email is dispatched (welcome email is out of
     scope, SPEC-002 §12).

Client create/edit is plain Eloquent CRUD handled by the Filament resource with
form validation; introducing a `CreateClient` / `UpdateClient` Action would be an
unnecessary abstraction at this stage (AGENTS.md §9-10, ARCHITECTURE §7), the
same precedent as SPEC-001 where `UserResource` performs CRUD directly.

### Models

**`App\Models\Client`** (new)

- Table: `clients`.
- Fillable: `full_name`, `dni`, `email`, `phone`, `emergency_contact`,
  `injuries_notes`, `medical_conditions_notes`. `user_id` is intentionally NOT
  fillable; the link is written only by the provisioning Action via
  `user()->associate()`.
- Casts: none required beyond defaults.
- Relationships:
  - `user(): BelongsTo` → `User` (FK `user_id`, nullable, unique). Simple domain
    behavior (ARCHITECTURE §8):
  - `hasLinkedUser(): bool` → `$this->user_id !== null` (used by FR-006
    display).
- No delete scope/method: deletion is not offered anywhere (BR-006).

**`App\Models\User`** (modified additively)

- New relationship only:
  - `client(): HasOne` → `Client` (foreign key `user_id`).
- No new columns, no change to `$fillable`, casts, helpers or
  `canAccessPanel`.

### Policies

**`App\Policies\ClientPolicy`** (new) — mirrors the `UserPolicy` pattern:

- `viewAny` / `view`: ADMIN only (BR-004).
- `create`: ADMIN only (BR-004).
- `update`: ADMIN only (BR-004) — covers edits and provisioning link changes.
- No `delete` policy is registered: client records are never hard-deleted
  (BR-006); there is no delete operation.
- All rules use `$user->hasRole(Role::ADMIN)` (ADR-001).

Authorization matrix (SPEC-002 §9):

| Operation | ADMIN | TRAINER | CLIENT |
| --- | --- | --- | --- |
| Create client record | Allowed | Denied | Denied |
| List / search clients | Allowed | Denied | Denied |
| View client detail incl. health notes | Allowed | Denied | Denied |
| Edit client record | Allowed | Denied | Denied |
| Provision linked user account | Allowed | Denied | Denied |

A multi-role user receives the union of permissions (SPEC-001 BR-002): an ADMIN
who also holds CLIENT can manage clients. Authorization is enforced server-side
via the Policy; the Filament navigation item is hidden for non-ADMIN as a
consequence of `viewAny` (frontend hiding is never the enforcement).

### Filament

**`App\Filament\Resources\ClientResource`** (new) with pages
`ListClients`, `CreateClient`, `ViewClient`, `EditClient`
(following the `UserResource` folder convention:
`app/Filament/Resources/ClientResource/Pages/*`).

- Form (create/edit):
  - `full_name` — required, maxLength 255 (FR-001, ERR-002).
  - `dni` — required, maxLength 255, `unique(ignoreRecord: true)` (BR-005,
    ERR-001). No format regex is imposed (DNI format is not specified; see §12
    risk note).
  - `email` — nullable, `email()` rule, maxLength 255 (ERR-006).
  - `phone` — nullable, maxLength 255 (ERR-006).
  - Health section (FR-007, AD-01): `emergency_contact` (nullable),
    `injuries_notes` (nullable, textarea), `medical_conditions_notes`
    (nullable, textarea). All optional (OQ-08 assumed optional).
- Table (FR-002, FR-007):
  - Columns: `full_name` (searchable), `dni` (searchable), `email`
    (searchable), `phone` (display only).
  - Link status columns (FR-006, AC-14): `user.email` (placeholder "No
    account") and `user.is_active` (boolean icon with placeholder) — or a
    single computed column combining both; the Developer may choose the Filament
    representation that shows "exists + active/inactive".
  - NO health columns in the table and NO health fields searchable (FR-007).
  - No delete action; `bulkActions([])` (BR-006).
- View page (`ViewClient`): infolist showing the full record including health
  notes (FR-003) and a link status section (linked user email, active status,
  or "no linked account") (FR-006).
- Header action on `ViewClient` (and optionally `EditClient`): "Provision user
  account" — Filament action with a modal (login email + password). It is shown
  only when `record->user_id` is null (ERR-004 UX), but the rule is enforced
  again inside the Action (server-side). The action is thin glue that invokes
  `App\Actions\ProvisionClientUser`.

### Events

None required.

Provisioning has no defined secondary effect (no welcome email — SPEC-002 §12).
The operation is a short synchronous transaction (ARCHITECTURE §10-11).

### Jobs

None required.

No queued work exists in SPEC-002.

### Routes

No new routes. Filament auto-registers `/admin/clients*` through the panel's
`discoverResources` (already configured in `AdminPanelProvider`).

### Seeders

None new. Client records are created by staff in the admin panel only
(SPEC-002 §3 precondition 5). The existing `RoleSeeder` already seeds the CLIENT
role required by provisioning.

---

## 6. Data Changes

### Migrations

1. **`create_clients_table`** (new; next migration in the existing timestamp
   sequence, e.g. `2026_08_15_000003_create_clients_table.php`):

   | Column | Type | Constraints |
   | --- | --- | --- |
   | `id` | bigint | PK |
   | `full_name` | string | NOT NULL (FR-001, ERR-002) |
   | `dni` | string | NOT NULL, UNIQUE (BR-005, ERR-001) |
   | `email` | string | nullable (FR-001, AD-01) |
   | `phone` | string | nullable (FR-001, AD-01) |
   | `emergency_contact` | string | nullable (FR-001, FR-007, AD-01) |
   | `injuries_notes` | text | nullable (FR-001, FR-007, AD-01) |
   | `medical_conditions_notes` | text | nullable (FR-001, FR-007, AD-01) |
   | `user_id` | foreignId | nullable, UNIQUE, FK → `users.id` `nullOnDelete` (BR-003) |
   | `created_at` / `updated_at` | timestamp | timestamps |

   - The UNIQUE index on `user_id` enforces BR-003 in both directions: a client
     has at most one linked user, and a user is linked to at most one client.
   - `nullOnDelete` is a safety net only: users are never hard-deleted in the
     MVP (SPEC-001 BR-007), and if one were ever removed the Client record must
     survive (BR-008).
   - Health columns live in the `clients` table as clearly separated fields
     (ADR-002). The access restriction is the business rule (BR-007), enforced
     by `ClientPolicy` and UI discipline.

No existing SPEC-001 migration is modified. The `users`, `roles` and
`role_user` tables are reused as-is.

### Relationships

```text
users 1 ──── 0..1 clients        (clients.user_id nullable unique FK)
```

### Data lifecycle

- **Created:** client records; provisioned User records (with CLIENT role) and
  their `role_user` assignments; the link value `clients.user_id`.
- **Modified:** client identity/contact/health fields via edit (FR-004);
  `clients.user_id` set by provisioning (FR-005); the linked User may be
  deactivated via the existing SPEC-001 `UserResource` (AF-004) without touching
  the Client record (BR-008).
- **Deleted:** none in the MVP. No client delete operation (BR-006) and no user
  hard deletion (SPEC-001 BR-007). The `nullOnDelete` FK is a defensive safety
  net, not a business flow.

---

## 7. External Integrations

None.

SPEC-002 touches no external service. Provisioning is fully internal; no
notification/email is sent (out of scope). Mercado Pago remains out of scope
(SPEC-014).

---

## 8. Testing Strategy

Pest (PHPUnit) feature tests following the existing conventions (`tests/Pest.php`
helpers `role()`, `userWithRoles()`; `RefreshDatabase`). Extend `tests/Pest.php`
with a small `client(...)` factory helper (e.g., a `ClientFactory`) for
convenience.

**Client CRUD (AC-1, AC-2, AC-3, AC-4, AC-5, AC-6, AC-11)**
- `tests/Feature/Admin/ClientManagementTest.php`
  - ADMIN can create a client with required name + DNI and optional
    contact/health fields; the record is persisted and listed (AC-1).
  - Creating a client does NOT create any User record or role assignment
    (AC-6, BR-001, BR-002).
  - Creating/editing with a duplicate DNI is rejected with a validation error
    (AC-2, ERR-001, BR-005).
  - Missing required fields are rejected (ERR-002).
  - Search by name, DNI and email returns matching clients (AC-3, FR-002).
  - ADMIN can view the full detail including health notes (AC-4, FR-003).
  - ADMIN can edit fields; changes persist (AC-5, FR-004).
  - Invalid email/phone formats are rejected (ERR-006).
  - No delete operation exists: a created client record persists and a delete
    attempt is impossible/denied (AC-11, BR-006).
  - Health fields never appear in list/search responses/table columns (AC-12
    partial, FR-007).

**Provisioning (AC-7, AC-8, AC-9, AC-13, AC-14)**
- `tests/Feature/Admin/ClientProvisioningTest.php`
  - ADMIN can provision a linked account for a client; the User receives the
    CLIENT role, is active, can authenticate, and is redirected to `/portal`
    after login (AC-7, FR-005, BR-003).
  - Provisioning with an email already used by another User is rejected
    (AC-8, ERR-003, BR-009).
  - A second provisioning for the same client is rejected (AC-9, ERR-004,
    BR-003).
  - The client detail shows whether a linked account exists and its active
    status (AC-14, FR-006).
  - Deactivating the linked User (via the SPEC-001 user management flow) does
    not modify the Client record, and the deactivated user cannot log in
    (AC-13, BR-008, AF-004).
  - Provisioning a user for a client whose DNI/email would collide is covered
    by the CRUD tests; provisioning itself only validates login email
    uniqueness and password policy.

**Authorization / Policy (AC-10, AC-12)**
- `tests/Feature/Admin/ClientPolicyTest.php`
  - TRAINER and CLIENT cannot create, view, list or edit client records or
    provision accounts (403 or hidden navigation) (AC-10, ERR-005, BR-004).
  - `ClientPolicy` has no `delete` ability for anyone (AC-11, BR-006).
  - A CLIENT cannot access another client's data (C-13) — trivially satisfied
    in this Specification because CLIENT has no client-management access at
    all; the isolation contract is asserted by the policy tests.

**Unit**
- `tests/Unit/ClientTest.php`
  - `Client::user()` / `User::client()` relationship navigation (BR-003).
  - `hasLinkedUser()` behavior for linked and unlinked clients (FR-006).
  - The DB unique constraint on `clients.dni` and on `clients.user_id` rejects
    duplicates (BR-005, BR-003).

All authorization assertions are server-side (AGENTS.md §17); no test relies on
frontend visibility.

---

## 9. Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Assumptions AD-01..AD-07 are unconfirmed (SPEC-002 §14.1). | If PO changes them, parts of the design change (e.g., AD-03/AD-04 extend management to TRAINER; AD-05 changes link cardinality). | Keep implementation isolated: `ClientPolicy` rules, the provisioning Action and the `clients` schema are the only touch points. Block Implementation until PO confirms §12 items. |
| OQ-01: consent/retention rules for health data undefined. | Legal exposure; possible future consent record. | Health data is stored as clearly separated columns; a future consent table or retention job can be added without restructuring the client record (ADR-002). |
| OQ-02: TRAINER read access to client records undefined. | Policy and UI may need to split non-health vs health access. | `ClientPolicy` is the single enforcement point; health columns are separate, so a policy-level split (or a later 1:1 health table migration) is feasible (ADR-002). |
| OQ-05: name synchronization between Client and linked User undefined. | Ambiguity for the Developer. | Design default documented: `users.name` is a snapshot of `client.full_name` at provisioning; editing the Client does NOT sync the User name. PO confirmation required. |
| OQ-07: login email independence from contact email. | Ambiguity at provisioning. | Design assumes independent (SPEC-002 FR-005, AD-01); provisioning only validates uniqueness among users. PO confirmation required. |
| OQ-08: health fields required vs optional. | Form validation may change. | Design assumes optional (AD-01). If PO makes them required, the form rules change only. |
| DNI format not specified (ERR-006 mentions email/phone only). | Developer might invent a regex. | Design imposes no DNI format rule (presence + uniqueness only). If a format is wanted, it must be specified by the PO. |
| `clients.user_id` on the `clients` table couples the link to the client module. | If a future module needs a different link shape, schema migration required. | 1:1 link is the confirmed requirement (BR-003); the unique FK is the simplest correct representation (ADR-002). |

---

## 10. Alternatives Considered

1. **Link stored as `users.client_id`** — puts the link inside the SPEC-001
   `users` table. Rejected: it couples the completed authentication foundation
   with a business module, requires changes to user management, and implies
   every user record has a client slot. Keeping `clients.user_id` leaves the
   Users module untouched (ADR-002).
2. **Separate `client_user` link table** — overkill for a nullable 1:1 link;
   adds a table, join and integrity management with no benefit over a unique FK
   (ADR-002).
3. **Separate `client_health_data` table** — would ease future consent records
   (OQ-01) and TRAINER non-health access (OQ-02), but today the module is
   ADMIN-only so a second table adds complexity with no functional protection.
   Rejected in favor of clearly separated columns in `clients`; migration path
   documented (ADR-002).
4. **`CreateClient` / `UpdateClient` Actions** — plain CRUD with form
   validation needs no explicit use case, matching the SPEC-001 precedent where
   `UserResource` performs CRUD directly. Only provisioning (a multi-entity,
   transactional, rule-bearing operation) gets an Action.
5. **Standalone web routes/controllers for client management** — unnecessary;
   the admin panel is the only presentation context for this feature
   (SPEC-002 §3 precondition 5), and Filament resources are the established
   convention (ARCHITECTURE §5).

---

## 11. Decision

Use the established SPEC-001 conventions throughout:

- **Persistence:** a new `clients` table with required `full_name` + unique
  `dni`, optional contact and health columns, and a nullable unique `user_id`
  FK to `users` for the optional 1:1 link (ADR-002). The `users`/`roles`/
  `role_user` schema is untouched.
- **Authorization:** `ClientPolicy` (viewAny/view/create/update = ADMIN only,
  no delete) on top of the existing `User::hasRole` helpers (ADR-001).
- **CRUD:** Filament `ClientResource` with form/table/infolist; health fields
  only in forms and the detail view, never in lists or search; no delete
  action (BR-006).
- **Provisioning:** explicit `App\Actions\ProvisionClientUser` (ADMIN check,
  email uniqueness, password policy, no-second-link rule, single transaction:
  create User with CLIENT role + associate to the client). Thin Filament header
  action with a modal on the client detail page.
- **No events, no jobs, no new routes, no new seeders, no external
  integrations.**

---

## 12. Pending PO Confirmations

These items are carried from SPEC-002 and must be confirmed before
Implementation (or at latest before Review). This design does not silently
resolve them.

### Assumptions (SPEC-002 §14.1)

| ID | Assumption | Impact on this design |
| --- | --- | --- |
| AD-01 | Client fields = full name, DNI, email, phone + health notes (emergency contact, injuries, medical conditions); name + DNI required. | `clients` columns; form rules. |
| AD-02 | DNI unique; duplicates rejected. | Unique index + validation (ERR-001). |
| AD-03 | Only ADMIN manages client records. | `ClientPolicy` rules. |
| AD-04 | Only ADMIN provisions linked accounts. | `ProvisionClientUser` authorization. |
| AD-05 | Client ↔ User link is 1:1. | Unique `clients.user_id` (BR-003, ERR-004). |
| AD-06 | No hard deletion of client records. | No delete policy/action (BR-006). |
| AD-07 | Modifying/deactivating a linked User does not affect the Client record. | Nullable FK + independent lifecycles (BR-008). |

### Open questions (SPEC-002 §14.2)

| ID | Question | Impact on this design |
| --- | --- | --- |
| OQ-01 | Consent/retention/deletion rules for health data? | Health columns remain the data source; a consent table or retention job can be added later (ADR-002). |
| OQ-02 | TRAINER access to client records (assigned clients)? | Today `ClientPolicy` denies TRAINER; changing this touches policy + UI only. |
| OQ-03 | Client status (active/inactive/blocked)? | Deliberately NOT modeled (SPEC-002 §12); no status column. |
| OQ-04 | Additional client fields (birth date, address, photo)? | Would be additive columns/forms only. |
| OQ-05 | Does editing a client's name sync to the linked User's name? | Design default: `users.name` is a snapshot taken at provisioning; no sync on edit. Confirmation required. |
| OQ-06 | Unlink operation needed? | Not modeled; `clients.user_id` stays set once provisioned. |
| OQ-07 | Login email must match contact email? | Design assumes independent; provisioning validates only uniqueness among users. |
| OQ-08 | Health fields required or optional at creation? | Design assumes optional (AD-01). |

### Additional design notes flagged for confirmation

- The provisioned User's `name` is copied from the client's `full_name` at
  provisioning time (SPEC-002 FR-005 does not define a name source for the
  account). No sync afterwards (OQ-05 default).
- No DNI format validation is imposed (presence + uniqueness only); ERR-006
  only mentions email/phone formats. If the PO wants a DNI format rule, it must
  be specified.
- The link is stored as `clients.user_id` (nullable, unique FK) rather than on
  the `users` table (ADR-002).

---

## 13. Related Documents

- Specification: `docs/specs/SPEC-002.md`
- Architecture decision: `docs/adr/ADR-001.md`, `docs/adr/ADR-002.md`
- Architecture: `docs/architecture/SPEC-001.md`, `ARCHITECTURE.md`
- Product: `docs/product/product-definition-v0.1.md`
- Domain: `docs/domain/domain-model-v0.1.md`
- Requirements analysis: `docs/requirements/analyst-pass-001.md` (D-01, D-13,
  §5.2, E-10)
- Development rules: `AGENTS.md`
- Workflow state: `docs/sdd/state.yaml`
