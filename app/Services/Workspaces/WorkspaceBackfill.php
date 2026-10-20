<?php

namespace App\Services\Workspaces;

use App\Models\Workspace;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkspaceBackfill
{
    public function run(): array
    {
        return DB::transaction(function (): array {
            $workspace = Workspace::query()->firstOrCreate(
                ['slug' => 'legacy'],
                ['name' => 'Legacy Workspace', 'status' => 'active'],
            );

            $before = $this->financialTotals();
            $assigned = [];

            $this->backfillUsers($workspace, $assigned);

            foreach ([
                'donors',
                'campaigns',
                'inventory_categories',
                'inventory_units',
                'inventory_items',
                'cash_disbursements',
                'bank_ledger_entries',
                'bank_reconciliations',
            ] as $table) {
                $assigned[$table] = $this->assignNullWorkspace($table, $workspace->id);
            }

            $this->assignContributionWorkspaces($assigned);
            $this->assignContributionItemWorkspaces($assigned);
            $this->assignInventorySubcategoryWorkspaces($assigned);
            $this->assignInventoryMovementWorkspaces($assigned);
            $this->assignReconciliationDiscrepancyWorkspaces($assigned);
            $this->assignWithdrawalMatchWorkspaces($assigned);
            $this->assignAuditWorkspaces($assigned);
            $this->validateRelationships();

            $after = $this->financialTotals();
            if ($before !== $after) {
                $this->recordIssue(
                    'workspace_backfill_issues',
                    0,
                    'financial_totals_changed',
                    json_encode(['before' => $before, 'after' => $after], JSON_THROW_ON_ERROR),
                );
            }

            $result = [
                'workspace_id' => $workspace->id,
                'assigned' => $assigned,
                'issues' => DB::table('workspace_backfill_issues')->count(),
                'financial_totals_before' => $before,
                'financial_totals_after' => $after,
            ];

            if ($result['issues'] > 0) {
                Log::warning('Workspace backfill completed with review issues.', $result);
            }

            return $result;
        });
    }

    private function backfillUsers(Workspace $workspace, array &$assigned): void
    {
        foreach (DB::table('users')->select(['id', 'role_id'])->get() as $user) {
            if ($user->role_id === null) {
                $this->recordIssue('users', $user->id, 'missing_role', 'User cannot receive a workspace membership without a role.');
                continue;
            }

            $otherWorkspaceMembership = DB::table('workspace_memberships')
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->where('workspace_id', '!=', $workspace->id)
                ->exists();

            if ($otherWorkspaceMembership) {
                $this->recordIssue('users', $user->id, 'existing_workspace_membership', 'User already has an active membership outside the legacy workspace; no legacy membership was added.');
                continue;
            }

            $membership = DB::table('workspace_memberships')
                ->where('workspace_id', $workspace->id)
                ->where('user_id', $user->id)
                ->first();

            if ($membership === null) {
                DB::table('workspace_memberships')->insert([
                    'workspace_id' => $workspace->id,
                    'user_id' => $user->id,
                    'role_id' => $user->role_id,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $assigned['users'] = ($assigned['users'] ?? 0) + 1;
                continue;
            }

            if ((int) $membership->role_id !== (int) $user->role_id) {
                $this->recordIssue('users', $user->id, 'membership_role_mismatch', 'Existing membership role differs from users.role_id; membership was not changed.');
            }
        }
    }

    private function assignNullWorkspace(string $table, int $workspaceId): int
    {
        return DB::table($table)->whereNull('workspace_id')->update([
            'workspace_id' => $workspaceId,
            'updated_at' => now(),
        ]);
    }

    private function assignContributionWorkspaces(array &$assigned): void
    {
        foreach (DB::table('contributions')->select(['id', 'donor_id', 'campaign_id', 'workspace_id'])->get() as $row) {
            $donorWorkspace = DB::table('donors')->where('id', $row->donor_id)->value('workspace_id');
            $campaignWorkspace = $row->campaign_id === null
                ? null
                : DB::table('campaigns')->where('id', $row->campaign_id)->value('workspace_id');

            if ($donorWorkspace === null) {
                $this->recordIssue('contributions', $row->id, 'missing_donor_workspace', 'Contribution donor does not have workspace ownership.');
                continue;
            }
            if ($row->campaign_id !== null && $campaignWorkspace === null) {
                $this->recordIssue('contributions', $row->id, 'missing_campaign_workspace', 'Contribution campaign does not have workspace ownership.');
                continue;
            }
            if ($campaignWorkspace !== null && (int) $campaignWorkspace !== (int) $donorWorkspace) {
                $this->recordIssue('contributions', $row->id, 'donor_campaign_workspace_mismatch', 'Contribution donor and campaign belong to different workspaces.');
                continue;
            }
            if ($row->workspace_id !== null && (int) $row->workspace_id !== (int) $donorWorkspace) {
                $this->recordIssue('contributions', $row->id, 'existing_workspace_mismatch', 'Existing contribution workspace differs from donor workspace.');
                continue;
            }

            if ($row->workspace_id === null) {
                $assigned['contributions'] = ($assigned['contributions'] ?? 0) + DB::table('contributions')->where('id', $row->id)->update([
                    'workspace_id' => $donorWorkspace,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function assignContributionItemWorkspaces(array &$assigned): void
    {
        $this->assignFromParent('contribution_items', 'contribution_id', 'contributions', $assigned);
    }

    private function assignInventorySubcategoryWorkspaces(array &$assigned): void
    {
        foreach (DB::table('inventory_subcategories')->select(['id', 'inventory_category_id', 'workspace_id'])->get() as $row) {
            $parentWorkspace = DB::table('inventory_categories')->where('id', $row->inventory_category_id)->value('workspace_id');
            $this->assignOrReport('inventory_subcategories', $row->id, $row->workspace_id, $parentWorkspace, 'category_workspace_missing', 'subcategory_category_workspace_mismatch', $assigned);
        }
    }

    private function assignInventoryMovementWorkspaces(array &$assigned): void
    {
        $this->assignFromParent('inventory_stock_movements', 'inventory_item_id', 'inventory_items', $assigned);
    }

    private function assignReconciliationDiscrepancyWorkspaces(array &$assigned): void
    {
        $this->assignFromParent('reconciliation_discrepancies', 'bank_reconciliation_id', 'bank_reconciliations', $assigned);
    }

    private function assignWithdrawalMatchWorkspaces(array &$assigned): void
    {
        foreach (DB::table('bank_withdrawal_disbursement_matches')->select(['id', 'bank_ledger_entry_id', 'cash_disbursement_id', 'workspace_id'])->get() as $row) {
            $ledgerWorkspace = DB::table('bank_ledger_entries')->where('id', $row->bank_ledger_entry_id)->value('workspace_id');
            $disbursementWorkspace = DB::table('cash_disbursements')->where('id', $row->cash_disbursement_id)->value('workspace_id');

            if ($ledgerWorkspace === null || $disbursementWorkspace === null) {
                $this->recordIssue('bank_withdrawal_disbursement_matches', $row->id, 'parent_workspace_missing', 'Match parent records do not both have workspace ownership.');
                continue;
            }
            if ((int) $ledgerWorkspace !== (int) $disbursementWorkspace) {
                $this->recordIssue('bank_withdrawal_disbursement_matches', $row->id, 'parent_workspace_mismatch', 'Bank ledger and disbursement belong to different workspaces.');
                continue;
            }
            $this->assignOrReport('bank_withdrawal_disbursement_matches', $row->id, $row->workspace_id, $ledgerWorkspace, 'parent_workspace_missing', 'existing_workspace_mismatch', $assigned);
        }
    }

    private function assignAuditWorkspaces(array &$assigned): void
    {
        foreach (DB::table('security_activity_events')->select(['id', 'user_id', 'actor_id', 'workspace_id'])->get() as $row) {
            $userWorkspaces = collect([$row->user_id, $row->actor_id])
                ->filter()
                ->map(fn (int $id) => DB::table('workspace_memberships')->where('user_id', $id)->where('status', 'active')->pluck('workspace_id'))
                ->flatten()
                ->unique()
                ->values();

            if ($userWorkspaces->count() === 1) {
                $this->assignOrReport('security_activity_events', $row->id, $row->workspace_id, $userWorkspaces->first(), 'audit_user_workspace_missing', 'audit_user_workspace_mismatch', $assigned);
            } else {
                $this->recordIssue('security_activity_events', $row->id, 'audit_workspace_ambiguous', 'Audit event has zero or multiple possible active user workspaces.');
            }
        }
    }

    private function assignFromParent(string $table, string $foreignKey, string $parentTable, array &$assigned): void
    {
        foreach (DB::table($table)->select(['id', $foreignKey, 'workspace_id'])->get() as $row) {
            $parentWorkspace = DB::table($parentTable)->where('id', $row->{$foreignKey})->value('workspace_id');
            $this->assignOrReport($table, $row->id, $row->workspace_id, $parentWorkspace, 'parent_workspace_missing', 'existing_workspace_mismatch', $assigned);
        }
    }

    private function assignOrReport(string $table, int $recordId, mixed $currentWorkspace, mixed $parentWorkspace, string $missingIssue, string $mismatchIssue, array &$assigned): void
    {
        if ($parentWorkspace === null) {
            $this->recordIssue($table, $recordId, $missingIssue, 'Parent record has no workspace ownership.');
            return;
        }
        if ($currentWorkspace !== null && (int) $currentWorkspace !== (int) $parentWorkspace) {
            $this->recordIssue($table, $recordId, $mismatchIssue, 'Record workspace differs from its parent workspace.');
            return;
        }
        if ($currentWorkspace === null) {
            $assigned[$table] = ($assigned[$table] ?? 0) + DB::table($table)->where('id', $recordId)->update([
                'workspace_id' => $parentWorkspace,
                'updated_at' => now(),
            ]);
        }
    }

    private function validateRelationships(): void
    {
        $relationships = [
            ['contributions', 'donor_id', 'donors'],
            ['contributions', 'campaign_id', 'campaigns'],
            ['inventory_items', 'inventory_subcategory_id', 'inventory_subcategories'],
            ['bank_ledger_entries', 'matched_contribution_id', 'contributions'],
            ['reconciliation_discrepancies', 'cleared_bank_ledger_entry_id', 'bank_ledger_entries'],
        ];

        foreach ($relationships as [$table, $foreignKey, $parentTable]) {
            DB::table($table)
                ->join($parentTable, "{$parentTable}.id", '=', "{$table}.{$foreignKey}")
                ->whereNotNull("{$table}.workspace_id")
                ->whereNotNull("{$parentTable}.workspace_id")
                ->whereColumn("{$table}.workspace_id", '!=', "{$parentTable}.workspace_id")
                ->select("{$table}.id")
                ->get()
                ->each(fn ($row) => $this->recordIssue($table, $row->id, 'workspace_relationship_mismatch', "{$table}.{$foreignKey} crosses workspace ownership."));
        }
    }

    private function recordIssue(string $table, int $recordId, string $type, string $details): void
    {
        DB::table('workspace_backfill_issues')->updateOrInsert(
            ['table_name' => $table, 'record_id' => $recordId, 'issue_type' => $type],
            ['details' => $details, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    private function financialTotals(): array
    {
        return [
            'contribution_count' => DB::table('contributions')->count(),
            'contribution_amount' => (string) DB::table('contributions')->sum('amount'),
            'disbursement_count' => DB::table('cash_disbursements')->count(),
            'disbursement_amount' => (string) DB::table('cash_disbursements')->sum('amount'),
            'bank_entry_count' => DB::table('bank_ledger_entries')->count(),
            'bank_entry_amount' => (string) DB::table('bank_ledger_entries')->sum('amount'),
        ];
    }
}
