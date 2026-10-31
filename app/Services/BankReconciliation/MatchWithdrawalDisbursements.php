<?php

namespace App\Services\BankReconciliation;

use App\Models\BankLedgerEntry;
use App\Models\CashDisbursement;
use App\Models\User;
use App\Support\Workspaces\WorkspaceContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MatchWithdrawalDisbursements
{
    public function __construct(private readonly ReconciliationPeriodGuard $periodGuard)
    {
    }

    public function handle(BankLedgerEntry $withdrawal, array $ids, User $actor, ?string $reason = null, bool $finalize = false): BankLedgerEntry
    {
        return DB::transaction(function () use ($withdrawal, $ids, $actor, $reason, $finalize) {
            $withdrawal = BankLedgerEntry::query()->lockForUpdate()->findOrFail($withdrawal->id);

            if (! $withdrawal->isWithdrawal() || $withdrawal->status !== 'unreconciled') {
                throw ValidationException::withMessages(['withdrawal' => 'Only unreconciled bank withdrawals can be matched.']);
            }

            $this->periodGuard->assertMutableEntry($withdrawal);

            // Payment recording and cancellation lock these same parent rows.
            $disbursements = CashDisbursement::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            $eligible = $disbursements->count() === count($ids);
            foreach ($disbursements as $disbursement) {
                $payment = $disbursement->payment()->lockForUpdate()->first();
                $match = $disbursement->withdrawalMatch()->lockForUpdate()->first();
                if ($disbursement->status !== 'approved' || $disbursement->isVoided()
                    || ($payment && $payment->payment_method !== 'bank_transfer')
                    || ($match && (int) $match->bank_ledger_entry_id !== (int) $withdrawal->id)) {
                    $eligible = false;
                    break;
                }
            }

            if (! $eligible) {
                throw ValidationException::withMessages([
                    'disbursement_ids' => 'Selected requests must be approved, active, unpaid or paid by bank transfer, and not matched to another withdrawal.',
                ]);
            }

            $total = $disbursements->reduce(fn (string $sum, CashDisbursement $item) => bcadd($sum, $item->amount, 2), '0.00');
            $difference = bcsub($withdrawal->amount, $total, 2);
            $balanced = bccomp($difference, '0.00', 2) === 0;

            if ($finalize && ! $balanced) {
                throw ValidationException::withMessages(['finalize' => 'The withdrawal can only be reconciled when the difference is zero.']);
            }

            if (! $balanced && blank($reason)) {
                throw ValidationException::withMessages(['discrepancy_reason' => 'An explanation is required while the withdrawal difference remains unresolved.']);
            }

            $withdrawal->matchedDisbursements()->syncWithPivotValues($ids, [
                'workspace_id' => app(WorkspaceContext::class)->requireId(),
            ]);
            $withdrawal->forceFill([
                'discrepancy_amount' => $balanced ? null : $difference,
                'discrepancy_reason' => $balanced ? null : trim($reason),
            ]);

            if ($finalize) {
                $withdrawal->forceFill([
                    'status' => 'reconciled',
                    'reconciled_by' => $actor->id,
                    'reconciled_at' => now(),
                ]);
            }

            $withdrawal->save();

            return $withdrawal;
        });
    }
}
