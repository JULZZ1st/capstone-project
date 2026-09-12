<?php

namespace Tests\Feature;

use App\Models\CashDisbursement;
use App\Models\Contribution;
use App\Models\Donor;
use App\Models\Role;
use App\Models\User;
use App\Support\Dashboard\DashboardMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisbursementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_cannot_approve_own_disbursement(): void
    {
        $requester = $this->userWithRole('treasurer');
        $disbursement = CashDisbursement::query()->create([
            'amount' => 1250,
            'purpose' => 'Emergency supplies',
            'requested_by' => $requester->id,
            'status' => 'pending',
        ]);

        $this->actingAs($requester)
            ->patch(route('disbursements.approve', $disbursement))
            ->assertForbidden();

        $this->assertDatabaseHas('cash_disbursements', ['id' => $disbursement->id, 'status' => 'pending']);
    }

    public function test_admin_approval_is_audited(): void
    {
        $requester = $this->userWithRole('treasurer');
        $admin = $this->userWithRole('admin');
        $disbursement = CashDisbursement::query()->create([
            'amount' => 1250,
            'purpose' => 'Emergency supplies',
            'requested_by' => $requester->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->patch(route('disbursements.approve', $disbursement))->assertRedirect();

        $this->assertDatabaseHas('cash_disbursements', ['id' => $disbursement->id, 'status' => 'approved', 'approved_by' => $admin->id]);
        $this->assertDatabaseHas('security_activity_events', ['action' => 'disbursement_approved', 'actor_id' => $admin->id]);
    }

    public function test_pending_unreconciled_contribution_can_be_voided_and_audited(): void
    {
        $admin = $this->userWithRole('admin');
        $creator = $this->userWithRole('secretary');
        $donor = Donor::query()->create([
            'donor_type' => 'individual', 'full_name' => 'Test Donor', 'address' => 'Test Address',
            'contact_number' => '09170000000', 'email' => 'void-test@example.com', 'status' => 'active',
        ]);
        $contribution = Contribution::query()->create([
            'donor_id' => $donor->id, 'created_by' => $creator->id, 'type' => 'cash', 'status' => 'pending',
            'amount' => 500, 'payment_method' => 'cash', 'received_at' => now(),
        ]);

        $this->actingAs($admin)->patch(route('contributions.void', $contribution), ['void_reason' => 'Duplicate entry'])->assertRedirect();

        $this->assertDatabaseHas('contributions', ['id' => $contribution->id, 'status' => 'cancelled', 'voided_by' => $admin->id, 'void_reason' => 'Duplicate entry']);
        $this->assertDatabaseHas('security_activity_events', ['action' => 'contribution_voided', 'actor_id' => $admin->id]);
    }

    public function test_approved_disbursement_can_be_voided_and_audited(): void
    {
        $requester = $this->userWithRole('treasurer');
        $admin = $this->userWithRole('admin');
        $disbursement = CashDisbursement::query()->create([
            'amount' => 1250, 'purpose' => 'Emergency supplies', 'requested_by' => $requester->id,
            'status' => 'approved', 'approved_by' => $admin->id, 'approved_at' => now(),
        ]);

        $this->actingAs($admin)->patch(route('disbursements.void', $disbursement), ['void_reason' => 'Funds no longer needed'])->assertRedirect();

        $this->assertDatabaseHas('cash_disbursements', ['id' => $disbursement->id, 'voided_by' => $admin->id, 'void_reason' => 'Funds no longer needed']);
        $this->assertDatabaseHas('security_activity_events', ['action' => 'disbursement_voided', 'actor_id' => $admin->id]);
    }

    public function test_requester_cannot_reject_own_disbursement(): void
    {
        $requester = $this->userWithRole('treasurer');
        $disbursement = CashDisbursement::query()->create([
            'amount' => 900,
            'purpose' => 'Relief goods',
            'requested_by' => $requester->id,
            'status' => 'pending',
        ]);

        $this->actingAs($requester)
            ->patch(route('disbursements.reject', $disbursement), ['rejection_reason' => 'Not needed anymore'])
            ->assertForbidden();

        $this->assertDatabaseHas('cash_disbursements', ['id' => $disbursement->id, 'status' => 'pending']);
    }

    public function test_admin_rejection_requires_reason_and_is_audited(): void
    {
        $requester = $this->userWithRole('treasurer');
        $admin = $this->userWithRole('admin');
        $disbursement = CashDisbursement::query()->create([
            'amount' => 900,
            'purpose' => 'Relief goods',
            'requested_by' => $requester->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->patch(route('disbursements.reject', $disbursement), [])
            ->assertSessionHasErrors('rejection_reason');

        $response = $this->actingAs($admin)->patch(route('disbursements.reject', $disbursement), [
            'rejection_reason' => 'Insufficient supporting documents',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cash_disbursements', [
            'id' => $disbursement->id,
            'status' => 'rejected',
            'rejection_reason' => 'Insufficient supporting documents',
        ]);
        $this->assertDatabaseHas('security_activity_events', [
            'action' => 'disbursement_rejected',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_dashboard_and_ledger_totals_only_count_approved_active_disbursements(): void
    {
        $requester = $this->userWithRole('treasurer');
        $admin = $this->userWithRole('admin');

        $approved = CashDisbursement::query()->create([
            'amount' => 1000, 'purpose' => 'Approved spend', 'requested_by' => $requester->id,
            'status' => 'approved', 'approved_by' => $admin->id, 'approved_at' => now(),
        ]);
        CashDisbursement::query()->create([
            'amount' => 500, 'purpose' => 'Still pending', 'requested_by' => $requester->id,
            'status' => 'pending',
        ]);
        CashDisbursement::query()->create([
            'amount' => 300, 'purpose' => 'Rejected spend', 'requested_by' => $requester->id,
            'status' => 'rejected', 'rejected_at' => now(), 'rejection_reason' => 'Denied',
        ]);
        CashDisbursement::query()->create([
            'amount' => 700, 'purpose' => 'Voided approved spend', 'requested_by' => $requester->id,
            'status' => 'approved', 'approved_by' => $admin->id, 'approved_at' => now(),
            'voided_at' => now(), 'voided_by' => $admin->id, 'void_reason' => 'Funds unused',
        ]);

        $metrics = (new DashboardMetrics())->build();

        $this->assertEquals(1000.0, (float) $metrics['disbursements']['total_disbursed']);
        $this->assertDatabaseHas('cash_disbursements', ['id' => $approved->id, 'status' => 'approved']);
    }

    private function userWithRole(string $role): User
    {
        $roleModel = Role::query()->firstOrCreate(['name' => $role], ['display_name' => ucfirst($role), 'description' => ucfirst($role).' role']);
        return User::factory()->create(['role_id' => $roleModel->id]);
    }
}