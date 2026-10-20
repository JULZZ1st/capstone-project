<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Workspace;
use App\Support\Workspaces\WorkspaceAuthorizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly WorkspaceAuthorizer $workspaceAuthorizer)
    {
    }

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
            $activeWorkspace = $this->authorizedActiveWorkspace($request);

            if ($activeWorkspace instanceof Workspace) {
                $request->session()->put('active_workspace_id', $activeWorkspace->getKey());

                return redirect()->intended(route('dashboard', absolute: false));
            }

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

    private function authorizedActiveWorkspace(Request $request): ?Workspace
    {
        $workspaceId = $request->session()->get('active_workspace_id');

        if ($workspaceId === null) {
            return null;
        }

        $workspace = Workspace::query()->find($workspaceId);

        return $workspace instanceof Workspace
            && $this->workspaceAuthorizer->canAccess($request->user(), $workspace)
            ? $workspace
            : null;
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
