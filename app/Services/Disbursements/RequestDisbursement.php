<?php

namespace App\Services\Disbursements;

use App\Models\CashDisbursement;
use App\Models\SecurityActivityEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RequestDisbursement
{
    public function handle(array $data, User $user): CashDisbursement
    {
        return DB::transaction(function () use ($data, $user) {
            $disbursement = CashDisbursement::query()->create([
                'amount' => $data['amount'],
                'purpose' => $data['purpose'],
                'requested_by' => $user->id,
                'status' => 'pending',
            ]);

            SecurityActivityEvent::query()->create([
                'user_id' => $user->id,
                'actor_id' => $user->id,
                'action' => 'disbursement_requested',
                'summary' => "{$user->name} requested a cash disbursement of {$disbursement->amount}.",
                'metadata' => ['disbursement_id' => $disbursement->id, 'purpose' => $disbursement->purpose],
                'occurred_at' => now(),
            ]);

            return $disbursement;
        });
    }
}