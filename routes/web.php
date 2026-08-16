<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\ClientPortalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| SPEC-001 Authentication & Roles. Registration and profile routes are NOT
| registered (public registration is deferred to SPEC-012).
|
*/

Route::get('/', function () {
    $user = auth()->user();

    if ($user !== null) {
        if ($user->hasAnyRole([App\Models\Role::ADMIN, App\Models\Role::TRAINER])) {
            return redirect('/admin');
        }

        if ($user->hasRole(App\Models\Role::CLIENT)) {
            return redirect('/portal');
        }
    }

    return view('welcome');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');

    Route::get('/register', [RegistrationController::class, 'create'])->name('register');
    Route::post('/register', [RegistrationController::class, 'store'])
        ->middleware('throttle:registration')->name('register.store');
    Route::get('/register/complete', [RegistrationController::class, 'complete'])
        ->name('register.complete');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});

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
