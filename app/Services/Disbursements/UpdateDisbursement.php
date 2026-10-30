<?php

namespace App\Services\Disbursements;

use App\Models\CashDisbursement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateDisbursement
{
    public function handle(CashDisbursement $disbursement, array $data): CashDisbursement
    {
        return DB::transaction(function () use ($disbursement, $data): CashDisbursement {
            $disbursement = CashDisbursement::query()->lockForUpdate()->findOrFail($disbursement->id);

            if (! $disbursement->isPending() || $disbursement->isVoided()) {
                throw ValidationException::withMessages([
                    'disbursement' => 'Only pending, active fund requests can be edited.',
                ]);
            }

            $disbursement->forceFill(collect($data)->except('dv_number')->all());

            if ($disbursement->hasTreasurerCertification() && $disbursement->isDirty([
                'amount', 'purpose', 'recipient', 'payee_address', 'payee_tin',
                'fund_source', 'obr_number', 'requested_date', 'province',
                'city_municipality', 'barangay',
            ])) {
                $disbursement->forceFill([
                    'treasurer_certified_by' => null,
                    'treasurer_certified_at' => null,
                    'treasurer_certification_notes' => null,
                ]);
            }

            $disbursement->save();

            return $disbursement->refresh();
        });
    }
}
