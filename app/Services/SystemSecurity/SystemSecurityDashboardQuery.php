<?php

namespace App\Services\SystemSecurity;

use App\Models\SecurityActivityEvent;
use App\Models\SecurityPermission;
use App\Models\User;
use App\Support\Workspaces\WorkspaceContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SystemSecurityDashboardQuery
{
    public function get(array $filters): array
    {
        return [
            'personnel' => $this->personnel(),
            'permissions' => $this->permissions(),
            'activityEvents' => $this->activityEvents($filters),
            'actionOptions' => $this->actionOptions(),
            'filters' => $filters,
        ];
    }

    private function personnel(): Collection
    {
        return User::query()
            ->select(['id', 'role_id', 'first_name', 'last_name', 'name', 'email', 'status', 'last_login_at'])
            ->with([
                'role:id,name,display_name',
                'securityProfile:id,user_id,two_factor_enabled,two_factor_enforced,two_factor_verified_at,security_key_suffix',
                'securityPermissions:id,key,name,sort_order',
            ])
            ->whereHas('role', fn ($query) => $query->whereIn('name', auth()->user()?->isSuperAdmin() ? ['admin', 'secretary', 'treasurer'] : ['secretary', 'treasurer']))
            ->whereHas('workspaceMemberships', function ($query): void {
                $query->where('workspace_id', app(WorkspaceContext::class)->requireId())
                    ->where('status', 'active');
            })
            ->orderByRaw("case when status = ? then 0 else 1 end", [config('auth_defaults.user_status')])
            ->orderBy('role_id')
            ->orderBy('name')
            ->get();
    }

    private function permissions(): Collection
    {
        return SecurityPermission::query()
            ->select(['id', 'key', 'name', 'description', 'sort_order'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function activityEvents(array $filters): LengthAwarePaginator
    {
        return SecurityActivityEvent::query()
            ->select(['id', 'user_id', 'actor_id', 'action', 'summary', 'metadata', 'ip_address', 'occurred_at'])
            ->with([
                'user:id,name,email,role_id',
                'user.role:id,name,display_name',
                'actor:id,name,email,role_id',
                'actor.role:id,name,display_name',
            ])
            ->action($filters['action'] ?? null)
            ->search($filters['search'] ?? null)
            ->latest('occurred_at')
            ->paginate(config('system_security.pagination.activity_events'))
            ->withQueryString();
    }

    private function actionOptions(): Collection
    {
        return SecurityActivityEvent::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->mapWithKeys(fn (string $action) => [$action => str($action)->replace('_', ' ')->title()->toString()]);
    }
}
