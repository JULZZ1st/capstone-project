<?php

namespace App\Services\Reports;

use App\Models\BankLedgerEntry;
use App\Models\BankReconciliation;

class ReconciliationReportBuilder
{
    public function build(int $periodId): array
    {
        $period = BankReconciliation::query()->with(['discrepancies', 'preparer', 'approver'])->findOrFail($periodId);
        $entries = BankLedgerEntry::query()->whereDate('transaction_date', '>=', $period->period_start->toDateString())->whereDate('transaction_date', '<=', $period->period_end->toDateString())->orderBy('transaction_date')->get();
        return ['period' => $period, 'entries' => $entries, 'outstanding' => $period->discrepancies->where('resolution_status', 'unresolved')];
    }
}
