<?php

namespace App\Services\Disbursements;

use App\Models\Contribution;
use App\Models\SecurityActivityEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoidContribution
{
    public function handle(Contribution $contribution, string $reason, User $actor): Contribution
    {
        return DB::transaction(function () use ($contribution, $reason, $actor) {
            $contribution = Contribution::query()->lockForUpdate()->findOrFail($contribution->id);

            if (! $contribution->isCash() || $contribution->status !== 'pending' || $contribution->isVoided() || $contribution->ledgerEntry()->exists()) {
                throw ValidationException::withMessages(['contribution' => 'Only pending, unreconciled cash contributions can be voided.']);
            }

            $contribution->forceFill(['voided_at' => now(), 'voided_by' => $actor->id, 'void_reason' => $reason, 'status' => 'cancelled'])->save();
            SecurityActivityEvent::query()->create([
                'user_id' => $contribution->created_by,
                'actor_id' => $actor->id,
                'action' => 'contribution_voided',
                'summary' => "{$actor->name} voided contribution #{$contribution->id}.",
                'metadata' => ['contribution_id' => $contribution->id, 'amount' => $contribution->amount, 'reason' => $reason],
                'occurred_at' => now(),
            ]);

            return $contribution;
        });
    }
}