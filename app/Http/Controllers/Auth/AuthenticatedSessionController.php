<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();
        $request->session()->forget('active_workspace_id');

        $request->user()->forceFill([
            'last_login_at' => now(),
        ])->save();

        $workspace = $request->user()->workspaceMemberships()
            ->with('workspace')
            ->where('status', 'active')
            ->whereHas('workspace', fn ($query) => $query->where('status', 'active'))
            ->latest('id')
            ->first()?->workspace;

        if ($workspace === null && $request->user()->hasRole('admin')) {
            $workspace = Workspace::query()->where('status', 'active')->orderBy('id')->first();
        }

        if ($workspace !== null) {
            $request->session()->put('active_workspace_id', $workspace->getKey());
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
