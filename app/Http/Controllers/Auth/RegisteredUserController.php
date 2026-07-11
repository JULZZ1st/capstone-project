<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $validated = $request->validated();
            $role = $this->defaultRole();

            return User::query()->create([
                'role_id' => $role->id,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'name' => $validated['first_name'].' '.$validated['last_name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'status' => config('auth_defaults.user_status'),
            ]);
        });

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('dashboard')
            ->with('status', 'Account created successfully.');
    }

    private function defaultRole(): Role
    {
        $roleName = config('auth_defaults.default_role');
        $roleConfig = config("auth_defaults.roles.{$roleName}");

        abort_if(! $roleConfig, 500, 'Default role is not configured.');

        return Role::query()->firstOrCreate(
            ['name' => $roleName],
            [
                'display_name' => $roleConfig['display_name'],
                'description' => $roleConfig['description'],
            ],
        );
    }
}
