<?php

namespace App\Http\Controllers;

use App\Http\Requests\SystemSecurity\SystemSecurityIndexRequest;
use App\Http\Requests\SystemSecurity\ToggleTwoFactorEnforcementRequest;
use App\Models\User;
use App\Services\SystemSecurity\SystemSecurityDashboardQuery;
use App\Services\SystemSecurity\ToggleTwoFactorEnforcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SystemSecurityController extends Controller
{
    public function index(SystemSecurityIndexRequest $request, SystemSecurityDashboardQuery $query): View
    {
        return view('system-security', $query->get($request->validated()));
    }

    public function updateTwoFactorEnforcement(
        ToggleTwoFactorEnforcementRequest $request,
        User $user,
        ToggleTwoFactorEnforcement $toggle,
    ): RedirectResponse {
        $toggle->handle(
            $user,
            (bool) $request->validated('enforced'),
            $request->user(),
            $request,
        );

        return redirect()
            ->route('system-security.index')
            ->with('success', 'Two-factor enforcement updated successfully.');
    }
}
