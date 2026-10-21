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

        $request->user()->forceFill([
            'last_login_at' => now(),
        ])->save();

        if ($request->user()->isSuperAdmin()) {
            $request->session()->forget('active_workspace_id');

            return redirect()->route('workspaces.index');
        }

        if (! $request->user()->isSuperAdmin()) {
            // A browser session can survive an account switch in some session
            // drivers. Never carry another user's workspace into this login.
            $workspace = $request->user()->workspaceMemberships()
                ->with('workspace')
                ->where('status', 'active')
                ->whereHas('workspace', fn ($query) => $query->where('status', 'active'))
                ->latest('id')
                ->first()?->workspace;

            $workspace instanceof Workspace
                ? $request->session()->put('active_workspace_id', $workspace->getKey())
                : $request->session()->forget('active_workspace_id');
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
