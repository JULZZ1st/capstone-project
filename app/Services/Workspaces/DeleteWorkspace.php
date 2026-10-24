<?php

namespace App\Services\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DeleteWorkspace
{
    private const OPERATIONAL_TABLES = [
        'donors',
        'campaigns',
        'contributions',
        'contribution_items',
        'inventory_categories',
        'inventory_subcategories',
        'inventory_units',
        'inventory_items',
        'inventory_stock_movements',
        'cash_disbursements',
        'bank_ledger_entries',
        'bank_reconciliations',
        'reconciliation_discrepancies',
        'bank_withdrawal_disbursement_matches',
    ];

    public function handle(Workspace $workspace): void
    {
        DB::transaction(function () use ($workspace): void {
            $workspace = Workspace::query()->lockForUpdate()->findOrFail($workspace->getKey());

            foreach (self::OPERATIONAL_TABLES as $table) {
                if (Schema::hasTable($table)
                    && Schema::hasColumn($table, 'workspace_id')
                    && DB::table($table)->where('workspace_id', $workspace->getKey())->exists()) {
                    throw ValidationException::withMessages([
                        'workspace' => 'This workspace has operational records and cannot be deleted. Deactivate it instead.',
                    ]);
                }
            }

            $memberIds = $workspace->memberships()->pluck('user_id');
            $workspace->delete();

            User::query()
                ->whereIn('id', $memberIds)
                ->whereDoesntHave('role', fn ($query) => $query->where('name', 'admin'))
                ->whereDoesntHave('workspaceMemberships', fn ($query) => $query->where('status', 'active'))
                ->update(['status' => 'inactive']);
        });
    }
}
