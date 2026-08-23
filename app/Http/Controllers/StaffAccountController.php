<?php

namespace App\Http\Controllers;

use App\Http\Requests\SystemSecurity\CreateStaffAccountRequest;
use App\Http\Requests\SystemSecurity\DeactivateStaffAccountRequest;
use App\Http\Requests\SystemSecurity\UpdateStaffAccountRequest;
use App\Models\User;
use App\Services\SystemSecurity\CreateStaffAccount;
use App\Services\SystemSecurity\DeactivateStaffAccount;
use App\Services\SystemSecurity\UpdateStaffAccount;
use Illuminate\Http\RedirectResponse;

class StaffAccountController extends Controller
{
    public function store(CreateStaffAccountRequest $request, CreateStaffAccount $creator): RedirectResponse
    {
        $user = $creator->handle($request->validated(), $request->user());

        return redirect()
            ->route('system-security.index')
            ->with('success', sprintf('Staff account %s created.', $user->email));
    }

    public function update(UpdateStaffAccountRequest $request, User $user, UpdateStaffAccount $updater): RedirectResponse
    {
        $updater->handle($user, $request->validated(), $request->user());

        return redirect()
            ->route('system-security.index')
            ->with('success', 'Staff account updated.');
    }

    public function deactivate(DeactivateStaffAccountRequest $request, User $user, DeactivateStaffAccount $deactivator): RedirectResponse
    {
        $deactivator->handle($user, $request->user());

        return redirect()
            ->route('system-security.index')
            ->with('success', 'Staff account deactivated.');
    }
}
