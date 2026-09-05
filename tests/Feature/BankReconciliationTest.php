<?php

namespace Tests\Feature;

use App\Models\BankLedgerEntry;
use App\Models\Contribution;
use App\Models\Donor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BankReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_bank_reconciliation_index(): void
    {
        $this->get(route('bank-reconciliation.index'))->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_view_the_workspace(): void
    {
        $entry = $this->ledgerEntry(['description' => 'BDO OVER THE COUNTER ICTSI FND']);
        $contribution = $this->electronicContribution(['reference_number' => $entry->reference_number]);

        $response = $this->actingAs(User::factory()->create())->get(route('bank-reconciliation.index'));

        $response->assertOk();
        $response->assertSee('Bank Reconciliation Workspace');
        $response->assertSee($entry->description);
        $response->assertSee($contribution->donor->full_name);
    }

    public function test_treasurer_can_import_csv_bank_transactions(): void
    {
        $csv = "statement_id,description,reference_number,amount,transaction_date,payment_method\n".
            "stmt-100,GCash incoming,GC-100,1250.50,2026-09-05,gcash\n";

        $response = $this->actingAs($this->userWithRole('treasurer'))->post(route('bank-reconciliation.import'), [
            'statement' => UploadedFile::fake()->createWithContent('statement.csv', $csv),
        ]);

        $response->assertRedirect(route('bank-reconciliation.index'));
        $this->assertDatabaseHas('bank_ledger_entries', [
            'statement_id' => 'stmt-100',
            'reference_number' => 'GC-100',
            'status' => 'unmatched',
        ]);
    }

    public function test_duplicate_statement_ids_are_rejected_without_partial_import(): void
    {
        $this->ledgerEntry(['statement_id' => 'stmt-duplicate']);
        $csv = "statement_id,description,amount,transaction_date\n".
            "stmt-duplicate,Duplicate,100,2026-09-05\n".
            "stmt-new,New transaction,200,2026-09-05\n";

        $response = $this->actingAs($this->userWithRole('treasurer'))->post(route('bank-reconciliation.import'), [
            'statement' => UploadedFile::fake()->createWithContent('statement.csv', $csv),
        ]);

        $response->assertSessionHasErrors('statement');
        $this->assertDatabaseMissing('bank_ledger_entries', ['statement_id' => 'stmt-new']);
    }

    public function test_secretary_cannot_import_bank_transactions(): void
    {
        $response = $this->actingAs($this->userWithRole('secretary'))->post(route('bank-reconciliation.import'), [
            'statement' => UploadedFile::fake()->createWithContent('statement.csv', "statement_id,description,amount,transaction_date\nstmt-1,Test,100,2026-09-05\n"),
        ]);

        $response->assertForbidden();
    }

    public function test_matching_reference_number_surfaces_as_a_suggestion(): void
    {
        $entry = $this->ledgerEntry(['reference_number' => 'BDO-TRF-55419', 'amount' => 100000]);
        $contribution = $this->electronicContribution(['reference_number' => 'BDO-TRF-55419', 'amount' => 100000]);
        $this->electronicContribution(['reference_number' => 'UNRELATED-REF', 'amount' => 5000]);

        $response = $this->actingAs(User::factory()->create())->get(route('bank-reconciliation.index'));

        $response->assertOk();
        $response->assertSee('1 suggestion found');
        $response->assertSee($contribution->donor->full_name);
        $response->assertSee($contribution->receipt_number);
    }

    public function test_unverified_electronic_contributions_are_not_match_candidates(): void
    {
        $entry = $this->ledgerEntry(['reference_number' => 'UNVERIFIED-REF']);
        $contribution = $this->electronicContribution([
            'reference_number' => 'UNVERIFIED-REF',
            'status' => 'pending',
        ]);

        $response = $this->actingAs(User::factory()->create())->get(route('bank-reconciliation.index'));

        $response->assertOk();
        $response->assertDontSee($contribution->donor->full_name);
        $response->assertSee($entry->description);
    }

    public function test_treasurer_can_reconcile_a_matched_entry(): void
    {
        $entry = $this->ledgerEntry(['reference_number' => 'GCSH-7741235', 'amount' => 5000]);
        $contribution = $this->electronicContribution(['reference_number' => 'GCSH-7741235', 'amount' => 5000]);
        $treasurer = $this->userWithRole('treasurer');

        $response = $this->actingAs($treasurer)->post(route('bank-reconciliation.reconcile'), [
            'bank_ledger_entry_id' => $entry->id,
            'contribution_id' => $contribution->id,
        ]);

        $response->assertRedirect(route('bank-reconciliation.index'));
        $this->assertDatabaseHas('bank_ledger_entries', [
            'id' => $entry->id,
            'status' => 'reconciled',
            'matched_contribution_id' => $contribution->id,
            'matched_by' => $treasurer->id,
        ]);
    }

    public function test_reconciled_transactions_appear_in_history(): void
    {
        $entry = $this->ledgerEntry(['reference_number' => 'GCSH-HISTORY']);
        $contribution = $this->electronicContribution(['reference_number' => 'GCSH-HISTORY']);
        $treasurer = $this->userWithRole('treasurer');

        $this->actingAs($treasurer)->post(route('bank-reconciliation.reconcile'), [
            'bank_ledger_entry_id' => $entry->id,
            'contribution_id' => $contribution->id,
        ]);

        $this->actingAs($treasurer)
            ->get(route('bank-reconciliation.index'))
            ->assertOk()
            ->assertSee('Reconciliation History')
            ->assertSee($contribution->receipt_number);
    }

    public function test_reconciled_entry_no_longer_appears_in_the_workspace(): void
    {
        $entry = $this->ledgerEntry(['reference_number' => 'GCSH-7741235', 'amount' => 5000]);
        $contribution = $this->electronicContribution(['reference_number' => 'GCSH-7741235', 'amount' => 5000]);

        $this->actingAs($this->userWithRole('treasurer'))->post(route('bank-reconciliation.reconcile'), [
            'bank_ledger_entry_id' => $entry->id,
            'contribution_id' => $contribution->id,
        ]);

        $response = $this->actingAs(User::factory()->create())->get(route('bank-reconciliation.index'));

        $response->assertOk();
        $response->assertSee('Reconciliation History');
        $response->assertSee($entry->description);
        $response->assertSee($contribution->donor->full_name);
    }

    public function test_secretary_cannot_reconcile_an_entry(): void
    {
        $entry = $this->ledgerEntry();
        $contribution = $this->electronicContribution();

        $response = $this->actingAs($this->userWithRole('secretary'))->post(route('bank-reconciliation.reconcile'), [
            'bank_ledger_entry_id' => $entry->id,
            'contribution_id' => $contribution->id,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('bank_ledger_entries', ['id' => $entry->id, 'status' => 'unmatched']);
    }

    private function ledgerEntry(array $overrides = []): BankLedgerEntry
    {
        return BankLedgerEntry::query()->create(array_merge([
            'statement_id' => 'bt_'.random_int(1000, 9999),
            'description' => 'BDO OVER THE COUNTER ICTSI FND',
            'reference_number' => 'BDO-TRF-'.random_int(10000, 99999),
            'amount' => 1000,
            'transaction_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'status' => 'unmatched',
        ], $overrides));
    }

    private function electronicContribution(array $overrides = []): Contribution
    {
        $donor = Donor::query()->create([
            'donor_type' => 'individual',
            'full_name' => 'Enrique Razon',
            'address' => 'Brgy. San Jose',
            'contact_number' => '09171234567',
            'email' => 'donor'.random_int(1, 100000).'@example.com',
            'status' => config('donors.defaults.status'),
        ]);

        return Contribution::query()->create(array_merge([
            'donor_id' => $donor->id,
            'type' => 'cash',
            'status' => 'verified',
            'amount' => 1000,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'BDO-TRF-'.random_int(10000, 99999),
            'received_at' => now(),
        ], $overrides));
    }

    private function userWithRole(string $role): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['name' => $role],
            config("auth_defaults.roles.{$role}"),
        );

        return User::factory()->create(['role_id' => $roleModel->id]);
    }
}
