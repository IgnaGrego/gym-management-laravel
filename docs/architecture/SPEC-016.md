# Architecture — SPEC-016

## 1. Feature

Spanish Localization ("El Area Gym"): translate the user-facing presentation
of the public website, the CLIENT portal and the Filament admin panel to
Spanish, without changing any business rule, authorization contract, route,
validation rule, state transition, stored identifier or schema value.

---

## 2. Specification

Reference:

`docs/specs/SPEC-016.md`

PO decisions adopted (already recorded in the spec, OQ-01..OQ-08):

- Fixed Spanish; **no** bilingual switcher, no per-user locale, no runtime switching.
- Filament app labels translated explicitly in resources; Filament's own chrome
  (`es`) comes from the app locale, **no package**.
- `lang/es/{validation,auth,passwords,pagination}.php` authored directly, **no package**.
- `locale = es`, `fallback_locale = en`, `faker_locale = es_ES`.
- Error/flash/exception strings translated **in place**; pinned tests updated.
- Statuses/enums translated **display-only**; stored identifiers unchanged.
- Brand name "El Area Gym" (application name).
- Console command descriptions and seeder messages remain English (out of scope).

---

## 3. Affected Modules

- **Locale/config** — `config/app.php`, `.env.example`.
- **Laravel message catalogs** — new `lang/es/` (validation, auth, passwords, pagination).
- **Filament (Administration + Trainers)** — all 12 resources, their Pages, 4
  Relation Managers, the `ClientProgress` custom page, and the panel brand.
- **Public website + Client portal** — Blade views under `resources/views/`.
- **Models** — display-label maps (new) and existing English `DomainException`
  / `$fail()` messages.
- **Actions** — `ValidationException` messages surfaced to users.
- **Controllers** — the post-login flash message.
- **Tests** — presentation-copy assertions updated to Spanish; new locale/catalog tests.

No module changes business behavior. No route, policy, migration or schema change.

---

## 4. Application Flow

Localization is a presentation-layer change only. The request flow is unchanged:

```text
Browser (Spanish UI copy)
    ↓
Route / Controller / Filament resource (unchanged behavior)
    ↓
Laravel translator (locale = es, fallback = en)  →  lang/es/*.php + Filament es chrome
    ↓
Views / Filament components render Spanish labels and messages
    ↓
Persistence unchanged (stored identifiers/statuses untouched)
```

The only new runtime behavior is translation resolution: Laravel resolves
`trans()` keys and Filament's own chrome against the `es` catalog and falls
back to `en` (never a raw key) when a key is missing (ERR-001, ERR-002).

---

## 5. Components

### Controllers

- `App\Http\Controllers\Auth\AuthenticatedSessionController` — translate the
  post-login flash string only (FR-005). The login error already uses
  `trans('auth.failed')` and needs no code change.
- No other controller change. Portal flash messages are already Spanish
  ("Tu turno fue reservado.", "Tu reserva fue cancelada.",
  "Tu entrenamiento fue registrado.", "Tu perfil fue actualizado.") and remain.

### Actions / Use Cases

Translate **in place** the user-facing `ValidationException::withMessages(...)`
strings (FR-005; OQ-05 option a). No behavior change:

- `App\Actions\CreateBooking` — all 9 `ValidationException` messages
  (client/turno missing, access-gate denial ×4, not bookable, date in past,
  lead-time window, already started, full, duplicate).
- `App\Actions\RegisterPayment` — "cuota missing", "not pending", "full payment only".
- `App\Actions\AssignRoutine` — "only active version", "clients missing".
- `App\Actions\VersionRoutine` — "only active routine can be versioned",
  "at least one day", "day number duplicated", "every day at least one set",
  "set number duplicated", "new row active exercise only".
- `App\Actions\RenewMembership` — "only active/expired renewable",
  "inactive plan".
- `App\Actions\ProvisionClientUser` — "already linked user account".
- `App\Actions\RegisterClient` — no user-facing strings (no change).

### Models

Three kinds of change (all presentation-only):

1. **DomainException messages** (surfaced via Filament notifications and the
   portal's `$e->getMessage()` pass-through) translated in place (FR-005):
   - `Client::approve()` / `reject()`
   - `Membership::activate()` (×2) / `cancel()`
   - `Cuota::markPaid()` / `cancel()` / `updateAmount()`
   - `Booking::cancel()`
   - `Turno::deactivate()` / `reactivate()` / `cancel()` / `assertCapacityLimitNotBelowConfirmed()`
   - `Routine::activate()` (×3)
   - `RoutineAssignment` — no user-facing string (verified: no message).
2. **Closure `$fail()` validation messages** (FR-005):
   - `WorkoutLog::assignedVersionRule()` — "This set belongs to a routine version…"
   - `WorkoutLog::activeExerciseRule()` — "A free log can only reference an active exercise."
3. **Display-label maps** (FR-006, ADR-009): see §5.1 below. Model constants and
   DB values are byte-for-byte unchanged (BR-003).

### Policies

No change. Authorization is unchanged (BR-006).

### Events

None.

### Jobs

None.

### 5.1 Display-label maps (new model-level helpers)

Following the existing `Exercise::muscleGroupLabels()` / `difficultyLabels()`
precedent (ADR-009), add static `*Labels()` maps and translate the existing
`Exercise` map values to Spanish. Each map is keyed by the stored identifier,
value is the Spanish display label (gender agreed per entity):

| Model | New/updated helper | Keys → Spanish |
| --- | --- | --- |
| `Exercise` | `muscleGroupLabels()` (translate values) | chest/back/shoulders/biceps/triceps/forearms/abs/quadriceps/hamstrings/glutes/calves/full_body → Pecho/Espalda/Hombros/Bíceps/Tríceps/Antebrazos/Abdominales/Cuádriceps/Isquiotibiales/Glúteos/Gemelos/Cuerpo completo |
| `Exercise` | `difficultyLabels()` (translate values) | beginner/intermediate/advanced → Principiante/Intermedio/Avanzado |
| `Client` | `statusLabels()` (new) | pending/active/rejected → Pendiente/Activo/Rechazado |
| `Membership` | `statusLabels()` (new) | pending/active/expired/cancelled → Pendiente/Activo/Vencida/Cancelada |
| `Cuota` | `statusLabels()` (new) | pending/paid/cancelled → Pendiente/Pagada/Cancelada |
| `Payment` | `statusLabels()` (new) | pending/confirmed/failed → Pendiente/Confirmado/Fallido |
| `Payment` | `methodLabels()` (new) | cash/transfer → Efectivo/Transferencia bancaria |
| `Booking` | `statusLabels()` (new) | confirmed/cancelled → Confirmada/Cancelada |
| `Turno` | `statusLabels()` (new) | active/inactive/cancelled → Activo/Inactivo/Cancelado |
| `Routine` | `statusLabels()` (new) | draft/active/archived → Borrador/Activo/Archivado |
| `WorkoutLog` | `referenceTypeLabels()` (new) | routine/free → Desde rutina asignada/Ejercicio libre |

Consumption rules (no shared helper, no Filament Enum, no `lang` keys for these):

- Filament badges: replace `->formatStateUsing(fn ($s) => ucfirst($s))` with a
  lookup into the model's `statusLabels()` (unknown → identifier itself), e.g.
  `fn (string $s) => Membership::statusLabels()[$s] ?? $s`.
- Filament `SelectFilter::options(...)` and form `Select::options(...)`: call the
  map directly (e.g. `Payment::methodLabels()`), keeping the identifier as the
  option key.
- Badges/columns that today render the raw identifier with no `formatStateUsing`
  (Client status, Payment method/status, relation-manager statuses) must add a
  map lookup so they no longer render lowercase English identifiers.
- Portal Blade: use `Model::statusLabels()[$value] ?? $value` (or a small
  `statusLabel()` helper per model if preferred) instead of the raw `$model->status`.
- `RoutineResource` version-history line and `Routine::status` badges replace
  `ucfirst($status)` with the `Routine::statusLabels()` lookup.
- `UserResource::roleOptions()` labels are NOT role-slug maps (role slugs stay
  `ADMIN`/`TRAINER`/`CLIENT`); they are a hardcoded display map — translate the
  three values directly (Administrador/Entrenador/Cliente).

`WorkoutLog::reference_type` is a transient form/filter toggle (never persisted),
so `referenceTypeLabels()` is a convenience map to keep the two option lists in
`WorkoutLogResource` (form + filter) consistent.

---

## 6. Data Changes

**None.** No migration, no column/table/index, no seeder change, no stored-data
rewrite (BR-002). Stored identifiers (role slugs, statuses, methods, muscle
groups, difficulties, reference types) remain byte-for-byte unchanged (BR-003).

---

## 7. External Integrations

None. No new dependency (BR-004). Filament's built-in `es` chrome
(`vendor/filament/*/resources/lang/es`) is used as-is; Laravel's own `es`
catalogs are authored in-repo.

---

## 8. Locale configuration (FR-001)

- `config/app.php`:
  - `'name' => env('APP_NAME', 'El Area Gym')` (FR-007)
  - `'locale' => env('APP_LOCALE', 'es')`
  - `'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en')`
  - `'faker_locale' => env('APP_FAKER_LOCALE', 'es_ES')`
- `.env.example`:
  - `APP_NAME="El Area Gym"`
  - `APP_LOCALE=es`
  - `APP_FALLBACK_LOCALE=en`
  - `APP_FAKER_LOCALE=es_ES`

No `AdminPanelProvider` change is required:

- **Locale**: Filament 3.2 resolves its chrome (buttons, dashboard, pagination,
  filters, search, unsaved-changes alert, global search, account widget) through
  the Laravel app locale, so `locale = es` is sufficient. There is no
  `LocaleSwitcher` in this panel and none is added.
- **Brand**: Filament's `brand()`/`brandName()` defaults to `config('app.name')`,
  so the `APP_NAME` change surfaces "El Area Gym" automatically. Do **not** add
  `->brandName()` unless the team decides to pin it explicitly (optional and
  redundant; avoid unnecessary change).

Note (AF-003): a pre-existing local `.env` with `APP_LOCALE=en` overrides the
config default. This is an environment concern, not a product rule; the config
default + `.env.example` document the intended value.

---

## 9. `lang/es/` catalogs (FR-002)

Create `lang/es/` at the project root (Laravel 11 convention; the project has no
`lang/` directory today). Author four files directly (no `lang:publish`, no package):

- `lang/es/validation.php`
- `lang/es/auth.php`
- `lang/es/passwords.php`
- `lang/es/pagination.php`

Cover at least the keys the application relies on:

- `auth.failed` (the only `trans()` auth key in use) — "Estas credenciales no
  coinciden con nuestros registros."
- Validation rule messages for: `required`, `email`, `string`, `min.numeric`,
  `max.numeric`, `min.string`, `max.string`, `unique`, `confirmed`, `date`,
  `numeric`, `integer`, `in`, `before_or_equal`, `after_or_equal`, `after`,
  `exists`, `prohibits`, `required_without`, `required_if`, `required_with`,
  `distinct`, `date_format`, `url`, `array`.
- Passwords: `reset`, `sent`, `throttled`, `token`, `user`.
- Pagination: `previous`, `next` and the numeric-page labels. (Note: Filament
  renders its own pagination chrome from its own `es` catalog, not Laravel's
  `pagination.php`; this file still satisfies the framework default and any
  non-Filament pagination.)

A **complete** translation of the standard Laravel `es` catalogs is preferred so
a missed key falls back to `en` cleanly (ERR-001). All messages must use
`:attribute` placeholders so Laravel can substitute field names.

---

## 10. Filament copy translation (FR-003)

Mechanism (OQ-02 option a): set explicit Spanish
`$navigationLabel` / `$navigationGroup` / `$modelLabel` / `$pluralModelLabel`,
`Section::make(...)`, `->label(...)`, `->formatStateUsing(...)`,
`Actions\Action::make(...)->label(...)`, `->modalHeading(...)`,
`->modalSubmitActionLabel(...)`, page `getTitle()`, and translate the helper
strings in the resource classes. Filament chrome remains untouched (built-in `es`).

### 10.1 Resource-level labels and groups

| Resource | navigationLabel | navigationGroup | modelLabel / pluralModelLabel |
| --- | --- | --- | --- |
| UserResource | Usuarios | Administración | Usuario / Usuarios |
| ClientResource | Clientes | (none today) | Cliente / Clientes |
| PlanResource | Planes | Comercial | Plan / Planes |
| MembershipResource | Membresías | Comercial | Membresía / Membresías |
| PaymentResource | Pagos | Comercial | Pago / Pagos |
| CuotaResource | Cuotas (already ES) | Comercial | Cuota / Cuotas |
| TurnoResource | Turnos (already ES) | Agenda (from "Scheduling") | Turno / Turnos |
| BookingResource | Reservas | Reservas | Reserva / Reservas |
| AttendanceResource | Asistencia | Asistencia | Asistencia / (n/a) |
| ExerciseResource | Ejercicios | Entrenamiento | Ejercicio / Ejercicios |
| RoutineResource | Rutinas | Entrenamiento | Rutina / Rutinas |
| WorkoutLogResource | Registros de entrenamiento | Entrenamiento | Registro de entrenamiento / Registros de entrenamiento |

`modelLabel`/`pluralModelLabel` are required wherever the English class name
would otherwise leak into page titles, headings, breadcrumbs, delete/notification
wording — most notably **WorkoutLogResource** ("WorkoutLog" → "Registro de
entrenamiento"), and to make create/edit/view page titles Spanish for the others.

### 10.2 Per-resource field/column/filter/section/action strings

Only the hardcoded English strings are listed. **In addition**, every form field
and table column that currently has **no** `->label()` auto-derives an English
label from its snake_case field name (e.g. `full_name` → "Full name",
`is_active` → "Is active", `start_from` → "Start from"). To prevent English
auto-labels from leaking, the Developer must add explicit Spanish `->label()`
to those fields/columns too (this is part of FR-003's "field/column/filter
`label()` values" requirement, not a new functional rule).

- **UserResource** — `roleOptions()`: Admin/Trainer/Client → Administrador/Entrenador/Cliente.
  Add labels: name→Nombre, email→Email, password→Contraseña, roles→Roles,
  is_active→Cuenta activa (form + table + filter).
- **ClientResource** — Sections: Identity→Identidad, Contact→Contacto,
  Health notes→Notas de salud, Linked account→Cuenta vinculada,
  Current routine→Rutina actual. Labels: Login email→Email de acceso,
  Account status→Estado de la cuenta, Current routine→Rutina actual,
  Linked account→Cuenta vinculada, Account active→Cuenta activa.
  formatStateUsing: Active→Activo, Inactive→Inactivo, No account→Sin cuenta,
  No routine assigned→Sin rutina asignada. Filter options: Pending/Active/Rejected
  → Pendiente/Activo/Rechazado. Actions: Approve→Aprobar, Reject→Rechazar.
  Status badge (form/infolist/table) must add a `Client::statusLabels()` lookup
  (currently renders raw `pending`/`active`/`rejected`).
- **PlanResource** — Section: Offer→Oferta. Label: Status→Estado.
  formatStateUsing: Active→Activo, Inactive→Inactivo. Actions: Deactivate→Desactivar,
  Activate→Activar. Add labels: name→Nombre, description→Descripción,
  price→Precio, enrollment_fee→Matrícula, is_active→Activo.
- **MembershipResource** — Section: Membership→Membresía. Labels: Client→Cliente,
  DNI→DNI, Plan→Plan, Duration (days)→Duración (días), Start date→Fecha de inicio.
  Status badge: replace `ucfirst($state)` with `Membership::statusLabels()` lookup.
  Filter options: Pending/Active/Expired/Cancelled → Spanish. Actions:
  Cancel→Cancelar, Renew→Renovar. Filter sub-form DatePickers `start_from`/
  `start_until`/`end_from`/`end_until` need Spanish labels.
- **PaymentResource** — Section: Payment→Pago. Labels: Cuota→Cuota,
  Reference→Referencia, Payment date→Fecha de pago, Client→Cliente, DNI→DNI,
  Plan→Plan, Recorded by→Registrado por. Method options:
  Cash→Efectivo, Bank transfer→Transferencia bancaria (`Payment::methodLabels()`).
  Status filter: Pending/Confirmed/Failed → Pendiente/Confirmado/Fallido.
  method + status badges: add map lookups (currently raw `cash`/`transfer`/`confirmed`).
- **CuotaResource** — Section: Cuota (already ES). Labels: Client→Cliente, DNI→DNI,
  Plan→Plan, Period start→Inicio del período, Period end→Fin del período,
  Amount→Monto. Status badge: replace `ucfirst($state)` with lookup.
  Filter options: Pending/Paid/Cancelled → Pendiente/Pagada/Cancelada.
  Action: Edit amount→Editar monto.
- **TurnoResource** — Section: Turno (already ES). Labels: Start time→Hora de inicio,
  End time→Hora de fin, Capacity limit→Cupo máximo, Occupancy→Ocupación.
  Status badge: replace `ucfirst($state)` with lookup. Filter options:
  Active/Inactive/Cancelled → Activo/Inactivo/Cancelado. Actions:
  Deactivate→Desactivar, Reactivate→Reactivar, Cancel→Cancelar.
  Add labels: date→Fecha, label→Etiqueta.
- **BookingResource** — Section: Booking→Reserva. Labels: Client→Cliente, DNI→DNI,
  Turno→Turno, Booked by→Reservado por. Status badge: replace `ucfirst($state)`
  with lookup. Filter options: Confirmed/Cancelled → Confirmada/Cancelada.
  Action: Cancel→Cancelar. ViewBooking header action: Cancel→Cancelar.
  Add label: notes→Notas.
- **AttendanceResource** — Section: Check-in→Registro de ingreso,
  Attendance→Asistencia. Labels: Client→Cliente, Access decision→Decisión de acceso,
  Attended at→Asistió el, Turno→Turno, Recorded by→Registrado por, DNI→DNI.
  `denialMessage()` (×4) and `accessDecisionText()` ("Qualified — this client can
  be checked in.") → Spanish. Add labels: notes→Notas.
- **ExerciseResource** — Section: Exercise→Ejercicio. Labels: Muscle group→Grupo
  muscular, Video URL→URL del video, Status→Estado. formatStateUsing:
  Active→Activo, Inactive→Inactivo. Filter options (is_active): Active/Inactive →
  Activo/Inactivo. Actions: Deactivate→Desactivar, Activate→Activar.
  `muscleGroupLabels()`/`difficultyLabels()` values translated (model-level).
  Add labels: name→Nombre, equipment→Equipamiento, instructions→Instrucciones.
- **RoutineResource** — Sections: Routine→Rutina, Days and sets→Días y series,
  Days→Días, Version history→Historial de versiones. Labels: Sets→Series,
  Exercise→Ejercicio, Set number→Número de serie, Target reps→Repeticiones objetivo,
  Target weight (kg)→Peso objetivo (kg), Rest (seconds)→Descanso (segundos),
  Days→Días, Day number→Número de día, Version→Versión, Created by→Creado por,
  Day→Día, Set→Serie, Rest (s)→Descanso (s), Version history→Historial de versiones.
  Status badge + version-history line: replace `ucfirst($status)` with
  `Routine::statusLabels()` lookup. Filter options: Draft/Active/Archived →
  Borrador/Activo/Archivado. Closure `$fail()`: "A new set row can only reference
  an active exercise." → Spanish. Add labels: name→Nombre, notes→Notas.
  ViewRoutine header actions: Activate→Activar, Assign to clients→Asignar a
  clientes, Clients→Clientes.
- **WorkoutLogResource** — Section: Workout log→Registro de entrenamiento.
  Labels: Client→Cliente, DNI→DNI, Performed at→Realizado el,
  Reference type→Tipo de referencia, Prescribed set→Serie prescrita,
  Exercise→Ejercicio, Actual weight (kg)→Peso real (kg), Actual reps→Repeticiones
  reales, Target weight (kg)→Peso objetivo (kg), Target reps→Repeticiones
  objetivo, Recorded by→Registrado por, Logged at→Registrado el.
  `reference_type` options: From assigned routine→Desde rutina asignada,
  Free exercise→Ejercicio libre (`WorkoutLog::referenceTypeLabels()`).
  `routineExerciseLabel()`: "Day %d · %s — %s × %d (Set %d)" → "Día %d · %s — %s × %d
  (Serie %d)", Bodyweight→Peso corporal.
  `routineExerciseHint()`: "This client has no assigned routine — use the free
  exercise reference." → Spanish. Add label: notes→Notas.

### 10.3 Relation managers and pages

- `CuotaResource\PaymentsRelationManager` — `$title` Payments→Pagos; column
  Recorded by→Registrado por; method + status badges → map lookups.
- `ClientResource\MembershipsRelationManager` — `$title` Memberships→Membresías;
  Plan→Plan, Duration (days)→Duración (días); status badge → map lookup.
- `ClientResource\RoutineAssignmentsRelationManager` — `$title` Routines→Rutinas;
  Routine→Rutina, Active→Activo.
- `RoutineResource\AssignmentsRelationManager` — `$title` Assigned clients→
  Clientes asignados; Client→Cliente, Active→Activo; action Unassign→Desasignar.
- `ClientResource\Pages\ViewClient` — Provision user account→Crear cuenta de
  usuario (label + modalHeading), Provision→Crear, Login email→Email de acceso,
  Password→Contraseña.
- `WorkoutLogResource\Pages\ClientProgress` — `$navigationLabel`
  Client progress→Progreso del cliente; `getTitle()` "Progress — {name}"→
  "Progreso — {name}"; fallback "Client"→"Cliente"; column labels mirror
  WorkoutLogResource (Exercise→Ejercicio, Target weight (kg)→Peso objetivo (kg),
  Target reps→Repeticiones objetivo, Actual weight (kg)→Peso real (kg),
  Actual reps→Repeticiones reales, Recorded by→Registrado por, Logged at→Registrado el).

---

## 11. Public and portal Blade views (FR-004)

Translate the hardcoded English strings in place, keeping structure and Tailwind
classes identical. Reuse the SPEC-015 "El Area Gym" brand; the already-Spanish
notice stays.

- `resources/views/welcome.blade.php` — "Welcome to the gym management system." →
  "Bienvenido al sistema de gestión del gimnasio."; "Log in" → "Iniciar sesión".
- `resources/views/auth/login.blade.php` — title "Log in - El Area Gym" →
  "Iniciar sesión - El Area Gym"; heading "Log in" → "Iniciar sesión";
  "Email" → "Email" (or "Correo electrónico"); "Password" → "Contraseña";
  submit "Log in" → "Iniciar sesión".
- `resources/views/auth/register.blade.php` — title "Register - Gym Management" →
  "Registrarse - El Area Gym"; "Create an account" → "Crear una cuenta";
  field labels Full name→Nombre completo, Email→Email, Phone→Teléfono,
  Emergency contact→Contacto de emergencia, Password→Contraseña,
  Confirm password→Confirmar contraseña, Injuries notes→Notas de lesiones,
  Medical conditions notes→Notas de condiciones médicas; "Register"→"Registrarse";
  "Already have an account? Log in" → "¿Ya tienes una cuenta? Inicia sesión".
- `resources/views/auth/registration-complete.blade.php` — title
  "Registration received - Gym Management" → "Registro recibido - El Area Gym";
  "Registration received" → "Registro recibido"; "Staff will review your
  registration. You will be able to log in once it is approved." → Spanish;
  "Go to login" → "Ir a iniciar sesión".
- `resources/views/partials/header.blade.php` — "Skip to content" → "Saltar al
  contenido"; `aria-label="Primary"` → "Principal"; "Log out" → "Cerrar sesión";
  "Log in" → "Iniciar sesión".
- `resources/views/partials/portal-nav.blade.php` — link labels: Overview→Resumen,
  Memberships→Membresías, Payments→Pagos, Attendance→Asistencia, Turnos→Turnos,
  Bookings→Reservas, Routine→Rutina, Workouts→Entrenamientos, Profile→Perfil;
  `aria-label="Portal"` → "Portal".
- `resources/views/portal.blade.php` — title "Client portal - El Area Gym" →
  "Portal del cliente - El Area Gym"; heading "Client portal" → "Portal del
  cliente"; labels Name→Nombre, Email→Email, Phone→Teléfono, Status→Estado;
  "Not provided" → "No informado"; status value rendered via
  `Client::statusLabels()` lookup (currently raw identifier).
- `resources/views/portal/memberships.blade.php` — title/heading "Memberships" →
  "Membresías"; "No memberships found." → Spanish; "{n} days" → "{n} días";
  "Status:" → "Estado:"; status value via `Membership::statusLabels()` lookup.
- `resources/views/portal/payments.blade.php` — title "Payments - El Area Gym" →
  "Pagos - El Area Gym"; heading "Payments & cuotas" → "Pagos y cuotas";
  "No memberships found." → Spanish; "status" → "estado"; "No payments recorded."
  → Spanish; method + status values via `Payment::methodLabels()` /
  `Payment::statusLabels()` lookups; cuota status via `Cuota::statusLabels()`.
- `resources/views/portal/attendance.blade.php` — title/heading "Attendance" →
  "Asistencia"; "No attendance records found." → Spanish; "Turno:" → "Turno:".
- `resources/views/portal/bookings.blade.php` — title/heading "Bookings" →
  "Reservas"; "No bookings found." → Spanish; "Status:" → "Estado:";
  "Booked at" → "Reservado el"; status via `Booking::statusLabels()` lookup;
  "Cancel booking" → "Cancelar reserva".
- `resources/views/portal/turnos.blade.php` — title/heading "Turnos" → "Turnos";
  "No bookable turnos available right now." → Spanish; "{n} spots left" →
  "{n} cupos restantes"; "Book this turno" → "Reservar este turno".
- `resources/views/portal/routine.blade.php` — title/heading "Routine" →
  "Rutina"; "You have no assigned routine yet." → "Aún no tienes una rutina
  asignada."; "Day {n}" → "Día {n}"; "Set {n}" → "Serie {n}"; "{n} reps" →
  "{n} repeticiones"; "Bodyweight" → "Peso corporal"; "rest {n}s" →
  "descanso {n}s".
- `resources/views/portal/workouts.blade.php` — title/heading "Workouts" →
  "Entrenamientos"; "Log a workout" → "Registrar un entrenamiento";
  "From assigned routine" → "Desde rutina asignada"; "Free exercise" →
  "Ejercicio libre"; "Prescribed set" → "Serie prescrita"; "Select a prescribed
  set" → "Seleccionar una serie prescrita"; "Day {n}" → "Día {n}"; "Set {n}" →
  "Serie {n}"; "Bodyweight" → "Peso corporal"; "Exercise" → "Ejercicio";
  "Select an exercise" → "Seleccionar un ejercicio"; "Performed at" →
  "Realizado el"; "Actual weight (kg)" → "Peso real (kg)"; "Actual reps" →
  "Repeticiones reales"; "Notes" → "Notas"; "Save workout" → "Guardar
  entrenamiento"; "History" → "Historial"; "No workouts logged yet." → Spanish.
- `resources/views/portal/profile.blade.php` — title/heading "Profile" →
  "Perfil"; labels Name→Nombre, Status→Estado, "Health notes" → "Notas de salud",
  "Injuries" → "Lesiones", "Medical conditions" → "Condiciones médicas",
  "None" → "Ninguna", "Edit contact details" → "Editar datos de contacto",
  Email→Email, Phone→Teléfono, "Emergency contact" → "Contacto de emergencia",
  "Save profile" → "Guardar perfil"; status value via `Client::statusLabels()` lookup.
- `resources/views/layouts/app.blade.php` and `partials/footer.blade.php` — already
  Spanish ("El Area Gym"); `lang="{{ app()->getLocale() }}"` now resolves to `es`.
- `resources/views/filament/resources/workout-log-resource/pages/client-progress.blade.php` —
  no strings (renders `$this->table`).

---

## 12. Testing Strategy

### 12.1 Existing tests that pin English copy (update to Spanish, BR-005)

- `tests/Feature/Auth/LoginPresentationTest.php` — "Log in" (×2), "These
  credentials do not match our records." → "Iniciar sesión", "Estas credenciales
  no coinciden con nuestros registros."
- `tests/Feature/Public/LandingPresentationTest.php` — "Log in", "Your account has
  no assigned roles yet. Contact an administrator." → Spanish.
- `tests/Feature/Auth/AccessControlTest.php` — "Client portal" → "Portal del cliente".
- `tests/Feature/Portal/PortalPresentationTest.php` — "Client portal" (×2),
  "Not provided" → "No informado"; `assertSee(Client::STATUS_ACTIVE)` → Spanish
  label "Activo".
- `tests/Feature/Portal/PortalAccessTest.php` — nav labels Memberships/Payments/
  Attendance/Bookings/Routine/Workouts/Profile → Spanish; "Client portal" →
  "Portal del cliente".
- `tests/Feature/Portal/PortalReadOnlySectionsTest.php` — raw status/method
  identifiers (`Membership::STATUS_ACTIVE/EXPIRED`, `Payment::METHOD_TRANSFER`,
  `Payment::STATUS_CONFIRMED`, `Booking::STATUS_CONFIRMED/CANCELLED`) → Spanish
  labels; "Cancel booking" → "Cancelar reserva"; "Day 1" → "Día 1";
  "You have no assigned routine yet." → Spanish; `assertDontSee('Target')` →
  `assertDontSee('Objetivo')` (label becomes "Peso objetivo (kg)"/"Repeticiones
  objetivo").
- `tests/Feature/Portal/PortalProfileEditTest.php` — "Health notes" → "Notas de salud".
- `tests/Feature/Membership/ActivationContractTest.php` — `toThrow(DomainException::class,
  'Only a pending membership can be activated.')` → Spanish message.
- `tests/Feature/Admin/AttendanceManagementTest.php` — "Qualified",
  "has no membership", "no active membership", "has expired" → Spanish
  ("No tiene membresía…", "no tiene membresía activa", "ha vencido", etc.).
- `tests/Feature/Admin/BookingManagementTest.php` — "Confirmed" → "Confirmada".
- `tests/Feature/Admin/CuotaManagementTest.php` — "Pending" → "Pendiente".
- `tests/Feature/Admin/ClientProvisioningTest.php` — "Active"/"Inactive" → "Activo"/"Inactivo".
- `tests/Feature/Admin/ExerciseManagementTest.php` — "Shoulders" → "Hombros",
  "Beginner" → "Principiante", "Active" → "Activo".
- `tests/Feature/Admin/PaymentManagementTest.php` — "Confirmed" → "Confirmada".
- `tests/Feature/Admin/PlanManagementTest.php` — "Active" → "Activo".
- `tests/Feature/Admin/MembershipManagementTest.php` — "Active" → "Activo".
- `tests/Feature/Admin/RoutineManagementTest.php` — "Archived" → "Archivado"
  (also any "Draft"/"Active"/"Assign to clients"/"Unassign"/"Activate" assertions
  present).
- `tests/Feature/Admin/TurnoManagementTest.php` — "Active" → "Activo".
- `tests/Feature/Admin/UserManagementTest.php`, `ClientManagementTest.php`,
  `ClientApprovalTest.php`, `ClientProvisioningTest.php`, `WorkoutLogManagementTest.php`,
  `RoutineAssignmentTest.php` — update any English action/label assertions found
  during the sweep (e.g. role options, "Approve"/"Reject", "Provision user
  account", "Client progress"/"Progress —").

Business-logic, policy, authorization, state-transition and data-isolation
assertions (`toThrow(DomainException::class)` / `ValidationException::class`
without a message, count/relationship/scope assertions, `assertForbidden`,
route/redirect assertions) **keep passing unchanged** (BR-005, AC-11, AC-12).

### 12.2 New tests

- **Locale/config** — assert `config('app.locale') === 'es'`,
  `config('app.fallback_locale') === 'en'`, `config('app.faker_locale') === 'es_ES'`,
  `config('app.name') === 'El Area Gym'`; assert `.env.example` documents the
  `es`/`en`/`es_ES` values.
- **Catalogs** — assert `trans('auth.failed')`, one representative validation
  message (e.g. `trans('validation.required', ['attribute' => 'email'])`), one
  password message and one pagination label resolve to Spanish text (not the key,
  not English); assert a deliberately missing key falls back to English without
  error (ERR-001).
- **Display-only maps** — unit-test each new `statusLabels()` / `methodLabels()` /
  `referenceTypeLabels()` map (count, keys = identifiers, Spanish values), and
  that the raw stored column value is unchanged after rendering (BR-003).
- **Filament copy** — Livewire tests (or feature tests via the panel) asserting a
  representative Spanish navigation label/group and one Spanish action label and
  badge (AC-6, AC-7). Optional; the existing management tests already cover
  label rendering once updated.

### 12.3 No-regression gate

Run the full suite (`php artisan test`) and confirm all business-logic, policy,
authorization and isolation tests pass unchanged (AC-11, AC-12).

---

## 13. Risks

- **Missed English auto-derived label** (AF-001): a field/column without an
  explicit label keeps rendering an English auto-label ("Full name", "Is active",
  "Start from"). Mitigation: add explicit Spanish `->label()` across all
  resources (documented in §10.2) and review for residual English copy.
- **Raw-key or English fallback leak** (ERR-001/ERR-002): missing `es` keys fall
  back to `en` silently. Mitigation: complete catalogs + review.
- **Test churn**: many presentation assertions change. Mitigation: update only
  presentation-copy assertions; never delete/relax a business assertion (BR-005).
- **Gendered Spanish labels** are a cosmetic ambiguity; each entity's own map
  carries the agreed form (e.g. membership "Vencida" vs turno "Cancelado").
  Wording is presentation guidance (§16), finalized at review.

---

## 14. Alternatives Considered

- **Bilingual switcher / per-user locale** — rejected (OQ-01): adds state, dual
  string maintenance and test surface with no product requirement.
- **Filament Spanish translation package** — rejected (OQ-02, BR-004): packages
  cover chrome only, not the project's app labels; Filament ships `es` natively.
- **`laravel-lang/lang` community package for Laravel catalogs** — rejected
  (OQ-03, BR-004): four authored files suffice.
- **`lang:publish` then translate** — acceptable convenience; authoring the four
  `es` files directly is preferred (no dependency).
- **Shared `App\Support\Labels` helper / Filament Enum for statuses** — rejected
  (ADR-009): a cross-cutting abstraction is unnecessary; per-model static maps
  follow the existing `Exercise::muscleGroupLabels()` precedent and keep each
  entity's labels next to its constants.
- **Migrate stored identifiers to Spanish** — rejected (OQ-06, BR-002/BR-003):
  a data/business change outside this spec's scope.
- **Translate via `lang` keys for statuses/enums** — rejected: status labels are
  fixed domain vocabulary, not user-composed text; model-level maps are simpler
  and keep identifiers as the only persisted value.

---

## 15. Decision

Apply Spanish as the fixed default locale (`es`, fallback `en`, faker `es_ES`)
with four authored `lang/es` catalogs and Filament's built-in `es` chrome.
Translate all hardcoded presentation strings in Filament resources/pages/relation
managers, Blade views and user-facing PHP strings in place. Translate stored
statuses/enums **display-only** via per-model static label maps (ADR-009),
leaving persisted identifiers unchanged. Set the application name to
"El Area Gym". Update presentation-copy tests to Spanish and add locale/catalog
tests. No new dependency, no migration, no authorization or behavior change.
