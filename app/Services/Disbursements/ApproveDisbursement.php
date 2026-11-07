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
                throw ValidationException::withMessages(['disbursement' => 'Only pending fund requests can be approved.']);
            }

            if ((int) $disbursement->requested_by === (int) $approver->id) {
                throw ValidationException::withMessages(['disbursement' => 'The requester cannot approve their own fund request.']);
            }

            if (! $disbursement->hasTreasurerCertification()) {
                throw ValidationException::withMessages(['disbursement' => 'The Treasurer\'s physical certification must be recorded before this fund request can be approved.']);
            }

            $disbursement->forceFill(['status' => 'approved', 'approved_by' => $approver->id, 'approved_at' => now()])->save();
            $disbursement->latestVoucher?->forceFill([
                'review_status' => 'approved',
                'reviewed_by' => $approver->id,
                'reviewed_at' => now(),
                'review_reason' => null,
            ])->save();
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
            'summary' => "{$actor->name} approved fund request #{$disbursement->id}.",
            'metadata' => ['disbursement_id' => $disbursement->id, 'amount' => $disbursement->amount],
            'occurred_at' => now(),
        ]);
    }
}
