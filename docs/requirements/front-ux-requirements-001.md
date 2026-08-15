# Front UX Requirements — Presentation Foundation (input for SPEC-015)

## 1. Status

| Field | Value |
| --- | --- |
| Version | 0.1 |
| Status | Draft |
| Role | Product Owner input |
| Date | 2026-08-15 |
| Target spec | SPEC-015 — Presentation Foundation & UX (proposed) |
| Inputs | ARCHITECTURE.md §5, product-definition-v0.1.md, domain-model-v0.1.md |

## 2. Purpose

This document captures the requirements for the public-facing presentation
foundation. It is the input for the Analyst to produce SPEC-015.

It does NOT replace the Specification. It does NOT define business behavior.

## 3. Problem

The application is functionally complete for its implemented features
(SPEC-001..003, all tests pass), but the public front is broken:

- `welcome`, `login` and `portal` views are bare HTML with inline CSS; no shared
  layout, no branding, no navigation.
- The declared presentation stack (Blade + Tailwind CSS + Alpine.js,
  ARCHITECTURE §2, §5) was never installed: no `package.json`, no Vite config,
  no `public/build`, no `node_modules`.
- The CLIENT flow ends in a placeholder ("Portal features are not yet available").
- The Filament admin panel works, but no shared visual identity exists between
  admin, public site and portal.

## 4. Confirmed constraints (source of truth)

- Three presentation contexts sharing one backend: Filament admin (ADMIN+TRAINER),
  client portal (CLIENT), public website (landing, plans, info, registration,
  login) — ARCHITECTURE §5, C-15.
- The public website and the authenticated application share the same backend.
- The project is a pragmatic modular monolith; avoid unnecessary abstraction
  (ARCHITECTURE §20, AGENTS.md §5).
- Roles: ADMIN, TRAINER, CLIENT (SPEC-001).
- Authentication: web sessions via `/login`, `role:CLIENT` gate on `/portal`
  (SPEC-001). No speculative public API (ARCHITECTURE §19).
- Post-login landing: ADMIN/TRAINER → `/admin`; CLIENT → `/portal` (SPEC-001).

## 5. Scope of SPEC-015 (proposed)

### 5.1 Toolchain

- Install and configure the frontend build pipeline: `package.json`, Vite,
  Tailwind CSS, Alpine.js.
- Wire Blade views to the compiled assets.
- Document build/run commands (`npm install`, `npm run build` / `npm run dev`).

### 5.2 Shared presentation foundation

- A shared Blade layout with consistent header, footer, branding and navigation
  adapted to the authenticated state and role.
- Consistent styling tokens (colors, spacing, typography) reused by public
  views and available to the portal.
- Responsive, accessible baseline (semantic HTML, focus states).

### 5.3 Public pages

- `/` landing page with gym identity, clear navigation, and a login entry point.
- `/login` form restyled to match the shared identity (behavior unchanged;
  SPEC-001 contract preserved).

### 5.4 Client portal base

- `/portal` (CLIENT only) shows the authenticated client's real data:
  profile (name, DNI, email, phone) and account status.
- Keep the existing logout behavior.
- No membership/payment/booking/routine content in SPEC-015: those belong to
  their own specs (SPEC-004, 005, ..., SPEC-013).

## 6. Out of scope for SPEC-015

- Admin panel visual redesign (Filament styling changes live in each business
  spec or a dedicated later spec).
- Client portal business content (memberships, payments, bookings, routines) —
  SPEC-013 and its predecessors.
- Public website complete content (plans listing, registration, online payment) —
  product-definition §Out of Scope.
- Any change to authentication, authorization, routes, business rules or tests
  of existing specs.
- Any API.

## 7. Open questions for the Analyst

- Should the shared layout also be applied to the admin panel, or is Filament
  kept as its own context for SPEC-015?
- Which design language (colors, name/branding) does the gym use? None is
  documented.
- Is a dark/light mode in scope?
- Does the landing page need a "Plans" teaser section, or pure static content?

## 8. Dependencies

- SPEC-001 (auth + roles) — COMPLETED: provides the role gates the layout and
  portal rely on.
- SPEC-002 (clients) — COMPLETED: provides the client data shown in `/portal`.
- No dependency on SPEC-004..014: SPEC-015 can run as soon as the orchestrator
  is free.
- SPEC-013 (client portal content) consumes the base built here.

## 9. Risks

- The toolchain was never installed: first-time setup may surface environment
  issues (Node/npm version, build output) — mitigation: keep the pipeline minimal
  and document commands.
- Redesigning login/welcome could accidentally alter SPEC-001 behavior — mitigation:
  SPEC-015 changes presentation only; SPEC-001 tests must keep passing.

## 10. Decision needed from PO before/at Review

- Confirm SPEC-015 is taken by the orchestrator after SPEC-004 completes
  (explicit ordering), or left in `pending` to be picked when idle.
- Confirm scope boundaries in §5 / §6.
