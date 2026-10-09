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
                'recipient' => $data['recipient'],
                'requested_date' => $data['requested_date'],
                'notes' => $data['notes'] ?? null,
                'supporting_reference' => $data['supporting_reference'] ?? null,
                'requested_by' => $user->id,
                'status' => 'pending',
            ]);

            SecurityActivityEvent::query()->create([
                'user_id' => $user->id,
                'actor_id' => $user->id,
                'action' => 'disbursement_requested',
                'summary' => "{$user->name} created a fund request for {$disbursement->amount}.",
                'metadata' => [
                    'disbursement_id' => $disbursement->id,
                    'purpose' => $disbursement->purpose,
                    'recipient' => $disbursement->recipient,
                    'requested_date' => $disbursement->requested_date?->toDateString(),
                    'supporting_reference' => $disbursement->supporting_reference,
                ],
                'occurred_at' => now(),
            ]);

            return $disbursement;
        });
    }
}
