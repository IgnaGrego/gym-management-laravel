# SPEC-016 — Spanish Localization ("El Area Gym")

## Status

Draft

---

## 1. Objective

Translate the user-facing presentation of "El Area Gym" to Spanish. Today the
system mixes English UI copy with a few already-Spanish strings: the public
site and portal headings/labels are English, Laravel's built-in validation and
authentication messages are English, the Filament admin panel chrome is English,
and the Filament resources use hardcoded English labels, navigation groups,
section headings, action labels, filter options and status text.

This specification makes Spanish the default presentation language across the
public website, the CLIENT portal and the Filament admin panel, while preserving
all business behavior, data and authorization contracts exactly as they are.

This is a **presentation/localization change only**. No business rule is added,
changed or removed. No route, authorization, validation rule, state transition,
data or schema is altered.

This specification resolves the previously flagged "mixed EN/ES copy" cosmetic
inconsistency recorded in `docs/architecture/SPEC-015.md` (which deliberately
deferred i18n and left existing English strings in place for test contracts).

---

## 2. Actors

| Actor | Use in this specification |
| --- | --- |
| Anonymous visitor | Reads the Spanish landing page and login/registration pages. |
| Authenticated CLIENT | Reads the Spanish client portal. |
| Authenticated ADMIN / TRAINER | Reads the Spanish Filament admin panel. |
| Developer/operator | Applies locale configuration, translation files and string changes, and updates presentation-copy tests. Operator-facing console/seed output is out of scope (see §12). |

Authorization roles are unchanged (SPEC-001). Localization does not introduce a
new actor or role.

---

## 3. Preconditions

1. SPEC-001..SPEC-015 are implemented; their route, authorization, validation
   and state contracts are authoritative and unchanged by this specification.
2. SPEC-015 has provided the public/portal presentation base (shared Blade
   layout, "El Area Gym" brand, Spanish ERR-005 notice) on which the Spanish
   copy is applied.
3. Laravel 11 and Filament 3.2 are installed. Filament 3.2 ships built-in
   Spanish (`es`) translations for its own chrome under
   `vendor/filament/*/resources/lang/es`; they are resolved through Laravel's
   translator against the application locale. No Filament translation package
   needs to be installed.
4. The application has no `lang/` directory today; Laravel's built-in
   validation/auth/pagination/password messages currently resolve to English
   (framework defaults under `vendor/laravel/framework/src/Illuminate/Translation/lang/en`).

---

## 4. Functional Requirements

### FR-001 — Spanish default locale

The application default locale shall be `es`. `fallback_locale` shall remain a
safe fallback that never surfaces a raw translation key; the recommended value
is `en` (see OQ-04). `faker_locale` shall become a Spanish locale (recommended
`es_ES`) for generated test/seed data.

The change shall be applied consistently so it survives a clean environment:
- the `locale`/`fallback_locale`/`faker_locale` defaults in `config/app.php`,
- the corresponding `APP_LOCALE` / `APP_FALLBACK_LOCALE` / `APP_FAKER_LOCALE`
  values in `.env.example`.

Because Filament resolves its own chrome (buttons, dashboard, pagination,
filters, search, unsaved-changes alert, global search, account widget) through
the Laravel locale, setting the app locale to `es` is sufficient to make
Filament's built-in chrome Spanish — no extra package and no published
overrides are required.

### FR-002 — Spanish Laravel message catalogs

Create a `lang/es/` directory containing Spanish translations of Laravel's
four standard catalogs so built-in messages render in Spanish:

- `lang/es/validation.php`
- `lang/es/auth.php`
- `lang/es/passwords.php`
- `lang/es/pagination.php`

The catalogs shall translate at least the keys the application actually relies
on (e.g. `auth.failed`, standard validation rule messages such as `required`,
`email`, `min`, `max`, `unique`, `confirmed`, `date`, `numeric`, `integer`,
`in`, `before_or_equal`, `after_or_equal`, `after`, `exists`, `prohibits`,
`required_without`, `required_if`, and the pagination labels `previous`/`next`/
`Showing`/`to`/`of`/`results`). A complete translation of the standard Laravel
catalog is preferred. No third-party package is required (see BR-004, OQ-03).

### FR-003 — Filament resource copy in Spanish

All hardcoded English strings in `app/Filament/Resources/**` shall be
translated to Spanish, including:

- `$navigationLabel` / `$navigationGroup` (e.g. "Users"→"Usuarios",
  "Administration"→"Administración", "Commercial"→"Comercial",
  "Training"→"Entrenamiento", "Scheduling"→"Agenda", "Bookings"→"Reservas",
  "Attendance"→"Asistencia");
- explicit singular/plural model labels so page titles, headings, breadcrumbs
  and delete/notification wording are Spanish even for resources that currently
  derive labels from the English class names (e.g. "WorkoutLog"→"Registro de
  entrenamiento");
- form/infolist `Section` headings (e.g. "Identity"→"Identidad",
  "Contact"→"Contacto", "Health notes"→"Notas de salud",
  "Linked account"→"Cuenta vinculada", "Current routine"→"Rutina actual",
  "Offer"→"Oferta", "Membership"→"Membresía", "Payment"→"Pago",
  "Check-in"→"Registro de ingreso", "Booking"→"Reserva", "Exercise"→"Ejercicio",
  "Routine"→"Rutina", "Days and sets"→"Días y series",
  "Version history"→"Historial de versiones", "Workout log"→"Registro de
  entrenamiento");
- field/column/filter `label()` values (e.g. "Duration (days)"→"Duración
  (días)", "Start date"→"Fecha de inicio", "Start time"→"Hora de inicio",
  "End time"→"Hora de fin", "Capacity limit"→"Cupo máximo", "Occupancy"→
  "Ocupación", "Muscle group"→"Grupo muscular", "Reference type"→"Tipo de
  referencia", "Prescribed set"→"Serie prescrita", "Actual weight (kg)"→"Peso
  real (kg)", "Actual reps"→"Repeticiones reales", "Target weight (kg)"→"Peso
  objetivo (kg)", "Target reps"→"Repeticiones objetivo", "Rest (seconds)"→
  "Descanso (segundos)", "Recorded by"→"Registrado por", "Booked by"→"Reservado
  por", "Logged at"→"Registrado el", "Created by"→"Creado por",
  "Period start/end"→"Inicio/Fin del período", "Reference"→"Referencia",
  "Payment date"→"Fecha de pago");
- action labels (e.g. "Approve"→"Aprobar", "Reject"→"Rechazar",
  "Deactivate"→"Desactivar", "Activate"→"Activar", "Reactivate"→"Reactivar",
  "Cancel"→"Cancelar", "Renew"→"Renovar", "Edit amount"→"Editar monto",
  "Unassign"→"Desasignar");
- relation-manager `$title` values (e.g. "Payments"→"Pagos",
  "Memberships"→"Membresías", "Routines"→"Rutinas",
  "Assigned clients"→"Clientes asignados");
- page-level titles/labels (`ClientProgress` `$navigationLabel` "Client
  progress"→"Progreso del cliente" and its `getTitle()` "Progress — {name}"→
  "Progreso — {name}");
- the role display options in `UserResource::roleOptions()` ("Admin"→
  "Administrador", "Trainer"→"Entrenador", "Client"→"Cliente");
- the access-gate denial and decision strings in `AttendanceResource`
  (`denialMessage()`, `accessDecisionText()`), the `routineExerciseHint()` and
  `routineExerciseLabel()` strings in `WorkoutLogResource`, and the closure
  `$fail()` message in `RoutineResource`.

The exact Spanish wording is a presentation choice (a representative glossary is
provided in §16); it is not a business rule and may be finalized by the
Developer/Reviewer, but the resulting copy must be understandable Spanish and
must not alter the information conveyed.

### FR-004 — Public and portal Blade views in Spanish

All hardcoded English strings in `resources/views/**` shall be translated to
Spanish, including:

- `welcome.blade.php` ("Welcome to the gym management system."→Spanish;
  "Log in"→"Iniciar sesión");
- `auth/login.blade.php` (title "Log in - El Area Gym"→"Iniciar sesión - El Area
  Gym", heading "Log in"→"Iniciar sesión", "Email"→"Email"/"Correo
  electrónico", "Password"→"Contraseña", submit "Log in"→"Iniciar sesión");
- `auth/register.blade.php` and `auth/registration-complete.blade.php` (title
  "Register - Gym Management"→Spanish, "Create an account"→"Crear una cuenta",
  field labels, "Register"→"Registrarse", "Already have an account? Log in"→
  Spanish, "Registration received"→"Registro recibido", "Staff will review…"→
  Spanish, "Go to login"→Spanish);
- `partials/header.blade.php` ("Skip to content"→"Saltar al contenido",
  `aria-label="Primary"`→"Principal", "Log out"→"Cerrar sesión", "Log in"→
  "Iniciar sesión");
- `partials/portal-nav.blade.php` (all link labels and the `aria-label="Portal"`
  value);
- `portal.blade.php` and `portal/*.blade.php` (heading "Client portal"→"Portal
  del cliente", profile labels "Name"/"Email"/"Phone"/"Status"→Spanish, "Not
  provided"→"No informado", the empty-state messages, status prefixes
  ("Status:"→"Estado:"), "days"→"días", "spots left"→"cupos restantes", "Book
  this turno"→"Reservar este turno", "Cancel booking"→"Cancelar reserva", the
  workout-log form labels and "Save workout"→"Guardar entrenamiento",
  "History"→"Historial", "Health notes"/"Injuries"/"Medical conditions"/"None"→
  Spanish, "Edit contact details"→"Editar datos de contacto", "Save profile"→
  "Guardar perfil").

### FR-005 — User-facing PHP strings in Spanish

All user-facing strings emitted from PHP shall be translated to Spanish,
including:

- the post-login flash message in `AuthenticatedSessionController`
  ("Your account has no assigned roles yet. Contact an administrator."→
  "Tu cuenta aún no tiene roles asignados. Contactá a un administrador.");
- the `DomainException` messages surfaced to users (via Filament notifications,
  `ValidationException::withMessages`, or the portal's `$e->getMessage()`
  error pass-through) in `Client`, `Membership`, `Cuota`, `Booking`, `Turno`,
  `Routine`, `RoutineAssignment` and the `App\Actions\*` classes (e.g.
  "Only a pending membership can be activated."→"Solo una membresía pendiente
  puede activarse.");
- the closure `$fail()` validation messages in `WorkoutLog` ("This set belongs
  to a routine version the client has never been assigned to."→Spanish, "A free
  log can only reference an active exercise."→Spanish).

The already-Spanish portal flash messages ("Tu turno fue reservado.",
"Tu reserva fue cancelada.", "Tu entrenamiento fue registrado.", "Tu perfil fue
actualizado.") and the SPEC-015 notice ("Perfil no disponible. Contactá a
recepción.") remain unchanged.

### FR-006 — Display-only translation of stored statuses and enums

Status and enum values that are stored as fixed identifiers shall be translated
in **display only**; the stored values remain unchanged (see BR-003). This
applies to:

- membership status `pending`/`active`/`expired`/`cancelled`;
- client status `pending`/`active`/`rejected`;
- cuota status `pending`/`paid`/`cancelled`;
- payment status `pending`/`confirmed`/`failed`;
- booking status `confirmed`/`cancelled`;
- turno status `active`/`inactive`/`cancelled`;
- routine status `draft`/`active`/`archived`;
- payment method `cash`/`transfer`;
- exercise muscle-group identifiers (`chest`, `back`, …) and difficulty
  identifiers (`beginner`/`intermediate`/`advanced`);
- workout reference type `routine`/`free`.

Where the current code renders a status with `ucfirst($state)` (e.g. membership,
cuota, payment, booking, turno, routine badges) or via the `Exercise`
`muscleGroupLabels()`/`difficultyLabels()` helpers, the display layer shall
produce Spanish labels. The model constants and database values must remain
identical (see the representative glossary in §16).

### FR-007 — Admin panel brand in Spanish (application name)

The Filament admin panel brand/document title currently resolves to
`config('app.name')` whose default is "Laravel". The application name shall be
set to "El Area Gym" so the admin panel brand and any framework-generated title
show the gym name instead of the framework default (presentation-only; see
OQ-07). This is the same "El Area Gym" identity already adopted in SPEC-015.

### FR-008 — Test contract update

Existing tests that assert on English presentation copy that this specification
legitimately changes shall be updated to assert on the corresponding Spanish
copy. The tests affected are those asserting UI copy such as "Log in",
"Client portal", "These credentials do not match our records.", "Your account
has no assigned roles yet…", the Filament "Active"/"Inactive"/"Pending"/
"Confirmed"/"Qualified"/"has no membership" strings, the `Exercise`
"Shoulders"/"Beginner" labels, and the `toThrow(DomainException::class,
'…')` message strings. Business-logic assertions (policies, authorization,
state transitions, data isolation, route contracts) shall continue to assert
the same business outcomes and must keep passing unchanged (BR-005).

---

## 5. Business Rules

This specification introduces **no new business rules**. The following rules
constrain the change so it cannot drift into business or data changes.

### BR-001 — No business-logic change

Authentication, authorization, validation rules, state transitions, routes,
redirects, policies, actions and all existing business behavior remain exactly
as defined by SPEC-001..SPEC-015. Localization changes only the human-readable
strings, never the conditions under which they are shown.

### BR-002 — No data or schema change

No migration, no new table/column/index, no stored-data rewrite and no seeder
data change is introduced. Localization does not modify persisted values.

### BR-003 — Stored identifiers and enum values unchanged

Role slugs (`admin`/`trainer`/`client`), status values, payment methods, muscle
groups, difficulties and reference types remain their existing fixed English
identifiers in the database and in code constants. Only their display labels are
translated (FR-006).

### BR-004 — No new dependency

No third-party package is added. Laravel's own `lang/es` catalogs are authored
directly (FR-002) and Filament's built-in `es` translations are used for its
chrome (FR-001). If a dependency were ever considered, AGENTS.md §14 requires a
written justification first.

### BR-005 — Tests are updated, not weakened

Existing tests may be changed only where they assert presentation copy that this
specification changes (FR-008). No test is deleted or relaxed to mask a
regression, and no business-logic assertion is altered. The complete test suite
must pass after the change.

### BR-006 — Server-side authorization remains authoritative

Localization is cosmetic. Header/navigation/label changes are never relied on as
an authorization mechanism; the SPEC-001..SPEC-015 server-side gates remain the
source of truth.

---

## 6. Main Flow

1. A developer sets the default locale to `es` (config defaults + `.env.example`)
   with a safe fallback, and creates `lang/es/` with the four Laravel catalogs.
2. The developer translates the hardcoded English strings in the Filament
   resources, relation managers, pages, public/portal Blade views and the
   user-facing PHP strings (flash/exception/validation messages).
3. The developer translates the display-only statuses/enums (badges, filters,
   option labels) while leaving stored values untouched.
4. The developer sets the application name to "El Area Gym" (FR-007).
5. The developer updates the tests that assert English presentation copy to
   assert Spanish, and runs the full test suite.
6. An anonymous visitor, a CLIENT and an ADMIN/TRAINER each see Spanish UI
   across the landing, login/registration, portal and admin panel; business
   behavior and authorization are unchanged.

---

## 7. Alternative Flows

### AF-001 — A string is missed and left in English

A hardcoded English string that is not updated remains English. A Laravel
translation key that is missing from `lang/es` falls back to the fallback
locale (`en`) rather than throwing. The review must detect remaining English
UI copy as a defect (see ERR-003). No runtime error occurs.

### AF-002 — Non-Spanish locale requested

The application does not offer a locale switcher (OQ-01). If `APP_LOCALE` is
set to an unsupported value in a given environment, Laravel/Filament fall back
per `fallback_locale`; this is an environment concern, not a supported user
feature.

### AF-003 — Locale not overridden in an existing `.env`

An already-existing local `.env` may still contain `APP_LOCALE=en` and override
the config default. The config default change (FR-001) plus `.env.example`
documents the intended Spanish value; the effective locale still follows the
environment. This is an environment setup concern, not a product rule.

---

## 8. Error Cases

### ERR-001 — Missing translation key

Condition: a Laravel/Filament translation key has no `es` entry.

Expected behavior: the key resolves to the fallback locale (`en`), never to the
raw key, and never raises an exception. Review treats a raw-key leak as a defect.

### ERR-002 — Unsupported locale

Condition: the effective locale is a value with no translation files.

Expected behavior: Laravel/Filament fall back per `fallback_locale`; no crash.

### ERR-003 — Regression detected by a test

Condition: a translation changes a string that a still-English test asserts on,
or a translation is applied inconsistently.

Expected behavior: the relevant test fails, signaling that the test copy (or the
translation) must be reconciled — the failure must not be silenced by deleting
or relaxing the test (BR-005).

---

## 9. Authorization

No authorization change is introduced. Localization applies uniformly to all
roles and to anonymous visitors; the SPEC-001..SPEC-015 authorization matrix
remains authoritative and is enforced server-side. There is no operation that
localization grants or denies.

---

## 10. Data Changes

No business data, tables, fields, routes or API endpoints are created, modified
or deleted.

The implementation changes only:
- locale configuration (`config/app.php`, `.env.example`);
- new `lang/es/` translation files;
- presentation strings in Filament resources/relation managers/pages, Blade
  views and user-facing PHP strings;
- tests asserting presentation copy.

Stored values (role slugs, statuses, methods, muscle groups, difficulties,
reference types) are unchanged (BR-003).

---

## 11. Acceptance Criteria

- [ ] AC-1: `config/app.php` defaults to `locale = 'es'` and a safe
  `fallback_locale` (recommended `en`); `.env.example` documents
  `APP_LOCALE=es` and the matching fallback/faker values.
- [ ] AC-2: `lang/es/` contains `validation.php`, `auth.php`, `passwords.php`
  and `pagination.php`; `trans('auth.failed')` and at least one standard
  validation message resolve to Spanish text (not the key and not English).
- [ ] AC-3: The login page shows Spanish copy — e.g. "Iniciar sesión" and a
  Spanish email/password labels — and does not show "Log in".
- [ ] AC-4: A failed login renders a Spanish credential error (not "These
  credentials do not match our records."), and a failed validation renders a
  Spanish validation message.
- [ ] AC-5: The landing page ("Bienvenido…" instead of "Welcome to the gym
  management system."), the header ("Cerrar sesión" instead of "Log out") and
  the portal navigation/headings (e.g. "Portal del cliente", "Resumen",
  "Membresías", "Pagos", "Asistencia", "Reservas", "Rutina", "Entrenamientos",
  "Perfil") are Spanish.
- [ ] AC-6: The Filament navigation shows Spanish resource labels and groups
  (e.g. "Usuarios", "Clientes", "Planes", "Membresías", "Pagos", "Cuotas",
  "Turnos", "Reservas", "Asistencia", "Ejercicios", "Rutinas", "Registros de
  entrenamiento", and groups "Administración", "Comercial", "Entrenamiento",
  "Agenda", "Reservas", "Asistencia").
- [ ] AC-7: Filament's built-in chrome (create/edit/delete/save/cancel buttons,
  pagination, search, filters, column toggle, unsaved-changes alert, dashboard
  and account widget) renders in Spanish, without a third-party package.
- [ ] AC-8: Status badges, filter options and enum/method displays show Spanish
  (e.g. "Pendiente", "Activo/Activa", "Vencida", "Cancelada", "Confirmada",
  "Pagada", "Borrador", "Archivada", "Efectivo", "Transferencia bancaria",
  "Pecho", "Principiante") while the stored database values remain the fixed
  English identifiers.
- [ ] AC-9: The admin panel brand/title reads "El Area Gym" (not "Laravel").
- [ ] AC-10: No migration or schema change exists; role slugs and stored enum
  values are byte-for-byte unchanged.
- [ ] AC-11: The complete test suite passes — business-logic, policy,
  authorization and data-isolation tests pass unchanged, and the tests that
  assert presentation copy have been updated to Spanish (BR-005).
- [ ] AC-12: No route name, redirect, validation rule or state-transition
  behavior changed (existing SPEC-001..SPEC-015 tests still pass).

### Test plan

- **Locale/config:** assert the effective application locale is `es` and the
  fallback is configured; assert `.env.example` documents the Spanish values.
- **Laravel catalogs:** assert `trans('auth.failed')`, a representative
  validation message, a pagination label and a password message resolve to
  Spanish and that a deliberately missing key falls back to English without
  error (ERR-001).
- **Public/portal copy:** render `/`, `/login`, the registration pages and each
  `/portal/*` section and assert the representative Spanish strings replace the
  English ones (AC-3, AC-5). Update `LoginPresentationTest`,
  `LandingPresentationTest`, `AccessControlTest` and the portal presentation
  tests accordingly.
- **Filament copy:** open the admin panel as ADMIN/TRAINER and assert the
  representative Spanish resource labels, navigation groups, action labels and
  built-in chrome (AC-6, AC-7). Update the Filament management tests that assert
  English status/label copy (`AttendanceManagementTest`, `ExerciseManagementTest`,
  `MembershipManagementTest`, `PlanManagementTest`, `PaymentManagementTest`,
  `CuotaManagementTest`, `BookingManagementTest`, `TurnoManagementTest`,
  `ClientManagementTest`, `ClientProvisioningTest`, etc.).
- **Display-only statuses:** assert a record whose stored status is
  `pending`/`active`/etc. displays the Spanish label while the raw column value
  is unchanged (AC-8, BR-003).
- **Exception/validation messages:** assert the translated `DomainException` /
  `ValidationException` / `$fail()` messages surface in Spanish; update the
  `toThrow(..., '…')` and `assertSee('…')` assertions that pin the old English
  text (FR-005).
- **No-regression gate:** run the full suite (`php artisan test`) and confirm all
  business-logic, policy, authorization and isolation tests pass (AC-11, AC-12).

---

## 12. Out of Scope

- A bilingual/language selector, per-user language preference, or any runtime
  locale switching (OQ-01; recommended out of scope).
- Any change to authentication, authorization, routes, redirects, validation
  rules, business rules, state transitions, policies or data (SPEC-001..SPEC-015
  contracts).
- Any migration, schema change or stored-data change (BR-002, BR-003).
- Changing stored enum/status/role/method/muscle-group/difficulty values
  (display translation only, BR-003).
- New dependencies or packages (BR-004).
- Translating PHP code identifiers, class/table/column names, comments or
  PHPDoc.
- Operator-facing output such as console command `$description` strings
  (e.g. `memberships:expire`) and seeder error messages (OQ-08; recommended out
  of scope, non-user-facing).
- Number/date/currency format localization (decimal separators, `Y-m-d` vs
  localized dates, currency symbols). Existing formats are preserved to avoid
  regressing tests and because no such requirement was raised.
- Filament admin visual redesign, dark/light mode, or the shared public/portal
  layout applied to Filament (already out of scope per SPEC-015 §12).
- Automatic translation of free-text user data (names, notes, descriptions,
  exercise names, instructions). These are user-authored content, not UI copy.

---

## 13. Dependencies

- SPEC-015 — completed presentation foundation and the "El Area Gym" brand that
  this localization completes.
- SPEC-001..SPEC-013 — the business contracts whose UI copy is translated; their
  strings and tests are the source of the changes in FR-003..FR-005 and FR-008.
- Laravel 11 — built-in `lang/es` convention and English framework defaults.
- Filament 3.2 — ships built-in `es` translations for its chrome
  (`vendor/filament/*/resources/lang/es`).

---

## 14. Open Questions

Each open question records the question, why it matters, the possible
interpretations, and the recommended interpretation. Recommendations are the
technical defaults the Analyst proposes; they are not business rules and remain
subject to Product Owner confirmation.

### OQ-01 — Fixed Spanish-only vs. a bilingual language switcher

- **Question:** Should the system be fixed Spanish, or offer a language switcher
  (e.g. ES/EN)?
- **Why it matters:** A switcher adds UI, state (session/cookie), dual
  maintenance of every string and broader test surface. The PO asked only to
  translate to Spanish.
- **Possible interpretations:** (a) fixed Spanish, no selector; (b) Spanish
  default with an optional EN/ES selector.
- **Recommended:** (a) fixed Spanish, no selector. Simple, matches the PO's
  request, and keeps this a presentation-only change. (Adopted as the working
  assumption for FR-001/FR-002/§12.)

### OQ-02 — Filament admin labels: explicit resource labels vs. relying on a Filament Spanish package

- **Question:** How should Filament's app-specific labels (navigation labels,
  groups, headings, actions) be translated?
- **Why it matters:** Filament ships `es` translations for its *own* chrome, but
  the project's resource labels/headings/actions are hardcoded English in the
  resource classes and must be translated explicitly; they are not covered by
  any package.
- **Possible interpretations:** (a) set explicit Spanish `$navigationLabel`/
  `$modelLabel`/`$pluralModelLabel`/`$navigationGroup`/`->label()` and rely on
  Filament's built-in `es` for the chrome; (b) install a Filament Spanish
  translation package and hope it covers app strings (it does not).
- **Recommended:** (a) explicit Spanish labels in the resources plus Filament's
  built-in `es` chrome via the app locale. No extra package. (FR-001, FR-003.)

### OQ-03 — How to handle Laravel's built-in auth/validation strings

- **Question:** Where should Spanish Laravel validation/auth/pagination/password
  messages come from?
- **Why it matters:** These messages (login errors, form validation, pagination)
  are currently English framework defaults; there is no `lang/` directory yet.
- **Possible interpretations:** (a) author `lang/es/{validation,auth,passwords,
  pagination}.php` directly; (b) `php artisan lang:publish` then translate the
  English base; (c) install a community package (e.g. `laravel-lang/lang`).
- **Recommended:** (a) author the four `lang/es` files directly (publishing the
  English base first is an acceptable convenience step). Avoid a new dependency
  (BR-004). (FR-002.)

### OQ-04 — Locale value and fallback

- **Question:** What locale code should be used (`es` vs `es_ES`) and what
  fallback?
- **Why it matters:** Filament ships the locale as `es` (not `es_ES`), so `es`
  matches Filament's catalog; the fallback must not leak raw keys.
- **Possible interpretations:** (a) locale `es`, fallback `en`, faker `es_ES`;
  (b) locale `es_ES`, fallback `es`; (c) locale `es`, fallback `es`.
- **Recommended:** (a) locale `es`, fallback `en`, faker `es_ES`. `es` matches
  Filament's shipped catalog; `en` fallback prevents raw-key leaks for any missed
  key. (FR-001.)

### OQ-05 — Translate user-facing error/flash/exception strings

- **Question:** Should the user-facing PHP strings — the post-login flash
  message, `DomainException`/`ValidationException` messages and closure `$fail()`
  messages — be translated, or treated as internal English?
- **Why it matters:** These strings are shown to users (Filament notifications,
  validation errors, and the portal's `$e->getMessage()` pass-through). Leaving
  them English would keep mixed EN/ES copy. Translating them changes strings
  that a few tests pin.
- **Possible interpretations:** (a) translate them in place (single source of
  truth) and update the tests that pin the exact message; (b) keep them English
  as "internal" and map to Spanish only at the UI boundary (more machinery);
  (c) leave them English.
- **Recommended:** (a) translate in place and update the pinned tests. Keeps one
  source of truth with the least machinery. (FR-005, FR-008.)

### OQ-06 — Stored values vs. display translation

- **Question:** Should statuses/enums stored as English identifiers (roles,
  membership/client/cuota/payment/booking/turno/routine statuses, payment
  methods, muscle groups, difficulties, reference types) be translated in the
  database, or only in display?
- **Why it matters:** Translating stored values would be a data/business change
  and would invalidate existing code constants, validation rules and data.
- **Possible interpretations:** (a) translate display only, stored values
  unchanged; (b) migrate stored values to Spanish (rejected: data/business
  change, outside this spec's scope).
- **Recommended:** (a) display-only translation; stored values unchanged. This is
  recorded as an assumption (BR-003, FR-006).

### OQ-07 — Application name / Filament brand

- **Question:** The Filament brand and document title currently resolve to
  `config('app.name')` default "Laravel". Should it be set to "El Area Gym"?
- **Why it matters:** Leaving "Laravel" shows a framework default as the product
  brand in the admin panel, inconsistent with the SPEC-015 "El Area Gym" identity.
- **Possible interpretations:** (a) set the application name to "El Area Gym"
  (presentation-only; also affects any framework title/notification "from" name);
  (b) leave "Laravel".
- **Recommended:** (a) set it to "El Area Gym". (FR-007.)

### OQ-08 — Operator-facing console/seed strings

- **Question:** Should operator-facing strings (console command `$description`
  such as `memberships:expire`, and the `AdminUserSeeder` `RuntimeException`
  message) be translated?
- **Why it matters:** These are developer/operator-facing, not end-user-facing;
  translating them adds no user value and touches non-UI code.
- **Possible interpretations:** (a) leave in English (out of scope); (b)
  translate them.
- **Recommended:** (a) leave in English, out of scope. Non-blocking. (§12.)

---

## 15. Related Documents

- `AGENTS.md`
- `README.md`
- `ARCHITECTURE.md`
- `docs/requirements/front-ux-requirements-001.md`
- `docs/product/product-definition-v0.1.md`
- `docs/domain/domain-model-v0.1.md`
- `docs/workflow/analyst.md`
- `docs/architecture/SPEC-015.md` (records the "mixed EN/ES copy" flag this spec resolves)
- `docs/specs/SPEC-001.md` … `docs/specs/SPEC-015.md`
- `docs/specs/_TEMPLATE.md`

---

## 16. Appendix — Representative Spanish glossary (presentation guidance)

The following is **presentation guidance** for the Developer/Reviewer, not a
business rule. Exact wording may be finalized during implementation/review, but
must remain understandable Spanish and preserve the original meaning.

| English | Recommended Spanish |
| --- | --- |
| Log in | Iniciar sesión |
| Log out | Cerrar sesión |
| Email / Password | Email (o "Correo electrónico") / Contraseña |
| Client portal | Portal del cliente |
| Overview / Profile | Resumen / Perfil |
| Memberships / Payments / Attendance / Bookings / Routine / Workouts | Membresías / Pagos / Asistencia / Reservas / Rutina / Entrenamientos |
| Users / Clients / Plans / Exercises / Workout Logs | Usuarios / Clientes / Planes / Ejercicios / Registros de entrenamiento |
| Administration / Commercial / Training / Scheduling | Administración / Comercial / Entrenamiento / Agenda |
| pending / active / expired / cancelled | pendiente / activo(-a) / vencido(-a) / cancelado(-a) |
| confirmed / failed / paid / draft / archived / rejected / inactive | confirmado(-a) / fallido / pagado(-a) / borrador / archivado(-a) / rechazado(-a) / inactivo(-a) |
| Cash / Bank transfer | Efectivo / Transferencia bancaria |
| chest / back / shoulders / biceps / triceps / forearms / abs / quadriceps / hamstrings / glutes / calves / full_body | Pecho / Espalda / Hombros / Bíceps / Tríceps / Antebrazos / Abdominales / Cuádriceps / Isquiotibiales / Glúteos / Gemelos / Cuerpo completo |
| beginner / intermediate / advanced | Principiante / Intermedio / Avanzado |
| From assigned routine / Free exercise / Bodyweight | Desde rutina asignada / Ejercicio libre / Peso corporal |
| Approve / Reject / Deactivate / Activate / Reactivate / Cancel / Renew / Unassign / Edit amount | Aprobar / Rechazar / Desactivar / Activar / Reactivar / Cancelar / Renovar / Desasignar / Editar monto |
| Duration (days) / Start date / Start time / End time / Capacity limit / Occupancy | Duración (días) / Fecha de inicio / Hora de inicio / Hora de fin / Cupo máximo / Ocupación |
| Muscle group / Status / Reference / Payment date / Reference type / Prescribed set | Grupo muscular / Estado / Referencia / Fecha de pago / Tipo de referencia / Serie prescrita |
| Actual weight (kg) / Actual reps / Target weight (kg) / Target reps / Rest (seconds) | Peso real (kg) / Repeticiones reales / Peso objetivo (kg) / Repeticiones objetivo / Descanso (segundos) |
| Recorded by / Booked by / Logged at / Created by / Period start / Period end | Registrado por / Reservado por / Registrado el / Creado por / Inicio del período / Fin del período |
| Name / Phone / Status / Not provided / No account / None | Nombre / Teléfono / Estado / No informado / Sin cuenta / Ninguna |
| Health notes / Injuries / Medical conditions / Edit contact details / Emergency contact / Save profile | Notas de salud / Lesiones / Condiciones médicas / Editar datos de contacto / Contacto de emergencia / Guardar perfil |
| These credentials do not match our records. | Estas credenciales no coinciden con nuestros registros. |
| Your account has no assigned roles yet. Contact an administrator. | Tu cuenta aún no tiene roles asignados. Contactá a un administrador. |
