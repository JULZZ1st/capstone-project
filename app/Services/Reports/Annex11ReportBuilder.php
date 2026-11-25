<?php

namespace App\Services\Reports;

use App\Models\BankReconciliation;
use App\Models\ReconciliationDiscrepancy;
use Illuminate\Support\Collection;

class Annex11ReportBuilder
{
    private const CHECK_TYPES = ['outstanding_check'];
    private const DEPOSIT_TYPES = ['deposit_in_transit', 'cash_not_deposited'];
    private const DEBIT_MEMO_TYPES = ['bank_charge'];
    private const CREDIT_MEMO_TYPES = ['interest'];
    private const OTHER_ADJUSTMENT_TYPES = ['unmatched_amount', 'recording_error', 'unidentified_transaction', 'other'];

    public function build(int $periodId): array
    {
        $period = BankReconciliation::query()->with(['discrepancies', 'preparer', 'approver'])->findOrFail($periodId);

        $discrepancies = $period->discrepancies;
        $timingUnresolved = $discrepancies->where('classification', 'timing_difference')->where('resolution_status', 'unresolved');
        $accountingSettled = $discrepancies->where('classification', 'accounting_adjustment')->whereIn('resolution_status', ['resolved', 'accounted']);

        $outstandingChecks = $timingUnresolved->whereIn('type', self::CHECK_TYPES);
        $depositsInTransit = $timingUnresolved->whereIn('type', self::DEPOSIT_TYPES);
        $debitMemos = $accountingSettled->whereIn('type', self::DEBIT_MEMO_TYPES);
        $creditMemos = $accountingSettled->whereIn('type', self::CREDIT_MEMO_TYPES);
        $otherAdjustments = $accountingSettled->whereIn('type', self::OTHER_ADJUSTMENT_TYPES);

        $bookOpening = (float) ($period->book_opening_balance ?? $period->opening_balance ?? 0);
        $bookUnadjusted = $bookOpening + (float) $period->financial_ledger_cash_in - (float) $period->financial_ledger_cash_out;

        $bankAccount = $period->bank_account;
        $bankProfile = collect(config('bank_reconciliation.bank_profiles', []))->get($bankAccount, []);
        $barangayProfile = config('disbursements.barangay', []);

        return [
            'period' => $period,
            'is_draft' => ! $period->isApproved(),
            'barangay' => [
                'name' => $barangayProfile['location_barangay'] ?? $barangayProfile['name'] ?? null,
                'tel_no' => $barangayProfile['tel_no'] ?? null,
                'city_municipality' => $barangayProfile['city_municipality'] ?? null,
                'province' => $barangayProfile['province'] ?? null,
            ],
            'bank' => [
                'bank_name' => $bankProfile['bank_name'] ?? $bankAccount,
                'branch' => $bankProfile['branch'] ?? null,
                'account_number' => $bankProfile['account_number'] ?? null,
            ],
            'book_unadjusted' => $bookUnadjusted,
            'bank_unadjusted' => $period->bank_ending_balance === null ? null : (float) $period->bank_ending_balance,
            'book_adjusted' => $period->adjusted_book_balance === null ? null : (float) $period->adjusted_book_balance,
            'bank_adjusted' => $period->adjusted_bank_balance === null ? null : (float) $period->adjusted_bank_balance,
            'lines' => [
                ['label' => 'Check Issued not taken up:', 'header' => true],
                $this->untrackedLine('By the Barangay'),
                $this->bankLine('By the Bank', $outstandingChecks, negate: true),
                ['label' => 'Check Issued Overstated:', 'header' => true],
                $this->untrackedLine('By the Barangay'),
                $this->untrackedLine('By the Bank'),
                ['label' => 'Check Issued Understated', 'header' => true],
                $this->untrackedLine('By the Barangay'),
                $this->untrackedLine('By the Bank'),
                ['label' => 'Deposits not taken up:', 'header' => true],
                $this->untrackedLine('By the Barangay'),
                $this->bankLine('By the Bank', $depositsInTransit, negate: false),
                ['label' => 'Deposits Overstated:', 'header' => true],
                $this->untrackedLine('By the Barangay'),
                $this->untrackedLine('By the Bank'),
                ['label' => 'Deposit Understated:', 'header' => true],
                $this->untrackedLine('By the Barangay'),
                $this->untrackedLine('By the Bank'),
                ['label' => 'Bank Debit/Credit Memos, Not taken up by the Barangay:', 'header' => true],
                $this->bookLine('Debit Memo', $debitMemos, indent: true),
                $this->bookLine('Credit Memo', $creditMemos, indent: true),
                $this->bookLine('Other Reconciling Items', $otherAdjustments, indent: false),
            ],
            'unresolved_amount' => (float) $period->unresolved_discrepancy_amount,
        ];
    }

    /**
     * A row Annex 11 defines but this system has no way to produce (no "by the Barangay"
     * book-side check/deposit correction, no overstated/understated distinction tracked).
     * Rendered with blank cells rather than an invented value.
     */
    private function untrackedLine(string $label): array
    {
        return ['label' => $label, 'indent' => true, 'book' => null, 'bank' => null, 'comment' => null, 'tracked' => false];
    }

    private function bankLine(string $label, Collection $items, bool $negate): array
    {
        $amount = (float) $items->sum('amount');
        $amount = $negate ? -$amount : $amount;

        return [
            'label' => $label,
            'indent' => true,
            'book' => null,
            'bank' => $amount,
            'comment' => $this->comment($items),
            'tracked' => true,
        ];
    }

    private function bookLine(string $label, Collection $items, bool $indent): array
    {
        return [
            'label' => $label,
            'indent' => $indent,
            'book' => (float) $items->sum('adjustment_amount'),
            'bank' => null,
            'comment' => $this->comment($items),
            'tracked' => true,
        ];
    }

    private function comment(Collection $items): ?string
    {
        if ($items->isEmpty()) {
            return null;
        }

        return $items->map(function (ReconciliationDiscrepancy $item): string {
            $reference = filled($item->evidence_reference) ? $item->evidence_reference.' — ' : '';

            return $reference.$item->explanation;
        })->implode("\n");
    }
}
