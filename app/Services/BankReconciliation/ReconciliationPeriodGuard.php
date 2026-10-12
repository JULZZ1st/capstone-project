<?php

namespace App\Services\BankReconciliation;

use App\Models\BankLedgerEntry;
use App\Models\BankReconciliation;
use Illuminate\Validation\ValidationException;

class ReconciliationPeriodGuard
{
    public function forDate(string $date): ?BankReconciliation
    {
        return BankReconciliation::query()->whereDate('period_start', '<=', $date)->whereDate('period_end', '>=', $date)->latest('id')->first();
    }

    public function assertMutableDate(string $date): void
    {
        if (($period = $this->forDate($date))?->isLocked()) {
            throw ValidationException::withMessages(['period' => 'Transactions in a locked reconciliation period cannot be modified.']);
        }
    }

    public function assertMutableEntry(BankLedgerEntry $entry): void
    {
        $this->assertMutableDate($entry->transaction_date->toDateString());
    }
}
