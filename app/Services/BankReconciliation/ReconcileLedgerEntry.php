<?php

namespace App\Services\BankReconciliation;

use App\Models\BankLedgerEntry;
use App\Models\Contribution;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReconcileLedgerEntry
{
    public function handle(BankLedgerEntry $entry, Contribution $contribution, User $user): BankLedgerEntry
    {
        return DB::transaction(function () use ($entry, $contribution, $user) {
            $entry = BankLedgerEntry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();

            if ($entry->isReconciled()) {
                throw ValidationException::withMessages([
                    'bank_ledger_entry_id' => 'This ledger entry has already been reconciled.',
                ]);
            }

            if ($contribution->ledgerEntry()->exists()) {
                throw ValidationException::withMessages([
                    'contribution_id' => 'This donation has already been reconciled against another ledger entry.',
                ]);
            }

            $entry->forceFill([
                'matched_contribution_id' => $contribution->id,
                'matched_by' => $user->id,
                'matched_at' => now(),
                'status' => 'reconciled',
            ])->save();

            return $entry;
        });
    }
}
