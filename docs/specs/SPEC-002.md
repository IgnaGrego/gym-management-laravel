# SPEC-002 — Client Management

## Status

Draft (analysis phase).

This is the second Specification of the MVP. It depends on SPEC-001 (Authentication
& Roles), which is COMPLETED (`docs/sdd/state.yaml`) and whose implementation exists
in the repository (User model, Role model, `role_user` pivot, UserPolicy,
UserResource, RoleSeeder, AdminUserSeeder).

**Assumption notice:** this specification contains explicitly flagged assumptions
(AD-01 to AD-07, see §14.1) that either borrow the documented "Recommended" options
of `analyst-pass-001.md` §8 (D-13), derive from documented edge cases (E-10), or
fill gaps required to make the specification implementable. **None of them is a
confirmed business rule** unless stated otherwise. Each requires Product Owner
confirmation before Implementation.

**PO-confirmed decision:** D-01 (Client ↔ User relationship, option 2 — Client is a
standalone record; a linked User account is optional and can be created later) was
CONFIRMED by the Product Owner and recorded in `docs/sdd/state.yaml` as the
confirmation of SPEC-001 assumption A-01. This Specification respects it and treats
client user provisioning as in-scope (SPEC-001 §12).

---

## 1. Objective

Provide client record management in the gym management system:

- an ADMIN can create, view, search and edit client records (identity, contact and
  basic health information);
- client records are standalone: a linked User account is optional and can be
  created later (PO-confirmed D-01);
- an ADMIN can provision a User account (CLIENT role) for an existing client so the
  client can later access their own portal context (portal features themselves are
  SPEC-013);
- client data isolation is preserved: a client must never access another client's
  private information (C-13);
- health information, if stored, is handled as sensitive data.

This is the base record for later commercial and operational Specifications
(memberships, payments, bookings, attendance, routines), which reference client
records (confirmed decision C-02).

---

## 2. Actors

Actors are defined by role, not by person (SPEC-001 §2). A single User may hold one
or more roles (confirmed decision C-01).

| Actor | Description |
| --- | --- |
| Anonymous visitor | No access to client management. Client data is never exposed on public pages. |
| ADMIN | Staff who administer the gym. In this Specification, the only actor that can create, view, search and edit client records and provision client-linked user accounts (assumption AD-03, AD-04). |
| TRAINER | Staff who train clients. No client record management capability in this Specification; trainer access to client data depends on the undefined trainer–client assignment (open question OQ-02). |
| CLIENT | A gym member. Cannot manage client records; access to their own data is defined by SPEC-013 (client portal). Client isolation (C-13) always applies. |

---

## 3. Preconditions

1. SPEC-001 is implemented and completed: authentication, fixed role catalog
   (ADMIN, TRAINER, CLIENT), admin panel access (ADMIN or TRAINER), role helpers
   (`hasRole` / `hasAnyRole`), CLIENT role seeded, no hard deletion of User records
   (`docs/sdd/state.yaml`, ADR-001).
2. An authenticated ADMIN exists and can access the admin panel (SPEC-001 FR-008).
3. Decision D-01 is confirmed (PO): a Client record is standalone; a linked User
   account is optional and can be created later (`docs/sdd/state.yaml`, SPEC-001
   A-01 confirmation).
4. The role catalog stays at ADMIN / TRAINER / CLIENT; no RECEPTIONIST role
   (confirmed SPEC-001 A-04; decision D-19 remains resolved as "no fourth role").
5. No public registration exists yet (SPEC-012). In this Specification, client
   records are created by staff in the admin panel only.
6. No client data tables exist yet; the Clients module is greenfield on top of the
   SPEC-001 foundation.

---

## 4. Functional Requirements

### FR-001 — Create client record

An ADMIN can create a client record. Required fields: full name, DNI (national
identification number). Optional fields: contact email, phone, emergency contact,
injuries notes, medical conditions notes (assumption AD-01 borrowing D-13
Recommended option 2). Creating a client record does NOT create a User account
(PO-confirmed D-01; BR-001, BR-002).

### FR-002 — List and search clients

An ADMIN can list client records and search them by name, DNI and email.

### FR-003 — View client detail

An ADMIN can view a client's full record, including contact data and health notes.

### FR-004 — Edit client record

An ADMIN can update a client's identity, contact and health fields.

### FR-005 — Provision linked user account

An ADMIN can create a User account linked to an existing client, with the CLIENT
role, a login email and a password (framework default password policy, SPEC-001
A-05). The login email must be unique among User records (SPEC-001 ERR-005) and may
differ from the client's contact email. Provisioning is explicit and optional: a
client may exist indefinitely without an account (BR-001, BR-002).

### FR-006 — Display link status

The client record shows whether a linked User account exists and whether it is
active, so the ADMIN knows whether the client can log in.

### FR-007 — Health data handling

Health notes (emergency contact, injuries, medical conditions) are treated as
sensitive data: access is restricted to users authorized to manage clients (ADMIN in
this Specification, assumption AD-03), they are never exposed to clients other than
their owner (C-13), and they are never displayed in lists or search results, only in
the client detail view (FR-003). Consent and retention rules are pending (OQ-01).

---

## 5. Business Rules

### BR-001 — Client record is standalone

A Client record is a valid, complete entity without a linked User account; the
account is optional and can be created later (PO-confirmed decision D-01 / SPEC-001
A-01).

### BR-002 — Optional explicit provisioning

A User account linked to a Client is created only by an explicit provisioning action
(FR-005). Creating or editing a Client never creates, modifies or deletes a User
account implicitly.

### BR-003 — 1:1 client ↔ user link

A Client links to at most one User account, and a User account linked to a Client is
linked to exactly one Client (assumption AD-05). A linked account holds the CLIENT
role.

### BR-004 — Client management is ADMIN-only

Only ADMIN can create, view, search and edit client records and provision
client-linked user accounts (assumption AD-03, AD-04; account creation derives from
SPEC-001 BR-006 "user management is ADMIN-only"). TRAINER and CLIENT cannot.

### BR-005 — Unique DNI

The DNI is unique among client records; a duplicate DNI is rejected (derived from
edge case E-10; assumption AD-02).

### BR-006 — No hard deletion of client records

Client records are never hard-deleted; historical client data is preserved
(AGENTS.md §12; same pattern as SPEC-001 BR-007; assumption AD-06). No delete
operation is provided.

### BR-007 — Health data confidentiality

Health notes are sensitive data: only users authorized to manage clients (ADMIN in
this Specification) may view them; a client must never access another client's
private information, including health notes (C-13). Consent and retention rules are
pending (OQ-01).

### BR-008 — Independent lifecycle of the linked account

Modifying or deactivating a linked User account does not modify or delete the Client
record; the Client record remains valid (derived from PO-confirmed D-01; assumption
AD-07).

### BR-009 — Unique login email

The login email of a provisioned User account must be unique among User records
(SPEC-001 ERR-005).

---

## 6. Main Flow

1. An authenticated ADMIN opens the Clients section of the admin panel (FR-001).
2. ADMIN creates a client record: fills required identity data (name, DNI) and
   optionally contact and health fields, and saves.
3. The system validates: required fields present (ERR-002), DNI unique (ERR-001),
   formats valid (ERR-006).
4. The client record is persisted and appears in the client list (FR-002).
5. ADMIN can open the client detail view (FR-003), edit fields (FR-004), or
   provision a linked user account (FR-005).
6. If provisioning: ADMIN supplies a login email and password; the system creates a
   User with the CLIENT role linked to the client (BR-002, BR-003), after
   validating email uniqueness (ERR-003).
7. The client record now displays the linked account and its status (FR-006).

---

## 7. Alternative Flows

### AF-001 — Provisioning at a later time

A client created without an account can have a User account provisioned later from
the client detail view (FR-005); the client's login capability starts only after
provisioning (BR-002).

### AF-002 — Provisioning when the desired login email is already in use

If the intended login email belongs to another User, the ADMIN uses a different
email, or provisioning is rejected (ERR-003); the client's contact email is
unaffected.

### AF-003 — Client without account

A client without a linked User account continues to exist normally; no warning is
required; the client simply cannot log in yet (BR-001, BR-002).

### AF-004 — Linked user deactivated

If the linked User account is deactivated (SPEC-001 FR-007), the client record is
unaffected and the client cannot log in (BR-008); the link status shown per FR-006
reflects the inactive state.

---

## 8. Error Cases

### ERR-001 — Duplicate DNI

Condition: creating or editing a client with a DNI already used by another client.

Expected behavior: creation/update is rejected with a validation error (BR-005,
AD-02).

### ERR-002 — Missing required fields

Condition: creating/editing a client without the full name or DNI.

Expected behavior: rejected with a validation error (FR-001).

### ERR-003 — Duplicate login email on provisioning

Condition: provisioning a user account with an email already used by another User.

Expected behavior: rejected (BR-009; SPEC-001 ERR-005).

### ERR-004 — Second link attempt

Condition: ADMIN attempts to provision a second User account for a client that
already has a linked account.

Expected behavior: rejected; a client may have at most one linked account (BR-003,
AD-05).

### ERR-005 — Unauthorized access

Condition: a TRAINER or CLIENT attempts to access client management (create, view,
search, edit, provision).

Expected behavior: access denied (403 or hidden from navigation) (BR-004).

### ERR-006 — Invalid formats

Condition: malformed email or phone on client create/edit or on provisioning.

Expected behavior: rejected with a validation error (FR-001, FR-004, FR-005).

---

## 9. Authorization

| Operation | Anonymous | ADMIN | TRAINER | CLIENT |
| --- | --- | --- | --- | --- |
| Create client record | Denied | Allowed (BR-004) | Denied | Denied |
| List / search clients | Denied | Allowed (BR-004) | Denied | Denied |
| View client detail incl. health notes | Denied | Allowed (BR-004, BR-007) | Denied | Denied |
| Edit client record | Denied | Allowed (BR-004) | Denied | Denied |
| Provision linked user account | Denied | Allowed (BR-004, AD-04) | Denied | Denied |
| Access another client's data | Denied | Per feature rules (later specs) | Per feature rules (later specs) | Denied always (BR-007, C-13) |

Notes:

- A User holding several roles receives the union of the column permissions
  (SPEC-001 BR-002); an ADMIN who is also CLIENT can manage clients in the admin
  panel.
- Authorization is enforced server-side; frontend-only restrictions are never
  sufficient (AGENTS.md §17).
- TRAINER read access to client records is deferred (OQ-02); client self-service
  access is SPEC-013.

---

## 10. Data Changes

This Specification describes the information that must exist; the exact persistence
schema is defined by the Architect.

Created:

- Client records: identity (full name), national identification (DNI, unique —
  BR-005), contact (email, phone), and health notes (emergency contact, injuries,
  medical conditions) treated as sensitive data (FR-001, FR-007). The health data
  separation (separate table vs. clearly separated fields) is an Architect
  decision; the business rule is the access restriction (BR-007).
- A Client ↔ User link (nullable, 1:1 — BR-003), established only by explicit
  provisioning (FR-005).
- User account records created by provisioning, with the CLIENT role (FR-005; uses
  the SPEC-001 User / Role / `role_user` infrastructure).

Modified:

- Client identity, contact and health fields (FR-004).
- Link status when a user account is provisioned (FR-005, FR-006).

Deleted:

- No hard deletion of client records in the MVP (BR-006); no delete operation.

---

## 11. Acceptance Criteria

- [ ] AC-1: ADMIN can create a client with name and DNI (required) plus optional
  contact/health fields; the record is persisted and listed (FR-001, FR-002).
- [ ] AC-2: Creating or editing a client with a DNI already used by another client
  is rejected with a validation error (ERR-001, BR-005).
- [ ] AC-3: ADMIN can search clients by name, DNI and email (FR-002).
- [ ] AC-4: ADMIN can view a client's full detail including health notes (FR-003).
- [ ] AC-5: ADMIN can edit a client's fields; changes persist (FR-004).
- [ ] AC-6: Creating a client does not create any User record or role assignment
  (BR-001, BR-002, PO-confirmed D-01).
- [ ] AC-7: ADMIN can provision a User account for a client; the account receives
  the CLIENT role, can authenticate, and is redirected to the client portal context
  (FR-005, BR-003).
- [ ] AC-8: Provisioning with an email already used by another User is rejected
  (ERR-003).
- [ ] AC-9: A client with an existing linked account cannot receive a second one
  (ERR-004, BR-003).
- [ ] AC-10: A TRAINER or CLIENT cannot create, view or edit client records or
  provision accounts (403) (BR-004, ERR-005).
- [ ] AC-11: No delete operation exists for client records; a created client record
  persists (BR-006).
- [ ] AC-12: Health notes are visible only to ADMIN (in this Specification); a
  CLIENT cannot access another client's data, including health notes (BR-007,
  C-13).
- [ ] AC-13: Deactivating or editing a linked User account does not delete or
  modify the Client record (BR-008, AF-004).
- [ ] AC-14: The client detail view displays whether a linked account exists and
  its active status (FR-006).

---

## 12. Out of Scope

The following are explicitly NOT part of this Specification:

- Public registration of clients (deferred to SPEC-012) and any "pending approval"
  client state created by public registration.
- Client portal: a client's access to their own data, profile editing, and health
  data visibility to themselves (SPEC-013).
- Trainer–client assignment and TRAINER access to client records (undefined in
  `analyst-pass-001.md` §5.3; open question OQ-02).
- Client lifecycle / status (active, inactive, blocked) and the transitions between
  statuses (undefined in the documentation; open question OQ-03 — this
  Specification deliberately does not define a client status field).
- Memberships, payments, bookings, attendance, routines and workout records per
  client (SPEC-003 onward; C-02 only references them conceptually).
- Health-data consent workflow, retention and deletion policies (OQ-01).
- Notifications (e.g., welcome email) when a user account is provisioned.
- Bulk import/export of clients.
- A Client linked to multiple User accounts, or a User linked to multiple Clients
  (BR-003, AD-05).
- Dynamic creation of new roles (SPEC-001 FR-004).

---

## 13. Dependencies

- **SPEC-001 (Authentication & Roles) — COMPLETED** (`docs/sdd/state.yaml`):
  authentication and session foundation; fixed role catalog with CLIENT seeded
  (RoleSeeder); admin panel access (ADMIN | TRAINER); User model with
  `hasRole` / `hasAnyRole`; UserPolicy (ADMIN-only user management, no hard
  deletion); duplicate-email rule (ERR-005); password policy (A-05). Client user
  provisioning was explicitly deferred to this Specification (SPEC-001 §12, A-01).
- PO-confirmed decision D-01 (= SPEC-001 A-01): Client standalone; linked User
  optional, created later (`docs/sdd/state.yaml`).
- ADR-001 (multi-role access model via native pivot; no permission package).
- Confirmed decisions used: C-01 (roles, multi-role), C-02 (client aggregates),
  C-13 (client isolation), C-15 (presentation contexts).
- Flagged assumptions AD-01 to AD-07 require Product Owner confirmation before
  Implementation (see §14.1).

---

## 14. Open Questions

### 14.1 Assumed decisions requiring PO confirmation

These are flagged assumptions. They are needed to make the Specification
implementable, but they are NOT confirmed business rules.

| ID | Assumption | Source | Blocks / Affects |
| --- | --- | --- | --- |
| AD-01 | Client record fields = identity (full name), DNI, contact (email, phone) plus basic health notes (emergency contact, injuries, medical conditions). Required: name, DNI; health fields optional. | Borrowed from analyst-pass-001 D-13 Recommended (option 2); exact field list not documented | FR-001, FR-003, FR-004, FR-007, BR-007 |
| AD-02 | DNI is unique among client records; duplicates rejected. | Derived from analyst-pass-001 edge case E-10 | FR-001, ERR-001, BR-005 |
| AD-03 | Only ADMIN can create, view, search and edit client records. | Not documented — analyst necessity | FR-001..FR-004, BR-004, ERR-005 |
| AD-04 | Only ADMIN can provision client-linked user accounts. | Derived from SPEC-001 BR-006 (user management is ADMIN-only) | FR-005, BR-004 |
| AD-05 | Client ↔ User link is 1:1: a client has at most one linked account; a linked account belongs to exactly one client. | Not documented — analyst necessity | BR-003, ERR-004, FR-005 |
| AD-06 | No hard deletion of client records; preservation pattern like SPEC-001 BR-007. | Borrowed from AGENTS.md §12 and SPEC-001 BR-007 | BR-006, AC-11 |
| AD-07 | Modifying/deactivating a linked User does not affect the Client record. | Derived from PO-confirmed D-01 (SPEC-001 A-01) | BR-008, AC-13, AF-004 |

### 14.2 Open questions to be answered before Implementation (or at latest before Review)

- OQ-01 (decision D-13, sub-question): What are the consent, retention and deletion
  rules for health/medical data? Is a consent record stored in the system?
- OQ-02 (trainer–client assignment, analyst-pass-001 §5.3): Should TRAINER be able
  to view client records — and health notes — for clients assigned to them? Depends
  on the undefined trainer–client assignment.
- OQ-03 (new decision; analyst-pass-001 §5.2): Does the MVP need a client status
  (active / inactive / blocked)? Who performs transitions and what does each status
  mean? (This Specification deliberately does not define client status.)
- OQ-04: Are additional client fields required (e.g., birth date, address, photo)?
  D-13's recommendation mentions only name/contact/DNI plus health notes.
- OQ-05: Should editing a client's name synchronize to the linked User account's
  name?
- OQ-06: Is an unlink operation needed (removing the Client ↔ User link without
  deleting either record)?
- OQ-07: Must the provisioned account's login email match the client's contact
  email, or is any unique email acceptable? (This Specification assumes
  independent — AD-01, FR-005.)
- OQ-08: Are the health fields (emergency contact, injuries, conditions) required
  or optional at creation? (This Specification assumes optional — AD-01.)

---

## 15. Related Documents

- Product documentation: `docs/product/product-definition-v0.1.md`
- Domain documentation: `docs/domain/domain-model-v0.1.md`
- Requirements analysis: `docs/requirements/analyst-pass-001.md` (D-01, D-13, §5.2,
  E-10, E-11)
- Specification: `docs/specs/SPEC-001.md`
- Architecture documentation: `docs/architecture/SPEC-001.md`, `ARCHITECTURE.md`
- Architecture decision: `docs/adr/ADR-001.md`
- Development rules: `AGENTS.md`
- Workflow state: `docs/sdd/state.yaml`
