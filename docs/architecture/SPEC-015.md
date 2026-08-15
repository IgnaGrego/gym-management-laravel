# Architecture — SPEC-015

## 1. Feature

Presentation Foundation & UX for the three public/client web screens:

- install the declared Vite + Tailwind CSS + Alpine.js frontend toolchain (currently
  absent: no `package.json`, `vite.config.js`, `tailwind.config.js`, or
  `public/build`);
- introduce a shared Blade layout (header/footer/branding/navigation adapted to
  auth state) and reusable styling tokens (colors, spacing, typography) with the
  "El Area Gym" fitness-orange identity over an accessible neutral base;
- restyle `/` (welcome), `/login` and `/portal` onto that shared foundation,
  preserving the existing routes, form actions/methods/CSRF, field names,
  validation/error rendering and redirect behavior (SPEC-001 contract);
- replace the `/portal` placeholder with the authenticated CLIENT's own profile
  data (name, DNI, email, phone, account status), including the generic ERR-005
  notice when no usable Client record exists.

This is a presentation/toolchain specification only. It adds no business rules,
no routes, no business data and no API. Authentication, authorization and the
Filament admin panel are unchanged.

---

## 2. Specification

Reference:

`docs/specs/SPEC-015.md`

Status note: SPEC-015 is approved (`spec_ready`) per `docs/sdd/state.yaml`. The
Product Owner pre-approval is recorded in
`docs/requirements/front-ux-requirements-001.md` and the SPEC-015
`po_decisions` in `state.yaml`:

- branding = "El Area Gym"; fitness-style palette, orange primary accent over an
  accessible neutral base;
- ERR-005 (authorized CLIENT with no usable Client record / unresolvable
  required value) = generic neutral notice "Perfil no disponible. Contactá a
  recepción.";
- out of scope (not blockers): Filament admin redesign, dark/light mode, landing
  "Plans" teaser, Node/npm version range.

No open questions remain that block the design (SPEC-015 §14).

---

## 3. Affected Modules

- **Public Website + Client Portal presentation** (main change): Blade views
  (`welcome`, `auth/login`, `portal`), a new shared layout and partials, the
  compiled asset entry point (`@vite`).
- **Frontend build toolchain** (new infrastructure): `package.json`,
  `vite.config.js`, `postcss.config.js`, `tailwind.config.js`,
  `resources/css/app.css`, `resources/js/app.js` (+ `resources/js/bootstrap.js`
  cleanup).
- **Clients/Users (read-only consumption, additive only):**
  `ClientPortalController` passes the authenticated user's linked `Client`
  record to the view; the `User::client()` relationship already exists
  (SPEC-002) and is read as-is.

Explicitly unchanged:

- Authentication (`AuthenticatedSessionController`, login/logout flows).
- Authorization: `role:CLIENT` middleware on `/portal`, `EnsureUserHasRole`,
  `User::canAccessPanel()`, and all existing Policies.
- Routes (`routes/web.php`) — no route is added, removed or renamed.
- Data model — no migration, no new table/column/field.
- Filament admin panel (`AdminPanelProvider` and all resources) — not restyled.

---

## 4. Application Flow

```text
Presentation (web)
    ↓
GET /            → view('welcome')  → @extends('layouts.app')
GET /login       → AuthenticatedSessionController@create → view('auth.login')
GET /portal      → (auth + role:CLIENT) ClientPortalController@index → view('portal')
    ↓
Shared Blade layout (layouts.app + partials/header + partials/footer)
    ↓
@vite(['resources/css/app.css', 'resources/js/app.js'])
    ↓
Vite dev server (npm run dev)  OR  compiled build manifest (npm run build)
    ↓
Persistence (portal only)
    ↓
auth()->user() → User::client() (HasOne via clients.user_id) → Client profile fields
```

Concrete flows:

1. **Asset compile (FR-001, FR-002).** A developer runs `npm install` then either
   `npm run build` (produces `public/build/manifest.json` + hashed assets
   consumed by `@vite`) or `npm run dev` (Vite dev server; `@vite` consumes the
   dev/hot assets). Page behavior and route contracts are identical in both modes
   (AF-003).
2. **Anonymous landing (FR-004).** `GET /` renders `welcome` inside the shared
   layout: "El Area Gym" branding, navigation, and a visible "Log in" link to
   `route('login')`. The existing `session('status')` notice is preserved (used by
   the SPEC-001 "user with no roles" login redirect).
3. **Login (FR-005).** `GET /login` renders `auth/login` inside the shared layout.
   The form keeps `method="POST" action="{{ route('login') }}"`, `@csrf`, field
   names `email`/`password`, `old('email')`, `autocomplete`, `required`,
   `autofocus`, and the `@error` blocks. On invalid submit Laravel returns the
   same generic error and redirect behavior (BR-001, ERR-003).
4. **CLIENT portal (FR-006).** `GET /portal` (guarded by `auth` + `role:CLIENT`)
   renders `portal` inside the shared layout. The controller reads
   `auth()->user()->client` and passes it to the view; the view renders the
   client's `full_name`, `dni`, `email`, `phone` and `status`, or the ERR-005
   notice when the Client record is missing (see §6 data mapping).
5. **Logout (FR-007).** The shared layout renders, for authenticated users, a
   `POST` form to `route('logout')` with `@csrf` (the portal's logout affordance).
   The route/method/CSRF contract is unchanged; the session terminates exactly as
   in SPEC-001.

---

## 5. Components

### Controllers

| Controller | Change | Notes |
| --- | --- | --- |
| `App\Http\Controllers\ClientPortalController` | Minimal additive | `index()` currently returns `view('portal', ['user' => auth()->user()])`. It shall additionally pass the linked Client record: `'client' => auth()->user()->client` (a `?Client` via the existing HasOne; null when unlinked). No business logic is added — it only exposes the existing relationship result to the view. |
| `App\Http\Controllers\Auth\AuthenticatedSessionController` | None | Login/logout/redirect behavior unchanged. |
| `App\Http\Controllers\WelcomeController` | None | Does not exist; `/` remains the inline closure in `routes/web.php` returning `view('welcome')`. |

### Actions / Use Cases

None. The portal read is a plain Eloquent relationship read; introducing an
Action would be an unnecessary abstraction (AGENTS.md §9-10, ARCHITECTURE §7, 20).

### Models

No model changes.

Read-only consumption:

- `App\Models\User` — identity and the existing `client(): HasOne` relationship
  (SPEC-002).
- `App\Models\Client` — `full_name`, `dni`, `email` (nullable), `phone`
  (nullable), `status` (`pending`/`active`/`rejected`; `Client::STATUS_*`
  constants), `user(): BelongsTo`.

### Policies

No changes. The portal is authorized server-side by the existing
`role:CLIENT` middleware (SPEC-001). No new Policy is introduced. Navigation
visibility in the layout is presentation only and is never an authorization
mechanism (SPEC-015 §9, AGENTS.md §17).

### Middleware

No changes. `web`, `auth`, `guest`, `role:CLIENT` and `throttle:login` are used
exactly as registered in `routes/web.php` and `bootstrap/app.php`.

### Events / Jobs

None required. No secondary effect, notification or queued work exists in this
specification (ARCHITECTURE §10-11).

### Routes

Unchanged. The existing route table stands:

| Method | URI | Middleware | Controller / Target |
| --- | --- | --- | --- |
| GET | `/` | `web` | closure → `view('welcome')` |
| GET | `/login` | `web`, `guest` | `AuthenticatedSessionController@create` |
| POST | `/login` | `web`, `guest`, `throttle:login` | `AuthenticatedSessionController@store` |
| POST | `/logout` | `web`, `auth` | `AuthenticatedSessionController@destroy` |
| GET | `/portal` | `web`, `auth`, `role:CLIENT` | `ClientPortalController@index` |

`/register*` (SPEC-012) views are not in scope and remain as-is.

### Frontend toolchain (new)

Declared minimal npm stack (FR-001, FR-009; no framework beyond
Blade/Tailwind/Alpine):

- **`package.json`** — `"type": "module"` (Laravel 11 convention). Scripts:
  `"dev": "vite"`, `"build": "vite build"`. Dependencies: `alpinejs`.
  devDependencies: `vite`, `laravel-vite-plugin`, `tailwindcss` (v3),
  `postcss`, `autoprefixer`.
- **`vite.config.js`** — `laravel-vite-plugin` with
  `input: ['resources/css/app.css', 'resources/js/app.js']` and
  `refresh: true`.
- **`postcss.config.js`** — `tailwindcss` + `autoprefixer` plugins.
- **`tailwind.config.js`** — `content` globs for `resources/views/**/*.blade.php`
  and `resources/js/**/*.js`; `theme.extend` holds the styling tokens (below).
- **`resources/css/app.css`** — rewrite the currently empty file with
  `@tailwind base; @tailwind components; @tailwind utilities;` plus optional
  small `@layer components` for shared button/input/form tokens.
- **`resources/js/app.js`** — rewrite to bootstrap Alpine:
  `import Alpine from 'alpinejs'; window.Alpine = Alpine; Alpine.start();`.
- **`resources/js/bootstrap.js`** — currently imports `axios` (a Laravel
  scaffold default that is **not** in the declared stack and has no API target;
  ARCHITECTURE §19, SPEC-015 FR-009/§12). `axios` is NOT added as a dependency;
  the import is removed (the file is emptied or dropped). Alpine is the only
  runtime JS.

Build commands (FR-002; the Developer must also document these in `README.md`):

```bash
npm install      # install dependencies
npm run build    # production build → public/build/manifest.json + hashed assets
npm run dev      # Vite development server (hot module replacement)
```

`node_modules/`, `public/build/` and `public/hot/` are already git-ignored
(`.gitignore`), so the compiled manifest is a build artifact, never committed
(ERR-004).

### Styling tokens (FR-003, FR-008, AC-14)

`tailwind.config.js` `theme.extend.colors` defines a `brand` orange scale as the
accent; the accessible neutral base uses Tailwind's stock `stone` scale (no
override needed). Design target (Developer finalizes shades while meeting WCAG
AA contrast):

```js
colors: {
  brand: {
    50: '#fff7ed', 100: '#ffedd5', 200: '#fed7aa', 300: '#fdba74',
    400: '#fb923c', 500: '#f97316',  // accent (decorative: borders, rings, tints)
    600: '#ea580c', 700: '#c2410c',  // interactive text/buttons
    800: '#9a3412', 900: '#7c2d12',
  },
},
```

- Neutral base: `stone-50`/`stone-100` page background, `stone-900` primary text
  (existing pages already use a `#f3f4f6`-like neutral).
- Typography: Tailwind default `font-sans` (system-ui stack, matching the current
  inline CSS); no external fonts/CDN (FR-009).
- Spacing: Tailwind default scale.
- Contrast rule (AC-14): `brand-500` is an accent only (not body text — ~2.8:1
  on white). Use `brand-600`/`brand-700` for text and white-on-`brand-600`
  buttons (≥4.5:1), and `brand-500` for borders, focus rings and background
  tints. Visible focus states use Tailwind `focus-visible:ring`.

### Blade presentation foundation (FR-003, FR-008)

New files:

```
resources/views/layouts/app.blade.php    (shared layout)
resources/views/partials/header.blade.php
resources/views/partials/footer.blade.php
```

**`layouts/app.blade.php`** — the single shared layout for all three pages
(public and portal; Filament is untouched). Structure:

- `<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">` (existing
  convention).
- `<head>`: `<meta charset>`, `<meta name="viewport">`, `<title>@yield('title', 'El Area Gym')</title>`,
  and `@vite(['resources/css/app.css', 'resources/js/app.js'])` as the single
  asset entry point (FR-003).
- `<body>`: `@include('partials.header')`, `<main id="main">@yield('content')</main>`,
  `@include('partials.footer')`.

**`partials/header.blade.php`** — `<header>` with a `skip to content` link,
branding ("El Area Gym" linking to `route('home')`), a `<nav>` adapted to auth
state via `@auth` / `@guest`:

- `@guest`: "Log in" link to `route('login')`.
- `@auth`: a small `POST` form to `route('logout')` with `@csrf` (the portal
  logout affordance; FR-007, AC-9). A mobile nav toggle may use Alpine
  (FR-009), with the nav links still reachable without JS.

**`partials/footer.blade.php`** — `<footer>` with "El Area Gym" identity.

Accessibility baseline (FR-008, AC-10, AC-11) is satisfied by the layout and
views: semantic landmarks (`header`/`nav`/`main`/`footer`), a single `h1` per
page, label/`for` association on form controls, visible `focus-visible` rings,
`aria-live="polite"` on status/error message containers, meaningful `<title>`,
and no presentation-caused horizontal overflow at narrow/wide widths.

### Views restyled

All three scoped views remove their inline `<style>` blocks (AC-3), extend the
shared layout, and set their own `<title>`.

**`welcome.blade.php`** — `@extends('layouts.app')`; keeps the `session('status')`
notice block and a visible "Log in" link to `route('login')` (AC-5). Content is
otherwise static "El Area Gym" identity copy (FR-004).

**`auth/login.blade.php`** — `@extends('layouts.app')`; the `<form>` is preserved
verbatim in behavior: `method="POST" action="{{ route('login') }}"`, `@csrf`,
inputs `email` (with `value="{{ old('email') }}"`, `required autofocus
autocomplete="username"`) and `password` (`required autocomplete="current-password"`),
associated `<label for>`, `@error` blocks, and the `session('status')` display.
Heading text "Log in" is retained (SPEC-001 `LoginTest` asserts it; AC-6).

**`portal.blade.php`** — `@extends('layouts.app')`; heading "Client portal" is
retained (SPEC-001 `AccessControlTest` asserts it). The body renders either the
profile section or the ERR-005 notice (see §6). The logout form lives in the
shared header (not duplicated in the body). No membership/payment/booking/routine
content is rendered (BR-003, AC-12).

---

## 6. Data Changes

**No migrations, tables, columns, fields, routes or API endpoints.** No business
data is created or modified.

The portal is a read-only projection of existing data (SPEC-015 §10). Field
mapping (FR-006):

| Displayed value | Source | Null handling |
| --- | --- | --- |
| Name | `$client->full_name` | NOT NULL; always resolves when a Client exists |
| DNI | `$client->dni` | NOT NULL; always resolves when a Client exists |
| Email | `$client->email` | nullable → neutral placeholder/omitted (AF-002) |
| Phone | `$client->phone` | nullable → neutral placeholder/omitted (AF-002) |
| Status | `$client->status` (`pending`/`active`/`rejected`) | technical default source (FR-006) |

Notes:

- Profile identity (`full_name`, `dni`, `phone`) is read from the `Client`
  record — the documented client profile (SPEC-002 FR-001). The `User` record
  provides authentication identity only and is not a second source. `email`
  maps to the Client contact email (nullable), which is why AF-002 contemplates
  a null email; the login email on `users` is intentionally not displayed.
- ERR-005 triggers when `$client === null` (authorized CLIENT with no linked
  `Client` record): the view renders the exact generic notice "Perfil no
  disponible. Contactá a recepción." and nothing else — no fallback identity,
  no cross-client lookup, no new lifecycle rule. Since `full_name`/`dni` are
  NOT NULL, null `email`/`phone` are AF-002 (placeholder), not ERR-005.
- Client isolation (BR-002, C-13) is guaranteed structurally: the view only ever
  receives `auth()->user()->client` for the authenticated user; there is no
  input, ID, or query that could resolve another client. The server-side
  `role:CLIENT` gate is authoritative.

Language note (presentation, not business): existing English UI strings ("Log in",
"Client portal", "Email", "Password") are retained to preserve SPEC-001 test
contracts; "El Area Gym" and the ERR-005 notice are specified verbatim in
Spanish. No i18n layer is introduced (out of scope).

---

## 7. External Integrations

None at runtime.

Node/npm is a build-time toolchain dependency (FR-001), not a runtime
integration. No CDN, external font, external framework, API client, or Mercado
Pago involvement. No new PHP dependency is added (the toolchain is npm-only).

---

## 8. Testing Strategy

Pest (PHPUnit) feature tests, following existing conventions (`tests/Pest.php`
helpers `role()`, `userWithRoles()`; `RefreshDatabase`). Extend `tests/Pest.php`
with a small `clientWithUser(...)` helper (or reuse `ClientFactory` directly)
that creates a `Client` linked to a CLIENT-role `User`.

### Node-independent test boundary (critical)

`phpunit.xml` sets `APP_ENV=testing`; the compiled build is git-ignored, so
`public/build/manifest.json` does **not** exist in CI. A view using `@vite`
would otherwise throw `ViteManifestNotFoundException`. Feature tests must
therefore render views with Laravel's native `withoutVite()` helper, which
stubs the `Vite` binding so `@vite` emits nothing without requiring Node or a
manifest.

- Add `beforeEach(fn () => $this->withoutVite());` to each new presentation
  test file.
- Add the one-line `->withoutVite()` to the three existing tests that render
  the restyled views and would otherwise hit `@vite`:
  - `tests/Feature/ExampleTest.php` (PHPUnit `GET /` → 200) — call
    `$this->withoutVite();` at the start of the method;
  - `LoginTest` "shows the login form to guests" (`GET /login` → `assertSee`);
  - `AccessControlTest` "allows a CLIENT to access the client portal"
    (`GET /portal` → `assertSee`).
  This is a non-behavioral test-infrastructure change; assertions are unchanged
  and no test is weakened (AC-13).

Feature assertions never depend on compiled asset existence. The asset entry
point is verified two ways instead: (a) a source-level test that reads
`resources/views/layouts/app.blade.php` and asserts it contains the `@vite(...)`
directive and that `welcome`/`auth.login`/`portal` each `@extends('layouts.app')`;
(b) the documented `npm run build` step and its manifest output (below), which
is a Node/CI build step, not a PHPUnit assertion.

### Feature tests

- `tests/Feature/Public/LandingPresentationTest.php`
  - `GET /` → 200; asserts shared layout artifacts ("El Area Gym" brand,
    `<header>`, `<nav>`, `<main>`, `<footer>`), a visible "Log in" link
    (`route('login')`), a `<title>`, and no inline `<style` (AC-4, AC-5, AC-14).
- `tests/Feature/Auth/LoginPresentationTest.php`
  - `GET /login` → 200; asserts the form `action`/`method`/CSRF `_token`, field
    names `email`/`password`, label association, "Log in" heading, `@error`
    containers, and no inline `<style` (AC-6).
  - Invalid login still yields the generic message and a guest (regression;
    already covered by `LoginTest`, retained).
- `tests/Feature/Portal/PortalPresentationTest.php`
  - CLIENT with a linked Client sees their own `full_name`, `dni`, `email`,
    `phone` and `status` (AC-7).
  - Isolation (AC-8, BR-002, C-13): two CLIENT fixtures; each portal response
    contains only the authenticated client's values and never the other's.
  - Null `email`/`phone` (AF-002): page renders without error, without another
    client's value, and with the neutral placeholder.
  - ERR-005 (AC-15): a CLIENT-role `User` with **no** linked `Client` sees the
    exact notice "Perfil no disponible. Contactá a recepción." and no profile
    values.
  - Logout: the page contains a `POST` form to `route('logout')` with CSRF
    (AC-9, FR-007).
  - Scope regression (AC-12): response does not contain membership/payment/
    booking/routine content markers.
- Authorization regression (AC-8, AC-13) remains in the existing
  `tests/Feature/Auth/AccessControlTest.php` (guest → redirect; staff-only →
  403; CLIENT → 200) — unchanged except the `withoutVite()` note above.

### Toolchain/build verification (Node-dependent; not part of PHPUnit)

Executed as a documented developer/CI step per SPEC-015 §11 test plan: clean
`npm install`, `npm run build` (assert `public/build/manifest.json` + hashed
assets exist and the build completes without error — AC-1), and `npm run dev`
(assert the dev/hot asset flow serves the scoped pages — AC-2). This is
environment-level verification; it must not be a dependency of `php artisan test`.

### Scope/branding regression

A source-level or rendered assertion that "El Area Gym" appears on `/` and in
the shared layout (AC-14), and that no Filament view was modified (scope
regression per SPEC-015 §12).

---

## 9. Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| First-time toolchain setup (Node/npm environment, version drift). | `npm install`/`build` may fail on the operator's machine. | Keep the pipeline minimal (Vite + Tailwind v3 + Alpine only); document the exact commands; no Node version guarantee is a product rule (SPEC-015 §12). |
| `@vite` breaks feature tests in CI (no manifest, `APP_ENV=testing`). | Existing/added tests throw `ViteManifestNotFoundException`. | `withoutVite()` test boundary (§8) + source-level `@vite` assertion; build verification is a separate Node step. |
| Restyling login/welcome/portal accidentally alters SPEC-001 behavior. | SPEC-001 contract regression (AC-13). | Views change markup only; form action/method/CSRF/fields/redirects and route/middleware untouched; existing auth/access tests preserved. |
| `bootstrap.js` still imports `axios` (scaffold default, no package). | `npm run build` fails on a missing dependency. | Remove the axios import; do not add axios (not in the declared stack, no API target). |
| Accent orange insufficient contrast if used for body text. | WCAG AA failure (AC-14). | Accent used decoratively; `brand-600/700` for text/controls; contrast documented in §5 tokens. |
| Mixed EN/ES copy (specified "El Area Gym" + ERR-005 are Spanish; existing UI strings English). | Cosmetic inconsistency. | Deliberate: existing strings retained for test contracts; no i18n introduced. Flagged, not a blocker. |
| Status display language/format unspecified. | Developer might invent a lifecycle label. | Display the raw `Client::STATUS_*` value as a technical default; no new status semantics (FR-006). |

---

## 10. Alternatives Considered

1. **Tailwind CSS v4 (CSS-first `@theme` config).** Modern default, but the
   specification explicitly asks for "styling tokens … in Tailwind config"
   (a JS config file — the v3 model) and the project sits on the Laravel 11 /
   Filament 3.2 toolchain generation. **Rejected** in favor of Tailwind v3 with
   `tailwind.config.js` + `postcss.config.js` + `autoprefixer` (smallest, most
   conventional change that satisfies the spec wording).
2. **A separate guest layout + app layout (two layouts).** Adds a second
   template for three pages with no behavioral difference. **Rejected**: one
   shared `layouts/app.blade.php` with `@auth`/`@guest` navigation is simpler
   (ARCHITECTURE §20).
3. **Blade anonymous components (`<x-layout>`, `components/`).** Idiomatic but
   not established in this repo (views are plain Blade). **Rejected** in favor
   of classic `@extends('layouts.app')` + partials to match existing style and
   keep the diff minimal.
4. **Committed build manifest so `@vite` works in tests.** Would require
   committing generated artifacts. **Rejected**: git-ignored build output is the
   Laravel default; `withoutVite()` is the sanctioned test approach.
5. **Global `beforeEach(fn () => $this->withoutVite())` on the Feature suite.**
   DRY, but broad (touches Filament feature tests). **Rejected as the default**
   in favor of per-file `beforeEach` for new presentation tests + two targeted
   one-liners; the global option remains a fallback if the Developer verifies
   Filament tests are unaffected.
6. **A `ClientPortalController` refactor or new ViewModel/Action.** Unnecessary:
   the controller only exposes the existing `User::client()` relationship.
   **Rejected** (AGENTS.md §5, §9-10).

---

## 11. Decision

Use native Laravel + npm mechanisms, minimal and presentation-only:

- **Toolchain:** `package.json` (`type: module`; scripts `dev`/`build`) with
  `vite`, `laravel-vite-plugin`, `tailwindcss` v3, `postcss`, `autoprefixer`,
  and `alpinejs` (runtime). `vite.config.js` inputs
  `resources/css/app.css` + `resources/js/app.js`; Blade loads assets via the
  single `@vite` directive in the shared layout. The axios scaffold import is
  removed (no API, no extra dependency).
- **Presentation foundation:** one shared `layouts/app.blade.php` +
  `partials/header.blade.php` + `partials/footer.blade.php`, with
  `@auth`/`@guest` navigation, "El Area Gym" branding, and `brand` orange +
  `stone` neutral tokens in `tailwind.config.js` (contrast-safe accent usage).
- **Views:** `welcome`, `auth/login`, `portal` extend the shared layout, drop
  inline CSS, and preserve the SPEC-001 form/route/redirect/validation
  contracts and the "Log in"/"Client portal" strings.
- **Portal data:** `ClientPortalController` additionally passes
  `auth()->user()->client`; the view renders the Client profile fields or the
  ERR-005 generic notice when the record is null. Read-only; no new rules.
- **Authorization:** unchanged (`role:CLIENT` gate; no new policy). Navigation
  visibility is never an authorization mechanism.
- **Tests:** Node-independent feature tests via `withoutVite()` asserting shared
  layout, assets-directive (source-level), ERR-005, isolation and SPEC-001
  regression; the `npm run build`/`dev` verification is a separate Node/CI step.
- **No migrations, no routes, no events/jobs, no new PHP dependency.**

**No ADR is required.** The notable decisions (Tailwind v3 over v4, single
shared layout, and the `withoutVite()` test boundary) are toolchain/test-design
details with clear conventional answers, documented inline above; none changes
an architectural contract (AGENTS.md §16).

---

## 12. Related Documents

- Specification: `docs/specs/SPEC-015.md`
- Requirements input: `docs/requirements/front-ux-requirements-001.md`
- Architecture: `ARCHITECTURE.md` §2, §5, §19-20
- Prior architecture: `docs/architecture/SPEC-001.md` (auth/roles/portal gate),
  `docs/architecture/SPEC-002.md` (`User::client()` / `clients.user_id`)
- ADRs: `docs/adr/ADR-001.md` (roles), `docs/adr/ADR-002.md` (Client link)
- Development rules: `AGENTS.md` §9-10, §14, §17
- Workflow state: `docs/sdd/state.yaml` (SPEC-015 `po_decisions`)
