<?php

namespace App\Support\Dashboard;

use App\Models\BankReconciliation;
use App\Models\CashTransaction;
use App\Models\Donation;
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

        return [
            'summary' => [
                'total_cash_ledger' => CashTransaction::query()->verified()->sum('amount'),
                'pending_cash_verification' => CashTransaction::query()->pending()->count(),
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
            'funding_distribution' => $this->fundingDistribution(),
            'recent_cash_awaiting_audit' => CashTransaction::query()
                ->select(['id', 'donor_name', 'amount', 'purpose', 'received_at', 'created_at', 'status'])
                ->pending()
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
            'recent_donations' => Donation::query()
                ->select(['id', 'donor_id', 'donation_type', 'amount', 'description', 'received_at', 'created_at'])
                ->with(['donor:id,full_name'])
                ->latest('received_at')
                ->latest()
                ->limit($limit)
                ->get(),
        ];
    }

    private function treasurerActionItems(string $pendingStatus): array
    {
        $latestReconciliation = BankReconciliation::query()->latest('date')->first(['date', 'closing_balance']);

        return [
            'pending_cash_count' => CashTransaction::query()->pending()->count(),
            'latest_reconciliation' => $latestReconciliation,
            'unreconciled_cash_count' => CashTransaction::query()
                ->when($latestReconciliation, fn ($query) => $query->whereDate('received_at', '>', $latestReconciliation->date))
                ->when(! $latestReconciliation, fn ($query) => $query->whereNotNull('id'))
                ->count(),
        ];
    }

    private function fundingDistribution(): Collection
    {
        return CashTransaction::query()
            ->select('purpose', DB::raw('sum(amount) as total'))
            ->verified()
            ->groupBy('purpose')
            ->orderByDesc('total')
            ->limit(config('dashboard.limits.funding_categories'))
            ->get();
    }

    private function systemLogActivity(int $limit): Collection
    {
        return collect()
            ->merge($this->activityFrom(Donation::query()->latest()->limit($limit)->get(['id', 'donation_type as label', 'created_at']), 'Donation recorded'))
            ->merge($this->activityFrom(InventoryItem::query()->latest()->limit($limit)->get(['id', 'name as label', 'created_at']), 'Inventory updated'))
            ->merge($this->activityFrom(CashTransaction::query()->latest()->limit($limit)->get(['id', 'donor_name as label', 'created_at']), 'Cash entry recorded'))
            ->merge($this->activityFrom(BankReconciliation::query()->latest()->limit($limit)->get(['id', 'date as label', 'created_at']), 'Reconciliation saved'))
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
