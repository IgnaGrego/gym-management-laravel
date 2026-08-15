# SPEC-015 — Presentation Foundation & UX

## Status

Approved

This specification uses the Product Owner pre-approval recorded in
`docs/requirements/front-ux-requirements-001.md`. It is a presentation and
toolchain specification only; it does not add business behavior.

Product Owner decisions recorded in this revision:

- Gym identity is "El Area Gym", with a fitness-style color palette using
  orange as the primary accent over an accessible neutral base.
- ERR-005 is resolved: an authorized CLIENT with no usable Client record, or a
  required profile value that cannot be resolved, is shown the generic notice
  "Perfil no disponible. Contactá a recepción."
- The previously open items (Filament admin redesign, dark/light mode, landing
  "Plans" teaser, Node/npm version range) are out of scope (see §12) and are
  not blockers.

---

## 1. Objective

Provide the web presentation foundation for the public website, login page and
CLIENT portal by installing the declared Vite/Tailwind/Alpine toolchain,
introducing a shared Blade presentation foundation, restyling the existing
screens, and replacing the portal placeholder with the authenticated client's
documented profile data and account status.

Authentication, authorization, route contracts and existing business behavior
remain unchanged.

---

## 2. Actors

| Actor | Use in this specification |
| --- | --- |
| Anonymous visitor | Views `/` and `/login`. |
| Authenticated ADMIN or TRAINER | Retains the existing authenticated context; no admin-panel redesign is included. |
| Authenticated CLIENT | Views `/portal`, limited to their own profile data and status, and can log out. |
| Developer/operator | Installs dependencies and runs the documented Vite build or development commands. |

---

## 3. Preconditions

1. The Laravel application and existing SPEC-001 authentication/role behavior
   are available.
2. The existing named routes and controller contracts for `/`, `/login`,
   `/portal` and logout remain available.
3. A CLIENT who uses the portal has the client data relationship supplied by
   the completed client-management foundation (SPEC-002).
4. Node/npm are available for the frontend dependency installation. The exact
   supported Node/npm versions are not documented; this is a setup concern,
   not a product rule.

---

## 4. Functional Requirements

### FR-001 — Frontend toolchain

The project shall define a minimal npm-based frontend toolchain containing
Vite, Tailwind CSS and Alpine.js, with the Laravel Blade asset integration
required to compile and load the application assets.

### FR-002 — Build and development commands

The project shall document and support the commands `npm install`,
`npm run build` and `npm run dev`. A production build shall complete without
errors and produce the assets consumed by the Blade views.

### FR-003 — Shared Blade presentation foundation

Public and portal views shall use a shared Blade layout/foundation providing:

- consistent header and footer structure;
- gym identity/branding treatment using the approved brand name
  "El Area Gym";
- navigation appropriate to anonymous or authenticated state;
- reusable styling tokens for colors, spacing and typography, using a
  fitness-style palette with orange as the primary accent over an accessible
  neutral base;
- an asset entry point for the compiled CSS and JavaScript.

The shared foundation shall not alter the Filament admin panel in this
specification.

### FR-004 — Landing page

`/` shall be restyled using the shared foundation and shall include the
"El Area Gym" identity (fitness-orange accent over the accessible neutral
base), clear navigation, and a visible login entry point. It may remain
static; complete public-site content is not part of this specification.

### FR-005 — Login presentation

`/login` shall use the shared visual identity and responsive accessible form
presentation. The existing login method, action, CSRF protection, validation,
error display semantics and redirect behavior shall remain unchanged.

### FR-006 — CLIENT portal presentation

`/portal` shall use the shared foundation and shall display the authenticated
client's:

- name;
- DNI;
- email;
- phone;
- account status.

For this presentation specification, the existing Client record's documented
status (`pending`, `active` or `rejected`) is the technical default source for
the displayed status. This does not define new status transitions or business
meaning.

### FR-007 — Portal logout

The portal shall retain the existing POST logout flow, including CSRF
protection and the existing authentication behavior.

### FR-008 — Responsive and accessible baseline

The landing, login and portal pages shall:

- use semantic HTML landmarks and heading structure;
- remain usable at supported narrow and wide viewport widths without
  horizontal scrolling caused by the presentation;
- provide visible keyboard focus states;
- associate form labels with their controls;
- expose validation and status messages in a readable, perceivable manner;
- preserve sufficient text/control contrast according to the project's chosen
  visual tokens;
- provide meaningful document language and page titles.

### FR-009 — Progressive enhancement and asset constraints

Alpine.js may enhance presentation interactions, but the three scoped pages
shall retain their essential navigation, form submission and logout behavior
without requiring a speculative API. No external asset or frontend framework
is required by this specification; additions must not replace the declared
Blade/Tailwind/Alpine stack.

---

## 5. Business Rules

This feature introduces no new business rules.

### BR-001 — Existing authentication contract is preserved

The `/login`, logout and `/portal` authorization contracts defined by SPEC-001
remain authoritative. Presentation changes must not grant access or change
post-login routing.

### BR-002 — CLIENT data isolation is preserved

The portal may display only the authenticated client's own profile data. A
CLIENT must not receive another client's private information. Server-side
authorization and data selection remain authoritative; hiding UI elements is
not sufficient.

### BR-003 — No portal business content

Memberships, payments, bookings, routines and other portal business content are
not rendered by this specification. They belong to SPEC-013 and related
specifications.

---

## 6. Main Flow

1. A developer installs npm dependencies using the documented command.
2. The developer runs the Vite build or development command.
3. An anonymous visitor opens `/` and receives the shared responsive layout,
   landing content and login navigation.
4. The visitor opens `/login` and sees the shared responsive login form.
5. The visitor submits the existing login form; Laravel handles authentication
   using the unchanged SPEC-001 contract.
6. A CLIENT is redirected to `/portal` as before and sees the shared layout,
   their own name, DNI, email, phone and account status.
7. The CLIENT submits the existing logout form and the session ends as before.

---

## 7. Alternative Flows

### AF-001 — Non-client authenticated user

ADMIN/TRAINER post-login routing and Filament access continue to follow
SPEC-001. This specification does not apply the client portal layout to the
Filament admin panel.

### AF-002 — Optional profile fields are absent

If the client's email or phone is null, the portal shall render the profile
section without exposing a different client's value. The exact placeholder
wording is a presentation choice and must remain understandable and
accessible.

### AF-003 — Development mode

When `npm run dev` is used, Blade may consume the Vite development server
assets; when `npm run build` is used, Blade shall consume the compiled build
assets. The page behavior and route contracts are the same in both modes.

---

## 8. Error Cases

### ERR-001 — Unauthenticated portal request

Condition: an anonymous visitor requests `/portal`.

Expected behavior: the existing SPEC-001 authentication middleware behavior is
preserved; the visitor is not shown client data.

### ERR-002 — Insufficient portal role

Condition: an authenticated user without CLIENT role requests `/portal`.

Expected behavior: the existing server-side role gate denies access; no portal
profile data is rendered.

### ERR-003 — Invalid login submission

Condition: login validation or authentication fails.

Expected behavior: the existing SPEC-001 validation/error and redirect
behavior remains unchanged; restyling must not reveal additional account
information.

### ERR-004 — Missing compiled assets

Condition: a production view is served without the required compiled Vite
assets.

Expected behavior: the implementation must document the required build step
and the review must detect the broken asset setup. No alternate business flow
or CDN fallback is defined.

### ERR-005 — Client data unavailable

Condition: an authorized CLIENT has no usable linked Client record or a
required profile value cannot be resolved.

Expected behavior: the portal shall render a generic neutral notice
"Perfil no disponible. Contactá a recepción." The implementation must not
invent a fallback identity, perform a cross-client lookup, or introduce a new
account-lifecycle rule.

---

## 9. Authorization

| Operation | Anonymous | ADMIN/TRAINER | CLIENT |
| --- | --- | --- | --- |
| View `/` | Allowed | Allowed | Allowed |
| View `/login` | Allowed | Existing SPEC-001 behavior | Existing SPEC-001 behavior |
| View `/portal` | Denied | Denied unless also CLIENT, per SPEC-001 | Allowed for own data |
| View another client's portal data | Denied | Not defined by this specification | Denied |
| Submit logout | Denied as an authenticated operation | Existing authenticated flow | Allowed |
| Access Filament admin presentation | Denied | Existing SPEC-001 role rules | Denied unless also staff role |

Authorization is enforced server-side. Header/navigation visibility is not an
authorization mechanism.

---

## 10. Data Changes

No new business data, tables, fields, routes or API endpoints are required.

The portal reads existing authenticated User/Client data: name, DNI, email,
phone and the existing Client status. No profile-edit operation is introduced;
the portal is read-only for these fields in this specification.

The presentation layer may add Blade layout/partial files and frontend source
assets/configuration. These are presentation/build artifacts, not domain data.

---

## 11. Acceptance Criteria

- [ ] AC-1: `package.json` and Vite/Tailwind/Alpine configuration exist, and
  `npm install` followed by `npm run build` completes successfully.
- [ ] AC-2: `npm run dev` is documented and serves the same scoped Blade pages
  through the Vite development asset flow.
- [ ] AC-3: `/`, `/login` and `/portal` load the compiled CSS/JavaScript through
  the shared Blade foundation; none relies on the current inline page CSS.
- [ ] AC-4: The three scoped pages share consistent header/footer, branding,
  navigation, colors, spacing and typography, while the Filament admin panel
  remains outside the redesign.
- [ ] AC-5: `/` includes gym identity, clear navigation and a visible link to
  the existing login route.
- [ ] AC-6: `/login` preserves its existing form action, POST method, CSRF
  token, field names, validation/error rendering and successful redirect
  behavior while receiving the shared styling.
- [ ] AC-7: An authenticated CLIENT can access `/portal` and sees their own
  name, DNI, email, phone and existing Client account status.
- [ ] AC-8: A CLIENT cannot see another client's profile values through
  `/portal`, and a non-CLIENT cannot bypass the existing server-side portal
  role gate.
- [ ] AC-9: Portal logout remains a POST request with CSRF protection and
  terminates the session according to SPEC-001.
- [ ] AC-10: The pages are usable at narrow and wide viewport widths, have no
  presentation-caused horizontal overflow, and expose visible keyboard focus
  states.
- [ ] AC-11: Form controls have associated labels, page language/title metadata
  exists, semantic landmarks/headings are present, and status/validation text
  is perceivable.
- [ ] AC-12: Membership, payment, booking, routine and other SPEC-013 business
  content is absent from the `/portal` implementation.
- [ ] AC-13: Existing SPEC-001 authentication/authorization tests and relevant
  portal tests continue to pass after the presentation changes.
- [ ] AC-14: `/`, `/login` and `/portal` use the "El Area Gym" identity and a
  fitness-orange accent over an accessible neutral base, with sufficient
  contrast for text and controls.
- [ ] AC-15: When an authorized CLIENT has no usable linked Client record (or a
  required profile value cannot be resolved), `/portal` renders the generic
  notice "Perfil no disponible. Contactá a recepción." and does not reveal any
  other client's data or invent a fallback identity.

### Test plan

- **Toolchain/build:** install dependencies from a clean environment, run
  `npm run build`, verify the manifest/build output, and run `npm run dev` to
  verify the development asset flow.
- **Blade/view integration:** render `/`, `/login` and `/portal` and verify
  that each uses the shared layout and compiled assets, with no inline page
  stylesheet remaining.
- **Authentication regression:** exercise valid login, invalid login,
  post-login role routing, unauthenticated `/portal`, insufficient-role
  `/portal`, and logout using the existing SPEC-001 test contract.
- **Portal authorization/data isolation:** use at least two CLIENT fixtures and
  verify each portal response contains only the authenticated client's name,
  DNI, email, phone and status; verify a non-CLIENT cannot access the page.
- **Presentation/accessibility:** inspect semantic landmarks, heading order,
  labels, focus states, status/error messaging, document language/title and
  narrow/wide viewport behavior. Automated markup/accessibility checks may
  supplement, but not replace, the route and authorization tests.
- **Branding:** verify "El Area Gym" appears on `/` and the shared layout, and
  that the fitness-orange accent over the accessible neutral base is applied
  consistently across `/`, `/login` and `/portal` with sufficient contrast.
- **Client data unavailable (ERR-005):** using a CLIENT fixture with no usable
  linked Client record, verify `/portal` renders the generic notice
  "Perfil no disponible. Contactá a recepción." and no other client's data.
- **Scope regression:** verify that the portal contains no membership,
  payment, booking, routine or other SPEC-013 content and that Filament's
  presentation is unchanged.

---

## 12. Out of Scope

- Filament admin-panel visual redesign, including applying the shared
  public/client layout or branding to Filament.
- Membership, payment, booking, attendance, exercise, routine, workout or
  other client portal business content (SPEC-013 and related specifications).
- Profile editing, password change/recovery, registration, email verification,
  notifications or new account lifecycle behavior.
- Complete public website content, including a landing page "Plans" teaser
  section, public plan listing, public registration or online payment.
- Changes to authentication, authorization, route names, redirects, business
  rules or existing SPEC-001..014 contracts.
- Any API, mobile UI, multi-tenancy or multi-location presentation.
- Dark/light mode, theme switching, or a documented dark-mode design.
- A documented or guaranteed Node/npm version range; this is an environment
  setup concern, not a product rule.
- External asset/CDN dependency or a new frontend framework.

---

## 13. Dependencies

- `docs/requirements/front-ux-requirements-001.md` — Product Owner input and
  exact scope.
- SPEC-001 — completed authentication, roles, route and portal authorization
  contracts.
- SPEC-002 — completed Client/User relationship and profile fields.
- Laravel Blade, Vite, Tailwind CSS and Alpine.js declared by README and
  `ARCHITECTURE.md`.
- Node/npm environment capable of installing and building the declared
  frontend dependencies.

---

## 14. Open Questions

No open questions remain that block this specification. The previously flagged
items are resolved as follows:

1. **Brand identity** — resolved: gym name "El Area Gym"; fitness-style palette
   with orange as the primary accent over an accessible neutral base
   (FR-003, FR-004).
2. **Authorized CLIENT with no usable Client record** — resolved: `/portal`
   renders the generic neutral notice "Perfil no disponible. Contactá a
   recepción." (ERR-005). The Client-status vs User-activation-status
   distinction remains a technical display default (FR-006) with no new
   business interpretation.
3. **Filament admin redesign, dark/light mode, landing "Plans" teaser,
   Node/npm version range** — out of scope (see §12), not blockers.

---

## 15. Related Documents

- `AGENTS.md`
- `README.md`
- `ARCHITECTURE.md`
- `docs/requirements/front-ux-requirements-001.md`
- `docs/product/product-definition-v0.1.md`
- `docs/domain/domain-model-v0.1.md`
- `docs/workflow/analyst.md`
- `docs/specs/SPEC-001.md`
- `docs/specs/SPEC-002.md`
- `docs/specs/_TEMPLATE.md`
