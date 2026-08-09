<?php

namespace App\Support\Dashboard;

use App\Models\BankLedgerEntry;
use App\Models\CashDisbursement;
use App\Models\Contribution;
use App\Models\Donor;
use App\Models\InventoryItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardMetrics
{
    public function build(): array
    {
        $limit = config('dashboard.limits.recent_items');
        $pendingStatus = config('dashboard.cash_statuses.pending');
        $verifiedStatus = config('dashboard.cash_statuses.verified');

        return [
            'summary' => [
                'total_cash_ledger' => Contribution::query()->where('type', 'cash')->where('status', $verifiedStatus)->sum('amount'),
                'pending_cash_verification' => Contribution::query()->where('type', 'cash')->where('status', $pendingStatus)->count(),
                'in_kind_batches' => Cache::remember(
                    'dashboard.summary.in_kind_batches',
                    config('dashboard.cache.safe_summary_ttl'),
                    fn (): int => InventoryItem::query()->count(),
                ),
                'registered_donors' => Cache::remember(
                    'dashboard.summary.registered_donors',
                    config('dashboard.cache.safe_summary_ttl'),
                    fn (): int => Donor::query()->count(),
                ),
            ],
            'treasurer_action_items' => $this->treasurerActionItems($pendingStatus),
            'funding_distribution' => $this->fundingDistribution($verifiedStatus),
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
            'secretary_in_kind_control' => [
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
                'total_disbursed' => CashDisbursement::query()->sum('amount'),
                'recent' => CashDisbursement::query()
                    ->select(['id', 'disbursed_to', 'purpose', 'amount', 'disbursed_at'])
                    ->latest('disbursed_at')
                    ->latest()
                    ->limit($limit)
                    ->get(),
            ],
        ];
    }

    private function treasurerActionItems(string $pendingStatus): array
    {
        $latestReconciledEntry = BankLedgerEntry::query()
            ->where('status', 'reconciled')
            ->whereNotNull('matched_at')
            ->latest('matched_at')
            ->first(['id', 'amount', 'transaction_date', 'matched_at']);

        return [
            'pending_cash_count' => Contribution::query()->where('type', 'cash')->where('status', $pendingStatus)->count(),
            'latest_reconciliation' => $latestReconciledEntry,
            'unreconciled_ledger_count' => BankLedgerEntry::query()->needsReview()->count(),
        ];
    }

    private function fundingDistribution(string $verifiedStatus): Collection
    {
        return Contribution::query()
            ->select('campaign_id', DB::raw('sum(amount) as total'))
            ->where('type', 'cash')
            ->where('status', $verifiedStatus)
            ->groupBy('campaign_id')
            ->with('campaign:id,name')
            ->orderByDesc('total')
            ->limit(config('dashboard.limits.funding_categories'))
            ->get()
            ->map(fn (Contribution $row): object => (object) [
                'label' => $row->campaign?->name ?? 'Uncategorized',
                'total' => $row->total,
            ]);
    }

    private function systemLogActivity(int $limit): Collection
    {
        return collect()
            ->merge($this->activityFrom(Contribution::query()->latest()->limit($limit)->get(['id', 'type as label', 'created_at']), 'Contribution logged'))
            ->merge($this->activityFrom(InventoryItem::query()->latest()->limit($limit)->get(['id', 'name as label', 'created_at']), 'Inventory updated'))
            ->merge($this->activityFrom(CashDisbursement::query()->latest()->limit($limit)->get(['id', 'disbursed_to as label', 'created_at']), 'Cash disbursed'))
            ->sortByDesc('created_at')
            ->take($limit)
            ->values();
    }

    private function activityFrom(Collection $records, string $event): Collection
    {
        return $records->map(fn ($record): array => [
            'event' => $event,
            'label' => $record->label,
            'created_at' => $record->created_at,
        ]);
    }
}
