<?php

namespace Tests\Feature;

use App\Models\CashDisbursement;
use App\Models\Contribution;
use App\Models\Donor;
use App\Models\Role;
use App\Models\User;
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

    private function userWithRole(string $role): User
    {
        $roleModel = Role::query()->firstOrCreate(['name' => $role], ['display_name' => ucfirst($role), 'description' => ucfirst($role).' role']);
        return User::factory()->create(['role_id' => $roleModel->id]);
    }
}