<?php

namespace App\Http\Controllers;

use App\Actions\CreateBooking;
use App\Http\Requests\Portal\StoreWorkoutLogRequest;
use App\Http\Requests\Portal\UpdateProfileRequest;
use App\Models\Client;
use App\Models\Exercise;
use App\Models\Turno;
use App\Models\WorkoutLog;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClientPortalController extends Controller
{
    /**
     * Overview (SPEC-013 FR-001; SPEC-015 base + navigation).
     */
    public function index(): View
    {
        return $this->renderPortal('portal');
    }

    /**
     * Own memberships, chronological (SPEC-013 FR-002, AC-2).
     */
    public function memberships(): View
    {
        return $this->renderPortal('portal.memberships', [
            'memberships' => auth()->user()->client?->memberships()
                ->with('plan')
                ->orderBy('start_date')
                ->get() ?? collect(),
        ]);
    }

    /**
     * Own cuotas and payments, read-only (SPEC-013 FR-003, CP-06).
     */
    public function payments(): View
    {
        return $this->renderPortal('portal.payments', [
            'memberships' => auth()->user()->client?->memberships()
                ->with(['plan', 'cuota.payments'])
                ->orderBy('start_date')
                ->get() ?? collect(),
        ]);
    }

    /**
     * Own attendance history, chronological (SPEC-013 FR-004, CP-07).
     */
    public function attendance(): View
    {
        return $this->renderPortal('portal.attendance', [
            'attendances' => auth()->user()->client?->attendances()
                ->with('turno')
                ->orderBy('attended_at')
                ->get() ?? collect(),
        ]);
    }

    /**
     * Own bookings (SPEC-013 FR-005).
     */
    public function bookings(): View
    {
        return $this->renderPortal('portal.bookings', [
            'bookings' => auth()->user()->client?->bookings()
                ->with('turno')
                ->orderByDesc('booked_at')
                ->get() ?? collect(),
        ]);
    }

    /**
     * Presentational bookable-turnos list (SPEC-013 FR-006).
     */
    public function turnos(): View
    {
        return $this->renderPortal('portal.turnos', [
            'turnos' => $this->bookableTurnos(),
        ]);
    }

    /**
     * Own current routine assignment, read-only (SPEC-013 FR-008, BR-007).
     */
    public function routine(): View
    {
        return $this->renderPortal('portal.routine', [
            'routine' => auth()->user()->client?->currentRoutine()?->load('days.exercises.exercise'),
        ]);
    }

    /**
     * Own workout history + self-log form data (SPEC-013 FR-009, FR-010).
     */
    public function workouts(): View
    {
        $client = auth()->user()->client;

        return $this->renderPortal('portal.workouts', [
            'workoutLogs' => $client?->workoutLogs()
                ->with('routineExercise.exercise', 'exercise')
                ->orderByDesc('performed_at')
                ->get() ?? collect(),
            'routine' => $client?->currentRoutine()?->load('days.exercises.exercise'),
            'exercises' => Exercise::active()->orderBy('name')->get(),
        ]);
    }

    /**
     * Own profile + read-only health notes + edit form (SPEC-013 FR-011).
     */
    public function profile(): View
    {
        return $this->renderPortal('portal.profile');
    }

    /**
     * Book a turno for the authenticated CLIENT's own Client record
     * (SPEC-013 FR-006, BR-004), reusing the CreateBooking Action.
     */
    public function book(int $turno): RedirectResponse
    {
        $client = $this->resolveClient();

        try {
            app(CreateBooking::class)->handle($client->id, $turno, null);
        } catch (ValidationException $e) {
            return redirect()->route('portal.turnos')->withErrors($e->errors())->withInput();
        }

        return redirect()->route('portal.bookings')->with('status', 'Tu turno fue reservado.');
    }

    /**
     * Cancel one of the authenticated CLIENT's own bookings
     * (SPEC-013 FR-007, BR-005).
     */
    public function cancelBooking(int $booking): RedirectResponse
    {
        $client = $this->resolveClient();

        $booking = $client->bookings()->findOrFail($booking);

        Gate::authorize('update', $booking);

        try {
            $booking->cancel();
        } catch (DomainException $e) {
            return redirect()->route('portal.bookings')->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('portal.bookings')->with('status', 'Tu reserva fue cancelada.');
    }

    /**
     * Log a workout for the authenticated CLIENT (SPEC-013 FR-009, BR-006).
     */
    public function storeWorkout(StoreWorkoutLogRequest $request): RedirectResponse
    {
        $client = $this->resolveClient();

        WorkoutLog::create([
            ...$request->validated(),
            'client_id' => $client->id,
            'recorded_by' => auth()->id(),
        ]);

        return redirect()->route('portal.workouts')->with('status', 'Tu entrenamiento fue registrado.');
    }

    /**
     * Edit the authenticated CLIENT's own contact fields (SPEC-013 FR-011,
     * BR-008, NC-01).
     */
    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $client = $this->resolveClient();

        Gate::authorize('update', $client);

        $client->update($request->validated());

        return redirect()->route('portal.profile')->with('status', 'Tu perfil fue actualizado.');
    }

    /**
     * Centralize the ERR-005 boundary (SPEC-013 BR-010, ERR-001; SPEC-015
     * ERR-005): a CLIENT with no linked Client record renders the generic
     * notice and no portal business data, for every section.
     */
    private function renderPortal(string $view, array $data = []): View
    {
        $client = auth()->user()->client;

        if ($client === null) {
            return view('portal', ['user' => auth()->user(), 'client' => null]);
        }

        return view($view, array_merge(['user' => auth()->user(), 'client' => $client], $data));
    }

    /**
     * Resolve the authenticated CLIENT's own Client record for a mutation
     * (BR-002, C-13); 404 when unlinked so no action is possible without an
     * owning Client (AF-001).
     */
    private function resolveClient(): Client
    {
        $client = auth()->user()->client;

        if ($client === null) {
            abort(404);
        }

        return $client;
    }

    /**
     * The presentational, non-authoritative bookable-turnos projection
     * (SPEC-013 §5; SPEC-007 §5 stance): active turnos within today..+7 whose
     * same-day start has not passed and which are not full. CreateBooking
     * re-validates the same window and capacity atomically (ERR-007).
     */
    private function bookableTurnos(): Collection
    {
        $today = Carbon::today();
        $windowEnd = $today->copy()->addDays(7);

        return Turno::query()
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
    }
}
