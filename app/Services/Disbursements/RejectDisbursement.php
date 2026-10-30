<?php

namespace App\Services\Disbursements;

use App\Models\CashDisbursement;
use App\Models\SecurityActivityEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RejectDisbursement
{
    public function handle(CashDisbursement $disbursement, string $reason, User $actor): CashDisbursement
    {
        return DB::transaction(function () use ($disbursement, $reason, $actor) {
            $disbursement = CashDisbursement::query()->lockForUpdate()->findOrFail($disbursement->id);

            if (! $disbursement->isPending()) {
                throw ValidationException::withMessages(['disbursement' => 'Only pending fund requests can be rejected.']);
            }

            if ((int) $disbursement->requested_by === (int) $actor->id) {
                throw ValidationException::withMessages(['disbursement' => 'The requester cannot reject their own fund request.']);
            }

            $disbursement->forceFill(['status' => 'rejected', 'rejected_at' => now(), 'rejection_reason' => $reason])->save();
            $disbursement->latestVoucher?->forceFill([
                'review_status' => 'rejected',
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_reason' => $reason,
            ])->save();
            SecurityActivityEvent::query()->create([
                'user_id' => $disbursement->requested_by,
                'actor_id' => $actor->id,
                'action' => 'disbursement_rejected',
                'summary' => "{$actor->name} rejected fund request #{$disbursement->id}.",
                'metadata' => ['disbursement_id' => $disbursement->id, 'reason' => $reason],
                'occurred_at' => now(),
            ]);

            return $disbursement;
        });
    }
}
