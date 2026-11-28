<?php

namespace App\Services\SystemSecurity;

use App\Models\SecurityActivityEvent;
use App\Models\User;
use App\Support\Workspaces\WorkspaceContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SystemSecurityDashboardQuery
{
    public function get(array $filters): array
    {
        return [
            'personnel' => $this->personnel(
                $filters['staff_search'] ?? null,
                $filters['staff_role'] ?? null,
                $filters['staff_status'] ?? null,
            ),
            'activityEvents' => $this->activityEvents($filters),
            'actionOptions' => $this->actionOptions(),
            'filters' => $filters,
        ];
    }

    private function personnel(?string $search, ?string $role, ?string $status): Collection
    {
        return User::query()
            ->select(['id', 'role_id', 'first_name', 'last_name', 'name', 'email', 'status', 'last_login_at'])
            ->with([
                'role:id,name,display_name',
                'workspaceMemberships' => fn ($query) => $query
                    ->select(['id', 'user_id', 'workspace_id', 'status'])
                    ->where('workspace_id', app(WorkspaceContext::class)->requireId()),
            ])
            ->whereHas('role', fn ($query) => $query->whereIn('name', ['admin', 'staff']))
            ->when($role, fn ($query, string $role) => $query->whereHas('role', fn ($roleQuery) => $roleQuery->where('name', $role)))
            ->when($status, fn ($query, string $status) => $query->where('status', $status))
            ->where(function ($query): void {
                $query->whereHas('role', fn ($role) => $role->where('name', 'admin'))
                    ->orWhereHas('workspaceMemberships', function ($membership): void {
                        $membership->where('workspace_id', app(WorkspaceContext::class)->requireId())
                            ->whereIn('status', ['active', 'deactivated']);
                    });
            })
            ->when($search, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByRaw("case when status = ? then 0 else 1 end", [config('auth_defaults.user_status')])
            ->orderBy('role_id')
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
