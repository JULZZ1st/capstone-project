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
        abort_unless($this->context->hasWorkspace(), 403, 'An authorized active workspace is required.');

        return $next($request);
    }
}
