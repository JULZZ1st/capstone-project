<?php

namespace App\Http\Middleware;

use App\Support\Workspaces\WorkspaceContext;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceRouteModels
{
    public function __construct(private readonly WorkspaceContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $workspaceId = $this->context->id();

        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if (! $parameter instanceof Model || ! array_key_exists('workspace_id', $parameter->getAttributes())) {
                continue;
            }

            abort_unless(
                $workspaceId !== null
                    && $parameter->getAttribute('workspace_id') !== null
                    && (int) $parameter->getAttribute('workspace_id') === (int) $workspaceId,
                404,
            );
        }

        return $next($request);
    }
}
