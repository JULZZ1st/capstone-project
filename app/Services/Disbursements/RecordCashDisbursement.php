<?php

namespace App\Services\Disbursements;

use App\Models\CashDisbursement;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RecordCashDisbursement
{
    public function handle(array $data, User $user): CashDisbursement
    {
        return DB::transaction(fn () => CashDisbursement::query()->create([
            'disbursed_to' => $data['disbursed_to'],
            'purpose' => $data['purpose'],
            'amount' => $data['amount'],
            'payment_method' => Arr::get($data, 'payment_method'),
            'reference_number' => Arr::get($data, 'reference_number'),
            'notes' => Arr::get($data, 'notes'),
            'disbursed_at' => Arr::get($data, 'disbursed_at') ?? now(),
            'created_by' => $user->id,
        ]));
    }
}
