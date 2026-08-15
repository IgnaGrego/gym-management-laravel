# Architecture — SPEC-013

## 1. Feature

Client Portal ("El Area Gym") — the CLIENT self-service presentation context:

- **Read-only, own-data-only sections** over the SPEC-002..011 models: memberships,
  payments/cuotas, attendance, own bookings, the assigned routine, own workout
  history, and own health notes (`injuries_notes` / `medical_conditions_notes`,
  read-only per NC-02);
- **Four interactive self-service actions** (D-18 option 3): book a turno, cancel
  an own booking, log a workout, edit own contact fields;
- **Profile edit is the final PO scope (NC-01):** only `email`, `phone`,
  `emergency_contact` are client-editable; `full_name`, `dni` (identity, DNI
  unique per SPEC-002 BR-005), `status` (SPEC-012 lifecycle) and the health notes
  (NC-02) are NOT client-editable;
- **Client isolation (C-13) is absolute and server-side:** a CLIENT never reads
  or mutates another client's data through any portal path (BR-002, AGENTS.md
  §17).

This Specification EXTENDS the `/portal` base built by SPEC-015 (shared Blade
layout "El Area Gym", CLIENT profile read-only, ERR-005 notice, logout). It adds
no new business rules and no new tables/columns; it only wires the already
implemented SPEC-002/004/005/006/007/008/010/011 rules into a CLIENT-facing,
ownership-scoped surface. SPEC-014 (Mercado Pago) and notifications are out of
scope.

---

## 2. Specification

Reference:

`docs/specs/SPEC-013.md`

Status note: SPEC-013 is approved (`spec_ready`). The gate D-18 (option 3, full)
is pre-approved under NIGHT MODE. The two formerly-uncovered business decisions
are RESOLVED by the Product Owner and recorded in SPEC-013 §14.2: **NC-01**
(a CLIENT edits only `email`/`phone`/`emergency_contact`) and **NC-02** (a CLIENT
views their own health notes read-only). The flagged assumptions CP-01..CP-09 are
technical defaults consistent with the implemented Specifications (SPEC-013
§14.1); no business rule is invented here. There are no blocking items; the
non-blocking open questions OQ-01..OQ-04 are carried to §12.

---

## 3. Affected Modules

- **Client Portal (web presentation)** — the main change: `ClientPortalController`
  gains section/action methods, new Blade views under `resources/views/portal/`,
  and new named routes under `/portal` (all behind `auth` + `role:CLIENT`).
- **Bookings (existing module, additive):** `CreateBooking` Action gains a
  self-service branch (CLIENT books only their own Client; `booked_by = null`).
  `BookingPolicy` `view`/`update` gain CLIENT-own access. `Booking::cancel()` is
  reused unchanged.
- **Workout Logs (existing module, additive):** the portal self-log endpoint
  reuses `WorkoutLog::referenceRules()` and the assigned-version / active-exercise
  rules (extracted to the model so both Filament and the portal share them).
  `WorkoutLogPolicy::view` gains CLIENT-own access. `WorkoutLog` stays immutable.
- **Clients (existing module, additive):** `ClientPolicy` `view`/`update` gain
  CLIENT-own access (field whitelist enforced by the Form Request, not the
  policy). `User` gains a tiny `clientId()` ownership helper.
- **Memberships / Payments / Attendance / Routines (existing modules, additive
  policy-only):** `MembershipPolicy`, `CuotaPolicy`, `PaymentPolicy`,
  `AttendancePolicy`, `RoutinePolicy` gain CLIENT-own `view` access only. No
  model, schema or admin-UI change.

Explicitly unchanged:

- Authentication (`AuthenticatedSessionController`), the `role:CLIENT` gate
  (`EnsureUserHasRole`), `AdminPanelProvider`, and `User::canAccessPanel()`.
- All staff Filament resources and the SPEC-002..011 **staff** policy abilities.
- The `users` / `roles` / `role_user` / `clients` / `plans` / `memberships` /
  `cuotas` / `payments` / `turnos` / `bookings` / `attendances` / `exercises` /
  `routines` / `routine_days` / `routine_exercises` / `routine_assignments` /
  `workout_logs` schemas (no migration).

---

## 4. Application Flow

```text
Presentation (web)
    ↓
GET  /portal[/section]        → auth + role:CLIENT → ClientPortalController
POST /portal/turnos/{turno}/book        → CreateBooking (self mode)
POST /portal/bookings/{booking}/cancel  → BookingPolicy::update → Booking::cancel()
POST /portal/workouts                   → StoreWorkoutLogRequest → WorkoutLog::create
POST /portal/profile                    → ClientPolicy::update → Client::update (3 fields)
    ↓
Application
    ↓
Ownership scoping: auth()->user()->client (derived; never a request id)
    ↓
Domain
    ↓
Client::memberships()/attendances()/bookings()/workoutLogs()/currentRoutine()
Client::hasQualifyingMembership()/accessDenialReason()      (booking gate, BR-004)
Membership::scopeQualifying()  Booking::cancel()  WorkoutLog::referenceRules()
Client::hasRoutineAssignmentTo()  Exercise::scopeActive()   (log rules, BR-006)
    ↓
Persistence
    ↓
PostgreSQL — all existing tables; NO schema change (SPEC-013 §10)
```

Concrete flows:

1. **Overview (FR-001, SPEC-015 base).** `GET /portal` renders the SPEC-015
   profile (name, DNI, email, phone, status) or the ERR-005 notice, and now also
   the portal navigation. The `role:CLIENT` middleware and the SPEC-015
   contracts are unchanged.
2. **Read-only sections (FR-002..FR-005, FR-008, FR-010, FR-011 health notes).**
   Each controller method resolves `auth()->user()->client` (ERR-005 when null)
   and renders only that Client's records via its relationships. No
   `client_id`/record id is read from the request.
3. **Book a turno (FR-006, BR-004).** The `turnos` section lists `active` turnos
   within the booking window (`today..+7`, same-day start not passed) that are
   not full. Submitting a turno id delegates to `CreateBooking` (reused), which
   treats the booking as self-service (derived client, `booked_by = null`) and
   enforces the SPEC-007 rules atomically (access gate, active, lead time,
   capacity, no duplicate).
4. **Cancel own booking (FR-007, BR-005).** The `bookings` section lists own
   bookings; a `confirmed` own booking is cancellable. The controller scopes the
   lookup to the client, authorizes `update` (policy), then calls
   `Booking::cancel()` (spot reopens; terminal).
5. **Log a workout (FR-009, BR-006).** The `workouts` section renders the own
   history (FR-010) and a self-log form (assigned-routine set rows of the
   current routine + free `active` exercises). Submitting validates via the
   shared SPEC-011 rules and persists a `WorkoutLog` with `client_id` derived
   and `recorded_by = auth()->id()`.
6. **Edit profile (FR-011, BR-008, NC-01).** The `profile` section renders the
   profile, the read-only health notes, and an edit form for the three contact
   fields. Submitting authorizes `update` (policy), validates formats, and
   updates only `email`/`phone`/`emergency_contact`.

---

## 5. Components

### Controllers

**`App\Http\Controllers\ClientPortalController`** (extended; keeps the existing
`index()`). One controller holds the whole portal — the methods are thin (resolve
the client, render a view, or delegate to an Action/Form Request), so no
per-concern controller classes are introduced (OQ-01 resolved in favor of a
single hub controller, the SPEC-015 precedent).

A private helper centralizes the ERR-005 boundary for every section:

```php
private function renderPortal(string $view, array $data = []): View
{
    $client = auth()->user()->client;

    if ($client === null) {
        // ERR-005 / AF-001: authorized CLIENT with no linked Client record.
        return view('portal', ['user' => auth()->user(), 'client' => null]);
    }

    return view($view, array_merge(['user' => auth()->user(), 'client' => $client], $data));
}
```

Methods:

| Method | Route | Responsibility / data passed |
| --- | --- | --- |
| `index()` | `GET /portal` | Overview (SPEC-015 base + navigation). Existing behavior preserved. |
| `memberships()` | `GET /portal/memberships` | `$client->memberships()->with('plan')->orderBy('start_date')->get()` (FR-002, chronological). |
| `payments()` | `GET /portal/payments` | `$client->memberships()->with(['plan', 'cuota.payments'])->orderBy('start_date')->get()` (FR-003; `reference`/`notes`/`recorded_by` hidden — CP-06). |
| `attendance()` | `GET /portal/attendance` | `$client->attendances()->with('turno')->orderBy('attended_at')->get()` (FR-004; `recorded_by` hidden — CP-07). |
| `bookings()` | `GET /portal/bookings` | `$client->bookings()->with('turno')->orderByDesc('booked_at')->get()` (FR-005; cancel form only for `confirmed`). |
| `turnos()` | `GET /portal/turnos` | Presentational bookable-turnos list (see below) — the FR-006 book surface. |
| `routine()` | `GET /portal/routine` | `$client->currentRoutine()?->load('days.exercises.exercise')` (FR-008; empty state when null, AF-004). |
| `workouts()` | `GET /portal/workouts` | Own logs (FR-010) + log-form data: `$client->currentRoutine()?->load('days.exercises.exercise')` (assigned-routine options) and `Exercise::active()->orderBy('name')->get()` (free-log options). |
| `profile()` | `GET /portal/profile` | The Client (profile + read-only health notes + edit form) (FR-011). |
| `book(Request, int $turno)` | `POST /portal/turnos/{turno}/book` | Delegates to `CreateBooking` for `$client->id`; `notes` not collected (null). |
| `cancelBooking(int $booking)` | `POST /portal/bookings/{booking}/cancel` | Scoped lookup + policy `update` + `Booking::cancel()`. |
| `storeWorkout(StoreWorkoutLogRequest)` | `POST /portal/workouts` | Persists a self `WorkoutLog` (client + `recorded_by` injected). |
| `updateProfile(UpdateProfileRequest)` | `POST /portal/profile` | Policy `update` + `Client::update()` of the 3 whitelisted fields. |

Mutation endpoints (book/cancel/log/profile):

- `book()`: resolve `$client` (abort ERR-005 if null); `$request->validate(['turno_id' => ['required', 'integer']])`; then
  `app(CreateBooking::class)->handle($client->id, (int) $request->integer('turno_id'), null)`.
  `ValidationException` (gate/full/not-bookable/duplicate) is surfaced with
  `->withErrors(...)->withInput()` (ERR-003..ERR-007). Success redirects to
  `route('portal.bookings')`.
- `cancelBooking()`: resolve `$client`; `$booking = $client->bookings()->findOrFail($booking)` (scoped → 404 for a
  foreign id, ERR-008/ERR-012); `Gate::authorize('update', $booking)`; then
  `$booking->cancel()` inside a try/catch for `DomainException` (already
  `cancelled` → ERR-009, redirect with an error). Success redirects to
  `route('portal.bookings')`.
- `storeWorkout()`: resolve `$client`; `WorkoutLog::create([...$request->validated(), 'client_id' => $client->id, 'recorded_by' => auth()->id()])`. Redirect back with a success flash. (The `role:CLIENT` route + derived `client_id` is the authorization — see Policies.)
- `updateProfile()`: resolve `$client`; `Gate::authorize('update', $client)`; `$client->update($request->validated())` (only the 3 whitelisted keys). Redirect back with a success flash.

The bookable-turnos list (`turnos()`) is a **presentational, non-authoritative
projection** (the same stance SPEC-007 §5 takes for the admin option list — a
stale list can never be the enforcement):

```php
$today = Carbon::today();
$windowEnd = $today->copy()->addDays(7);

$turnos = Turno::query()
    ->active()
    ->whereBetween('date', [$today, $windowEnd])
    ->withCount(['bookings as confirmed_count' => fn ($q) => $q->confirmed()])
    ->get()
    ->reject(function (Turno $turno) use ($today): bool {
        if ($turno->date->isSameDay($today)) {
            $start = Carbon::createFromFormat('H:i', $turno->start_time);
            return $start->lte(now());
        }
        return false;
    })
    ->reject(fn (Turno $turno): bool => $turno->confirmed_count >= $turno->capacity_limit)
    ->sortBy(fn (Turno $turno): string => $turno->date->format('Y-m-d').' '.$turno->start_time)
    ->values();
```

`CreateBooking` re-validates the same window and the capacity **atomically**
(ERR-007), so the list filter is UX only.

### Actions / Use Cases

**`App\Actions\CreateBooking`** (modified additively) — the single enforcement
point for booking creation is reused, extended for the self-service branch:

- `handle(int $clientId, int $turnoId, ?string $notes = null)` signature is
  **unchanged**; the Filament `CreateBooking` page keeps working as-is.
- `authorize()` becomes mode-aware. Replace the current unconditional
  `Gate::authorize('create', Booking::class)` with:

  ```php
  protected function authorize(int $clientId): void
  {
      $user = auth()->user();

      // Self-service branch: the actor is booking their own linked Client.
      if ($user !== null && $user->client?->id === $clientId) {
          abort_unless($user->hasRole(Role::CLIENT), 403);
          return;
      }

      // Staff branch (admin panel): unchanged ADMIN/TRAINER gate (BK-02).
      Gate::authorize('create', Booking::class);
  }
  ```

- The insert inside the transaction sets `booked_by` conditionally:

  ```php
  'booked_by' => auth()->user()?->client?->id === $clientId ? null : auth()->id(),
  ```

  Self-service bookings persist `booked_by = null` (CP-09, SPEC-007 BK-12);
  staff bookings keep the staff User. The capacity/duplicate/gate/lead-time
  validation is untouched (ADR-006 atomicity is reused as-is).

- The self/staff distinction is by linked-client id, not by context: a staff
  member who books **their own** linked Client record is recorded as self-service
  (`booked_by = null`) — consistent with AF-005/CP-09. A CLIENT attempting to
  book any other client id falls into the staff branch and is denied by
  `BookingPolicy::create` (C-13).

No new Action is introduced for cancellation, workout logging or profile edit:

- Cancellation is the existing single-record `Booking::cancel()` model method
  (SPEC-007 §5 "cancellation does not need an Action").
- Workout self-logging is a single-row insert with framework validation — the
  same "no Action" decision SPEC-011 §5/§10 took for staff logging; the portal
  reuses the same shared validation and injects `client_id`/`recorded_by` in the
  controller (the `CreateWorkoutLog::mutateFormDataBeforeCreate` analogue).
- Profile edit is a three-field Eloquent update with a Form Request; an Action
  would be an unnecessary abstraction (AGENTS.md §9-10).

### Models

**No model changes to business behavior.** Two additive, read-only helpers:

- **`App\Models\User`** — add `clientId(): ?int` returning `$this->client?->id`.
  Single source for the ownership comparison used by every policy (`client` is
  the existing `hasOne(Client)` from SPEC-002). Avoids repeating the null-safe
  navigation in eight policies.
- **`App\Models\WorkoutLog`** — extract the two reference-validity closure rules
  currently private to `WorkoutLogResource` so the portal and the Filament form
  share them (avoid duplicating business logic, AGENTS.md §9):
  - `assignedVersionRule(?int $clientId): array` — returns `[]` or a
    `[Closure]` that fails when the selected `routine_exercise_id` belongs to a
    routine version the client was never assigned to (uses
    `Client::hasRoutineAssignmentTo()`; active or historical — SPEC-011 BR-004,
    ERR-003).
  - `activeExerciseRule(): array` — returns `[]` or a `[Closure]` that fails
    when the selected `exercise_id` is not an `active` catalogue exercise
    (`Exercise::scopeActive()`, SPEC-011 BR-005, ERR-005).
  `WorkoutLogResource`'s private helpers become thin calls to these (or are
  removed). `WorkoutLog::referenceRules()` is reused unchanged.

Reused unchanged (no modification): `Client::currentRoutine()`,
`Client::hasQualifyingMembership()`, `Client::accessDenialReason()`,
`Membership::scopeQualifying()`, `Client::hasRoutineAssignmentTo()`,
`Exercise::scopeActive()`/`isActive()`, `Booking::cancel()`,
`Booking::confirmedCountForTurno()`, `WorkoutLog::exerciseName()`, and the
`Client` relationships `memberships()`, `attendances()`, `bookings()`,
`routineAssignments()`, `workoutLogs()`.

### Policies

The portal extends the **instance** `view`/`update` abilities of eight policies
to allow a CLIENT to access their OWN records, by comparing the model's owning
client to `auth()->user()->clientId()`. Staff clauses are kept verbatim and only
an `||` CLIENT-own term is added, so no staff permission is weakened and no
cross-client access is possible (C-13). This is the ADR-007 pattern.

| Policy | Ability | Before | After (additive) |
| --- | --- | --- | --- |
| `ClientPolicy` | `view` | ADMIN | + `$user->hasRole(CLIENT) && $client->id === $user->clientId()` |
| `ClientPolicy` | `update` | ADMIN | + `$user->hasRole(CLIENT) && $client->id === $user->clientId()` (field whitelist is the Form Request, not the policy — see §5 Validation) |
| `MembershipPolicy` | `view` | ADMIN | + `$user->hasRole(CLIENT) && $membership->client_id === $user->clientId()` |
| `CuotaPolicy` | `view` | ADMIN+TRAINER | + `$user->hasRole(CLIENT) && $cuota->membership?->client_id === $user->clientId()` |
| `PaymentPolicy` | `view` | ADMIN+TRAINER | + `$user->hasRole(CLIENT) && $payment->cuota?->membership?->client_id === $user->clientId()` |
| `AttendancePolicy` | `view` | ADMIN+TRAINER | + `$user->hasRole(CLIENT) && $attendance->client_id === $user->clientId()` |
| `BookingPolicy` | `view` | ADMIN+TRAINER | + `$user->hasRole(CLIENT) && $booking->client_id === $user->clientId()` |
| `BookingPolicy` | `update` | ADMIN+TRAINER | + `$user->hasRole(CLIENT) && $booking->client_id === $user->clientId()` (covers own cancel, FR-007) |
| `WorkoutLogPolicy` | `view` | ADMIN+TRAINER | + `$user->hasRole(CLIENT) && $workoutLog->client_id === $user->clientId()` |
| `RoutinePolicy` | `view` | ADMIN+TRAINER | + `$user->hasRole(CLIENT) && $routine->assignments()->where('client_id', $user->clientId())->where('is_active', true)->exists()` (current assignment only, BR-007) |

**Unchanged (NOT extended — deliberately staff-only or no-op):**

- All `viewAny` abilities (the portal never renders an unrestricted list; CLIENT
  self-reads are relationship-scoped, and Filament `viewAny` remains the staff
  gate).
- `create` abilities for `Booking`, `WorkoutLog`, `Attendance`, `Payment`,
  `Membership`, `Client`, `Routine` — CLIENT self-create for Booking/WorkoutLog
  is enforced in the `CreateBooking` self branch and by the derived-client log
  endpoint, NOT by widening `create` (see ADR-007).
- `update` for `Membership`, `Cuota`, `Routine` (CLIENT has no management
  ability, BR-003); `ClientPolicy::approve`/`reject` (SPEC-012).
- No `delete` ability is registered anywhere (all preservation rules unchanged).

**Two-layer enforcement (authoritative vs. contract):**

- **Reads** are enforced **structurally**: every portal controller resolves
  `auth()->user()->client` and queries through that Client's relationships; no
  request-supplied `client_id`/record id is ever trusted (BR-002, C-13). The
  `view` policy extensions above are the explicit, testable contract and
  defense-in-depth — they are not the primary enforcement for portal reads.
- **Mutations** are enforced by the `update` policy abilities (cancel, profile)
  plus the `CreateBooking` self branch (book) plus the derived-client endpoint
  (log). The state rules (e.g. only `confirmed` cancellable) and business rules
  (gate, capacity, log invariants) are enforced by the reused Actions/model
  methods, not by the policies (SPEC-007/011 §9 separation).

A multi-role user receives the union (SPEC-001 BR-002): an ADMIN/TRAINER who also
holds CLIENT keeps their staff abilities AND gains CLIENT-self access in the
portal (AF-005). Authorization is always server-side; navigation/control hiding
is never enforcement (AGENTS.md §17).

### Middleware

No change. `web`, `auth`, `role:CLIENT` (alias `EnsureUserHasRole`), `guest` and
`throttle` are used exactly as today. The new routes are registered in one
`auth` + `role:CLIENT` group.

### Events / Jobs

None. No portal operation has a decoupled secondary effect (ARCHITECTURE §10-11;
SPEC-013 §12 excludes notifications). Booking capacity enforcement stays
synchronous and transactional (reused `CreateBooking`; ADR-006).

### Routes

Refactor `routes/web.php` (the existing `GET /portal` route is preserved with the
same name and middleware):

```php
Route::middleware(['auth', 'role:CLIENT'])->prefix('portal')->group(function () {
    Route::get('/', [ClientPortalController::class, 'index'])->name('portal');
    Route::get('/memberships', [ClientPortalController::class, 'memberships'])->name('portal.memberships');
    Route::get('/payments', [ClientPortalController::class, 'payments'])->name('portal.payments');
    Route::get('/attendance', [ClientPortalController::class, 'attendance'])->name('portal.attendance');
    Route::get('/bookings', [ClientPortalController::class, 'bookings'])->name('portal.bookings');
    Route::get('/turnos', [ClientPortalController::class, 'turnos'])->name('portal.turnos');
    Route::post('/turnos/{turno}/book', [ClientPortalController::class, 'book'])->name('portal.turnos.book');
    Route::post('/bookings/{booking}/cancel', [ClientPortalController::class, 'cancelBooking'])->name('portal.bookings.cancel');
    Route::get('/routine', [ClientPortalController::class, 'routine'])->name('portal.routine');
    Route::get('/workouts', [ClientPortalController::class, 'workouts'])->name('portal.workouts');
    Route::post('/workouts', [ClientPortalController::class, 'storeWorkout'])->name('portal.workouts.store');
    Route::get('/profile', [ClientPortalController::class, 'profile'])->name('portal.profile');
    Route::post('/profile', [ClientPortalController::class, 'updateProfile'])->name('portal.profile.update');
});
```

- The index route keeps the name `portal` (SPEC-001/015 contract); all
  sub-routes use `portal.*` names.
- All four mutations use `POST` (no `@method` spoofing), consistent with the
  repo's existing mutation convention (`login`, `logout`, `register`).
- No Filament routes; `/admin/*` is untouched.

### Validation

Three validation surfaces, all server-side:

1. **Booking (FR-006)** — the `CreateBooking` Action's own rules (reused
   unchanged): client/turno existence, `Client::hasQualifyingMembership()` gate
   (ERR-003), turno `active`, `today..+7` + same-day start-not-passed window,
   atomic capacity, duplicate invariant. The controller adds only the trivial
   `turno_id` required-integer check; the rest is the Action.

2. **Workout log (FR-009, BR-006)** — `App\Http\Requests\Portal\StoreWorkoutLogRequest`
   mirrors the SPEC-011 rules exactly:
   - `performed_at` → `['required', 'date', 'before_or_equal:now']` (WL-05);
   - `routine_exercise_id` / `exercise_id` → `WorkoutLog::referenceRules()`
     (`required_without` + `prohibits` + `exists`, ERR-001/ERR-002) **plus**
     `WorkoutLog::assignedVersionRule($clientId)` (ERR-003) and
     `WorkoutLog::activeExerciseRule()` (ERR-005);
   - `actual_weight` → `['nullable', 'numeric', 'min:0']` (WL-06);
   - `actual_reps` → `['required', 'integer', 'min:1']` (WL-06);
   - `notes` → `['nullable', 'string']`.
   `client_id` and `recorded_by` are NOT request fields — the controller injects
   them. No membership gate (SPEC-011 BR-010). `authorize()` returns `true`
   (route gate + derived client are the authorization).

3. **Profile edit (FR-011, BR-008, NC-01)** — `App\Http\Requests\Portal\UpdateProfileRequest`
   whitelists exactly three fields:
   - `email` → `['nullable', 'email', 'max:255']`;
   - `phone` → `['nullable', 'string', 'max:255']`;
   - `emergency_contact` → `['nullable', 'string', 'max:255']`.
   `full_name`, `dni`, `status`, `injuries_notes`, `medical_conditions_notes` are
   NOT accepted (absent from the request), so they cannot be changed even though
   they are `fillable` on `Client` (ERR-011). `authorize()` returns `true`; the
   controller calls `Gate::authorize('update', $client)` separately.

Note on "phone format": SPEC-002 ERR-006 imposed no phone regex (only max
length). The Developer must NOT invent one — `string` + `max:255` is the exact
SPEC-002 constraint. `email` is format-validated only and is **not**
unique-validated: the client contact `clients.email` is independent of the login
`users.email` (SPEC-002 OQ-07) and has no uniqueness rule; the portal never
touches `users` (BR-008).

### Views

Reuse the SPEC-015 shared layout (`layouts/app.blade.php`) and the portal base.
New files:

```
resources/views/partials/portal-nav.blade.php   (portal navigation, shared by all portal pages)
resources/views/portal/memberships.blade.php
resources/views/portal/payments.blade.php
resources/views/portal/attendance.blade.php
resources/views/portal/bookings.blade.php
resources/views/portal/turnos.blade.php
resources/views/portal/routine.blade.php
resources/views/portal/workouts.blade.php
resources/views/portal/profile.blade.php
```

- **`resources/views/portal.blade.php`** (existing) — keeps the SPEC-015 heading
  "Client portal", the profile `dl`, the ERR-005 notice, and gains
  `@include('partials.portal-nav')` (FR-001). No business content beyond the
  profile + navigation.
- All section views `@extends('layouts.app')`, include `partials/portal-nav`,
  set their own `<title>`, and render simple read-only lists (no Filament). They
  receive `$client` and the section data; the controller's `renderPortal()` has
  already handled the ERR-005 case.
- **`turnos`** renders each bookable turno (date, start–end, remaining spots)
  with a `POST` form to `route('portal.turnos.book', $turno)` + `@csrf`.
- **`bookings`** renders each own booking (turno date/time, status, `booked_at`)
  with, for `confirmed` rows only, a `POST` form to
  `route('portal.bookings.cancel', $booking)` + `@csrf` (ERR-009 UX; the state
  rule is enforced server-side by `Booking::cancel()`).
- **`workouts`** renders the own history (grouped by `performed_at` date, per-set
  rows showing exercise name, performed timestamp, weight, reps, notes — the
  flat FR-010 view; **no** Target/Actual comparison, CP-05) plus the self-log
  form (two reference selects + `reference_type` toggle via Alpine, `performed_at`
  default now, weight/reps/notes). The `reference_type` toggle is transient and
  the `prohibits`/`required_without` rules remain the server-side exactly-one
  enforcement (the SPEC-011 §5 pattern).
- **`profile`** renders the profile summary, the read-only health notes
  (`injuries_notes`, `medical_conditions_notes`, NC-02), and the edit form for
  only `email`/`phone`/`emergency_contact` (NC-01). `full_name`/`dni`/`status`
  are displayed, never edited.
- **`payments`** hides `reference`, `notes`, `recorded_by` (CP-06); **`attendance`**
  hides `recorded_by` (CP-07). **`routine`** shows the current routine's days and
  set rows (exercise name, set number, target reps/weight, rest seconds, notes)
  or an empty state (AF-004).

Existing EN/ES copy is preserved where SPEC-015 fixed it (heading "Client
portal", ERR-005 "Perfil no disponible. Contactá a recepción."); new portal
labels are a Developer/presentation choice with no test contract yet.

---

## 6. Data Changes

**No migrations, no new tables, no new columns** (SPEC-013 §10). The portal is an
additive read + self-service surface over the existing SPEC-002..011 schemas.

Data lifecycle:

- **Created:** `Booking` rows by self-service with `booked_by = null` and
  `booked_at = now` (FR-006, CP-09); `WorkoutLog` rows by self-service with
  `recorded_by` = the CLIENT's User (FR-009). Creating either never touches a
  Client, Turno, Membership, Cuota, Payment, Attendance, Routine or Assignment
  record (SPEC-007 BR-002 / SPEC-011 BR-003, C-07).
- **Modified:** `Client.email` / `phone` / `emergency_contact` only (FR-011,
  BR-008, NC-01) — never `full_name`, `dni`, the health notes or `status`, and
  never the linked `User` record. `Booking.status` via `Booking::cancel()` on
  self-cancellation (FR-007).
- **Deleted:** none (preservation pattern of all predecessor Specifications).

---

## 7. External Integrations

None. No payment provider (SPEC-014 excluded), no notifications/email, no API,
no queue. No new PHP or npm dependency (the portal reuses the SPEC-015 Blade +
Tailwind + Alpine toolchain).

---

## 8. Testing Strategy

Pest (PHPUnit) feature tests, following existing conventions (`tests/Pest.php`
helpers `role()`, `userWithRoles()`, `clientWithUser()`; `RefreshDatabase`). All
new portal tests add `beforeEach(fn () => $this->withoutVite())` (the SPEC-015
Node-independent boundary). Factories reused: `Client`, `User`, `Membership`,
`Cuota`, `Payment`, `Turno`, `Booking`, `Attendance`, `Routine`, `RoutineDay`,
`RoutineExercise`, `Exercise`, `WorkoutLog`.

The spec's test plan (SPEC-013 §11) maps to:

- **`tests/Feature/Portal/PortalAccessTest.php`** (AC-1, AC-18): CLIENT → 200;
  anonymous → redirect to login; non-CLIENT (TRAINER/ADMIN-only) → 403; CLIENT
  with no linked Client → ERR-005 notice and no business content on every
  section.
- **`tests/Feature/Portal/PortalIsolationTest.php`** (AC-16, ERR-012, BR-002,
  C-13): two CLIENT fixtures; each section renders only the authenticated
  client's records; a foreign record id on a mutation path (cancel a foreign
  booking, book with a foreign id) is rejected without leaking data.
- **`tests/Feature/Portal/PortalReadOnlySectionsTest.php`** (AC-2, AC-3, AC-4,
  AC-5, AC-11, AC-14): memberships chronological; cuotas/payments amount-status-
  method-date (no `reference`/`recorded_by`); attendance chronological (no
  `recorded_by`); own bookings with status+turno; routine + empty state; workout
  history grouped by date (no Target/Actual columns).
- **`tests/Feature/Portal/PortalBookingTest.php`** (AC-6..AC-10, ERR-003..ERR-009):
  self-book success with `booked_by = null`; access-gate denial surfaced; full/
  not-bookable/out-of-window/duplicate rejections; self-cancel + spot reopen;
  cancel foreign booking → 404/403; cancel already-`cancelled` → rejected. The
  last-spot race (ERR-007) is covered by the existing
  `tests/Feature/Bookings/CapacityTest.php` (the portal reuses `CreateBooking`
  unchanged, so the atomicity test is not duplicated); `PortalBookingTest`
  asserts the portal delegates to the Action (functional integration).
- **`tests/Feature/Portal/PortalWorkoutLogTest.php`** (AC-12, AC-13): self-log
  via assigned-routine row and via free `active` exercise, `recorded_by` = own
  user, immutability (no edit/delete route exists); rejections for both/neither
  reference, never-assigned version, inactive exercise, invalid weight/reps,
  future `performed_at`.
- **`tests/Feature/Portal/PortalProfileEditTest.php`** (AC-15, AC-20, NC-01,
  NC-02): edit email/phone/emergency_contact success; invalid email rejected;
  `full_name`/`dni`/`status`/health notes are not editable (submitting them is
  ignored); own health notes visible read-only; another client's health notes
  never exposed.
- **`tests/Feature/Portal/PortalPolicyTest.php`** (AC-17, AC-19): CLIENT can
  `view`/`update` their OWN records and cannot on another's (cross-client
  denied) for Client/Membership/Cuota/Payment/Attendance/Booking/WorkoutLog/
  Routine; `viewAny`/`create`/`delete` remain staff-only; a multi-role
  ADMIN+CLIENT user still passes the staff abilities.

**Regression:**

- The existing `tests/Feature/Portal/PortalPresentationTest.php` (SPEC-015) is
  preserved **except** the final `renders no out-of-scope portal business
  content` test: SPEC-013 FR-001 adds portal navigation to `/portal`, so the
  `assertDontSee('Membership'/'Payment'/'Booking'/'Routine')` assertions are
  legitimately superseded (SPEC-015 §12 marked those sections out of scope for
  the BASE; SPEC-013 adds the navigation). That single test is replaced by
  SPEC-013's own navigation assertions in `PortalAccessTest`. All other SPEC-015
  assertions (heading, profile fields, ERR-005, logout form, no data leak) are
  unchanged. This is a scope extension, not a weakening.
- All existing SPEC-001..012 and SPEC-015 staff/Filament tests continue to pass;
  the admin policies' staff abilities are byte-for-byte unchanged (AC-19).

---

## 9. Risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Widening policies could accidentally grant a CLIENT staff access. | A bad `update`/`view` change could leak or allow cross-client/staff actions. | Only `||` CLIENT-own terms are added to instance `view`/`update`; `viewAny`/`create`/staff `update` clauses are untouched; `PortalPolicyTest` asserts staff abilities unchanged (AC-19). |
| Cross-client read/mutation via a forged record id. | Data leak / unauthorized mutation (C-13). | Reads are relationship-scoped (no request id); mutations use `$client->bookings()->findOrFail()` (404) + policy ownership + the `CreateBooking` self branch. `PortalIsolationTest` covers it. |
| `ClientPolicy::update` is broad (CLIENT may update own Client) while `Client` is broadly `fillable`. | A client could overwrite `full_name`/`dni`/`status`/health notes if the endpoint passed them. | The `UpdateProfileRequest` whitelists exactly 3 keys; the controller passes only `$request->validated()`. Field restriction is enforced at the request layer (ERR-011). |
| `CreateBooking` self/staff branch misclassified. | A staff booking for another client could be wrongly recorded as self-service, or a CLIENT could book for another. | Branch key is `auth()->user()->client?->id === $clientId` (unique ids → no false positives); the staff branch still `Gate::authorize('create', Booking::class)`. |
| Extracting the two WorkoutLog closure rules regresses the admin form. | Staff logging could break. | The rules move to `WorkoutLog` as pure static helpers; `WorkoutLogResource` calls them; existing `WorkoutLogManagementTest` must still pass. |
| Duplicated booking-window filter in `turnos()` drifts from `CreateBooking`. | The list could show a turno the Action rejects (or vice versa). | The list is documented presentational; `CreateBooking` is the authoritative re-validation; drift only affects UX, never correctness. |
| The self-log form's assigned-routine options vs. the `hasRoutineAssignmentTo()` (active-or-historical) validation. | Minor: the form offers current-routine rows only, while validation also accepts historical versions. | Design default: form offers current-routine rows (BR-007 privacy); validation reused unchanged (harmless superset — a client logging against their own historical prescription reveals nothing extra). See §12 OQ. |

---

## 10. Alternatives Considered

1. **One controller per portal section vs. a single hub controller.** A split
   adds 3–4 near-empty controllers with no behavioral benefit; the methods are
   thin and the business logic already lives in models/Actions. **Single
   `ClientPortalController` chosen** (the SPEC-015 precedent; OQ-01 resolved).
2. **Overloaded `create(User, ?Client)` or `createForSelf` policy abilities for
   self-booking/logging vs. Action/structural self-enforcement.** Overloading the
   standard `create` to take an optional client would muddy the staff-only admin
   contract; a `createForSelf` ability is non-standard. **Self-create enforced in
   the `CreateBooking` self branch and by the derived-client log endpoint chosen**
   (ADR-007). The instance `view`/`update` abilities are where ownership naturally
   lives (the model is available to compare to `auth()->user()->client`).
3. **A dedicated `PortalPolicy` / `canAccessOwnData` gate.** A new single policy
   would duplicate what the eight existing policies already express per model,
   and would leave the per-model policies inconsistent. **Extend the existing
   policies chosen** — one consistent ownership pattern across the domain.
4. **A `RecordWorkout` Action for the portal.** SPEC-011 §10 already decided "no
   Action" for the single-row validated insert; the portal mirrors that and only
   extracts the shared validation. **No new Action chosen.**
5. **Put the portal behind Filament.** ARCHITECTURE §5 fixes the client portal as
   the Laravel-web/Blade context; Filament is the staff context. **Blade chosen.**
6. **A public API for the portal.** ARCHITECTURE §19 says no speculative API;
   the portal is server-rendered. **Rejected.**
7. **Profile edit as a `ProvisionClientUser`-style Action.** A three-field
   validated Eloquent update needs no Action (AGENTS.md §9-10). **Form Request +
   controller chosen.**

The ownership-scoped self-access integration (how CLIENT self-access coexists
with the role-based staff policies without widening `create`/`viewAny`) is the one
genuinely new, repo-wide pattern and is documented in **ADR-007**.

---

## 11. Decision

Use the established SPEC-001/002/004/005/006/007/008/010/011/015 conventions,
extended only where the CLIENT self-service surface requires it:

- **Routes/controller:** a single `ClientPortalController` (extended) with the
  overview + 8 read sections + 4 POST actions, registered in one
  `auth` + `role:CLIENT` group under `/portal`; the index route keeps the name
  `portal`; all mutations use `POST`.
- **Reads:** relationship-scoped queries resolving `auth()->user()->client`
  (ERR-005 helper), never a request id — structural C-13 enforcement.
- **Mutations:** book = reused `CreateBooking` (self branch, `booked_by = null`);
  cancel = scoped lookup + `BookingPolicy::update` + `Booking::cancel()`; log =
  `StoreWorkoutLogRequest` (shared SPEC-011 rules) + controller injects
  `client_id`/`recorded_by`; profile = `UpdateProfileRequest` (3-field
  whitelist) + `ClientPolicy::update` + `Client::update()`.
- **Authorization:** instance `view`/`update` CLIENT-own extensions in the eight
  policies (ownership compared to `auth()->user()->clientId()`); `viewAny`/
  `create`/staff `update` unchanged; self-create in the Actions/endpoints
  (ADR-007). No staff ability is weakened; C-13 is absolute.
- **Validation:** reuse `CreateBooking`, `WorkoutLog::referenceRules()` +
  extracted assigned-version/active-exercise rules, and the SPEC-002 field
  constraints (no invented phone regex, no email-uniqueness against `users`).
- **Presentation:** Blade views extending `layouts/app.blade.php` + a shared
  portal-nav partial; no Filament; no new dependency.
- **No migrations, no events, no jobs, no external integrations.** One new ADR
  (ADR-007).

---

## 12. Open design notes (non-blocking; SPEC-013 §14.3)

- **OQ-01 (CP-02)** — controller split: resolved (§5) to a single hub controller.
- **OQ-02 (CP-06/CP-07)** — `reference`/`recorded_by` visibility in payments/
  attendance: resolved to hidden (CP-06/CP-07).
- **OQ-03 (CP-03)** — both log paths kept (assigned-routine + free), per SPEC-011
  WL-02/C-11.
- **OQ-04 (CP-05)** — staff prescription-vs-actual comparison stays staff-only;
  the client sees the flat history only.
- **Design default (assigned-routine log options):** the self-log form offers the
  current routine's set rows (BR-007 privacy); validation reuses
  `hasRoutineAssignmentTo()` (active or historical) unchanged — a harmless
  superset (see §9 risks).
- **Email uniqueness:** the portal email is format-validated only; `clients.email`
  is independent of `users.email` (SPEC-002 OQ-07) and is not unique-validated.
- **SPEC-015 test adjustment:** the `PortalPresentationTest` "no business content"
  assertion is superseded by FR-001 navigation (see §8); the Developer must
  update that one test.

---

## 13. Related Documents

- Specification: `docs/specs/SPEC-013.md`
- Architecture decisions: `docs/adr/ADR-001.md` (roles/authorization),
  `docs/adr/ADR-002.md` (Client link), `docs/adr/ADR-003.md` (validation-first),
  `docs/adr/ADR-004.md` (status-as-string), `docs/adr/ADR-006.md` (booking
  capacity atomicity), **`docs/adr/ADR-007.md`** (CLIENT self-access ownership
  pattern — new, this Specification)
- Architecture: `docs/architecture/SPEC-001.md`, `SPEC-002.md`, `SPEC-004.md`,
  `SPEC-005.md`, `SPEC-006.md`, `SPEC-007.md`, `SPEC-008.md`, `SPEC-010.md`,
  `SPEC-011.md`, `SPEC-015.md`; `ARCHITECTURE.md` (§5 presentation contexts,
  §12 authorization, §19 no API, §20 simplest correct architecture)
- Product: `docs/product/product-definition-v0.1.md`
- Domain: `docs/domain/domain-model-v0.1.md` (§Client, §Membership, §Payment,
  §Booking, §Attendance, §Routine, §WorkoutLog; C-01/C-02/C-13/C-15)
- Workflow state: `docs/sdd/state.yaml` (D-18 option 3 pre-approved)
- Development rules: `AGENTS.md` (§9-10, §13, §17)
