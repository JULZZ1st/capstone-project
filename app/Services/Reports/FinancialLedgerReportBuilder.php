<?php

namespace App\Services\Reports;

use App\Models\Contribution;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FinancialLedgerReportBuilder
{
    public function build(int $year, int $month): array
    {
        $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();

        $contributions = Contribution::query()
            ->with([
                'donor:id,full_name',
                'campaign:id,name',
                'ledgerEntry:id,matched_contribution_id,status',
            ])
            ->where('type', 'cash')
            ->where('status', '!=', 'cancelled')
            ->whereBetween('received_at', [$periodStart, $periodEnd])
            ->orderByDesc('received_at')
            ->get();

        return [
            'period' => $periodStart,
            'summary' => $this->summarize($contributions),
            'wire_sources' => $this->wireSourceBreakdown($contributions),
            'ledger' => $this->ledgerRows($contributions),
        ];
    }

    private function summarize(Collection $contributions): array
    {
        $reconciled = $contributions->filter(fn (Contribution $contribution) => $this->isReconciled($contribution));
        $pending = $contributions->filter(
            fn (Contribution $contribution) => ! $this->isReconciled($contribution) && $this->isElectronic($contribution)
        );

        return [
            'reconciled_total' => (float) $reconciled->sum('amount'),
            'pending_clearing_total' => (float) $pending->sum('amount'),
            'recorded_total' => (float) $contributions->sum('amount'),
        ];
    }

    private function wireSourceBreakdown(Collection $contributions): Collection
    {
        $methodLabels = config('donations.payment_methods');

        $totals = $contributions
            ->groupBy('payment_method')
            ->map(fn (Collection $group) => (float) $group->sum('amount'))
            ->sortDesc();

        $max = (float) $totals->max();

        return $totals
            ->map(fn (float $amount, string $method) => [
                'method' => $method,
                'label' => $methodLabels[$method] ?? ucfirst($method),
                'amount' => $amount,
                'percentage' => $max > 0 ? (int) min(100, round($amount / $max * 100)) : 0,
            ])
            ->values();
    }

    private function ledgerRows(Collection $contributions): Collection
    {
        $statuses = config('bank_reconciliation.receipt_statuses');
        $methodLabels = config('donations.payment_methods');

        return $contributions->map(function (Contribution $contribution) use ($statuses, $methodLabels) {
            $statusKey = $this->isReconciled($contribution) ? 'reconciled' : 'pending';

            return [
                'id' => $contribution->id,
                'donor_name' => $contribution->donor?->full_name ?? 'Unknown Donor',
                'campaign_name' => $contribution->campaign?->name,
                'payment_method_label' => $methodLabels[$contribution->payment_method] ?? ucfirst((string) $contribution->payment_method),
                'amount' => (float) $contribution->amount,
                'received_at' => $contribution->received_at,
                'status_key' => $statusKey,
                'status_label' => $statuses[$statusKey]['label'],
                'status_class' => $statuses[$statusKey]['class'],
            ];
        });
    }

    private function isReconciled(Contribution $contribution): bool
    {
        return $contribution->ledgerEntry !== null && $contribution->ledgerEntry->status === 'reconciled';
    }

    private function isElectronic(Contribution $contribution): bool
    {
        return in_array($contribution->payment_method, config('donations.electronic_payment_methods'), true);
    }
}
