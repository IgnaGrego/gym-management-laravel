# SDD Review Report — SPEC-013 (Client Portal)

- **Verdict:** PASS
- **Date:** 2026-08-15
- **Reviewer:** SDD Reviewer agent (read-only)
- **Implementation attempts:** 1

## Summary

| Area | Result |
|---|---|
| Portal sections (8 read-only + 4 actions) | All implemented, scoped to own Client (C-13) |
| Book/cancel/log/edit profile | Self-service via reused CreateBooking, shared WorkoutLog rules, whitelist |
| Authorization (8 policies additive CLIENT-own) | Staff abilities preserved; RoutinePolicy::view scoped to active assignment |
| ERR-005 | renderPortal() boundary renders generic notice |
| Persistence | No new migrations |
| Tests | Portal 50 passed; full suite 524 passed (2957 assertions) |

## Verification

- All reads resolve auth()->user()->client and query through its relationships only; no request client_id/record id trusted.
- Book turno: active-membership gate, atomic capacity, duplicate, lead time, booked_by=null when self.
- Cancel own booking: foreign → 404, Gate authorize, Booking::cancel().
- Log workout: shared referenceRules() + assigned-version/active-exercise rules; client_id/recorded_by injected server-side.
- Edit profile: only email/phone/emergency_contact; full_name/dni immutable; health notes read-only.
- No Mercado Pago, no invented business rules (no phone regex, no email uniqueness), no unrelated changes.

## Findings

No blockers.

- **Nit**: `resolveClient()` aborts 404 on mutations for unlinked CLIENT, while architecture §5 says ERR-005 notice. Spec defines ERR-005 only for reads; mutation behavior unspecified. No security implication. Cosmetic only.
- **Nit**: `book()` uses route param `{turno}` instead of form field — behavior identical, more consistent with architecture §4/§5 route table.

## Result

PASS — implementation is spec-conformant, convention-consistent, and fully tested.
