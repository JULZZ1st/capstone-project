<?php

namespace App\Services\SystemSecurity;

use App\Models\Role;
use App\Models\SecurityActivityEvent;
use App\Models\User;
use App\Support\Workspaces\WorkspaceContext;
use Illuminate\Support\Facades\DB;

class DeactivateStaffAccount
{
    public function handle(User $user, User $actor): User
    {
        return DB::transaction(function () use ($user, $actor): User {
            $roleName = $user->roleName();

            if ($roleName === 'admin') {
                $activeAdmins = User::query()
                    ->whereHas('role', fn ($q) => $q->where('name', 'admin'))
                    ->whereHas('workspaceMemberships', function ($q): void {
                        $q->where('workspace_id', app(WorkspaceContext::class)->requireId())
                            ->where('status', 'active');
                    })
                    ->where('status', config('auth_defaults.user_status'))
                    ->count();

                if ($activeAdmins <= 1) {
                    abort(422, 'Cannot deactivate the last remaining admin account.');
                }
            }

            $user->forceFill(['status' => 'deactivated'])->save();

            SecurityActivityEvent::query()->create([
                'user_id' => $user->id,
                'actor_id' => $actor->id,
                'action' => 'staff_account_deactivated',
                'summary' => sprintf('%s deactivated staff account %s', $actor->name, $user->email),
                'metadata' => ['previous_status' => $user->getOriginal('status')],
                'ip_address' => request()->ip(),
                'occurred_at' => now(),
            ]);

            return $user;
        });
    }
}
