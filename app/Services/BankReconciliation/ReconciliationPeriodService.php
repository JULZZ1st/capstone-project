<?php

namespace App\Services\BankReconciliation;

use App\Models\BankReconciliation;
use App\Models\BankLedgerEntry;
use App\Models\CashDisbursement;
use App\Models\Contribution;
use App\Models\ReconciliationDiscrepancy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReconciliationPeriodService
{
    public function updateBalances(BankReconciliation $period, array $data): BankReconciliation
    {
        if ($period->isLocked()) {
            throw ValidationException::withMessages(['period' => 'Locked reconciliation periods cannot be changed.']);
        }

        $period->forceFill($data)->save();
        return $this->recalculate($period->fresh());
    }

    public function create(array $data): BankReconciliation
    {
        $start = Carbon::parse($data['period_start'])->startOfDay();
        $end = Carbon::parse($data['period_end'])->startOfDay();

        if ($end->lt($start)) {
            throw ValidationException::withMessages(['period_end' => 'The period end must be on or after the period start.']);
        }

        return DB::transaction(function () use ($data, $start, $end) {
            if (BankReconciliation::query()
                ->whereNotNull('period_start')
                ->whereNotNull('period_end')
                ->where('period_start', '<=', $end->toDateString())
                ->where('period_end', '>=', $start->toDateString())
                ->exists()) {
                throw ValidationException::withMessages(['period_start' => 'The reconciliation period overlaps an existing period.']);
            }

            $previous = BankReconciliation::query()
                ->whereIn('status', ['locked', 'approved_locked'])
                ->whereDate('period_end', '<', $start->toDateString())
                ->orderByDesc('period_end')
                ->lockForUpdate()
                ->first();

            if ($previous !== null) {
                if (! $previous->period_end->copy()->addDay()->isSameDay($start)) {
                    throw ValidationException::withMessages(['period_start' => 'The period must begin immediately after the previous locked period.']);
                }

                if ($previous->bank_ending_balance === null) {
                    throw ValidationException::withMessages(['opening_balance' => 'The previous locked period has no bank ending balance to carry forward.']);
                }

                if (bccomp((string) $data['opening_balance'], (string) $previous->bank_ending_balance, 2) !== 0) {
                    throw ValidationException::withMessages(['opening_balance' => 'The opening balance must equal the previous locked period bank ending balance.']);
                }
            }

            $period = BankReconciliation::query()->create([
                ...$data,
                'date' => $end->toDateString(),
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'status' => 'draft',
            ]);

            if ($previous !== null) {
                foreach ($previous->discrepancies()->where('classification', 'timing_difference')->whereIn('type', ['outstanding_check', 'deposit_in_transit'])->where('resolution_status', 'unresolved')->where('carry_forward', true)->get() as $item) {
                    $period->discrepancies()->create([
                        'type' => $item->type,
                        'classification' => $item->classification,
                        'amount' => $item->amount,
                        'explanation' => 'Carried forward from reconciliation period '.$previous->id.': '.$item->explanation,
                        'evidence_reference' => $item->evidence_reference,
                        'resolution_status' => 'unresolved',
                        'adjustment_amount' => 0,
                        'carry_forward' => true,
                    ]);
                }
            }

            return $this->recalculate($period);
        });
    }

    public function recalculate(BankReconciliation $period): BankReconciliation
    {
        if ($period->isLocked()) {
            throw ValidationException::withMessages(['period' => 'Locked reconciliation periods cannot be recalculated.']);
        }

        $start = $period->period_start->startOfDay();
        $end = $period->period_end->endOfDay();

        $bankIn = (float) BankLedgerEntry::query()->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])->where('transaction_type', 'deposit')->sum('amount');
        $bankOut = (float) BankLedgerEntry::query()->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])->where('transaction_type', 'withdrawal')->sum('amount');
        $ledgerIn = (float) Contribution::query()->where('type', 'cash')->where('status', '!=', 'cancelled')->whereNull('voided_at')->whereBetween('received_at', [$start, $end])->sum('amount');
        $ledgerOut = (float) CashDisbursement::query()->where('status', 'approved')->whereNull('voided_at')->whereBetween('created_at', [$start, $end])->sum('amount');
        $ledgerEnding = (float) $period->opening_balance + $ledgerIn - $ledgerOut;
        $accountingAdjustment = (float) ReconciliationDiscrepancy::query()->where('bank_reconciliation_id', $period->id)->where('classification', 'accounting_adjustment')->whereIn('resolution_status', ['resolved', 'accounted'])->sum('adjustment_amount');
        $this->clearTimingItems($period, $start, $end);
        $timingAdjustment = (float) ReconciliationDiscrepancy::query()
            ->where('bank_reconciliation_id', $period->id)
            ->where('classification', 'timing_difference')
            ->where('resolution_status', 'unresolved')
            ->whereIn('type', ['outstanding_check', 'deposit_in_transit'])
            ->sum(DB::raw("CASE WHEN type = 'outstanding_check' THEN amount ELSE -amount END"));
        $unresolved = (float) ReconciliationDiscrepancy::query()->where('bank_reconciliation_id', $period->id)->where('resolution_status', 'unresolved')->where(function ($query) {
            $query->where('classification', '!=', 'timing_difference')->orWhereNotIn('type', ['outstanding_check', 'deposit_in_transit']);
        })->sum('amount');
        $adjustedBank = $period->bank_ending_balance === null ? null : (float) $period->bank_ending_balance + $accountingAdjustment + $timingAdjustment;
        $difference = $adjustedBank === null ? null : $adjustedBank - $ledgerEnding;

        return tap($period)->update([
            'calculated_bank_cash_in' => $bankIn,
            'calculated_bank_cash_out' => $bankOut,
            'cash_in' => $bankIn,
            'cash_out' => $bankOut,
            'financial_ledger_cash_in' => $ledgerIn,
            'financial_ledger_cash_out' => $ledgerOut,
            'financial_ledger_ending_balance' => $ledgerEnding,
            'accounting_adjustment' => $accountingAdjustment,
            'adjusted_bank_balance' => $adjustedBank,
            'unresolved_discrepancy_amount' => $unresolved,
            'reconciliation_difference' => $difference,
            'calculated_at' => now(),
        ]);
    }

    private function clearTimingItems(BankReconciliation $period, Carbon $start, Carbon $end): void
    {
        $items = ReconciliationDiscrepancy::query()
            ->where('bank_reconciliation_id', $period->id)
            ->where('classification', 'timing_difference')
            ->whereIn('type', ['outstanding_check', 'deposit_in_transit'])
            ->where('resolution_status', 'unresolved')
            ->whereNull('cleared_bank_ledger_entry_id')
            ->get();

        foreach ($items as $item) {
            $transactionType = $item->type === 'outstanding_check' ? 'withdrawal' : 'deposit';
            $entry = BankLedgerEntry::query()
                ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
                ->where('transaction_type', $transactionType)
                ->where('amount', $item->amount)
                ->where(function ($query) use ($item) {
                    $query->where('reference_number', $item->evidence_reference)
                        ->orWhere('statement_id', $item->evidence_reference);
                })
                ->whereDoesntHave('clearedDiscrepancy')
                ->first();

            if ($entry) {
                $item->forceFill([
                    'resolution_status' => 'resolved',
                    'resolved_at' => now(),
                    'cleared_bank_ledger_entry_id' => $entry->id,
                    'cleared_at' => now(),
                ])->save();
            }
        }
    }
}
