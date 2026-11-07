<?php

namespace App\Support\Dashboard;

use App\Models\BankLedgerEntry;
use App\Models\CashDisbursement;
use App\Models\Contribution;
use App\Models\Donor;
use App\Models\InventoryItem;
use App\Support\Workspaces\WorkspaceContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardMetrics
{
    public function build(): array
    {
        $workspaceId = app(WorkspaceContext::class)->requireId();
        $limit = config('dashboard.limits.recent_items');
        $pendingStatus = config('dashboard.cash_statuses.pending');
        $verifiedStatus = config('dashboard.cash_statuses.verified');

        return [
            'summary' => [
                'total_cash_ledger' => Contribution::query()->where('type', 'cash')->where('status', $verifiedStatus)->sum('amount'),
                'pending_cash_verification' => Contribution::query()->where('type', 'cash')->where('status', $pendingStatus)->count(),
                'in_kind_batches' => Cache::remember(
                    "dashboard.workspace.{$workspaceId}.summary.in_kind_batches",
                    config('dashboard.cache.safe_summary_ttl'),
                    fn (): int => InventoryItem::query()->count(),
                ),
                'registered_donors' => Cache::remember(
                    "dashboard.workspace.{$workspaceId}.summary.registered_donors",
                    config('dashboard.cache.safe_summary_ttl'),
                    fn (): int => Donor::query()->count(),
                ),
            ],
            'staff_finance_action_items' => $this->staffFinanceActionItems($pendingStatus),
            'recent_cash_awaiting_audit' => Contribution::query()
                ->select(['id', 'donor_id', 'amount', 'payment_method', 'received_at', 'created_at'])
                ->with('donor:id,full_name')
                ->where('type', 'cash')
                ->where('status', $pendingStatus)
                ->latest('received_at')
                ->latest()
                ->limit($limit)
                ->get(),
            'system_log_activity' => $this->systemLogActivity($limit),
            'staff_in_kind_control' => [
                'status_counts' => InventoryItem::query()
                    ->select('status', DB::raw('count(*) as total'))
                    ->groupBy('status')
                    ->orderBy('status')
                    ->get(),
                'recent_items' => InventoryItem::query()
                    ->select(['id', 'name', 'category', 'quantity', 'status', 'received_at', 'created_at'])
                    ->latest('received_at')
                    ->latest()
                    ->limit($limit)
                    ->get(),
            ],
            'recent_donations' => Contribution::query()
                ->select(['id', 'donor_id', 'type', 'amount', 'received_at', 'created_at'])
                ->with('donor:id,full_name')
                ->withCount('items')
                ->latest('received_at')
                ->latest()
                ->limit($limit)
                ->get(),
            'disbursements' => [
                'total_disbursed' => CashDisbursement::query()->where('status', 'approved')->whereNull('voided_at')->sum('amount'),
                'recent' => CashDisbursement::query()
                    ->select(['id', 'purpose', 'amount', 'status', 'requested_by', 'created_at'])
                    ->with('requester:id,first_name,last_name,name')
                    ->latest()
                    ->limit($limit)
                    ->get(),
            ],
        ];
    }

    private function staffFinanceActionItems(string $pendingStatus): array
    {
        $latestReconciledEntry = BankLedgerEntry::query()
            ->where('status', 'reconciled')
            ->whereNotNull('matched_at')
            ->latest('matched_at')
            ->first(['id', 'amount', 'transaction_date', 'matched_at']);

        $withdrawalsToReview = BankLedgerEntry::query()->withdrawalsToReview();
        $discrepanciesToReview = (clone $withdrawalsToReview)->whereNotNull('discrepancy_amount');

        return [
            'pending_cash_count' => Contribution::query()->where('type', 'cash')->where('status', $pendingStatus)->count(),
            'latest_reconciliation' => $latestReconciledEntry,
            'unreconciled_ledger_count' => BankLedgerEntry::query()->needsReview()->count(),
            'bank_withdrawals_awaiting_reconciliation' => $withdrawalsToReview->count(),
            'discrepancies_requiring_review' => $discrepanciesToReview->count(),
            'unresolved_difference_amount' => (clone $discrepanciesToReview)
                ->select('discrepancy_amount')
                ->get()
                ->sum(fn (BankLedgerEntry $entry): float => abs((float) $entry->discrepancy_amount)),
        ];
    }

    private function systemLogActivity(int $limit): Collection
    {
        return collect()
            ->merge($this->activityFrom(Contribution::query()->latest()->limit($limit)->get(['id', 'type as label', 'created_at']), 'Contribution logged', 'donations.show'))
            ->merge($this->activityFrom(InventoryItem::query()->latest()->limit($limit)->get(['id', 'name as label', 'created_at']), 'Inventory updated', 'inventory.show'))
            ->merge($this->activityFrom(CashDisbursement::query()->latest()->limit($limit)->get(['id', 'purpose as label', 'created_at']), 'Cash request updated', 'disbursements.show'))
            ->sortByDesc('created_at')
            ->take($limit)
            ->values();
    }

    private function activityFrom(Collection $records, string $event, ?string $routeName = null): Collection
    {
        return $records->map(fn ($record): array => [
            'event' => $event,
            'label' => $record->label,
            'created_at' => $record->created_at,
            'url' => $routeName ? route($routeName, $record->id) : null,
        ]);
    }
}
