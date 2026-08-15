# AGENTS.md

# Gym Management — AI Development Rules

## 1. Purpose

This repository contains a gym management application.

The project is developed using a Specification-Driven Development (SDD) workflow.

The AI must treat the repository documentation as the source of truth.

The AI must not rely on previous conversations as authoritative project knowledge.

If a decision is not documented in the repository, it must be considered unknown unless it can be safely inferred from existing code and documentation.

---

# 2. Source of Truth

The priority order is:

1. Approved specifications
2. Architecture documentation
3. ADRs
4. Domain documentation
5. Product documentation
6. Existing implementation
7. AI assumptions

The AI must never use an assumption to override an explicit project decision.

---

# 3. SDD Workflow

All non-trivial functionality follows:

Product Requirement
→ Specification
→ Architecture
→ Implementation
→ Tests
→ Review

The workflow has four conceptual roles:

- Analyst
- Architect
- Developer
- Reviewer

A single AI model may perform different roles in different sessions, but the responsibilities must remain separated.

---

# 4. Analyst

The Analyst is responsible for understanding business requirements.

The Analyst must:

- analyze requirements;
- identify ambiguity;
- identify missing business rules;
- identify edge cases;
- produce specifications;
- update requirements documentation when appropriate.

The Analyst must NOT:

- implement application code;
- modify database schemas;
- introduce technical architecture;
- invent business rules.

When requirements are ambiguous, the Analyst must document the ambiguity.

---

# 5. Architect

The Architect is responsible for translating approved requirements into a technical design.

The Architect must:

- inspect the existing architecture;
- identify affected modules;
- identify required data changes;
- identify application services/actions;
- identify authorization requirements;
- identify integration requirements;
- document significant architectural decisions.

The Architect must NOT implement the feature unless explicitly asked to do so as a separate development task.

The Architect must avoid unnecessary abstraction.

---

# 6. Developer

The Developer is responsible for implementing an approved specification.

Before writing code, the Developer must read:

- AGENTS.md
- ARCHITECTURE.md
- relevant product documentation
- relevant domain documentation
- the feature specification
- the relevant architecture document
- relevant ADRs

The Developer must:

- implement the requested functionality;
- follow the existing architecture;
- add or update tests;
- run relevant tests;
- avoid unrelated changes.

The Developer must NOT:

- invent business rules;
- silently change requirements;
- perform unrelated refactoring;
- introduce dependencies without justification;
- change architectural conventions without documentation.

---

# 7. Reviewer

The Reviewer validates the implementation.

The Reviewer must compare:

- specification;
- architecture;
- implementation;
- tests.

The Reviewer must verify:

- functional requirements;
- business rules;
- authorization;
- validation;
- edge cases;
- tests;
- architectural consistency.

The Reviewer must not automatically modify code.

The Reviewer reports problems for the Developer to fix.

---

# 8. Specification First

A non-trivial feature must have an approved specification under:

docs/specs/

Do not implement a feature based only on a natural-language request if the required business behavior is not sufficiently defined.

If the request is ambiguous:

STOP.

Report:

1. What is ambiguous.
2. Why it matters.
3. Possible interpretations.
4. Recommended interpretation.

---

# 9. Business Logic

Business rules must not be placed directly in:

- Blade templates;
- controllers;
- Filament resources;
- migrations.

Controllers and UI components should coordinate application behavior.

Important business operations should use explicit Actions / Use Cases when appropriate.

Do not create abstractions merely to satisfy a theoretical architecture.

---

# 10. Laravel

Prefer Laravel conventions whenever they solve the problem adequately.

Use Laravel features before introducing custom infrastructure.

Prefer:

- Eloquent;
- Form Requests;
- Policies;
- Jobs;
- Events;
- Notifications;
- Actions when useful;
- framework validation;
- framework authentication.

Do not introduce repositories, domain services, interfaces, factories or other abstractions without a concrete reason.

---

# 11. Modular Monolith

The application is a modular monolith.

Initial business modules include:

- Clients
- Users
- Trainers
- Plans
- Memberships
- Payments
- Scheduling
- Bookings
- Attendance
- Exercises
- Routines

Modules should have clear responsibilities.

Avoid creating a giant generic service layer.

---

# 12. Database

The primary database is PostgreSQL.

Use Eloquent by default.

Database migrations must represent the current domain model.

Avoid destructive migrations unless explicitly required.

Historical business data should generally be preserved.

---

# 13. Testing

Every new business feature must include tests.

Tests should cover:

- happy path;
- validation;
- business rules;
- authorization;
- relevant failure cases.

Do not delete or weaken existing tests merely to make new code pass.

Before considering a feature complete:

- run the relevant tests;
- run the complete test suite when practical.

---

# 14. Dependencies

Do not install a package without justification.

Before adding a dependency, document:

- problem being solved;
- why existing Laravel functionality is insufficient;
- package purpose;
- impact on architecture.

---

# 15. Scope Control

Only modify what is necessary for the requested task.

Do not perform unrelated refactoring.

If a discovered issue is unrelated:

document it instead of silently fixing it.

---

# 16. Architectural Decisions

Important architectural decisions must be documented as ADRs under:

docs/adr/

Examples:

- database strategy;
- authentication architecture;
- payment provider integration;
- multi-tenancy strategy;
- major infrastructure changes.

---

# 17. Security

Never expose:

- passwords;
- secrets;
- API keys;
- tokens;
- private credentials.

Do not commit .env files containing secrets.

Authorization must be enforced server-side.

Never rely only on frontend restrictions.

---

# 18. Completion

A feature is complete only when:

- specification is satisfied;
- implementation exists;
- tests exist;
- tests pass;
- authorization is correct;
- documentation is updated where required;
- no known specification violation remains.

---

# 19. When in Doubt

Do not guess about business behavior.

Ask.

Do not introduce architectural complexity without justification.

Prefer the simplest correct solution.
