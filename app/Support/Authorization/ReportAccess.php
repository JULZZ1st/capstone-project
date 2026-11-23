<?php

namespace App\Support\Authorization;

use App\Models\User;
use Illuminate\Support\Collection;

class ReportAccess
{
    public const PERMISSION_BY_TAB = [
        'financial' => ['view_reports_donations', 'view_reports_disbursements'],
        'financial-ledgers' => ['view_reports_donations', 'view_reports_disbursements'],
        'philanthropy-roster' => ['view_reports_donations'],
        'inventory' => ['view_reports_inventory'],
        'in-kind-audits' => ['view_reports_inventory'],
        'audit' => ['view_reports_audit'],
        'reconciliation' => ['view_reports_reconciliation'],
    ];

    public function can(User $user, string $tab): bool
    {
        return $user->hasRole('admin') || $user->hasAnySecurityPermission(self::PERMISSION_BY_TAB[$tab] ?? []);
    }

    public function allowedTabs(User $user): Collection
    {
        return collect(array_keys(self::PERMISSION_BY_TAB))->filter(fn (string $tab): bool => $this->can($user, $tab))->values();
    }

    public function authorize(User $user, string $tab): void
    {
        abort_unless($this->can($user, $tab), 403);
    }
}
