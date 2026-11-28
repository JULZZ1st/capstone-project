<?php

namespace App\Services\SystemSecurity;

use App\Models\SecurityActivityEvent;
use App\Models\User;
use App\Support\Workspaces\WorkspaceContext;
use Illuminate\Support\Facades\DB;

class ToggleStaffAccountStatus
{
    public function handle(User $user, User $actor): User
    {
        abort_unless($user->roleName() === 'staff', 403, 'Only Staff accounts can be activated or deactivated.');

        return DB::transaction(function () use ($user, $actor): User {
            $isActive = $user->status === config('auth_defaults.user_status');
            $status = $isActive ? 'deactivated' : config('auth_defaults.user_status');
            $action = $isActive ? 'staff_account_deactivated' : 'staff_account_reactivated';
            $verb = $isActive ? 'deactivated' : 'reactivated';

            $user->forceFill(['status' => $status])->save();
            $user->workspaceMemberships()
                ->where('workspace_id', app(WorkspaceContext::class)->requireId())
                ->update(['status' => $status === config('auth_defaults.user_status') ? 'active' : 'deactivated']);

            SecurityActivityEvent::query()->create([
                'user_id' => $user->id,
                'actor_id' => $actor->id,
                'action' => $action,
                'summary' => sprintf('%s %s staff account %s', $actor->name, $verb, $user->email),
                'metadata' => ['previous_status' => $isActive ? config('auth_defaults.user_status') : 'deactivated', 'status' => $status],
                'ip_address' => request()->ip(),
                'occurred_at' => now(),
            ]);

            return $user;
        });
    }
}
