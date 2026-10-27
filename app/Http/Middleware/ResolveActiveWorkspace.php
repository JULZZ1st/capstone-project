<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\Workspace;
use App\Support\Workspaces\WorkspaceAuthorizer;
use App\Support\Workspaces\WorkspaceContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveActiveWorkspace
{
    public function __construct(
        private readonly WorkspaceContext $context,
        private readonly WorkspaceAuthorizer $authorizer,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->context->clear();

        $user = $request->user();
        if (! $user instanceof User) {
            return $next($request);
        }

        if ($user->hasRole('admin')) {
            $sessionWorkspaceId = $request->session()->get('active_workspace_id');
            $workspace = $sessionWorkspaceId !== null ? Workspace::query()->find($sessionWorkspaceId) : null;

            if ($workspace === null || ! $this->authorizer->canAccess($user, $workspace)) {
                $workspace = $user->workspaceMemberships()
                    ->with('workspace')
                    ->where('status', 'active')
                    ->whereHas('workspace', fn ($query) => $query->where('status', 'active'))
                    ->latest('id')
                    ->first()?->workspace
                    ?? Workspace::query()->where('status', 'active')->orderBy('id')->first();
            }

            if ($workspace !== null) {
                $this->context->set($workspace);
                $request->session()->put('active_workspace_id', $workspace->getKey());
            } else {
                $request->session()->forget('active_workspace_id');
            }

            return $next($request);
        }

        $workspaceId = $request->session()->get('active_workspace_id');
        if ($workspaceId !== null) {
            $workspace = Workspace::query()->find($workspaceId);

            if ($workspace === null || ! $this->authorizer->canAccess($user, $workspace)) {
                $request->session()->forget('active_workspace_id');

                if ($workspace !== null && ! $user->hasRole('admin')) {
                    abort(403, 'You are not authorized to access this workspace.');
                }
            } else {
                $this->context->set($workspace);
            }
        }

        if (! $this->context->hasWorkspace()) {
            $membership = $user->workspaceMemberships()
                ->with('workspace')
                ->where('status', 'active')
                ->whereHas('workspace', fn ($query) => $query->where('status', 'active'))
                ->latest('id')
                ->first();

            if ($membership?->workspace !== null) {
                $this->context->set($membership->workspace);
                $request->session()->put('active_workspace_id', $membership->workspace->getKey());
            }
        }

        return $next($request);
    }
}
