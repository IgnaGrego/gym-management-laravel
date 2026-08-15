# Analyst Pass 001 — Full Domain Review

## 1. Status

| Field | Value |
| --- | --- |
| Version | 0.1 |
| Status | Draft |
| Role | Analyst |
| Date | 2026-08-14 |
| Inputs | Product Definition v0.1, Domain Model v0.1, ARCHITECTURE.md, AGENTS.md, workflow docs |

## 2. Purpose

This document converts the current product knowledge into a precise map of requirements
status before any Specification is written.

It identifies:

- rules already confirmed by documentation;
- missing rules;
- contradictions and tensions;
- dependencies;
- edge cases;
- pending decisions the Product Owner must close;
- functional risks.

No Specification is produced in this pass. The Analyst does not decide business behavior.
Every pending decision is presented with options; the recommended option is only a
proposal and requires Product Owner approval.

## 3. Sources Read

- `AGENTS.md`
- `ARCHITECTURE.md`
- `README.md`
- `docs/product/product-definition-v0.1.md`
- `docs/domain/domain-model-v0.1.md`
- `docs/workflow/analyst.md`, `architect.md`, `developer.md`, `reviewer.md`
- Templates: `docs/specs/_TEMPLATE.md`, `docs/architecture/_TEMPLATE.md`, `docs/adr/_TEMPLATE.md`

Note: no application implementation exists yet (empty Laravel scaffold). No code was
consulted; the documentation is the only source of truth.

---

# 4. Confirmed Decisions

The following are already defined and must be respected.

| ID | Rule | Source |
| --- | --- | --- |
| C-01 | Roles: ADMIN, TRAINER, CLIENT. A User may hold one or more roles. | domain-model §User, ARCHITECTURE §12 |
| C-02 | A Client may have memberships, payments, bookings, attendance records, routines and workout records. | domain-model §Client |
| C-03 | A Trainer may be assigned clients, creates routines, assigns routines and reviews workout progress. | domain-model §Trainer |
| C-04 | A Plan is a product/service offered by the gym. | domain-model §Plan |
| C-05 | A Membership is a client's enrollment in a Plan for a specific period. | domain-model §Membership, ARCHITECTURE §14 |
| C-06 | A Payment is a financial transaction related to a Membership; it records money received or processed. | domain-model §Payment |
| C-07 | Plan, Membership and Payment are separate, persistent concepts. | ARCHITECTURE §14 |
| C-08 | A Client may have multiple Membership records over time. | domain-model §Membership |
| C-09 | A Routine is organized in days (RoutineDay → RoutineExercise → Exercise). A RoutineExercise defines a prescription: sets, repetitions, weight. | domain-model §Routine |
| C-10 | Prescription and Execution are separated. A Workout Log records what the client actually performed and must not modify the prescribed routine. | ARCHITECTURE §16, domain-model §WorkoutLog |
| C-11 | A Workout Log references the performed RoutineExercise or Exercise (both cases exist). | domain-model §WorkoutLog |
| C-12 | Payment processing is separated conceptually from membership state. The domain must not depend on Mercado Pago-specific concepts when avoidable. | ARCHITECTURE §13 |
| C-13 | A client must never access another client's private information. | ARCHITECTURE §12 |
| C-14 | MVP is web-based with one location and no multi-tenancy. No speculative public API. | ARCHITECTURE §17-19, product-definition §Out of Scope |
| C-15 | Three presentation contexts sharing one backend: Filament admin (ADMIN + TRAINER), client portal (CLIENT), public website (landing, plans, info, registration, login). | ARCHITECTURE §5 |
| C-16 | MVP is primarily a free-weight gym. Full class/group-session management and capacity limits are future scope, but architecture must not preclude them. | ARCHITECTURE §15 |
| C-17 | Billing and payment concepts are separated from access concepts; a confirmed-open membership decision ("more than one active Membership if the business permits it") is not yet decided. | domain-model §Membership |
| C-18 | Existing open questions from Product Definition v0.1 (must be answered before their feature is specified): automatic vs manual billing; missed/cancelled booking handling; attendance scope; routine versioning; online booking/purchase on the public site. | product-definition §Open Questions |

---

# 5. Per-Area Analysis

## 5.1 Authentication and Roles

Confirmed:
- Roles ADMIN, TRAINER, CLIENT; a User may hold more than one role (C-01).
- Authorization via Laravel Policies / permission mechanism; client isolation enforced (C-13).
- Admin panel is used by ADMIN and TRAINER.

Missing / ambiguous:
- Whether a person can be simultaneously TRAINER and CLIENT (e.g., a trainer who also trains). The "one or more roles" rule allows it, but the consequences (which profile manages routines vs attends) are undefined.
- Whether every Client is necessarily a User (login), or whether a Client record may exist without an account. See D-01.
- Whether a RECEPTIONIST / front-desk staff type exists or is needed for check-in, payments and attendance. The three roles may be insufficient. See D-19.
- How staff users are created and managed (who can create ADMIN/TRAINER accounts). The docs do not specify role management or role-assignment permissions.
- Password policy, account recovery, and whether public registration creates a User (see 5.15 and D-17).

## 5.2 Clients

Confirmed:
- A Client aggregates memberships, payments, bookings, attendance, routines and workout records (C-02).
- Client isolation is mandatory (C-13).

Missing / ambiguous:
- Client data fields (name, contact, DNI, birth date, etc.) are not defined.
- Health / medical information handling (injuries, conditions, emergency contact) is not mentioned at all. This is a sensitive-data decision with legal implications. See D-13.
- Client lifecycle: what makes a client "active", "inactive", "blocked", and who performs those transitions is not defined.
- Duplicate detection and uniqueness (e.g., DNI) are not defined.
- Whether clients are created by staff, by public registration, or both is not defined (see 5.15).
- Whether a Client must be linked to a User account is not defined (D-01).

## 5.3 Trainers

Confirmed:
- A Trainer may be assigned clients; creates and assigns routines; reviews workout progress (C-03).

Missing / ambiguous:
- Cardinality of the client–trainer relationship (1:1, 1:many, many:many) and who decides the assignment. Not defined.
- Whether a client may have no assigned trainer.
- Trainer qualifications / specialties (not defined; may be out of scope for MVP).
- Relationship between trainer availability and Scheduling is undefined (see 5.8).

## 5.4 Plans

Confirmed:
- A Plan is the offer the gym sells (C-04); examples: monthly membership, personal training, future plans.
- Plan, Membership, Payment are separate concepts (C-07).

Missing / ambiguous:
- Pricing model: fixed price per period? Per-session packages? Are "monthly membership" and "personal training" different plan categories with different semantics? See D-14.
- Whether a one-time enrollment fee (matrícula) exists. Not mentioned.
- Duration semantics: does a plan carry a period length, or does the membership? See D-03.
- Plan lifecycle: active/inactive/deprecated; whether an edited plan affects existing memberships. Not defined.
- Trial periods and discounts are not mentioned (may be out of MVP).

## 5.5 Memberships

Confirmed:
- A Membership is a client's enrollment in a Plan for a period (C-05); a client may have several over time (C-08).
- Multiple active memberships are possible "if the business permits it" — explicitly undecided (C-17).

Missing / ambiguous:
- Period model: start/end dates, duration in days vs calendar months, renewal behavior. See D-03.
- State machine: active / pending / expired / suspended / cancelled / frozen are not defined. See D-04.
- Whether the system bills automatically or manually (open question C-18).
- What an active membership grants: gym access? booking rights? See D-05.
- Freeze / on-hold (pausa) is not mentioned at all — common real-world need.
- Overlapping memberships and how multiple active memberships allocate payments. See D-06.

## 5.6 Cuotas (installments / dues)

Note: "cuota" is treated as a first-class concept in the requested domain, but it is
NOT present in the current documentation. The domain model only has Plan → Membership → Payment.

Missing / ambiguous:
- Whether the system generates a cuota per period (e.g., monthly due amount) and whether the amount is fixed from the plan or manually editable. See D-02.
- How a Payment satisfies a cuota: full / partial / multiple payments per cuota, overpayment, underpayment, discounts. Not defined.
- Late / overdue state and grace period. Not defined.
- Because this concept is absent from the documentation, its specification is blocked until the Product Owner defines it. This is the highest-impact modeling gap.

## 5.7 Payments

Confirmed:
- A Payment is a financial transaction related to a Membership and records money received or processed (C-06).
- Payment processing is separated from membership state; the domain should avoid MP-specific coupling (C-12).

Missing / ambiguous:
- Payment status lifecycle (pending / confirmed / failed / refunded) is implied by "received or processed" but not defined. See D-16.
- Payment methods: cash, bank transfer, Mercado Pago. Who records in-person payments (ADMIN? TRAINER? a receptionist?) is not defined. See D-15 and D-19.
- Refunds, chargebacks, retries, and MP webhooks are not defined.
- Whether a membership activates only after confirmed payment is not defined (ties to D-05/D-16).
- Invoices / receipts (comprobantes) are not mentioned.

## 5.8 Scheduling (Turnos)

Confirmed:
- A Schedule is an organized set of planned activities or trainer availability; a Session is a scheduled activity a Client can book and belongs to a Schedule (domain-model §Schedule/Session).
- MVP is a free-weight gym; full class management and capacity limits are future (C-16).

Missing / ambiguous:
- What a "turno" means in the MVP: a bookable time slot for gym access, a trainer-led session, or both? The domain model's Schedule→Session→Booking reads as class management, which conflicts with the free-weight MVP. See D-07. This is the main contradiction.
- Gym operating hours (opening/closing) are not defined.
- Recurring schedules (weekly templates) are not mentioned.
- Capacity limits per slot (future per C-16) — so what, if anything, limits a turno in MVP?
- Whether a booking/turno requires an active membership (ties to D-05).

## 5.9 Bookings (Reservas)

Confirmed:
- A Booking is a reservation made by a Client for a Session (domain-model §Booking).

Missing / ambiguous:
- Booking rules: lead time, maximum bookings per client, cancellation window, waitlist. Not defined.
- Handling of missed or cancelled bookings is an explicit open question (C-18).
- Booking status model (confirmed / cancelled / completed / no-show) is not defined.
- Whether bookings are re-openable after cancellation.
- No-show penalties or recredits are not defined.

## 5.10 Attendance

Confirmed:
- Attendance is a record that a Client accessed the gym or attended a Session (domain-model §Attendance). Whether it must cover one, the other, or both is an open question (C-18).

Missing / ambiguous:
- Recording mechanism: staff manual check-in, client self-check-in (PIN/QR), both. See D-09.
- Whether access is denied without an active membership (D-05).
- Whether attendance requires a booking.
- Who may record attendance and for whom (ADMIN, TRAINER, receptionist — ties to D-19).

## 5.11 Exercises

Confirmed:
- An Exercise is a single exercise that can be included in routines (domain-model §Exercise).

Missing / ambiguous:
- Catalogue attributes (name, muscle group, equipment, instructions/video, difficulty) are not defined. See D-20.
- Who manages the catalogue (ADMIN only, or TRAINER too) is not defined.
- Whether exercises are editable/deactivated without breaking routines is not defined (ties to versioning, D-12).

## 5.12 Routines

Confirmed:
- A Routine is a plan of exercises assigned to a client, organized in days; RoutineDay → RoutineExercise → Exercise (C-09).
- Prescription (sets, reps, weight) vs Execution is separated (C-10).

Missing / ambiguous:
- Semantics of "days": days of the week template (Mon/Thu) vs ordinal days in a cycle (Day 1..N). See D-10.
- Prescription granularity: the domain example lists per-set rows ("60 kg × 10" twice), which suggests set-level rows; alternatively a single RoutineExercise with a sets count. See D-11.
- Routine lifecycle: draft / active / archived; whether a client can have more than one active routine. Not defined.
- Routine versioning is an open question (C-18). See D-12.
- Whether editing a prescribed routine changes what the client currently sees or creates a new version.
- Whether ADMIN can create routines or only TRAINER.

## 5.13 Workout Tracking (Seguimiento de entrenamiento)

Confirmed:
- A Workout Log records what the client actually performed, referencing the performed RoutineExercise or Exercise, and must not alter the prescription (C-10, C-11).
- A Trainer reviews workout progress (C-03).

Missing / ambiguous:
- Logged fields: actual weight, repetitions, sets, date/time, notes, RPE. Not defined.
- Granularity: per set or per exercise? (ties to D-11).
- Who records: client self-recording in the portal, trainer on behalf, or both? Not defined (ties to D-18).
- Progress comparison / trainer review view is not defined.
- Whether logging is restricted to clients with an assigned routine, or free logging is allowed (C-11 implies both cases).

## 5.14 Client Portal

Confirmed:
- Clients access a web portal separate from the admin panel (C-15).

Missing / ambiguous:
- Portal feature scope is entirely undefined: view memberships/payments, book turnos, view routine, log workouts, edit profile, pay online. See D-18.
- Whether the portal is read-only or interactive is undefined.
- Account creation for the portal (self-registration vs staff-issued) ties to D-01 and D-17.

## 5.15 Public Registration

Confirmed:
- The public website includes registration and login (C-15).
- Full public website content is out of scope (product-definition §Out of Scope).

Missing / ambiguous:
- What a public registration creates: an immediate active Client, a pending Client for staff approval, or a lead? See D-17.
- Whether public registration includes plan selection and/or online payment (open question C-18).
- Data collected at registration (ties to D-13).

## 5.16 Mercado Pago

Confirmed:
- MP is the external payment provider; the domain avoids MP-specific coupling (C-12).

Missing / ambiguous:
- Integration scope: online checkout (client-initiated) vs QR/point-of-sale vs recurring subscriptions. See D-16.
- What triggers an MP payment: public-site purchase, portal payment of a cuota, or both? (ties to D-02, D-17, D-18).
- Webhooks, reconciliation, refunds, and pending-state handling. Not defined.

---

# 6. Contradictions and Tensions

| ID | Tension | Impact |
| --- | --- | --- |
| T-01 | The domain model's Scheduling (Schedule → Session → Booking) describes session/class management, but the product is a free-weight gym where full class management is out of MVP scope (C-16). What a "turno" is in the MVP is therefore undefined. | Blocks Scheduling/Bookings/Attendance specs until resolved (D-07). |
| T-02 | A Client may hold more than one active Membership "if the business permits it" (C-17), but membership is the natural gate for access and booking (D-05). Multiple active memberships create ambiguity about which membership grants access and to which payments apply. | Blocks Membership spec until resolved (D-06). |
| T-03 | A Payment is "related to a Membership" (C-06), but the "cuota" concept (the specific due amount the payment satisfies) is absent from the documentation while being a core requested domain concept. | Blocks Payments/Membership specs until resolved (D-02). Highest-impact gap. |
| T-04 | "Money received or processed" (C-06) implies a payment lifecycle (pending/confirmed/failed), but no status model is documented. | Blocks Payments and MP specs (D-16). |
| T-05 | The domain diagram shows User ├── Client and ├── Trainer, implying Clients are users, but nothing states every Client has credentials, or whether a client record requires an account. | Blocks Auth, Clients, Portal and Public Registration specs (D-01). |

---

# 7. Edge Cases Requiring Product Owner Attention

| ID | Edge case | Appears in |
| --- | --- | --- |
| E-01 | A membership expires while the client still attends. What happens at the door (attendance) and to bookings? | D-05, D-09 |
| E-02 | A client pays late: grace period, overdue status, backdated payment. | D-02, D-04 |
| E-03 | A client overpays or prepays several months in advance. | D-02, D-06 |
| E-04 | A plan price changes while clients hold active memberships. | D-14, D-03 |
| E-05 | A trainer leaves; their assigned clients and routines must be reassigned. | C-03, 5.3 |
| E-06 | A booking is cancelled at the last moment or is a no-show. | D-08 |
| E-07 | A routine is edited while clients are actively executing it. | D-12 |
| E-08 | A client registers publicly and the staff never approves the record. | D-17 |
| E-09 | An MP payment is marked pending and never confirmed (webhook lost). | D-16 |
| E-10 | The same DNI/contact registers twice. | 5.2, D-13 |
| E-11 | A client holds both TRAINER and CLIENT roles (self-conflict in routines/attendance). | C-01, 5.1 |

---

# 8. Pending Decisions

Every item below requires Product Owner approval. Options marked "Recommended"
are Analyst proposals only — the PO decides.

## Gate decisions for the first specifications

### D-01 — Client ↔ User relationship

Question: Does every Client require a User account (login credentials), or can a Client
record exist without an account (e.g., created by staff, credentials created later)?

Why it matters: determines authentication design, client creation, portal account
provisioning and public registration.

Options:
1. Every Client must have a User account from creation.
2. Client is a standalone record; a linked User account is optional and can be created later.
3. Client record always exists; the User is always created but is optional regarding portal access.

Recommended: option 2 — staff-created clients should not be forced into accounts, while
portal access stays possible later.

### D-02 — Cuota (installment/due) model

Question: Does the system generate a cuota (fixed due amount) per membership period, and
are cuota amounts fixed from the plan or manually editable?

Why it matters: the cuota is the missing link between Membership and Payment; without it,
payment allocation (partial, overpayment, multiple cuotas) is undefined.

Options:
1. System auto-generates a cuota per period with amount fixed from the plan.
2. System auto-generates cuotas but staff may edit the amount of a pending cuota.
3. No cuota entity; payments are free amounts linked to a membership period.

Recommended: option 2 — automatic generation with limited manual adjustment.

### D-03 — Membership period and renewal model

Question: How are membership periods and renewals defined?

Why it matters: resolves the auto-vs-manual billing open question (C-18) and defines
start/end, duration, expiry and renewal.

Options:
1. Fixed duration from start date (e.g., 30 days), manual renewal.
2. Calendar-month based (monthly), manual renewal.
3. Automatic renewal with recurring billing (ties to MP subscriptions, D-16).

Recommended: option 1 for MVP (manual renewal), with automatic billing deferred.

### D-04 — Membership state machine

Question: What states does a Membership have?

Options:
1. Minimal: active / expired.
2. Basic: pending (awaiting first payment) / active / expired / cancelled.
3. Rich: pending / active / expired / suspended / cancelled / frozen.

Recommended: option 2 for MVP; freeze (pausa) can be added later as it is not mentioned in
the documentation.

### D-05 — Access rule

Question: Is an active membership required for gym access (attendance), booking, or both?

Why it matters: defines the gate for attendance and bookings and the meaning of "active".

Options:
1. Active membership required for attendance and bookings.
2. Attendance allowed regardless; membership is informational only.
3. Active membership required, with a grace period after expiry.

Recommended: option 3 (grace period) — but depends on D-03.

### D-06 — Multiple active memberships

Question: May a client hold more than one active membership at the same time?

Options:
1. Only one active membership per client.
2. Multiple allowed (e.g., monthly gym + personal training).

Recommended: option 2, because Plan examples include both monthly membership and personal
training which may coexist — but the access rule (D-05) must then define which membership
governs access.

### D-07 — Scheduling scope in MVP ("turno")

Question: What does a turno represent in the MVP?

Options:
1. Bookable time slot for gym access (capacity-limited).
2. Trainer-led sessions only.
3. Both, minimally.
4. No scheduling in MVP.

Recommended: option 1 — fits the free-weight gym and the future class management direction
(C-16).

### D-08 — Booking rules

Question: Which booking rules apply? (sub-decisions)
- How far in advance can a client book?
- Maximum bookings per client (per day / per week)?
- Cancellation deadline and who may cancel?
- Is there a waitlist?
- What happens on miss (no-show) or cancellation (open question C-18)?
- Are cancelled spots re-bookable?

Recommended: define minimal defaults for MVP (short lead time, no waitlist, cancellation
without penalty, spots reopen). PO must confirm each.

### D-09 — Attendance scope and recording

Question: Does attendance cover gym access, sessions, or both? How is it recorded?

Options:
1. Gym access only; staff manual check-in.
2. Sessions only; staff manual.
3. Both; staff manual in MVP (self check-in later).
4. Client self check-in (PIN/QR) in MVP.

Recommended: option 3 (staff manual) for MVP.

## Decisions for later specifications

### D-10 — Routine "days" semantics

Question: Does a routine's days mean days-of-the-week templates or ordinal days of a cycle?

Options:
1. Days of the week (e.g., Mon/Thu).
2. Ordinal days within a repeating cycle (Day 1..N).

Recommended: option 2 — fits free-weight prescription cycles.

### D-11 — Prescription granularity

Question: Is the prescription stored per exercise (sets count) or per set (rows as shown in
the domain example)?

Options:
1. Exercise-level: one RoutineExercise with sets, reps, weight.
2. Set-level: one RoutineExercise row per set (matches the documented example).

Recommended: option 2 — matches the domain model example.

### D-12 — Routine versioning (open question C-18)

Question: When a prescribed routine is edited, what happens to clients already executing it?

Options:
1. Live edit — current assignment changes immediately.
2. Versioning — edits create a new version; old versions remain for history.
3. Versioning with reassignment — new version created and clients reassigned explicitly.

Recommended: option 3 — preserves history (archives §12) without breaking active clients.

### D-13 — Client data and health information

Question: Which client fields are stored, and is health/medical information included?

Options:
1. Minimal contact data only (name, contact, DNI).
2. Contact data plus basic health notes (injuries, conditions, emergency contact).

Recommended: option 2 with clear sensitive-data handling; confirm consent/retention rules.

### D-14 — Plan pricing model

Question: How are plans priced?

Options:
1. Fixed price per period only.
2. Fixed price per period plus optional one-time enrollment fee (matrícula).
3. Multiple plan categories with different pricing (subscription vs session packages).

Recommended: option 2 for MVP; session packages later.

### D-15 — Payment methods and recording

Question: Which payment methods exist and who records in-person payments?

Options:
1. Cash, bank transfer, MP; ADMIN/TRAINER record cash/transfer.
2. Same but a dedicated receptionist role records payments (ties to D-19).

Recommended: option 1 unless D-19 adds a receptionist role.

### D-16 — Payment lifecycle and MP integration

Question: What is the payment status lifecycle and how does MP integrate?

Options:
1. Statuses pending/confirmed/failed; membership activates only after confirmed; MP online
   checkout (client-initiated) with webhooks.
2. Same plus QR/point-of-sale MP and refunds in MVP.
3. Recurring subscription model via MP (ties to D-03).

Recommended: option 1 for MVP; refunds handled manually.

### D-17 — Public registration flow

Question: What does a public registration create?

Options:
1. Immediate active Client.
2. Pending Client record awaiting staff approval.
3. Lead/contact record without a client profile.

Recommended: option 2 — prevents unpaid/invalid registrations from becoming active.

### D-18 — Client portal scope

Question: Which features does the client portal include?

Options:
1. Read-only: view own memberships, payments, attendance.
2. Interactive: view plus book turnos and cancel bookings.
3. Full: view, book, view routine, log workouts, edit profile.

Recommended: option 3 is the likely target; confirm which pieces belong to MVP.

### D-19 — Roles and staff types

Question: Is a fourth role (RECEPTIONIST/front-desk) needed, or do ADMIN/TRAINER cover
check-in, payments and attendance?

Options:
1. Keep three roles; front-desk tasks assigned to TRAINER.
2. Add a RECEPTIONIST role for check-in, payments, attendance.

Recommended: confirm with PO; option 2 if the gym has non-trainer front-desk staff.

### D-20 — Exercise catalogue attributes

Question: Which attributes does an Exercise have, and who manages the catalogue?

Options:
1. Name, muscle group, equipment; ADMIN-managed.
2. Plus instructions/video and difficulty; ADMIN and TRAINER managed.

Recommended: option 2 with ADMIN/TRAINER management, if the gym wants trainer-authored content.

---

# 9. Functional Risks

| ID | Risk | Severity | Mitigation |
| --- | --- | --- | --- |
| R-01 | Cuota/payment reconciliation is undefined (T-03): the Membership/Payment specs could be built on a wrong model. | High | Close D-02 before Payment spec. |
| R-02 | MP pending/refund lifecycle undefined: financial errors (double charge, no activation). | High | Close D-16 before Payment/MP spec. |
| R-03 | Access rule undefined: attendance/booking built without a membership gate. | High | Close D-05 before Attendance/Booking spec. |
| R-04 | Scheduling scope ambiguity (T-01) could force a rework of the Scheduling module. | Medium | Close D-07 before Scheduling spec. |
| R-05 | Health/medical data decision deferred: schema changes and liability later. | Medium | Close D-13 before Client spec. |
| R-06 | Multiple active memberships (D-06) cause access/payment allocation confusion if unresolved. | Medium | Close D-06 before Membership spec. |
| R-07 | Routine granularity/versioning ambiguity (D-11/D-12) risks routine data migration. | Medium | Close D-11/D-12 before Routine spec. |
| R-08 | Undefined portal feature scope causes scope creep in the Client Portal. | Medium | Close D-18 before Portal spec. |
| R-09 | No implementation exists yet: schema decisions made now are cheap to change, expensive later. | Medium | Prioritize gating decisions before the first specs. |

---

# 10. Proposed Specification Order

Order respects dependencies. Each spec is gated by the decisions in parentheses.

### Batch 1 — Foundation and commercial core

1. **SPEC-001 Authentication & Roles** — gates: D-01, D-19.
2. **SPEC-002 Client Management** — gates: D-01, D-13.
3. **SPEC-003 Plan Management** — gates: D-14.
4. **SPEC-004 Membership Management** — gates: D-02, D-03, D-04, D-06.
5. **SPEC-005 Payments & Cuotas** — gates: D-02, D-15, D-16.

### Batch 2 — Operations

6. **SPEC-006 Scheduling & Turnos** — gates: D-07.
7. **SPEC-007 Bookings** — gates: D-05, D-08.
8. **SPEC-008 Attendance** — gates: D-05, D-09.

### Batch 3 — Training

9. **SPEC-009 Exercise Catalogue** — gates: D-20.
10. **SPEC-010 Routines** — gates: D-10, D-11, D-12.
11. **SPEC-011 Workout Logs & Progress** — gates: D-11, D-12.

### Batch 4 — Client-facing

12. **SPEC-012 Public Registration** — gates: D-17.
13. **SPEC-013 Client Portal** — gates: D-18.
14. **SPEC-014 Mercado Pago Integration** — gates: D-16 (can be specified alongside SPEC-005).

Dependency notes:
- SPEC-001 and SPEC-002 are prerequisites for nearly every later spec.
- SPEC-005 (Payments) must not be specified before D-02 (cuota model).
- SPEC-007/008 depend on the access rule (D-05).
- SPEC-013 depends on the feature scopes of batches 1–3 (portal aggregates them).
- SPEC-014 can be specified in parallel with SPEC-005 because MP is an external integration.

---

# 11. Next Steps

1. Product Owner reviews this document.
2. Product Owner closes the pending decisions (D-01 to D-20), prioritizing the gate
   decisions: D-01, D-02, D-03, D-05, D-13, D-14, D-19 for Batch 1.
3. Analyst produces SPEC-001 onward once the corresponding gates are closed.
4. Decisions with architectural impact are later recorded as ADRs by the Architect.

---

# 12. Related Documents

- `docs/product/product-definition-v0.1.md`
- `docs/domain/domain-model-v0.1.md`
- `ARCHITECTURE.md`
- `AGENTS.md`
