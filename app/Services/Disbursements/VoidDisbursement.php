<?php

namespace App\Services\Disbursements;

use App\Models\CashDisbursement;
use App\Models\SecurityActivityEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoidDisbursement
{
    public function handle(CashDisbursement $disbursement, string $reason, User $actor): CashDisbursement
    {
        return DB::transaction(function () use ($disbursement, $reason, $actor) {
            $disbursement = CashDisbursement::query()->lockForUpdate()->findOrFail($disbursement->id);

            if ($disbursement->status !== 'approved' || $disbursement->isVoided()) {
                throw ValidationException::withMessages(['disbursement' => 'Only approved, active fund requests can be cancelled.']);
            }

            $disbursement->forceFill(['voided_at' => now(), 'voided_by' => $actor->id, 'void_reason' => $reason])->save();
            SecurityActivityEvent::query()->create([
                'user_id' => $disbursement->requested_by,
                'actor_id' => $actor->id,
                'action' => 'disbursement_voided',
                'summary' => "{$actor->name} cancelled fund request #{$disbursement->id}.",
                'metadata' => ['disbursement_id' => $disbursement->id, 'amount' => $disbursement->amount, 'reason' => $reason],
                'occurred_at' => now(),
            ]);

            return $disbursement;
        });
    }
}
