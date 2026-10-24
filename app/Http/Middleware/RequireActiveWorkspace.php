<?php

namespace App\Http\Middleware;

use App\Support\Workspaces\WorkspaceContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireActiveWorkspace
{
    public function __construct(private readonly WorkspaceContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->context->hasWorkspace()) {
            if ($request->user()?->hasRole('admin') && $request->isMethod('GET') && $request->routeIs('dashboard')) {
                return redirect()
                    ->route('workspaces.index')
                    ->with('error', 'Select an active workspace to continue.');
            }

            abort(403, 'An authorized active workspace is required.');
        }

        return $next($request);
    }
}
