# SPEC-001 — Authentication & Roles

## Status

Draft (analysis phase).

This is the first Specification of the MVP. No other Specification exists yet.

**Assumption notice:** this specification contains a small number of explicitly
flagged assumptions (A-01 to A-05, see §14.1) that either borrow the documented
"Recommended" options of `analyst-pass-001.md` §8 or fill gaps required to make the
specification implementable. **None of them is a confirmed business rule.** Each
requires Product Owner confirmation before Implementation.

---

## 1. Objective

Provide the authentication and role foundation of the gym management system:

- a User can authenticate (log in) and end their session (log out);
- every authenticated request carries the User's identity and roles;
- access to the two authenticated presentation contexts (admin panel and client
  portal) is controlled by roles;
- client data isolation is enforced through the authentication/authorization
  foundation (a client must never access another client's private information);
- staff user accounts can be created and assigned roles.

This is the foundation for every later Specification: without identity and roles,
no other module (Clients, Plans, Memberships, Payments, Routines, etc.) can enforce
its own authorization.

---

## 2. Actors

Actors are defined by role, not by person. A single User may hold one or more roles
(confirmed decision C-01).

| Actor | Description |
| --- | --- |
| Anonymous visitor | A person visiting the public website (landing, plans, information, login). No authenticated access. |
| ADMIN | Staff who administer the gym. Full access to the admin panel; manages user accounts and role assignments. |
| TRAINER | Staff who train clients. Access to the admin panel; feature-specific permissions (routines, schedules, etc.) are defined by later Specifications. |
| CLIENT | A gym member. Access to the client portal only, and only to their own information (confirmed decision C-13). |

Notes:

- A User holding ADMIN and/or TRAINER uses the admin panel; a User holding CLIENT
  uses the client portal (confirmed decision C-15).
- A User holding both a staff role and CLIENT (e.g., a trainer who also trains) is
  permitted by C-01; the navigation/context behavior for such users is not defined
  and is tracked as open question OQ-04.

---

## 3. Preconditions

1. The Laravel application scaffold is available and runnable (README, ARCHITECTURE §2).
2. PostgreSQL and Redis are available (ARCHITECTURE §2). Exact configuration is the
   Architect's concern, not a business rule of this Specification.
3. An initial ADMIN user exists so that the first human can access the system
   (provisioning mechanism defined by assumption A-03).
4. A User record with credentials must exist before login is possible. Staff users
   are created per FR-007; creation of client user accounts is out of scope of this
   Specification (assumption A-01, deferred to SPEC-002 Client Management).

---

## 4. Functional Requirements

### FR-001 — Login

A User can authenticate with email and password on the public login page.

### FR-002 — Logout

An authenticated User can end their session.

### FR-003 — Session

Once authenticated, the User's identity and roles are available on every subsequent
request so that authorization can be enforced server-side.

### FR-004 — Role catalog

The MVP role catalog is exactly: ADMIN, TRAINER, CLIENT (confirmed decision C-01;
catalog stability assumption A-04). Roles are fixed values; the MVP does not provide
dynamic creation of new roles through the UI.

### FR-005 — Multi-role users

A User may hold one or more roles simultaneously (confirmed decision C-01).

### FR-006 — Context access control

- The admin panel (Filament) is accessible only to authenticated Users holding
  ADMIN or TRAINER (confirmed decision C-15).
- The client portal is accessible only to authenticated Users holding CLIENT
  (confirmed decision C-15).
- Anonymous visitors can access public pages and the login page only.

### FR-007 — Staff user management (ADMIN)

ADMIN can create staff user accounts, assign roles, change role assignments, and
deactivate accounts (assumption A-02).

### FR-008 — Initial ADMIN provisioning

A seed mechanism creates the first ADMIN user so the system is usable from the start
(assumption A-03).

### FR-009 — Authorization foundation

Role checks are enforced server-side via Laravel Policies and/or a suitable
permission mechanism (ARCHITECTURE §12). The authenticated identity is exposed to
all modules for their own authorization rules.

### FR-010 — Credential storage

Passwords are stored hashed; plaintext passwords are never persisted or logged
(AGENTS.md §17).

---

## 5. Business Rules

### BR-001 — Fixed role set

The MVP recognizes exactly three roles: ADMIN, TRAINER, CLIENT (C-01, A-04).
This Specification does not introduce any additional role.

### BR-002 — Union of permissions

A User holding several roles receives the union of the permissions granted by those
roles (derived from C-01 "one or more roles"). Consequences for mixed
staff/client users (edge case E-11) are not fully defined; see OQ-04.

### BR-003 — Authentication gate

Anonymous users cannot access the admin panel or the client portal; unauthenticated
requests to protected pages are redirected to the login page (C-15).

### BR-004 — Context authorization

- Admin panel access requires holding ADMIN or TRAINER (C-15).
- Client portal access requires holding CLIENT (C-15).

### BR-005 — Client isolation

The foundation must guarantee that a CLIENT can never access another client's
private information (C-13). Concrete isolation rules for each data area are enforced
in the corresponding later Specifications on top of this foundation.

### BR-006 — User and role management is ADMIN-only

Only ADMIN can create staff user accounts, assign roles, change assignments, and
deactivate accounts (assumption A-02). TRAINER and CLIENT cannot.

### BR-007 — No hard deletion of user records

User records are deactivated rather than deleted so that historical data is
preserved (AGENTS.md §12; assumption A-02). Deactivated users cannot log in.

### BR-008 — Hashed credentials

Credentials are never stored or transmitted as plaintext; passwords are hashed with
the framework's default hashing (AGENTS.md §17, FR-010).

---

## 6. Main Flow

1. An anonymous visitor opens the public website.
2. The visitor navigates to the login page and submits email and password (FR-001).
3. The system validates the credentials (BR-008).
4. On success, the system authenticates the User and records a session (FR-003).
5. The system redirects the User to the context(s) their roles allow (FR-006):
   - admin panel if the User holds ADMIN or TRAINER;
   - client portal if the User holds CLIENT.
6. The User performs authorized operations within the granted context.
7. The User logs out (FR-002); the session is terminated and protected pages are no
   longer accessible.

---

## 7. Alternative Flows

### AF-001 — Staff login

A User holding ADMIN or TRAINER is redirected to the admin panel after login.

### AF-002 — Client login

A User holding CLIENT (and no staff role) is redirected to the client portal after
login.

### AF-003 — Multi-role user

A User holding a staff role and CLIENT (e.g., TRAINER + CLIENT) is permitted by C-01
and can access both contexts. The initial landing context and navigation between
contexts are not defined; see open question OQ-04 (edge case E-11).

### AF-004 — Persistent session ("remember me")

Not defined for the MVP; deferred (see §12 Out of Scope).

---

## 8. Error Cases

### ERR-001 — Invalid credentials

Condition: the submitted email/password do not match any User or the password is wrong.

Expected behavior: login is rejected with a generic "invalid email or password"
message. The message must not reveal whether the email exists in the system (account
enumeration prevention; security practice per AGENTS.md §17, flagged as A-05).

### ERR-002 — Deactivated account

Condition: a deactivated User attempts to log in.

Expected behavior: login is rejected (BR-007, A-02).

### ERR-003 — Unauthenticated access to a protected page

Condition: an anonymous visitor requests an admin panel or client portal page.

Expected behavior: the request is redirected to the login page (BR-003), optionally
with a return URL.

### ERR-004 — Insufficient role for a protected context

Condition: an authenticated CLIENT-only User requests an admin panel page.

Expected behavior: access denied (403 or redirect to the client portal) (BR-004).

### ERR-005 — Duplicate email on user creation

Condition: ADMIN attempts to create a user with an email already in use.

Expected behavior: creation is rejected with a validation error (FR-007).

---

## 9. Authorization

| Operation | Anonymous | ADMIN | TRAINER | CLIENT |
| --- | --- | --- | --- | --- |
| View public pages and login page | Allowed | Allowed | Allowed | Allowed |
| Log in / log out | Log in only | Allowed | Allowed | Allowed |
| Access the admin panel (Filament) | Denied | Allowed | Allowed | Denied |
| Access the client portal | Denied | Allowed only if also CLIENT | Allowed only if also CLIENT | Allowed |
| Create staff users / assign roles / deactivate users | Denied | Allowed (BR-006) | Denied | Denied |
| Access another client's private information | Denied | Per feature rules (later specs) | Per feature rules (later specs) | Denied always (BR-005, C-13) |

Notes:

- A User holding several roles receives the union of the column permissions (BR-002).
- Authorization is enforced server-side; frontend-only restrictions are never
  sufficient (AGENTS.md §17, FR-009).

---

## 10. Data Changes

This Specification describes the information that must exist; the exact persistence
schema is defined by the Architect.

Created:

- User records: identity data (name, email), credential data (hashed password), and
  account status (active / deactivated) (FR-001, FR-007, BR-007).
- Role assignment records linking a User to one or more of the fixed roles
  ADMIN / TRAINER / CLIENT (FR-004, FR-005).
- Session data for authenticated users (FR-003), managed by the framework session
  mechanism.
- Initial ADMIN user record via seed (FR-008).

Modified:

- User account status when deactivated (FR-007, BR-007).
- Role assignments of a User (FR-007).

Deleted:

- No hard deletion of user records in the MVP (BR-007); deactivation is used instead.

---

## 11. Acceptance Criteria

- [ ] AC-1: A registered User can log in with valid email and password and is
  redirected to a context allowed by their roles (FR-001, FR-006).
- [ ] AC-2: An invalid email/password combination is rejected with a generic error
  message that does not reveal whether the email exists (ERR-001, A-05).
- [ ] AC-3: After logout, the session is terminated and protected pages are no
  longer accessible without logging in again (FR-002, BR-003).
- [ ] AC-4: An unauthenticated request to a protected page is redirected to the
  login page (ERR-003).
- [ ] AC-5: A CLIENT-only user cannot access the admin panel (403 or redirect)
  (ERR-004, BR-004).
- [ ] AC-6: A TRAINER can access the admin panel but cannot create or manage users
  or role assignments (FR-006, BR-006, A-02).
- [ ] AC-7: ADMIN can create a staff user, assign roles, change the assignment, and
  deactivate the account; changes take effect on the user's next request (FR-007).
- [ ] AC-8: A deactivated user cannot log in (ERR-002, BR-007).
- [ ] AC-9: A User holding multiple roles can access all contexts granted by those
  roles (FR-005, BR-002, C-01).
- [ ] AC-10: Passwords are stored hashed; no plaintext password is persisted or
  logged (FR-010, BR-008).
- [ ] AC-11: After seeding, an initial ADMIN user exists and can log in (FR-008,
  A-03).
- [ ] AC-12: The authenticated identity and roles are exposed to authorization
  checks (policies) used by all modules, so later Specifications can enforce their
  own rules and client isolation on top of it (FR-009, BR-005, C-13).

---

## 12. Out of Scope

The following are explicitly NOT part of this Specification:

- Client account provisioning and the Client ↔ User relationship (deferred to
  SPEC-002 Client Management; see assumption A-01 and decision D-01).
- Public registration of new users (deferred to SPEC-012 Public Registration,
  decision D-17).
- A fourth role RECEPTIONIST or any front-desk staff type (decision D-19; open
  question OQ-01).
- Password recovery / reset, email verification, two-factor authentication, social
  login.
- "Remember me" persistent sessions (AF-004).
- Profile management (users editing their own account data).
- Dynamic creation of new roles or a granular permission matrix through the UI
  (the role catalog is fixed, FR-004).
- API tokens / API authentication (ARCHITECTURE §19).
- Multi-tenancy or multi-location infrastructure (ARCHITECTURE §17-18).

---

## 13. Dependencies

- No dependency on other Specifications (state.yaml: `depends_on: []`); this is the
  first Specification of the MVP.
- Confirmed decisions used: C-01 (roles and multi-role), C-13 (client isolation),
  C-15 (presentation contexts).
- Architecture constraints used: ARCHITECTURE §5 (presentation contexts), §12
  (Laravel authentication and Policies), §17-19 (no multi-tenant/API speculation).
- Framework: Laravel authentication scaffolding (documented technology stack,
  README / ARCHITECTURE §2).
- Assumptions A-01 to A-05 require Product Owner confirmation before Implementation
  (see §14.1).

---

## 14. Open Questions

### 14.1 Assumed decisions requiring PO confirmation

These are flagged assumptions. They are needed to make the Specification
implementable, but they are NOT confirmed business rules.

| ID | Assumption | Source | Blocks / Affects |
| --- | --- | --- | --- |
| A-01 | A Client record is a standalone record; a linked User account is optional and can be created later. Consequently, this Specification does not require every Client to have credentials; client user provisioning is deferred to SPEC-002. | Borrowed from analyst-pass-001 D-01 Recommended (option 2) | Client provisioning, Public Registration, SPEC-002 |
| A-02 | Only ADMIN can create staff user accounts, assign roles, change assignments, and deactivate accounts. Users are deactivated, not deleted. | Not documented — analyst necessity | FR-007, BR-006, BR-007, ERR-002 |
| A-03 | An initial ADMIN user is provisioned via a database seeder so the system is usable from the start. | Not documented — analyst necessity | FR-008, AC-11 |
| A-04 | The role catalog stays at the three confirmed roles (ADMIN, TRAINER, CLIENT); a RECEPTIONIST role is NOT introduced by this Specification. | Derived from C-01; D-19 remains open | FR-004, BR-001 |
| A-05 | Password policy for MVP is the framework default (minimum length 8) and login failures use a generic message to prevent account enumeration. | Security practice per AGENTS.md §17; policy not documented | FR-001, ERR-001, AC-2 |

### 14.2 Open questions to be answered before Implementation (or at latest before Review)

- OQ-01 (decision D-19): Is a fourth role RECEPTIONIST needed for check-in,
  payments and attendance? If approved, this Specification must be updated (role
  catalog, FR-004, BR-001, user management permissions).
- OQ-02: What is the exact password policy (minimum length, complexity) and is
  password recovery in scope for the MVP?
- OQ-03: What is the user account lifecycle? Confirmed: deactivation over deletion
  (A-02). Open: can a deactivated user be reactivated, and who decides?
- OQ-04 (edge case E-11): For a User holding a staff role and CLIENT, what is the
  initial landing context and how does the user navigate between the admin panel
  and the client portal?
- OQ-05: Session lifetime and expiry rules (e.g., idle timeout, concurrent
  sessions allowed or not)?
- OQ-06: How are the initial ADMIN credentials provisioned and delivered to the
  gym owner (seeder with fixed credentials vs. environment variable vs. artisan
  command)?

---

## 15. Related Documents

- Product documentation: `docs/product/product-definition-v0.1.md`
- Domain documentation: `docs/domain/domain-model-v0.1.md`
- Requirements analysis: `docs/requirements/analyst-pass-001.md`
- Architecture documentation: `ARCHITECTURE.md`
- Development rules: `AGENTS.md`