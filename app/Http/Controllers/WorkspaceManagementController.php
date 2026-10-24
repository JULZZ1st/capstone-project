<?php

namespace App\Http\Controllers;

use App\Http\Requests\Workspace\CreateWorkspaceRequest;
use App\Models\Workspace;
use App\Services\Workspaces\CreateWorkspaceWithAdmin;
use App\Services\Workspaces\DeleteWorkspace;
use App\Support\Workspaces\WorkspaceAuthorizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceManagementController extends Controller
{
    public function index(): View
    {
        $workspaces = Workspace::query()
            ->with(['memberships.user', 'memberships.role'])
            ->withCount(['memberships as active_member_count' => fn ($query) => $query->where('status', 'active')])
            ->orderBy('name')
            ->get();

        return view('workspaces.index', [
            'workspaces' => $workspaces,
        ]);
    }

    public function store(CreateWorkspaceRequest $request, CreateWorkspaceWithAdmin $creator): RedirectResponse
    {
        $workspace = $creator->handle($request->validated(), $request->user());

        return redirect()->route('workspaces.index')->with('success', "Workspace {$workspace->name} created with its Admin account.");
    }

    public function activate(Request $request, Workspace $workspace, WorkspaceAuthorizer $authorizer): RedirectResponse
    {
        abort_unless($authorizer->canAccess($request->user(), $workspace), 403);

        $request->session()->put('active_workspace_id', $workspace->getKey());

        return redirect()->route('dashboard');
    }

    public function toggle(Workspace $workspace): RedirectResponse
    {
        $workspace->update(['status' => $workspace->isActive() ? 'inactive' : 'active']);

        return redirect()->route('workspaces.index')->with('success', "Workspace {$workspace->name} status updated.");
    }

    public function destroy(Workspace $workspace, DeleteWorkspace $deleter): RedirectResponse
    {
        $name = $workspace->name;
        $deleter->handle($workspace);

        return redirect()->route('workspaces.index')->with('success', "Workspace {$name} deleted.");
    }
}
