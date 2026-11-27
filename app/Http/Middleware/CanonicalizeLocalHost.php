<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CanonicalizeLocalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local')
            && config('app.local_server_host') === '127.0.0.1'
            && $request->getHost() === 'localhost'
            && in_array($request->method(), ['GET', 'HEAD'], true)) {
            return redirect($request->getScheme().'://127.0.0.1:'.$request->getPort().$request->getRequestUri());
        }

        return $next($request);
    }
}
