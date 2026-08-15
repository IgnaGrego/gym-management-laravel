<?php

namespace App\Http\Controllers\Auth;

use App\Actions\RegisterClient;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function create(): View { return view('auth.register'); }

    public function store(RegisterRequest $request, RegisterClient $registerClient): RedirectResponse
    {
        $registerClient->handle($request->validated());
        return redirect()->route('register.complete');
    }

    public function complete(): View { return view('auth.registration-complete'); }
}
