<?php

namespace App\Services\BankReconciliation;

use App\Models\BankLedgerEntry;
use App\Models\Contribution;
use App\Models\SecurityActivityEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReconcileLedgerEntry
{
    public function __construct(private readonly ReconciliationPeriodGuard $periodGuard)
    {
    }

    public function handle(BankLedgerEntry $entry, Contribution $contribution, User $user): BankLedgerEntry
    {
        return DB::transaction(function () use ($entry, $contribution, $user) {
            $entry = BankLedgerEntry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();
            $contribution = Contribution::query()->whereKey($contribution->id)->lockForUpdate()->firstOrFail();

            $this->periodGuard->assertMutableEntry($entry);

            if (! in_array($entry->transaction_type, ['deposit', null], true) || bccomp((string) $entry->amount, (string) $contribution->amount, 2) !== 0) {
                throw ValidationException::withMessages(['contribution_id' => 'Only deposits with an exact contribution amount can be reconciled.']);
            }

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

            SecurityActivityEvent::query()->create([
                'actor_id' => $user->id,
                'action' => 'bank_reconciliation_matched',
                'summary' => "{$user->name} reconciled bank ledger entry #{$entry->id} with contribution #{$contribution->id}.",
                'metadata' => ['bank_ledger_entry_id' => $entry->id, 'contribution_id' => $contribution->id, 'amount' => $entry->amount],
                'occurred_at' => now(),
            ]);

            return $entry;
        });
    }
}
