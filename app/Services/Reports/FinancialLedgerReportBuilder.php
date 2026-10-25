<?php

namespace App\Services\Reports;

use App\Models\CashDisbursement;
use App\Models\Contribution;
use App\Models\BankLedgerEntry;
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
                'ledgerEntry:id,matched_contribution_id,status',
            ])
            ->where('type', 'cash')
            ->where(function ($query) {
                $query->where('status', '!=', 'cancelled')->orWhereNotNull('voided_at');
            })
            ->whereBetween('received_at', [$periodStart, $periodEnd])
            ->orderByDesc('received_at')
            ->get();

        $disbursements = CashDisbursement::query()
            ->with('requester:id,name')
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->orderByDesc('created_at')
            ->get();

        $bankEntries = BankLedgerEntry::query()
            ->whereBetween('transaction_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->get();

        return [
            'period' => $periodStart,
            'summary' => array_merge($this->summarize($contributions, $disbursements), [
                'bank_cleared_total' => (float) $bankEntries->where('status', 'reconciled')->sum('amount'),
                'bank_unmatched_total' => (float) $bankEntries->whereIn('status', ['unmatched', 'unreconciled'])->sum('amount'),
                'bank_voided_total' => (float) $bankEntries->where('status', 'voided')->sum('amount'),
            ]),
            'wire_sources' => $this->wireSourceBreakdown($contributions),
            'ledger' => $this->ledgerRows($contributions, $disbursements),
        ];
    }

    private function summarize(Collection $contributions, Collection $disbursements): array
    {
        $activeContributions = $contributions->reject(fn (Contribution $contribution) => $contribution->isVoided());
        $reconciled = $activeContributions->filter(fn (Contribution $contribution) => $this->isReconciled($contribution));
        $pending = $activeContributions->filter(
            fn (Contribution $contribution) => ! $this->isReconciled($contribution) && $this->isElectronic($contribution)
        );
        $approvedDisbursements = $disbursements->filter(fn (CashDisbursement $disbursement) => $disbursement->status === 'approved' && ! $disbursement->isVoided());
        $disbursementTotal = (float) $approvedDisbursements->sum('amount');

        return [
            'reconciled_total' => (float) $reconciled->sum('amount'),
            'pending_clearing_total' => (float) $pending->sum('amount'),
            'recorded_total' => (float) $activeContributions->sum('amount'),
            'disbursement_total' => $disbursementTotal,
            'net_total' => (float) $activeContributions->sum('amount') - $disbursementTotal,
        ];
    }

    private function wireSourceBreakdown(Collection $contributions): Collection
    {
        $methodLabels = config('donations.payment_methods');

        $totals = $contributions->reject(fn (Contribution $contribution) => $contribution->isVoided())
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

    private function ledgerRows(Collection $contributions, Collection $disbursements): Collection
    {
        $statuses = config('bank_reconciliation.receipt_statuses');
        $methodLabels = config('donations.payment_methods');

        $contributionRows = $contributions->map(function (Contribution $contribution) use ($statuses, $methodLabels) {
            $statusKey = $contribution->isVoided() ? 'voided' : ($this->isReconciled($contribution) ? 'reconciled' : 'pending');

            return [
                'id' => $contribution->id,
                'entry_type' => 'Contribution',
                'donor_name' => $contribution->donor?->full_name ?? 'Unknown Donor',
                'purpose' => 'General Fund',
                'payment_method_label' => $methodLabels[$contribution->payment_method] ?? ucfirst((string) $contribution->payment_method),
                'amount' => (float) $contribution->amount,
                'received_at' => $contribution->received_at,
                'status_key' => $statusKey,
                'status_label' => $statusKey === 'voided' ? 'Voided' : $statuses[$statusKey]['label'],
                'status_class' => $statusKey === 'voided' ? 'bg-red-100 text-red-800 ring-red-200' : $statuses[$statusKey]['class'],
            ];
        });

        $disbursementRows = $disbursements->map(fn (CashDisbursement $disbursement) => [
            'id' => $disbursement->id,
            'entry_type' => 'Disbursement',
            'donor_name' => $disbursement->requester?->name ?? 'Unknown Requester',
            'purpose' => $disbursement->purpose,
            'payment_method_label' => 'Cash out',
            'amount' => (float) $disbursement->amount,
            'received_at' => $disbursement->created_at,
            'status_key' => $disbursement->isVoided() ? 'voided' : $disbursement->status,
            'status_label' => $disbursement->isVoided() ? 'Voided' : ucfirst($disbursement->status),
            'status_class' => $disbursement->isVoided() ? 'bg-red-100 text-red-800 ring-red-200' : 'bg-blue-100 text-blue-800 ring-blue-200',
        ]);

        return $contributionRows->concat($disbursementRows)->sortByDesc('received_at')->values();
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
