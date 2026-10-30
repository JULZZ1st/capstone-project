<?php

namespace App\Services\Disbursements;

use App\Models\CashDisbursement;
use App\Models\SecurityActivityEvent;
use App\Models\User;
use App\Support\Workspaces\WorkspaceContext;
use Illuminate\Support\Facades\DB;

class RequestDisbursement
{
    public function __construct(private readonly GenerateDvNumber $dvNumberGenerator) {}

    public function handle(array $data, User $user): CashDisbursement
    {
        return DB::transaction(function () use ($data, $user) {
            $requestedDate = now()->parse($data['requested_date']);
            $workspaceId = app(WorkspaceContext::class)->requireId();

            $disbursement = CashDisbursement::query()->create([
                'workspace_id' => $workspaceId,
                'amount' => $data['amount'],
                'purpose' => $data['purpose'],
                'recipient' => $data['recipient'],
                'requested_date' => $data['requested_date'],
                'payee_address' => $data['payee_address'],
                'province' => $data['province'] ?? null,
                'city_municipality' => $data['city_municipality'] ?? null,
                'barangay' => $data['barangay'] ?? null,
                'payee_tin' => $data['payee_tin'] ?? null,
                'fund_source' => $data['fund_source'],
                'obr_number' => $data['obr_number'] ?? null,
                'dv_number' => $this->dvNumberGenerator->handle($workspaceId, $requestedDate),
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
                    'dv_number' => $disbursement->dv_number,
                    'requested_date' => $disbursement->requested_date?->toDateString(),
                    'supporting_reference' => $disbursement->supporting_reference,
                ],
                'occurred_at' => now(),
            ]);

            return $disbursement;
        });
    }
}
