# SDD Review Report — SPEC-010 (Routines)

- **Verdict:** PASS
- **Date:** 2026-08-15
- **Reviewer:** SDD Reviewer agent (read-only)
- **Implementation attempts:** 1

## Summary

| Area | Result |
|---|---|
| Functional requirements FR-001..FR-014 | All implemented |
| Business rules BR-001..BR-011 | All enforced (D-10 op2 ordinal days, D-11 op2 set-level rows, D-12 op3 copy-on-edit versioning, one active assignment per client, draft/active/archived lifecycle, no delete) |
| Authorization (RoutinePolicy/RoutineAssignmentPolicy) | ADMIN/TRAINER manage, CLIENT denied, no delete — correct |
| Validation | ERR-001..ERR-009 handled at form + action + DB levels |
| Persistence | 4 migrations match architecture table-for-table |
| Tests (AC-1..AC-19) | Covered, meaningful — 47 SPEC-010 tests |
| Architecture consistency | Matches conventions; ADR-001/003/004 |
| Scope | Only additive changes (Client, ClientResource) |

## Verification

- Full suite: 321 passed (1869 assertions). SPEC-010 files: 47 passed (352 assertions).
- Tests meaningful: DB-level uniqueness, supersession counts, version-copy preserves inactive references, one-active invariant, activation DomainExceptions, policy abilities, 403/redirect, no-delete, AC-17 table-count assertions.

## Findings

No blockers or minor findings. Nits only:
1. One-active-assignment invariant enforced at application level (as spec mandates); partial unique index is optional hardening.
2. `AssignRoutine::handle()` empty clients collection is a silent no-op; UI requires ≥1 client.
3. Active-version edit flow depends on Filament hook ordering — re-verify on Filament upgrade.
4. `RoutineAssignmentFactory` defaults to draft routine — test-only convenience.

## Assessment of reported deviations

All 4 implementation deviations from architecture are behavior-preserving details within the architecture's documented flexibility; none violates spec or architecture:
1. Active-edit save mechanism (beforeValidate capture) — required for installed Filament behavior; BR-001 preserved.
2. `->distinct()` instead of closure rules for duplicate rejection — functionally equivalent, defense in depth.
3. Version history as TextEntry + state closure — architecture explicitly allows.
4. Activate surfaces DomainException as Notification — architecture explicitly permits.

## Result

PASS — implementation is spec-conformant, convention-consistent, and fully tested.
