<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ChangePasswordController extends Controller
{
    public function create(): View
    {
        return view('auth.change-password');
    }

    public function store(ChangePasswordRequest $request): RedirectResponse
    {
        $request->user()->forceFill([
            // User::password is cast with Laravel's `hashed` cast. Passing the
            // plain value here lets the model hash it exactly once.
            'password' => $request->validated('password'),
            'must_change_password' => false,
        ])->save();

        return redirect()->route('dashboard')->with('status', 'Your password has been changed successfully.');
    }
}
