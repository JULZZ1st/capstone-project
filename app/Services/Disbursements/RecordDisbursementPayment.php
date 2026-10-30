<?php

namespace App\Services\Disbursements;

use App\Models\CashDisbursement;
use App\Models\SecurityActivityEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordDisbursementPayment
{
    public function handle(CashDisbursement $disbursement, array $data, User $releaser)
    {
        return DB::transaction(function () use ($disbursement, $data, $releaser) {
            $disbursement = CashDisbursement::query()->lockForUpdate()->findOrFail($disbursement->id);

            if ($disbursement->status !== 'approved' || $disbursement->isVoided()) {
                throw ValidationException::withMessages(['disbursement' => 'Only approved, active fund requests can be marked as disbursed.']);
            }

            if ($disbursement->payment()->exists() || $disbursement->payment_status === 'disbursed') {
                throw ValidationException::withMessages(['disbursement' => 'This fund request already has an actual payment recorded.']);
            }

            $difference = abs((float) $data['amount'] - (float) $disbursement->amount);
            if ($difference >= 0.005) {
                throw ValidationException::withMessages(['amount' => 'The recorded payment must equal the approved request amount. Partial payments are not supported by the current workflow.']);
            }

            $payment = $disbursement->payment()->create([
                'payment_date' => $data['payment_date'],
                'amount' => $data['amount'],
                'payment_method' => $data['payment_method'],
                'reference_number' => $data['reference_number'] ?? null,
                'released_by' => $releaser->id,
                'released_at' => now(),
            ]);

            $disbursement->forceFill([
                'payment_status' => 'disbursed',
                'disbursed_at' => now(),
            ])->save();

            SecurityActivityEvent::query()->create([
                'user_id' => $disbursement->requested_by,
                'actor_id' => $releaser->id,
                'action' => 'disbursement_payment_recorded',
                'summary' => "{$releaser->name} recorded actual payment for fund request #{$disbursement->id}.",
                'metadata' => [
                    'disbursement_id' => $disbursement->id,
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount,
                    'payment_date' => $payment->payment_date?->toDateString(),
                    'payment_method' => $payment->payment_method,
                    'reference_number' => $payment->reference_number,
                    'released_at' => $payment->released_at?->toIso8601String(),
                ],
                'ip_address' => request()->ip(),
                'occurred_at' => now(),
            ]);

            return $payment;
        });
    }
}
