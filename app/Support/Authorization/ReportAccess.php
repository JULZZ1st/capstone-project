<?php

namespace App\Support\Authorization;

use App\Models\User;
use Illuminate\Support\Collection;

class ReportAccess
{
    /** @var list<string> */
    private const ADMIN_TABS = [
        'financial',
        'inventory',
        'audit',
        'reconciliation',
        'financial-ledgers',
        'in-kind-audits',
        'philanthropy-roster',
    ];

    /**
     * Staff report access follows the operational reporting scope in the SRS:
     * cash ledger, reconciliation, donor, and in-kind activity. The audit
     * trail and consolidated financial summary remain Admin review reports.
     *
     * @var list<string>
     */
    private const STAFF_TABS = [
        'financial-ledgers',
        'inventory',
        'reconciliation',
        'in-kind-audits',
        'philanthropy-roster',
    ];

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
        if ($user->hasRole('admin')) {
            return collect(self::ADMIN_TABS);
        }

        if (! $user->hasRole('staff')) {
            return collect();
        }

        $tabs = collect(self::STAFF_TABS);

        $assigned = $user->securityPermissions()
            ->whereIn('key', $this->reportPermissionKeys())
            ->pluck('key');

        // Existing Staff accounts may not have report permission rows because
        // the role-level operational boundary predates granular report
        // permissions. Their safe baseline is the Staff report set above.
        // Once report permissions are assigned, they narrow that baseline to
        // the explicitly permitted report domains.
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
