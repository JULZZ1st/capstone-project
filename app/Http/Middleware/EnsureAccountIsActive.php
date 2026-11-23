<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && Str::lower(trim((string) $user->status)) !== Str::lower(trim((string) config('auth_defaults.user_status')))) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'This account has been deactivated.'], 401);
            }

            return redirect()->route('login')->withErrors(['auth' => 'This account has been deactivated.']);
        }

        return $next($request);
    }
}
