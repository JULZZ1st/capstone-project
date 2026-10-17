<?php

namespace App\Http\Controllers;

use App\Http\Requests\Workspace\CreateWorkspaceRequest;
use App\Models\Workspace;
use App\Services\Workspaces\CreateWorkspaceWithAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WorkspaceManagementController extends Controller
{
    public function index(): View
    {
        return view('workspaces.index', [
            'workspaces' => Workspace::query()
                ->with(['memberships.user', 'memberships.role'])
                ->withCount(['memberships as active_member_count' => fn ($query) => $query->where('status', 'active')])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(CreateWorkspaceRequest $request, CreateWorkspaceWithAdmin $creator): RedirectResponse
    {
        $workspace = $creator->handle($request->validated(), $request->user());

        return redirect()->route('workspaces.index')->with('success', "Workspace {$workspace->name} created with its Admin account.");
    }

    public function toggle(Workspace $workspace): RedirectResponse
    {
        abort_unless($workspace->slug !== 'legacy', 422, 'The legacy workspace cannot be deactivated.');

        $workspace->update(['status' => $workspace->isActive() ? 'inactive' : 'active']);

        return redirect()->route('workspaces.index')->with('success', "Workspace {$workspace->name} status updated.");
    }
}
