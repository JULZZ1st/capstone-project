<?php

namespace App\Support\Authorization;

use App\Models\User;
use Illuminate\Support\Collection;

class ReportAccess
{
    /** @var array<string, list<string>> */
    private const TAB_PERMISSIONS = [
        'financial' => ['view_reports_donations', 'view_reports_disbursements'],
        'financial-ledgers' => ['view_reports_donations', 'view_reports_disbursements'],
        'inventory' => ['view_reports_inventory'],
        'in-kind-audits' => ['view_reports_inventory'],
        'philanthropy-roster' => ['view_reports_donations'],
        'reconciliation' => ['view_reports_reconciliation'],
        'audit' => ['view_reports_audit'],
    ];

    /** @return Collection<int, string> */
    public function allowedTabs(User $user): Collection
    {
        $tabs = collect(array_keys(self::TAB_PERMISSIONS));

        if ($user->hasRole('admin')) {
            return $tabs;
        }

        if (! $user->hasRole('staff')) {
            return collect();
        }

        $assigned = $user->securityPermissions()
            ->whereIn('key', $this->reportPermissionKeys())
            ->pluck('key');

        // Existing staff predate granular report permissions and retain the
        // report access their role already provided. Once report permissions
        // are assigned, they become an explicit allow-list.
        if ($assigned->isEmpty()) {
            return $tabs;
        }

        return $tabs->filter(
            fn (string $tab): bool => collect(self::TAB_PERMISSIONS[$tab])->intersect($assigned)->isNotEmpty()
        )->values();
    }

    public function can(User $user, string $tab): bool
    {
        return $this->allowedTabs($user)->contains($tab);
    }

    public function authorize(User $user, string $tab): void
    {
        abort_unless($this->can($user, $tab), 403);
    }

    /** @return list<string> */
    private function reportPermissionKeys(): array
    {
        return collect(self::TAB_PERMISSIONS)->flatten()->unique()->values()->all();
    }
}
