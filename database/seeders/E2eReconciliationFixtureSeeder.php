<?php

namespace Database\Seeders;

use App\Models\CashDisbursement;
use App\Models\Contribution;
use App\Models\Donor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class E2eReconciliationFixtureSeeder extends Seeder
{
    public function run(): void
    {
        $staff = User::query()->where('email', 'e2e.staff@example.test')->firstOrFail();
        $admin = User::query()->where('email', 'e2e.admin@example.test')->firstOrFail();
        $donor = Donor::query()->updateOrCreate(['email' => 'e2e.fixture@example.test'], [
            'donor_type' => 'individual', 'full_name' => 'E2E Fixture Donor',
            'address' => 'E2E Test Address', 'contact_number' => '09170000002', 'status' => 'active',
        ]);

        Contribution::query()->updateOrCreate(['reference_number' => 'E2E-CONTRIB-001'], [
            'donor_id' => $donor->id, 'created_by' => $staff->id, 'verified_by' => $admin->id,
            'type' => 'cash', 'status' => 'verified', 'amount' => 1000,
            'payment_method' => 'bank_transfer', 'received_at' => '2026-10-05 10:00:00', 'verified_at' => now(),
        ]);

        foreach ([['E2E-DISB-001', 150], ['E2E-DISB-002', 150]] as [$reference, $amount]) {
            CashDisbursement::query()->updateOrCreate(['supporting_reference' => $reference], [
                'amount' => $amount, 'purpose' => 'E2E approved disbursement '.$reference,
                'recipient' => 'E2E Recipient', 'requested_date' => '2026-10-06',
                'requested_by' => $staff->id, 'approved_by' => $admin->id,
                'approved_at' => '2026-10-06 10:00:00', 'status' => 'approved',
            ]);
        }
    }
}
