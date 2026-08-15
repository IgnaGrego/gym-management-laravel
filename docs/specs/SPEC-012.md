# SPEC-012 — Public Registration

## Status

Draft (analysis phase).

This is the twelfth Specification of the MVP. It depends on SPEC-001 (Authentication
& Roles), SPEC-002 (Client Management), SPEC-003 (Plan Management) and SPEC-004
(Membership Management), all COMPLETED and implemented in the repository
(`docs/sdd/state.yaml`): User / Role / Client / Plan / Membership models, the
`role_user` pivot, `clients.user_id` 1:1 optional link (ADR-002), policies
(`UserPolicy`, `ClientPolicy`, `PlanPolicy`, `MembershipPolicy`), the
`EnsureUserHasRole` middleware, the SPEC-001 login flow with deactivated-user
rejection, and the SPEC-002 provisioning Action `ProvisionClientUser`.

**NIGHT MODE notice:** this Specification is produced under the PO pre-approval
("modo nocturno", `docs/sdd/state.yaml` `project.po_decisions`). The business
decisions are pre-approved as follows:

- **D-17 — option 2** (public registration flow): a public registration creates a
  **PENDING Client record awaiting staff approval** — it is NOT an immediate active
  client and NOT a lead.
- **C-15** (confirmed): the public website includes landing, plans, information,
  registration and login; the public website and the authenticated application
  share the same backend.
- **C-18 — note** (product open question): "online booking/purchase on the public
  site" is NOT covered by this Specification. SPEC-012 is registration only: it
  creates a pending Client. Plan selection, purchase and payment at registration
  are NOT pre-approved and are deliberately out of scope (§12, BR-012); they belong
  to SPEC-005 / SPEC-013 concerns.
- **D-01 — option 2** (Client ↔ User): the Client is a standalone record; a linked
  User account is optional and can be created later. For public registration this
  Specification documents, as an assumption (AS-03), that registration creates
  BOTH a pending Client AND a linked User with the CLIENT role, deactivated until
  staff approval — the design decision requested for the SPEC-013 portal needs.
  No business rule is invented that contradicts D-01 or D-17.

**Assumption notice:** this specification contains explicitly flagged assumptions
(AS-01 to AS-10, see §14.1) that either represent the design decision requested
for the D-01/D-17 combination (User creation at registration), represent how the
pre-approved "pending" state is persisted on the existing Client model, or fill
operational gaps required to make the Specification implementable (edge cases
E-08/E-10, spam hardening). **None of them is a confirmed business rule** unless
stated otherwise. Each requires Product Owner confirmation before Implementation
(or at latest before Review).

---

## 1. Objective

Provide the public registration flow of the gym management system:

- an anonymous visitor can submit a registration from the public website
  (C-15), providing their identity and, optionally, contact and health
  information (D-13 option 2 field set, reused from SPEC-002);
- a successful registration creates a **pending Client record awaiting staff
  approval** (D-17 option 2, pre-approved): it is not immediately active and it
  is not a lead;
- to make the pending registration usable by the SPEC-013 client portal, the
  registration also creates the linked User account (CLIENT role) in a
  **deactivated** state, activated only when staff approve the client
  (design decision documented as AS-03; SPEC-001 login already rejects
  deactivated users);
- an ADMIN can review pending registrations in the admin panel and **approve**
  or **reject** them (approval activates the Client and its linked User;
  rejection leaves the registration closed and the User deactivated);
- duplicate registrations are detected: the DNI is unique among clients
  (SPEC-002 BR-005, edge case E-10) and the login email is unique among users
  (SPEC-001 ERR-005);
- the public registration does NOT include plan selection, purchase or payment
  (pre-approved decision on C-18; §12).

This is the public-side entry point that feeds the existing Client module
(SPEC-002): the records created here are ordinary Client records that later
Specifications (memberships, bookings, attendance, routines, portal) consume
without knowing their origin.

---

## 2. Actors

Actors are defined by role, not by person (SPEC-001 §2). A single User may hold
one or more roles (confirmed decision C-01).

| Actor | Description |
| --- | --- |
| Anonymous visitor | A person visiting the public website. Can submit a public registration and can log in (SPEC-001). Cannot access the admin panel or the client portal (SPEC-001 BR-003). |
| ADMIN | Staff who administer the gym. The only actor that can review, approve or reject pending registrations (assumption AS-06, derived from SPEC-002 BR-004 "client management is ADMIN-only"). |
| TRAINER | Staff who train clients. No registration-approval capability in this Specification (AS-06). |
| CLIENT | A gym member. Cannot approve/reject registrations. A registered applicant does not become a CLIENT (cannot log in to the portal) until their registration is approved and the linked User is activated (AS-04). |

Notes:

- A User holding ADMIN and/or TRAINER uses the admin panel; a User holding CLIENT
  uses the client portal (SPEC-001 FR-006, confirmed C-15).
- A User holding both a staff role and CLIENT (e.g., a trainer who also trains)
  is permitted by C-01; the mixed-role behavior is tracked as SPEC-001 OQ-04.

---

## 3. Preconditions

1. SPEC-001 is implemented and completed: authentication, fixed role catalog
   (ADMIN, TRAINER, CLIENT), admin panel access (ADMIN | TRAINER), role helpers
   (`hasRole` / `hasAnyRole`), `Role::CLIENT` seeded, `EnsureUserHasRole`
   middleware, deactivated-user login rejection, generic login-failure message
   (`docs/sdd/state.yaml`, ADR-001).
2. SPEC-002 is implemented and completed: Client model and `clients` table with
   `full_name` / `dni` (required, DNI unique), optional contact and health
   fields, and the nullable unique `clients.user_id` 1:1 link (ADR-002).
   `ClientPolicy` restricts client management to ADMIN; `ProvisionClientUser`
   exists for the staff-side provisioning flow. SPEC-002 explicitly does NOT
   define a client status field (SPEC-002 §12, OQ-03) — this Specification
   introduces the minimum status needed by D-17 as assumption AS-01.
3. SPEC-003 and SPEC-004 are implemented and completed (context only: public
   registration does not create plans or memberships; `docs/sdd/state.yaml`
   records `depends_on` on SPEC-001..004).
4. An authenticated ADMIN exists and can access the admin panel (SPEC-001
   FR-008).
5. The role catalog stays at ADMIN / TRAINER / CLIENT; no RECEPTIONIST role
   (confirmed SPEC-001 A-04).
6. No public registration exists yet: the registration route is NOT registered
   in `routes/web.php` (SPEC-001 §12 and architecture SPEC-001 §5 defer it to
   this Specification).
7. Decision D-17 (option 2) and the C-18 scope note are pre-approved (NIGHT
   MODE, `docs/sdd/state.yaml`). Decision D-01 (option 2) is confirmed (PO).
   The User-creation approach at registration is a design decision documented as
   AS-03 and requires PO confirmation before Implementation.

---

## 4. Functional Requirements

### FR-001 — Public registration form

An anonymous visitor can open the public registration page and submit a
registration. The form reuses the SPEC-002 client field set (D-13 option 2,
AD-01) plus login credentials:

- Required: full name, DNI, email, password (with confirmation).
- Optional: phone, emergency contact, injuries notes, medical conditions notes.
- The single email field is used BOTH as the Client's contact email and as the
  linked User's login email (assumption AS-02).
- The form contains NO plan selection, NO pricing, NO payment step (BR-012).

### FR-002 — Submit registration

Submitting a valid registration, in one database transaction (BR-008):

1. creates a Client record with status `pending` (D-17, AS-01);
2. creates a User record with the CLIENT role and `is_active = false`
   (deactivated) (AS-03);
3. links the User to the Client via `clients.user_id` (1:1, SPEC-002 BR-003);
4. does NOT authenticate the applicant (no auto-login, AS-04);
5. does NOT create any membership, plan, booking or payment record (BR-012);
6. shows a success screen ("registration received; staff will review it; you
   will be able to log in once approved") and does not reveal any internal
   detail (AS-11).

### FR-003 — Display pending registrations in the admin panel

An ADMIN can see every client's status in the admin panel and can filter the
client list to show pending registrations, so the approval queue is visible at a
glance (AS-01, AS-06). The detail view shows the client's status and the linked
account status (SPEC-002 FR-006).

### FR-004 — Approve a pending registration

An ADMIN can approve a client whose status is `pending` (AS-06). Approval:

1. sets the Client status from `pending` to `active` (BR-005);
2. activates the linked User (`is_active` false → true) if one exists (BR-005,
   AS-03);
3. does not create a membership (BR-012): a newly approved client has no
   membership until ADMIN creates one via SPEC-004.

After approval the applicant can log in with the credentials they chose at
registration and is redirected to the client portal context (SPEC-001 redirect
rule, FR-006).

### FR-005 — Reject a pending registration

An ADMIN can reject a client whose status is `pending` (AS-06). Rejection:

1. sets the Client status from `pending` to `rejected` (terminal, BR-004,
   BR-005);
2. leaves the linked User deactivated (`is_active = false`); the applicant
   cannot log in (AS-05).

### FR-006 — Duplicate detection

A registration is rejected with a validation error when the DNI already belongs
to a client (any status — pending, active or rejected; SPEC-002 BR-005, edge
case E-10) or when the email already belongs to a user (SPEC-001 ERR-005)
(ERR-001, ERR-002).

### FR-007 — Health data handling

Health fields submitted at registration (emergency contact, injuries notes,
medical conditions notes) are stored as sensitive client data and follow
SPEC-002 FR-007 / BR-007: they are visible only to ADMIN (in this
Specification), never exposed on the public website, never shown in list or
search results, only in the client detail view.

### FR-008 — Spam / abuse hardening

The registration submission route is rate-limited with the framework throttle
mechanism (assumption AS-07), mirroring the existing `throttle:login` pattern.
CAPTCHA / anti-bot services are out of scope (§12).

---

## 5. Business Rules

### BR-001 — Registration creates a pending Client

A public registration creates a Client record with status `pending`, awaiting
staff approval (D-17 option 2, pre-approved). It is NOT an immediate active
client and NOT a lead. The applicant is not considered an active gym member
until approved.

### BR-002 — Registration creates the linked User deactivated

A public registration also creates a User account with the CLIENT role, linked
1:1 to the pending Client, with `is_active = false` (AS-03). This is the
documented design decision that reconciles D-17 (pending client) with D-01
option 2 (linked User optional, created later) and the SPEC-013 portal need
(a User to log in). It does NOT change SPEC-002 BR-002 (client CRUD never
creates users implicitly) nor SPEC-001 BR-006 (staff user management remains
ADMIN-only): registration is a distinct self-service public flow whose purpose
is account creation.

### BR-003 — No login before approval

The linked User is created deactivated, so the applicant cannot authenticate
until an ADMIN approves the registration (SPEC-001 BR-007 / ERR-002; AS-04).
Registration never authenticates the applicant (no auto-login).

### BR-004 — Client status set for the registration flow

The Client record gains a status with exactly three values for this flow:
`pending`, `active`, `rejected` (AS-01). Staff-created clients (SPEC-002
ClientResource) and pre-existing clients default to `active`; only public
registration creates `pending`. `inactive` / `blocked` / any other client
lifecycle state are NOT introduced (SPEC-002 OQ-03 remains open for those).

### BR-005 — Status transitions

Allowed transitions:

- `pending → active` (approve, FR-004) — also activates the linked User;
- `pending → rejected` (reject, FR-005) — the linked User stays deactivated.

No other transitions exist. `active` is the normal operating state and is not
changed by this Specification; `rejected` is terminal (BR-006). Approval or
rejection is only possible from `pending` (ERR-007).

### BR-006 — Rejected is terminal

A `rejected` client cannot be approved, re-rejected or re-registered: the DNI
stays unique (SPEC-002 BR-005), so a rejected applicant cannot submit a second
registration with the same DNI and cannot log in. The applicant must contact the
gym (AS-05). This mirrors the terminal `cancelled`/`expired` pattern of SPEC-004
(BR-009, AM-10) and SPEC-006 (BR-004, AS-07).

### BR-007 — Uniqueness rules at registration

- The DNI must be unique among client records regardless of status (SPEC-002
  BR-005, edge case E-10).
- The email must be unique among user records (SPEC-001 ERR-005).
- Both are enforced server-side; duplicates produce a validation error and no
  record is created (ERR-001, ERR-002).

### BR-008 — Registration is transactional

A registration either creates the Client, the User and the link together, or
creates nothing: the operation runs in one database transaction (AS-03). A
partial registration (e.g., a Client without its User) never persists.

### BR-009 — Approval and rejection are ADMIN-only

Only ADMIN can review, approve or reject pending registrations (AS-06, derived
from SPEC-002 BR-004). TRAINER and CLIENT cannot.

### BR-010 — No hard deletion of registration records

Pending and rejected Client records are never hard-deleted; historical
registration data is preserved (AGENTS.md §12; same pattern as SPEC-001 BR-007 /
SPEC-002 BR-006 / SPEC-003 BR-004 / SPEC-004 BR-015 / SPEC-006 BR-009; AS-09).
No delete operation is provided. A pending registration that staff never review
simply remains `pending` (edge case E-08); no automatic expiry or cleanup job is
introduced (AS-09).

### BR-011 — Registration is guest-only

The registration page and submission are accessible to anonymous visitors only;
an authenticated user is redirected away (ERR-008, AS-08).

### BR-012 — No plan selection, purchase or payment at registration

Public registration collects only identity/contact/health data plus login
credentials (FR-001). It never includes plan selection, pricing, enrollment fee,
online purchase or payment; a registration never creates a membership (pre-
approved decision on C-18; SPEC-004 BR-001 keeps membership creation ADMIN-only).

### BR-013 — Rate limiting

The registration submission route is protected by the framework throttle with a
conservative per-IP limit (AS-07). Exceeding the limit yields the framework's
standard rate-limit response (ERR-005).

### BR-014 — Registration does not alter other modules' rules

This Specification introduces no new restriction on how later modules reference
clients (e.g., SPEC-004 membership creation, SPEC-008 attendance) based on the
client status; those modules gate on membership state, not client status. Whether
pending/rejected clients should be excluded from e.g. membership creation is an
open question (OQ-06), not a rule of this Specification (AS-10).

---

## 6. Main Flow

1. An anonymous visitor opens the public registration page (`GET /register`,
   guest-only, FR-001, BR-011).
2. The visitor fills the form: required full name, DNI, email, password (+
   confirmation); optional phone and health fields; no plan/payment fields
   (FR-001, BR-012).
3. The visitor submits (`POST /register`). The system validates: required fields
   present (ERR-003), email format and password policy (ERR-004), DNI unique
   (ERR-001), email unique (ERR-002), rate limit (ERR-005).
4. In one transaction (BR-008), the system creates:
   - a Client record with status `pending` (BR-001, BR-004);
   - a User record with the CLIENT role and `is_active = false` (BR-002);
   - the 1:1 link `clients.user_id` (SPEC-002 BR-003).
5. The applicant is NOT logged in (BR-003) and sees a success screen: the
   registration was received and staff will review it; the applicant will be
   able to log in once approved (FR-002, AS-11).
6. An ADMIN opens the Clients section of the admin panel; the pending
   registration appears with status `pending`, filterable (FR-003). The ADMIN
   opens the detail view to review the identity, contact and health data.
7. The ADMIN approves (FR-004) or rejects (FR-005) the registration.
   - Approve: Client `pending → active`; linked User `is_active` false → true.
     The applicant can now log in (SPEC-001 FR-001) and is redirected to the
     client portal context; the client is a normal Client record for SPEC-004
     onward (no membership is created, BR-012).
   - Reject: Client `pending → rejected` (terminal); the linked User remains
     deactivated; the applicant cannot log in (BR-003, BR-005, BR-006).

---

## 7. Alternative Flows

### AF-001 — Duplicate DNI at registration

The submitted DNI already belongs to a client — pending, active or rejected.
The registration is rejected with a validation error on the DNI field; no record
is created (ERR-001, BR-007, E-10).

### AF-002 — Duplicate email at registration

The submitted email already belongs to a user (any role). The registration is
rejected with a validation error on the email field; no record is created
(ERR-002, BR-007).

### AF-003 — Staff edits a pending registration before deciding

An ADMIN may edit a pending client's identity/contact/health fields (SPEC-002
FR-004) to fix data before approving or rejecting. Editing does not change the
status (`pending` stays `pending`); approval/rejection is a separate action
(BR-005, AS-06).

### AF-004 — Registration never reviewed (edge case E-08)

A pending registration that staff never act on remains `pending` indefinitely;
there is no automatic expiry, rejection or cleanup (BR-010, AS-09). The linked
User stays deactivated, so the applicant cannot log in (BR-003).

### AF-005 — Rejected applicant attempts to log in or re-register

The rejected applicant cannot log in (the linked User stays deactivated,
SPEC-001 ERR-002) and cannot re-register with the same DNI (DNI unique,
BR-006/BR-007). The applicant must contact the gym (AS-05).

### AF-006 — Authenticated user opens the registration page

A user who is already authenticated (any role) visits `/register`. The page is
guest-only: the user is redirected away from registration (ERR-008, BR-011,
AS-08).

### AF-007 — Approval or rejection of a non-pending client

An ADMIN attempts to approve or reject a client whose status is `active` or
`rejected`. The action is rejected; only `pending` clients can be approved or
rejected (ERR-007, BR-005).

### AF-008 — Deactivated linked user logs in (SPEC-001 reuse)

Until approval, the applicant's credentials match a deactivated User; the login
attempt is rejected with the generic "invalid credentials" message (SPEC-001
ERR-001/ERR-002, A-05). The response never reveals that the account exists or
that it is pending approval.

---

## 8. Error Cases

### ERR-001 — Duplicate DNI

Condition: the submitted DNI already belongs to a client (any status).

Expected behavior: rejected with a validation error on the DNI field; no record
is created (BR-007, SPEC-002 BR-005, E-10).

### ERR-002 — Duplicate email

Condition: the submitted email already belongs to a user (any role).

Expected behavior: rejected with a validation error on the email field; no
record is created (BR-007, SPEC-001 ERR-005).

### ERR-003 — Missing or invalid required fields

Condition: the form is missing the full name, DNI, email or password, or the
email is malformed, or the password confirmation does not match.

Expected behavior: rejected with validation errors (FR-001, FR-002).

### ERR-004 — Password policy violation

Condition: the password is shorter than the framework default minimum (8) or
exceeds the maximum length.

Expected behavior: rejected with a validation error (SPEC-001 A-05).

### ERR-005 — Rate limit exceeded

Condition: the visitor submits the registration form more often than the
configured per-IP limit.

Expected behavior: the submission is rejected with the framework's standard
rate-limit response (BR-013, AS-07).

### ERR-006 — Unauthorized approval or rejection

Condition: a TRAINER or CLIENT attempts to approve or reject a registration.

Expected behavior: access denied (403 or hidden from navigation) (BR-009,
AS-06, SPEC-002 BR-004).

### ERR-007 — Approval/rejection of a non-pending client

Condition: an ADMIN attempts to approve or reject a client whose status is
`active` or `rejected`.

Expected behavior: rejected; only `pending` clients can be approved or rejected
(BR-005, AF-007).

### ERR-008 — Authenticated access to registration

Condition: an authenticated user (any role) opens the registration page.

Expected behavior: redirected away from registration; registration is guest-only
(BR-011, AS-08).

### ERR-009 — Login of an unapproved applicant

Condition: the applicant attempts to log in before approval, or after rejection.

Expected behavior: login is rejected with the generic "invalid credentials"
message (SPEC-001 ERR-001/ERR-002; BR-003, AF-008).

---

## 9. Authorization

| Operation | Anonymous | ADMIN | TRAINER | CLIENT |
| --- | --- | --- | --- | --- |
| View the public registration page | Allowed (BR-011) | Redirected away (ERR-008) | Redirected away (ERR-008) | Redirected away (ERR-008) |
| Submit a registration | Allowed (FR-001, FR-002) | Redirected away (ERR-008) | Redirected away (ERR-008) | Redirected away (ERR-008) |
| Log in as a registered applicant before approval | Denied (ERR-009, BR-003) | — | — | — |
| Log in after approval | — (becomes CLIENT) | — | — | Allowed (SPEC-001) |
| Review / filter pending registrations in the admin panel | Denied | Allowed (BR-009, AS-06) | Denied | Denied |
| Approve a pending registration | Denied | Allowed (BR-009, FR-004) | Denied | Denied |
| Reject a pending registration | Denied | Allowed (BR-009, FR-005) | Denied | Denied |
| Edit a pending client's data (fix before deciding) | Denied | Allowed (SPEC-002 FR-004) | Denied | Denied |
| View health data submitted at registration | Denied | Allowed (FR-007, SPEC-002 BR-007) | Denied | Denied |
| Access another applicant's data | Denied | Per feature rules | Per feature rules | Denied always (C-13) |

Notes:

- A User holding several roles receives the union of the column permissions
  (SPEC-001 BR-002); an ADMIN who is also CLIENT can approve/reject in the admin
  panel.
- Authorization is enforced server-side; frontend-only restrictions are never
  sufficient (AGENTS.md §17).
- The approval/rejection capability derives from SPEC-002 BR-004 (client
  management is ADMIN-only): approving/rejecting mutates Client and User
  records, both ADMIN-only areas.

---

## 10. Data Changes

This Specification describes the information that must exist; the exact
persistence schema is defined by the Architect.

Created:

- Client records with status `pending`, created by public registration
  (BR-001). Fields follow the SPEC-002 field set: `full_name`, `dni`, `email`
  (single email = contact + login, AS-02), `phone`, `emergency_contact`,
  `injuries_notes`, `medical_conditions_notes`.
- User records with the CLIENT role, `is_active = false`, email unique,
  password hashed (BR-002; SPEC-001 credential rules), `name` = the full name
  submitted at registration (snapshot, per the SPEC-002 provisioning convention).
- The 1:1 Client ↔ User link (`clients.user_id`) created by registration
  (BR-002, SPEC-002 BR-003).
- No membership, plan, booking or payment record (BR-012).

Modified:

- `clients.status` on the registration lifecycle: created `pending`; `pending →
  active` on approval (FR-004, BR-005); `pending → rejected` on rejection
  (FR-005, BR-005). Storage representation (string column vs. DB enum) is an
  Architect decision; the business rule is the three-value set and the
  transitions (BR-004, BR-005).
- `users.is_active` false → true when a registration is approved (FR-004,
  BR-005).
- Client contact/health fields via the existing SPEC-002 edit flow (AF-003).

Schema change (additive, new migration; no existing migration is modified):

- Add a `status` column to the `clients` table (e.g., string, NOT NULL, default
  `active`) to represent the pending/active/rejected state introduced by D-17
  (AS-01). The default `active` preserves the behavior of existing and
  staff-created clients; only public registration writes `pending`. Existing
  rows receive the default `active` (no destructive migration; AGENTS.md §12).

Deleted:

- No hard deletion in the MVP (BR-010): pending and rejected records are
  preserved; no delete operation.

No new seeder is required: registrations are created by visitors on the public
website; approval/rejection is performed by ADMIN in the admin panel.

---

## 11. Acceptance Criteria

- [ ] AC-1: An anonymous visitor can open the public registration page and
  submit a valid registration (full name, DNI, email, password + confirmation;
  optional phone/health fields); the system creates one Client with status
  `pending`, one User with the CLIENT role and `is_active = false`, and the 1:1
  link, in one transaction (FR-001, FR-002, BR-001, BR-002, BR-008).
- [ ] AC-2: After registration the applicant is NOT logged in, sees a success
  screen, and a login attempt is rejected with the generic message until
  approval (BR-003, ERR-009, AF-008).
- [ ] AC-3: Registering with a DNI that already belongs to a client (pending,
  active or rejected) is rejected with a validation error; no record is created
  (ERR-001, BR-007).
- [ ] AC-4: Registering with an email that already belongs to a user is rejected
  with a validation error; no record is created (ERR-002, BR-007).
- [ ] AC-5: Missing required fields, malformed email, or a password below the
  framework default policy are rejected with validation errors (ERR-003,
  ERR-004).
- [ ] AC-6: The pending registration appears in the admin panel Clients section
  with status `pending` and is filterable; the ADMIN can open its detail view
  including health data (FR-003, FR-007).
- [ ] AC-7: ADMIN can approve a `pending` registration; the Client becomes
  `active`, the linked User becomes `is_active = true`, and the applicant can
  log in and is redirected to the client portal context (FR-004, BR-005,
  SPEC-001 FR-006).
- [ ] AC-8: ADMIN can reject a `pending` registration; the Client becomes
  `rejected` (terminal), the linked User stays deactivated, and the applicant
  cannot log in or re-register with the same DNI (FR-005, BR-005, BR-006).
- [ ] AC-9: Approving or rejecting a client whose status is `active` or
  `rejected` is rejected (ERR-007, BR-005).
- [ ] AC-10: A TRAINER or CLIENT cannot review, approve or reject registrations
  (403 or hidden) (ERR-006, BR-009).
- [ ] AC-11: The registration submission route is rate-limited; exceeding the
  limit yields the framework's standard rate-limit response (ERR-005, BR-013).
- [ ] AC-12: An authenticated user (any role) is redirected away from the
  registration page (ERR-008, BR-011).
- [ ] AC-13: Existing and staff-created clients are unaffected: their status
  defaults to `active` after the schema change, and the SPEC-002 flows keep
  working (AS-01).
- [ ] AC-14: A registration never includes plan selection, purchase or payment
  fields and never creates a membership (BR-012).
- [ ] AC-15: Health fields submitted at registration never appear on the public
  website, in lists or in search results — only in the ADMIN client detail view
  (FR-007, SPEC-002 BR-007).
- [ ] AC-16: No delete operation exists for pending/rejected client records; a
  registration that is never reviewed remains `pending` and its linked User
  stays deactivated (BR-010, AF-004, E-08).

---

## 12. Out of Scope

The following are explicitly NOT part of this Specification:

- Plan selection, pricing display, purchase, enrollment fee (matrícula) or
  payment at registration, and any online purchase on the public site (product-
  definition open question C-18 "online booking/purchase on the public site";
  pre-approved decision: SPEC-012 is registration only). A registration never
  creates a membership (BR-012).
- Online booking on the public site (C-18; SPEC-007 / SPEC-013 concerns).
- Login and logout behavior (SPEC-001; this Specification only creates the
  account and toggles its activation).
- The client portal and any applicant-facing status checking / "waiting for
  approval" page (SPEC-013). The applicant is informed only by the registration
  success screen (AS-11).
- Notifications to the applicant (e.g., welcome / approval / rejection email).
  SPEC-002 §12 already excludes welcome email; this Specification adds none
  (AS-11).
- CAPTCHA / anti-bot services, email verification, two-factor authentication
  (AS-07; §8).
- Client lifecycle states beyond the three registration-flow values `pending` /
  `active` / `rejected` (AS-01): `inactive`, `blocked`, freeze/pausa and other
  statuses remain open (SPEC-002 OQ-03; SPEC-004 BR-004) and are not introduced
  here.
- Automatic expiry, SLA or cleanup of pending registrations (edge case E-08;
  AS-09).
- Re-registration by a rejected applicant (BR-006) and any "unreject" /
  re-opening of a rejected registration.
- Changes to other modules' business rules based on client status, e.g.
  whether ADMIN may create a membership for a `pending` or `rejected` client
  (OQ-06; AS-10).
- Staff-side client creation (SPEC-002) and staff-side user provisioning
  (SPEC-002 FR-005) — unchanged.
- Public website pages content (landing, plans, information): C-15 lists them
  but their content is out of scope of this Specification (SPEC-003 OQ-03;
  product-definition §Out of Scope "Complete public website content").
- Hard deletion of any record (BR-010).
- Multi-tenancy, multi-location, API (ARCHITECTURE §17-19).

---

## 13. Dependencies

- **SPEC-001 (Authentication & Roles) — COMPLETED** (`docs/sdd/state.yaml`):
  authentication/session foundation; fixed role catalog (`Role::CLIENT`);
  `User::hasRole` / `hasAnyRole`; `EnsureUserHasRole` middleware; login rejects
  deactivated users with a generic message (used for the pre-approval period,
  ERR-009); unique user email (ERR-005); framework default password policy
  (A-05); `users.is_active` deactivation pattern. Registration was explicitly
  deferred to this Specification (SPEC-001 §12).
- **SPEC-002 (Client Management) — COMPLETED** (`docs/sdd/state.yaml`): the
  Client record and field set reused at registration (D-13 option 2, AD-01);
  unique DNI (BR-005); ADMIN-only client management (BR-004); 1:1 optional
  Client ↔ User link via `clients.user_id` (BR-003, ADR-002); health-data
  confidentiality (BR-007); `ClientPolicy`; the `ProvisionClientUser` Action
  pattern. The "pending" representation is an additive assumption on top of this
  Spec (AS-01), which deliberately had no client status field (SPEC-002 §12,
  OQ-03).
- **SPEC-003 (Plan Management) — COMPLETED** (`docs/sdd/state.yaml`): context
  only — registration does not reference plans; plan data belongs to the admin
  panel (SPEC-003 BR-006) and public plans display is out of scope (SPEC-003
  OQ-03).
- **SPEC-004 (Membership Management) — COMPLETED** (`docs/sdd/state.yaml`):
  context only — registration creates no membership; membership creation remains
  ADMIN-only via SPEC-004 FR-001; a newly approved client has no membership
  until ADMIN creates one (BR-012).
- **SPEC-013 (Client Portal) — FUTURE**: consumes the User + linked Client
  created here; the deactivated-then-activated User is the account the portal
  logs in with. The registration-side design decision for the portal need is
  AS-03.
- **SPEC-015 (Presentation Foundation & UX) — FUTURE** (soft dependency, not in
  `docs/sdd/state.yaml` `depends_on`): provides the shared public-site layout
  and styling the registration page will use; the route and behavior contract
  are defined here and do not depend on SPEC-015.
- Gate decisions: **D-17 option 2** (pre-approved, NIGHT MODE,
  `docs/sdd/state.yaml`); **C-15** (confirmed); **C-18** scope note (pre-
  approved: purchase/booking NOT covered); **D-01 option 2** (confirmed PO).
- Confirmed decisions used: C-01 (roles, multi-role), C-13 (client isolation),
  C-14 (single location), C-15 (presentation contexts).
- Requirements analysis: `analyst-pass-001.md` §5.15 (Public Registration), D-01,
  D-13, D-17, C-15, C-18, edge cases E-08 (registration never approved) and
  E-10 (duplicate DNI/contact).
- Architecture constraints used: ARCHITECTURE §5 (presentation contexts: public
  website shares the backend), §12 (authentication), §19 (no speculative API),
  §20 (simplest correct architecture).
- Flagged assumptions AS-01 to AS-10 require Product Owner confirmation before
  Implementation (see §14.1).

---

## 14. Open Questions

### 14.1 Assumed decisions requiring PO confirmation

These are flagged assumptions. They are needed to make the Specification
implementable, but they are NOT confirmed business rules. The prefix AS
distinguishes this Specification's assumptions from SPEC-001 (A-xx),
SPEC-002 (AD-xx), SPEC-003 (AP-xx), SPEC-004 (AM-xx) and SPEC-006 (AS-xx, which
uses the same prefix; the two Specifications' assumption tables are independent).

| ID | Assumption | Source | Blocks / Affects |
| --- | --- | --- | --- |
| AS-01 | The pre-approved "pending" state (D-17) is represented by a new `status` column on `clients` with exactly three values for this flow: `pending`, `active`, `rejected`. Default `active` so existing and staff-created clients keep their current behavior; only public registration writes `pending`. Additive migration; no existing migration modified. `inactive`/`blocked` are NOT introduced (SPEC-002 OQ-03 stays open for those). | D-17 option 2 requires a pending Client; SPEC-002 has no client status field (SPEC-002 §12, OQ-03) so one is introduced minimally | BR-004, BR-005, FR-003..FR-005, §10, AC-6..AC-9, AC-13 |
| AS-02 | The registration form collects a single email used BOTH as the Client's contact email and as the linked User's login email. Therefore email is required at registration even though D-13 option 2 lists contact as optional. This differs from SPEC-002 provisioning where the login email may differ from the contact email (SPEC-002 FR-005, OQ-07). | Design decision for the public flow (one email field, applicant expectation); not documented | FR-001, BR-007, ERR-002 |
| AS-03 | Registration creates BOTH a pending Client AND a linked User with the CLIENT role and `is_active = false`, in one transaction. Approval activates the User; rejection leaves it deactivated. This is the requested design decision for the D-01/D-17 combination and the SPEC-013 portal need (a User to log in). It does not change SPEC-002 BR-002 (client CRUD never creates users implicitly) or SPEC-001 BR-006 (staff user management stays ADMIN-only): registration is a distinct self-service public flow. | Design decision requested by the task (option A of the D-01/D-17 question); consistent with D-01 option 2 and D-17 option 2 | BR-002, BR-003, BR-008, FR-002, AC-1, AC-2 |
| AS-04 | No auto-login after registration; the linked User is created deactivated so login is rejected until approval (SPEC-001 ERR-002). | Consistent with D-17 (pending approval: the applicant must not gain access before staff approval); SPEC-001 login already rejects deactivated users | BR-003, ERR-009, FR-002, AC-2 |
| AS-05 | Rejection is represented by the terminal `rejected` client status; the linked User stays deactivated. A rejected applicant cannot re-register with the same DNI (DNI unique, SPEC-002 BR-005) and cannot log in; they must contact the gym. | Task requires an approve/reject workflow; rejection representation mirrors the terminal `cancelled`/`expired` pattern of SPEC-004 (BR-009, AM-10) and SPEC-006 (BR-004) | BR-005, BR-006, FR-005, ERR-007, AF-005, AC-8 |
| AS-06 | The approval/rejection UI lives in the existing `ClientResource` (status badge/column, pending filter, Approve/Reject actions on pending records, ADMIN-only), extending SPEC-002's ADMIN-only client management. The status is NOT a staff-editable form field: it is set by the flow (default `active` on create; `pending` on registration; transitions via Approve/Reject actions). | Derived from SPEC-002 BR-004 (client management is ADMIN-only) and the task directive "where does approval live? ClientResource in Filament"; not documented | FR-003..FR-005, BR-009, §9, AC-6..AC-10 |
| AS-07 | The registration submission route is rate-limited with the framework throttle (a conservative per-IP limit, analogous to the existing `throttle:login`), as spam hardening. CAPTCHA / anti-bot services are out of scope. | Not documented — analyst necessity, consistent with SPEC-001's login rate limiting (`RateLimiter::for('login')`, AppServiceProvider) | FR-008, BR-013, ERR-005, AC-11, §12 |
| AS-08 | The registration page is guest-only: anonymous visitors can register; authenticated users are redirected away. | Not documented — analyst necessity; standard public-registration practice; avoids authenticated users creating duplicate accounts | BR-011, ERR-008, AC-12 |
| AS-09 | No auto-expiry, SLA or cleanup of pending registrations (edge case E-08): a pending registration remains `pending` until staff act; the linked User stays deactivated. No cleanup job. | Not documented — analyst necessity; consistent with the no-hard-deletion / preservation pattern (AGENTS.md §12; SPEC-001..006) | BR-010, AF-004, AC-16 |
| AS-10 | SPEC-012 introduces no restriction on other modules referencing a pending/rejected client (e.g., SPEC-004 membership creation); those modules gate on membership state, not client status. Whether such a restriction is wanted is an open question (OQ-06). | Not documented — analyst necessity to avoid silently changing other modules' rules; no documented prohibition | BR-014, OQ-06, §12 |

### 14.2 Open questions to be answered before Implementation (or at latest before Review)

- OQ-01: Should the registration success screen provide any additional guidance
  (e.g., a reference number, expected review time)? This Specification assumes
  only the generic "registration received; you will be able to log in once
  approved" message (AS-11). The exact copy/design is a SPEC-015 presentation
  concern.
- OQ-02: Is an applicant-facing "check registration status" page wanted before
  SPEC-013 (which would require identifying the applicant, e.g., by DNI + email,
  without authentication)? This Specification assumes no such page (§12, AS-11).
- OQ-03: Should notifications (email) be sent to the applicant on approval or
  rejection? This Specification assumes none (SPEC-002 §12 excludes welcome
  email; §12).
- OQ-04 (edge case E-08): Is an automatic expiry or reminder for pending
  registrations wanted (e.g., reject after N days)? This Specification assumes
  none (AS-09).
- OQ-05: Should the registration page be reachable from a public "Plans" page
  (C-15 lists plans in the public website)? The plans listing itself is out of
  scope (SPEC-003 OQ-03); linking from it to registration is a presentation
  concern of SPEC-015.
- OQ-06 (AS-10 sub-question): Should a `pending` or `rejected` client be
  excluded from membership creation (SPEC-004 FR-001) or other modules' flows?
  This Specification introduces no such restriction; if the PO wants one, it is a
  change to the consuming module's rules.
- OQ-07: Is a DNI format validation wanted at registration? Consistent with
  SPEC-002 (no DNI format regex; presence + uniqueness only), this Specification
  imposes none.
- OQ-08: Should staff be able to convert a `pending` client to `active` without
  activating the linked User, or vice versa? This Specification couples them in
  the approval action (AS-03, FR-004); a split is possible if required.

---

## 15. Related Documents

- Product documentation: `docs/product/product-definition-v0.1.md` (public
  website scope, Open Question "online booking/membership purchases" = C-18)
- Domain documentation: `docs/domain/domain-model-v0.1.md` (User, Client)
- Requirements analysis: `docs/requirements/analyst-pass-001.md` (§5.15 Public
  Registration, D-01, D-13, D-17, C-15, C-18, E-08, E-10)
- Specifications: `docs/specs/SPEC-001.md`, `docs/specs/SPEC-002.md`,
  `docs/specs/SPEC-003.md`, `docs/specs/SPEC-004.md`, `docs/specs/SPEC-006.md`
  (AS-xx assumption pattern)
- Architecture documentation: `docs/architecture/SPEC-001.md`,
  `docs/architecture/SPEC-002.md`, `ARCHITECTURE.md` (§5 presentation contexts,
  §12 authentication, §20 simplest correct architecture)
- Architecture decisions: `docs/adr/ADR-001.md`, `docs/adr/ADR-002.md`
- Workflow state: `docs/sdd/state.yaml` (SPEC-012 entry, NIGHT MODE
  `project.po_decisions`)
- Development rules: `AGENTS.md`
