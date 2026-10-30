<?php

namespace App\Services\Disbursements;

use App\Models\CashDisbursement;
use Illuminate\Support\Facades\DB;

class UpdateDisbursement
{
    public function handle(CashDisbursement $disbursement, array $data): CashDisbursement
    {
        return DB::transaction(function () use ($disbursement, $data): CashDisbursement {
            $disbursement->forceFill(collect($data)->except('dv_number')->all())->save();

            return $disbursement->refresh();
        });
    }
}
