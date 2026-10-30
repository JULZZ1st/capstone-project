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

    public function test_disbursement_request_saves_new_fields_as_pending_and_displays_in_history(): void
    {
        $requester = $this->userWithRole('staff');

        $response = $this->actingAs($requester)->post(route('disbursements.store'), [
            'amount' => 2450.75,
            'purpose' => 'Medical assistance payout',
            'recipient' => 'Juan Dela Cruz',
            'requested_date' => '2026-09-12',
            'notes' => 'Urgent dialysis support',
        ]);

        $response->assertRedirect(route('disbursements.index'));
        $this->assertDatabaseHas('cash_disbursements', [
            'amount' => 2450.75,
            'purpose' => 'Medical assistance payout',
            'recipient' => 'Juan Dela Cruz',
            'notes' => 'Urgent dialysis support',
            'requested_by' => $requester->id,
            'status' => 'pending',
        ]);
        $this->assertSame('2026-09-12', CashDisbursement::query()->firstOrFail()->requested_date->toDateString());

        $this->actingAs($requester)
            ->get(route('disbursements.index'))
            ->assertOk()
            ->assertSee('Sep 12, 2026')
            ->assertSee('Medical assistance payout')
            ->assertSee('Juan Dela Cruz')
            ->assertSee('PHP 2,450.75')
            ->assertSee($requester->name)
            ->assertSee('pending')
            ->assertSee(route('disbursements.show', CashDisbursement::query()->firstOrFail()), false)
            ->assertSee('Urgent dialysis support');
    }

    public function test_request_modal_preserves_unicode_quotes_and_backslashes(): void
    {
        $requester = $this->userWithRole('staff');
        $purpose = 'Tulong sa "Pamilya" – ₱1,250';
        $notes = 'Drop-off at sitio Niño\\Relief & assistance';

        $this->actingAs($requester)->post(route('disbursements.store'), [
            'amount' => 1250,
            'purpose' => $purpose,
            'recipient' => 'José Niño',
            'requested_date' => '2026-09-12',
            'notes' => $notes,
        ])->assertRedirect(route('disbursements.index'));

        $disbursement = CashDisbursement::query()->firstOrFail();
        $response = $this->get(route('disbursements.index'))->assertOk();
        $document = new \DOMDocument();
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $row = (new \DOMXPath($document))
            ->query('//tr[@data-fund-request-id="'.$disbursement->id.'"]')
            ->item(0);

        $this->assertInstanceOf(\DOMElement::class, $row);
        $this->assertSame($purpose, $row->getAttribute('data-purpose'));
        $this->assertSame($notes, $row->getAttribute('data-notes'));
    }

    public function test_disbursement_request_requires_new_required_fields(): void
    {
        $requester = $this->userWithRole('staff');

        $this->actingAs($requester)
            ->post(route('disbursements.store'), [
                'amount' => 2450.75,
                'purpose' => 'Medical assistance payout',
            ])
            ->assertSessionHasErrors(['recipient', 'requested_date']);

        $this->assertDatabaseCount('cash_disbursements', 0);
    }

    public function test_requester_cannot_approve_own_disbursement(): void
    {
        $requester = $this->userWithRole('staff');
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
        $requester = $this->userWithRole('staff');
        $admin = $this->userWithRole('admin');
        $disbursement = CashDisbursement::query()->create([
            'amount' => 1250,
            'purpose' => 'Emergency supplies',
            'requested_by' => $requester->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->patch(route('disbursements.approve', $disbursement))->assertRedirect();

        $this->assertDatabaseHas('cash_disbursements', ['id' => $disbursement->id, 'status' => 'approved', 'approved_by' => $admin->id]);
        $this->assertNotNull($disbursement->fresh()->approved_at);
        $this->assertDatabaseHas('security_activity_events', ['action' => 'disbursement_approved', 'actor_id' => $admin->id]);
    }

    public function test_pending_disbursement_detail_shows_admin_decision_actions(): void
    {
        $requester = $this->userWithRole('staff');
        $admin = $this->userWithRole('admin');
        $disbursement = CashDisbursement::query()->create([
            'amount' => 1250,
            'purpose' => 'Emergency supplies',
            'recipient' => 'Community Pharmacy',
            'requested_date' => '2026-09-12',
            'notes' => 'Prescription support',
            'requested_by' => $requester->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('disbursements.show', $disbursement))
            ->assertOk()
            ->assertSee('Emergency supplies')
            ->assertSee('Community Pharmacy')
            ->assertSee('PHP 1,250.00')
            ->assertSee('Prescription support')
            ->assertSee('Approve')
            ->assertSee('Reject');
    }

    public function test_admin_can_approve_another_users_pending_request_from_request_history(): void
    {
        $requester = $this->userWithRole('staff');
        $admin = $this->userWithRole('admin');
        CashDisbursement::query()->create([
            'amount' => 1250,
            'purpose' => 'Emergency supplies',
            'requested_by' => $requester->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('disbursements.index'))
            ->assertOk()
            ->assertSee('Approve Request')
            ->assertSee('data-can-approve="true"', false)
            ->assertSee('id="modal_rejection_reason"', false)
            ->assertSee('form="reject-fund-request-form"', false)
            ->assertSee('id="approve-fund-request-form"', false)
            ->assertSee('id="reject-fund-request-form"', false);
    }

    public function test_non_admin_cannot_approve_or_reject_server_side(): void
    {
        $requester = $this->userWithRole('staff');
        $secretary = $this->userWithRole('staff');
        $disbursement = CashDisbursement::query()->create([
            'amount' => 1250,
            'purpose' => 'Emergency supplies',
            'requested_by' => $requester->id,
            'status' => 'pending',
        ]);

        $this->actingAs($secretary)
            ->patch(route('disbursements.approve', $disbursement))
            ->assertForbidden();

        $this->actingAs($secretary)
            ->patch(route('disbursements.reject', $disbursement), ['rejection_reason' => 'Missing details'])
            ->assertForbidden();

        $this->assertDatabaseHas('cash_disbursements', ['id' => $disbursement->id, 'status' => 'pending']);
    }

    public function test_non_admin_can_view_pending_details_without_decision_actions(): void
    {
        $requester = $this->userWithRole('staff');
        $disbursement = CashDisbursement::query()->create([
            'amount' => 1250,
            'purpose' => 'Emergency supplies',
            'requested_by' => $requester->id,
            'status' => 'pending',
        ]);

        $this->actingAs($requester)
            ->get(route('disbursements.show', $disbursement))
            ->assertOk()
            ->assertSee('Emergency supplies')
            ->assertDontSee('Admin decision')
            ->assertDontSee('Approve')
            ->assertDontSee('Reject');
    }

    public function test_pending_unreconciled_contribution_can_be_voided_and_audited(): void
    {
        $admin = $this->userWithRole('admin');
        $creator = $this->userWithRole('staff');
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
        $requester = $this->userWithRole('staff');
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
        $requester = $this->userWithRole('staff');
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
        $requester = $this->userWithRole('staff');
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
        $requester = $this->userWithRole('staff');
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
