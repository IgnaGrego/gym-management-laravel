<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view (SPEC-001 FR-001).
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request (SPEC-001 FR-001).
     *
     * Deactivated users are rejected with the same generic message as invalid
     * credentials so the response never reveals whether the email exists or
     * whether the account is deactivated (SPEC-001 ERR-001, ERR-002, A-05).
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attemptWhen($credentials, fn (User $user) => $user->is_active)) {
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();

        if (! $user->hasAnyRole([Role::ADMIN, Role::TRAINER, Role::CLIENT])) {
            $request->session()->flash(
                'status',
                'Your account has no assigned roles yet. Contact an administrator.'
            );
        }

        return redirect()->intended($this->redirectTo($user));
    }

    /**
     * Destroy an authenticated session (SPEC-001 FR-002).
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Post-login landing context.
     *
     * Default rule (OQ-04, architecture SPEC-001 §12): a user holding a staff
     * role (ADMIN or TRAINER) is redirected to the admin panel; otherwise a
     * CLIENT is redirected to the client portal; a user with no roles is
     * redirected to the public landing page.
     */
    protected function redirectTo(User $user): string
    {
        if ($user->hasAnyRole([Role::ADMIN, Role::TRAINER])) {
            return '/admin';
        }

        if ($user->hasRole(Role::CLIENT)) {
            return '/portal';
        }

        return '/';
    }
}
