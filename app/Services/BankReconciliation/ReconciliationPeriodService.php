<?php

namespace App\Services\BankReconciliation;

use App\Models\BankReconciliation;
use App\Models\BankLedgerEntry;
use App\Models\CashDisbursement;
use App\Models\CashDisbursementPayment;
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

        $previous = BankReconciliation::query()
            ->whereIn('status', ['locked', 'approved_locked'])
            ->where('id', '!=', $period->id)
            ->where(function ($query) use ($period) {
                $query->where('bank_account', $period->bank_account);
                if ($period->bank_account === config('bank_reconciliation.bank_accounts.0')) {
                    $query->orWhereNull('bank_account');
                }
            })
            ->whereDate('period_end', '<', $period->period_start)
            ->orderByDesc('period_end')
            ->first();

        if ($previous !== null && bccomp((string) $data['opening_balance'], (string) $previous->bank_ending_balance, 2) !== 0) {
            throw ValidationException::withMessages(['opening_balance' => 'The opening balance must equal the previous locked period bank ending balance.']);
        }

        $data['book_opening_balance'] = $previous === null
            ? $data['opening_balance']
            : ($previous->adjusted_book_balance ?? $previous->financial_ledger_ending_balance ?? $previous->bank_ending_balance);

        $period->forceFill($data)->save();
        return $this->recalculate($period->fresh());
    }

    public function create(array $data): BankReconciliation
    {
        $bankAccount = $data['bank_account'] ?? config('bank_reconciliation.bank_accounts.0', 'LANDBANK Barangay Operating Account');
        $start = Carbon::parse($data['period_start'])->startOfDay();
        $end = Carbon::parse($data['period_end'])->startOfDay();

        if ($end->lt($start)) {
            throw ValidationException::withMessages(['period_end' => 'The period end must be on or after the period start.']);
        }

        return DB::transaction(function () use ($data, $bankAccount, $start, $end) {
            if (BankReconciliation::query()
                ->whereNotNull('period_start')
                ->whereNotNull('period_end')
                ->where(function ($query) use ($bankAccount) {
                    $query->where('bank_account', $bankAccount);
                    if ($bankAccount === config('bank_reconciliation.bank_accounts.0')) {
                        $query->orWhereNull('bank_account');
                    }
                })
                ->where('period_start', '<=', $end->toDateString())
                ->where('period_end', '>=', $start->toDateString())
                ->exists()) {
                throw ValidationException::withMessages(['period_start' => 'This bank account already has an overlapping reconciliation period. Resume the existing period instead.']);
            }

            $draftPrevious = BankReconciliation::query()
                ->where('status', 'draft')
                ->where(function ($query) use ($bankAccount) {
                    $query->where('bank_account', $bankAccount);
                    if ($bankAccount === config('bank_reconciliation.bank_accounts.0')) {
                        $query->orWhereNull('bank_account');
                    }
                })
                ->whereDate('period_end', '<', $start->toDateString())
                ->orderByDesc('period_end')
                ->first();

            if ($draftPrevious !== null) {
                throw ValidationException::withMessages(['period_start' => 'Complete and submit the previous Draft period before starting a new period for this account.']);
            }

            $previous = BankReconciliation::query()
                ->whereIn('status', ['locked', 'approved_locked'])
                ->where(function ($query) use ($bankAccount) {
                    $query->where('bank_account', $bankAccount);
                    if ($bankAccount === config('bank_reconciliation.bank_accounts.0')) {
                        $query->orWhereNull('bank_account');
                    }
                })
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

            $bookOpening = (float) ($data['book_opening_balance'] ?? $data['opening_balance']);
            if ($previous !== null) {
                $bookOpening = (float) ($previous->adjusted_book_balance
                    ?? $previous->financial_ledger_ending_balance
                    ?? $previous->bank_ending_balance);
            }

            $period = BankReconciliation::query()->create([
                ...$data,
                'bank_account' => $bankAccount,
                'book_opening_balance' => $bookOpening,
                'date' => $end->toDateString(),
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'status' => 'draft',
            ]);

            if ($previous !== null) {
                foreach ($previous->discrepancies()->where('classification', 'timing_difference')->whereIn('type', ['outstanding_check', 'deposit_in_transit', 'cash_not_deposited'])->where('resolution_status', 'unresolved')->where('carry_forward', true)->get() as $item) {
                    $period->discrepancies()->create([
                        'type' => $item->type,
                        'classification' => $item->classification,
                        'amount' => $item->amount,
                        'explanation' => 'Carried forward from reconciliation period '.$previous->id.': '.$item->explanation,
                        'evidence_reference' => $item->evidence_reference,
                        'system_record_type' => $item->system_record_type,
                        'system_record_id' => $item->system_record_id,
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

        $bankIn = (float) BankLedgerEntry::query()->forBankAccount($period->bank_account)->whereDate('transaction_date', '>=', $start->toDateString())->whereDate('transaction_date', '<=', $end->toDateString())->where('transaction_type', 'deposit')->sum('amount');
        $bankOut = (float) BankLedgerEntry::query()->forBankAccount($period->bank_account)->whereDate('transaction_date', '>=', $start->toDateString())->whereDate('transaction_date', '<=', $end->toDateString())->where('transaction_type', 'withdrawal')->sum('amount');
        $ledgerIn = (float) Contribution::query()
            ->forReconciliationBankAccount($period->bank_account)
            ->where('type', 'cash')
            ->whereIn('payment_method', config('donations.electronic_payment_methods'))
            ->where('status', 'verified')
            ->whereNull('voided_at')
            ->whereBetween('received_at', [$start, $end])
            ->sum('amount');
        $ledgerOut = (float) CashDisbursementPayment::query()
            ->forBankAccount($period->bank_account)
            ->where('payment_method', 'bank_transfer')
            ->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()])
            ->whereHas('disbursement', fn ($query) => $query->where('status', 'approved')->whereNull('voided_at'))
            ->sum('amount');
        $accountingAdjustment = (float) ReconciliationDiscrepancy::query()->where('bank_reconciliation_id', $period->id)->where('classification', 'accounting_adjustment')->whereIn('resolution_status', ['resolved', 'accounted'])->sum('adjustment_amount');
        $bookOpening = (float) ($period->book_opening_balance ?? $period->opening_balance);
        $adjustedBook = $bookOpening + $ledgerIn - $ledgerOut + $accountingAdjustment;
        $this->clearTimingItems($period, $start, $end);
        $timingAdjustment = (float) ReconciliationDiscrepancy::query()
            ->where('bank_reconciliation_id', $period->id)
            ->where('classification', 'timing_difference')
            ->where('resolution_status', 'unresolved')
            ->whereIn('type', ['outstanding_check', 'deposit_in_transit', 'cash_not_deposited'])
            ->sum(DB::raw("CASE WHEN type = 'outstanding_check' THEN -amount ELSE amount END"));
        $unresolved = (float) ReconciliationDiscrepancy::query()->where('bank_reconciliation_id', $period->id)->where('resolution_status', 'unresolved')->where(function ($query) {
            $query->where('classification', '!=', 'timing_difference')->orWhereNotIn('type', ['outstanding_check', 'deposit_in_transit', 'cash_not_deposited']);
        })->sum('amount');
        $adjustedBank = $period->bank_ending_balance === null ? null : (float) $period->bank_ending_balance + $timingAdjustment;
        $difference = $adjustedBank === null ? null : $adjustedBank - $adjustedBook;

        return tap($period)->update([
            'calculated_bank_cash_in' => $bankIn,
            'calculated_bank_cash_out' => $bankOut,
            'cash_in' => $bankIn,
            'cash_out' => $bankOut,
            'financial_ledger_cash_in' => $ledgerIn,
            'financial_ledger_cash_out' => $ledgerOut,
            'book_opening_balance' => $bookOpening,
            'financial_ledger_ending_balance' => $adjustedBook,
            'accounting_adjustment' => $accountingAdjustment,
            'adjusted_bank_balance' => $adjustedBank,
            'adjusted_book_balance' => $adjustedBook,
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
            ->whereIn('type', ['outstanding_check', 'deposit_in_transit', 'cash_not_deposited'])
            ->where('resolution_status', 'unresolved')
            ->whereNull('cleared_bank_ledger_entry_id')
            ->get();

        foreach ($items as $item) {
            $transactionType = $item->type === 'outstanding_check' ? 'withdrawal' : 'deposit';
            $entry = BankLedgerEntry::query()
                ->forBankAccount($period->bank_account)
                ->whereDate('transaction_date', '>=', $start->toDateString())
                ->whereDate('transaction_date', '<=', $end->toDateString())
                ->where('transaction_type', $transactionType)
                ->where('status', '!=', 'reconciled')
                ->where('amount', $item->amount)
                ->when($item->bank_ledger_entry_id, fn ($query, $entryId) => $query->where('id', '!=', $entryId))
                ->where(function ($query) use ($item) {
                    $query->where('reference_number', $item->evidence_reference)
                        ->orWhere('statement_id', $item->evidence_reference);
                })
                ->whereDoesntHave('clearedDiscrepancy')
                ->first();

            if ($entry) {
                $entryUpdates = [
                    'status' => 'reconciled',
                    'reconciled_at' => now(),
                ];

                if ($item->system_record_type === 'contribution' && $item->system_record_id) {
                    $contribution = Contribution::query()->find($item->system_record_id);
                    if ($contribution
                        && ! $contribution->ledgerEntry()->exists()
                        && bccomp((string) $contribution->amount, (string) $entry->amount, 2) === 0) {
                        $entryUpdates['matched_contribution_id'] = $contribution->id;
                        $entryUpdates['matched_at'] = now();
                    }
                }

                if ($item->system_record_type === 'cash_disbursement' && $item->system_record_id) {
                    $disbursement = CashDisbursement::query()->find($item->system_record_id);
                    if ($disbursement
                        && ! $disbursement->withdrawalMatch()->exists()
                        && bccomp((string) $disbursement->amount, (string) $entry->amount, 2) === 0) {
                        $entry->matchedDisbursements()->syncWithoutDetaching([
                            $disbursement->id => ['workspace_id' => $period->workspace_id],
                        ]);
                    }
                }

                $entry->forceFill($entryUpdates)->save();
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
