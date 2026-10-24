<?php

namespace Tests\Feature;

use App\Models\BankReconciliation;
use App\Models\CashDisbursement;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminRbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_required_operational_actions_and_review_controls(): void
    {
        $admin = $this->userWithRole('admin');
        $treasurer = $this->userWithRole('treasurer');

        CashDisbursement::query()->create([
            'amount' => 500,
            'purpose' => 'Relief supplies',
            'recipient' => 'Barangay residents',
            'requested_date' => '2026-10-21',
            'requested_by' => $treasurer->id,
            'status' => 'pending',
        ]);
        BankReconciliation::query()->create([
            'date' => '2026-10-31',
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'opening_balance' => 1000,
            'bank_ending_balance' => 1000,
            'status' => 'ready_for_review',
            'prepared_by' => $treasurer->id,
        ]);

        $this->actingAs($admin)->get(route('donors.index'))
            ->assertOk()->assertSee('Add Donor Profile');
        $this->actingAs($admin)->get(route('donations.index'))
            ->assertOk()->assertSee('Log New Contribution');
        $this->actingAs($admin)->get(route('inventory.index'))
            ->assertOk()->assertSee('Add New Inventory Item');
        $this->actingAs($admin)->get(route('disbursements.index'))
            ->assertOk()->assertSee('Approve Request');
        $this->actingAs($admin)->get(route('reports.index'))
            ->assertOk()->assertSee('Report Generator');
        $this->actingAs($admin)->get(route('system-security.index'))
            ->assertOk()->assertSee('Create Staff Account');
        $this->actingAs($admin)->get(route('bank-reconciliation.index'))
            ->assertOk()
            ->assertSee('Approve &amp; Lock', false)
            ->assertDontSee('Import Statement');
    }

    public function test_admin_cannot_import_or_match_bank_entries(): void
    {
        $admin = $this->userWithRole('admin');
        $csv = "statement_id,description,amount,transaction_date\nadmin-import,Blocked,100,2026-10-21\n";

        $this->actingAs($admin)->post(route('bank-reconciliation.import'), [
            'statement' => UploadedFile::fake()->createWithContent('blocked.csv', $csv),
        ])->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $roleModel = Role::query()->firstOrCreate(['name' => $role], [
            'display_name' => ucfirst($role),
            'description' => ucfirst($role),
        ]);

        return User::factory()->create(['role_id' => $roleModel->id]);
    }
}
