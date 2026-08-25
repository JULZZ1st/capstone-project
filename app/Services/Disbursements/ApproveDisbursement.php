<?php

namespace App\Services\Disbursements;

use App\Models\CashDisbursement;
use App\Models\SecurityActivityEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveDisbursement
{
    public function handle(CashDisbursement $disbursement, User $approver): CashDisbursement
    {
        return DB::transaction(function () use ($disbursement, $approver) {
            $disbursement = CashDisbursement::query()->lockForUpdate()->findOrFail($disbursement->id);

            if (! $disbursement->isPending()) {
                throw ValidationException::withMessages(['disbursement' => 'Only pending disbursements can be approved.']);
            }

            if ((int) $disbursement->requested_by === (int) $approver->id) {
                throw ValidationException::withMessages(['disbursement' => 'The requester cannot approve their own disbursement.']);
            }

            $disbursement->forceFill(['status' => 'approved', 'approved_by' => $approver->id, 'approved_at' => now()])->save();
            $this->audit($disbursement, $approver, 'disbursement_approved');

            return $disbursement;
        });
    }

    private function audit(CashDisbursement $disbursement, User $actor, string $action): void
    {
        SecurityActivityEvent::query()->create([
            'user_id' => $disbursement->requested_by,
            'actor_id' => $actor->id,
            'action' => $action,
            'summary' => "{$actor->name} approved cash disbursement #{$disbursement->id}.",
            'metadata' => ['disbursement_id' => $disbursement->id, 'amount' => $disbursement->amount],
            'occurred_at' => now(),
        ]);
    }
}