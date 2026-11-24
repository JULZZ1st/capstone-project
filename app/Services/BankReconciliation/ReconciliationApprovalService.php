<?php

namespace App\Services\BankReconciliation;

use App\Models\BankLedgerEntry;
use App\Models\BankReconciliation;
use App\Models\ReconciliationDiscrepancy;
use App\Models\SecurityActivityEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReconciliationApprovalService
{
    public function submit(BankReconciliation $period, User $actor): BankReconciliation
    {
        return DB::transaction(function () use ($period, $actor) {
            $period = BankReconciliation::query()->lockForUpdate()->findOrFail($period->id);
            if (! $period->isDraft() || (int) $period->prepared_by !== (int) $actor->id) {
                throw ValidationException::withMessages(['period' => 'Only the staff member who prepared this draft can submit it.']);
            }
            $this->validateForApproval($period, forSubmission: true);
            $period->forceFill(['status' => 'ready_for_review', 'submitted_at' => now()])->save();
            $this->audit($actor, 'bank_reconciliation_submitted', $period, "{$actor->name} submitted reconciliation period #{$period->id} for review.");
            return $period;
        });
    }

    public function approve(BankReconciliation $period, User $actor): BankReconciliation
    {
        return DB::transaction(function () use ($period, $actor) {
            $period = BankReconciliation::query()->lockForUpdate()->findOrFail($period->id);
            if (! $period->isReadyForReview() || (int) $period->prepared_by === (int) $actor->id) {
                throw ValidationException::withMessages(['period' => 'Only an independent administrator can approve this period.']);
            }
            $this->validateForApproval($period);
            $period->forceFill(['status' => 'approved_locked', 'approved_by' => $actor->id, 'approved_at' => now(), 'locked_by' => $actor->id, 'locked_at' => now()])->save();
            $this->audit($actor, 'bank_reconciliation_approved', $period, "{$actor->name} approved and locked reconciliation period #{$period->id}.");
            return $period;
        });
    }

    public function reject(BankReconciliation $period, User $actor, string $reason): BankReconciliation
    {
        return DB::transaction(function () use ($period, $actor, $reason) {
            $period = BankReconciliation::query()->lockForUpdate()->findOrFail($period->id);
            if (! $period->isReadyForReview() || (int) $period->prepared_by === (int) $actor->id) {
                throw ValidationException::withMessages(['period' => 'Only an independent administrator can reject this period.']);
            }
            $period->forceFill(['status' => 'draft', 'rejection_reason' => $reason, 'correction_requested_by' => $actor->id, 'correction_requested_at' => now(), 'correction_count' => $period->correction_count + 1])->save();
            $this->audit($actor, 'bank_reconciliation_rejected', $period, "{$actor->name} returned reconciliation period #{$period->id} for correction: {$reason}");
            return $period;
        });
    }

    public function reopen(BankReconciliation $period, User $actor, string $reason): BankReconciliation
    {
        return DB::transaction(function () use ($period, $actor, $reason) {
            $period = BankReconciliation::query()->lockForUpdate()->findOrFail($period->id);
            if (! $period->isApproved()) throw ValidationException::withMessages(['period' => 'Only approved periods can be reopened.']);
            $period->forceFill(['status' => 'draft', 'locked_by' => null, 'locked_at' => null, 'approved_by' => null, 'approved_at' => null, 'rejection_reason' => $reason, 'correction_requested_by' => $actor->id, 'correction_requested_at' => now(), 'correction_count' => $period->correction_count + 1])->save();
            $this->audit($actor, 'bank_reconciliation_reopened', $period, "{$actor->name} reopened locked reconciliation period #{$period->id}: {$reason}");
            return $period;
        });
    }

    private function validateForApproval(BankReconciliation $period, bool $forSubmission = false): void
    {
        app(ReconciliationPeriodService::class)->recalculate($period->fresh());
        $period->refresh();
        $errors = [];
        if ($period->bank_ending_balance === null) $errors['bank_ending_balance'] = 'A raw bank ending balance is required.';
        if (abs((float) $period->reconciliation_difference) >= 0.005) $errors['reconciliation_difference'] = 'The adjusted bank balance must equal the financial ledger ending balance.';
        $blockingDiscrepancy = $period->discrepancies()->get()->contains(fn (ReconciliationDiscrepancy $discrepancy) => $forSubmission ? $discrepancy->blocksSubmission() : $discrepancy->needsFurtherAction());
        if ($blockingDiscrepancy) $errors['discrepancies'] = $forSubmission
            ? 'Unexplained differences must be resolved, or reviewed and accepted by Admin, before this period can be submitted.'
            : 'Unexplained differences must be resolved; timing items require valid evidence and review.';
        $unmatchedQuery = BankLedgerEntry::query()->forBankAccount($period->bank_account)->whereDate('transaction_date', '>=', $period->period_start->toDateString())->whereDate('transaction_date', '<=', $period->period_end->toDateString())->whereIn('status', ['unmatched', 'unreconciled'])->with('discrepancies');
        $unmatched = (clone $unmatchedQuery)->get()->contains(fn (BankLedgerEntry $entry) => $forSubmission ? $entry->blocksSubmission() : $entry->blocksApproval());
        if ($unmatched) $errors['matching'] = $forSubmission
            ? 'Bank movements must be matched, or have a submission-ready discrepancy explanation, before this period can be submitted.'
            : 'Bank movements must be matched, or have a fully resolved/documented discrepancy explanation, before this period can be approved.';
        if ($errors !== []) throw ValidationException::withMessages($errors);
    }

    private function audit(User $actor, string $action, BankReconciliation $period, string $summary): void
    {
        SecurityActivityEvent::query()->create(['actor_id' => $actor->id, 'action' => $action, 'summary' => $summary, 'metadata' => ['bank_reconciliation_id' => $period->id, 'status' => $period->status], 'occurred_at' => now()]);
    }
}
