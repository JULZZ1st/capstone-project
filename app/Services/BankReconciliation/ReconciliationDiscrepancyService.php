<?php

namespace App\Services\BankReconciliation;

use App\Models\BankReconciliation;
use App\Models\ReconciliationDiscrepancy;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReconciliationDiscrepancyService
{
    public const TYPES = ['unmatched_amount', 'bank_charge', 'interest', 'outstanding_check', 'deposit_in_transit', 'recording_error'];
    public const CLASSIFICATIONS = ['accounting_adjustment', 'timing_difference'];
    public const STATUSES = ['unresolved', 'resolved', 'accounted'];

    public function create(BankReconciliation $period, array $data, User $user): ReconciliationDiscrepancy
    {
        if ($period->isLocked()) {
            throw ValidationException::withMessages(['period' => 'Locked reconciliation periods cannot be changed.']);
        }

        return DB::transaction(function () use ($period, $data, $user) {
            $discrepancy = $period->discrepancies()->create([
                ...$data,
                'resolved_by' => in_array($data['resolution_status'], ['resolved', 'accounted'], true) ? $user->id : null,
                'resolved_at' => in_array($data['resolution_status'], ['resolved', 'accounted'], true) ? now() : null,
            ]);
            app(ReconciliationPeriodService::class)->recalculate($period->fresh());
            return $discrepancy;
        });
    }
}
