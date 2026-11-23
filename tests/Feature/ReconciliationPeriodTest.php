<?php

namespace Tests\Feature;

use App\Models\BankLedgerEntry;
use App\Models\BankReconciliation;
use App\Models\CashDisbursement;
use App\Models\Contribution;
use App\Models\Donor;
use App\Models\User;
use App\Models\Role;
use App\Models\SecurityActivityEvent;
use App\Services\BankReconciliation\ReconciliationPeriodService;
use App\Services\BankReconciliation\ReconciliationDiscrepancyService;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReconciliationPeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_period_totals_are_calculated_from_bank_and_accounting_records_once(): void
    {
        $user = User::factory()->create();
        $donor = Donor::query()->create([
            'donor_type' => 'individual', 'full_name' => 'Period Donor', 'address' => 'Address',
            'contact_number' => '09170000000', 'email' => 'period@example.com', 'status' => 'active',
        ]);

        foreach ([
            ['statement_id' => 'period-deposit-1', 'description' => 'Deposit', 'amount' => 1000, 'transaction_date' => '2026-10-02', 'transaction_type' => 'deposit', 'status' => 'unmatched'],
            ['statement_id' => 'period-deposit-2', 'description' => 'Deposit', 'amount' => 500, 'transaction_date' => '2026-10-03', 'transaction_type' => 'deposit', 'status' => 'reconciled'],
            ['statement_id' => 'period-withdrawal-1', 'description' => 'Withdrawal', 'amount' => 300, 'transaction_date' => '2026-10-04', 'transaction_type' => 'withdrawal', 'status' => 'unreconciled'],
        ] as $entry) {
            BankLedgerEntry::query()->create($entry);
        }

        Contribution::query()->create([
            'donor_id' => $donor->id, 'created_by' => $user->id, 'type' => 'cash', 'status' => 'verified',
            'amount' => 1200, 'payment_method' => 'bank_transfer', 'received_at' => '2026-10-02 10:00:00',
        ]);
        $disbursement = CashDisbursement::query()->create([
            'amount' => 250, 'purpose' => 'Period expense', 'requested_by' => $user->id,
            'approved_by' => $user->id, 'approved_at' => '2026-10-04 10:00:00', 'status' => 'approved',
            'created_at' => '2026-10-04 09:00:00', 'updated_at' => '2026-10-04 09:00:00',
        ]);
        $disbursement->payment()->create([
            'payment_date' => '2026-10-04', 'amount' => 250, 'payment_method' => 'bank_transfer',
            'released_by' => $user->id, 'released_at' => '2026-10-04 10:00:00',
        ]);

        $period = app(ReconciliationPeriodService::class)->create([
            'period_start' => '2026-10-01', 'period_end' => '2026-10-31',
            'opening_balance' => 10000, 'bank_ending_balance' => 10800,
        ]);

        $this->assertSame(1500.0, (float) $period->calculated_bank_cash_in);
        $this->assertSame(300.0, (float) $period->calculated_bank_cash_out);
        $this->assertSame(1200.0, (float) $period->financial_ledger_cash_in);
        $this->assertSame(250.0, (float) $period->financial_ledger_cash_out);
        $this->assertSame(10950.0, (float) $period->financial_ledger_ending_balance);
        $this->assertSame(-150.0, (float) $period->reconciliation_difference);
        $this->assertSame(10000.0, (float) $period->opening_balance);
        $this->assertSame(10800.0, (float) $period->bank_ending_balance);
    }

    public function test_periods_cannot_overlap(): void
    {
        $service = app(ReconciliationPeriodService::class);
        $service->create(['period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'opening_balance' => 0]);

        $this->expectException(ValidationException::class);
        $service->create(['period_start' => '2026-10-15', 'period_end' => '2026-11-15', 'opening_balance' => 0]);
    }

    public function test_new_period_must_follow_previous_locked_period_and_use_its_bank_ending_balance(): void
    {
        BankReconciliation::query()->create([
            'date' => '2026-09-30', 'period_start' => '2026-09-01', 'period_end' => '2026-09-30',
            'opening_balance' => 5000, 'bank_ending_balance' => 7500, 'status' => 'locked',
        ]);

        $service = app(ReconciliationPeriodService::class);
        $this->expectException(ValidationException::class);
        $service->create(['period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'opening_balance' => 7499.99]);
    }

    public function test_locked_period_is_not_recalculated(): void
    {
        $period = BankReconciliation::query()->create([
            'date' => '2026-10-31', 'period_start' => '2026-10-01', 'period_end' => '2026-10-31',
            'opening_balance' => 0, 'status' => 'locked',
        ]);

        $this->expectException(ValidationException::class);
        app(ReconciliationPeriodService::class)->recalculate($period);
    }

    public function test_accounting_adjustments_change_the_book_side_not_the_bank_side(): void
    {
        $period = app(ReconciliationPeriodService::class)->create([
            'period_start' => '2026-10-01', 'period_end' => '2026-10-31',
            'opening_balance' => 1000, 'bank_ending_balance' => 1000,
        ]);

        app(ReconciliationDiscrepancyService::class)->create($period, [
            'type' => 'bank_charge', 'classification' => 'accounting_adjustment', 'amount' => 25,
            'explanation' => 'Monthly bank charge', 'evidence_reference' => 'BANK-CHARGE-10',
            'resolution_status' => 'accounted', 'adjustment_amount' => -25, 'carry_forward' => false,
        ], User::factory()->create());

        $period = $period->fresh();
        $this->assertSame(1000.0, (float) $period->bank_ending_balance);
        $this->assertSame(-25.0, (float) $period->accounting_adjustment);
        $this->assertSame(1000.0, (float) $period->adjusted_bank_balance);
        $this->assertSame(975.0, (float) $period->adjusted_book_balance);
        $this->assertSame(0.0, (float) $period->unresolved_discrepancy_amount);
    }

    public function test_unresolved_timing_discrepancy_is_carried_to_the_next_period(): void
    {
        $first = app(ReconciliationPeriodService::class)->create([
            'period_start' => '2026-10-01', 'period_end' => '2026-10-31',
            'opening_balance' => 1000, 'bank_ending_balance' => 1000,
        ]);
        app(ReconciliationDiscrepancyService::class)->create($first, [
            'type' => 'deposit_in_transit', 'classification' => 'timing_difference', 'amount' => 100,
            'explanation' => 'Deposit made on period end', 'evidence_reference' => 'DEP-TRANSIT-10',
            'resolution_status' => 'unresolved', 'adjustment_amount' => 0, 'carry_forward' => true,
        ], User::factory()->create());
        $first->update(['status' => 'locked']);

        $next = app(ReconciliationPeriodService::class)->create([
            'period_start' => '2026-11-01', 'period_end' => '2026-11-30',
            'opening_balance' => 1000, 'bank_ending_balance' => 1000,
        ]);

        $this->assertDatabaseHas('reconciliation_discrepancies', [
            'bank_reconciliation_id' => $next->id, 'type' => 'deposit_in_transit',
            'resolution_status' => 'unresolved', 'carry_forward' => 1,
        ]);
    }

    public function test_documented_timing_item_does_not_block_independent_approval(): void
    {
        $treasurer = $this->userWithRole('staff');
        $admin = $this->userWithRole('admin');
        $period = app(ReconciliationPeriodService::class)->create([
            'period_start' => '2026-12-01', 'period_end' => '2026-12-31',
            'opening_balance' => 1000, 'bank_ending_balance' => 1100,
            'prepared_by' => $treasurer->id,
        ]);
        app(ReconciliationDiscrepancyService::class)->create($period, [
            'type' => 'outstanding_check', 'classification' => 'timing_difference', 'amount' => 100,
            'explanation' => 'Issued check not yet presented', 'evidence_reference' => 'CHK-DEC-001',
            'resolution_status' => 'unresolved', 'adjustment_amount' => 0, 'carry_forward' => true,
        ], $treasurer);

        $this->actingAs($treasurer)->post(route('bank-reconciliation.periods.submit', $period))->assertRedirect();
        $this->actingAs($admin)->post(route('bank-reconciliation.periods.approve', $period))->assertRedirect();
        $this->assertDatabaseHas('bank_reconciliations', ['id' => $period->id, 'status' => 'approved_locked']);
    }

    public function test_carried_forward_timing_item_clears_once_when_matching_bank_row_appears(): void
    {
        $user = $this->userWithRole('staff');
        $first = app(ReconciliationPeriodService::class)->create([
            'period_start' => '2027-01-01', 'period_end' => '2027-01-31',
            'opening_balance' => 1000, 'bank_ending_balance' => 1000,
        ]);
        app(ReconciliationDiscrepancyService::class)->create($first, [
            'type' => 'deposit_in_transit', 'classification' => 'timing_difference', 'amount' => 100,
            'explanation' => 'Deposit pending bank clearance', 'evidence_reference' => 'DEP-JAN-001',
            'resolution_status' => 'unresolved', 'adjustment_amount' => 0, 'carry_forward' => true,
        ], $user);
        $first->update(['status' => 'approved_locked']);

        $next = app(ReconciliationPeriodService::class)->create([
            'period_start' => '2027-02-01', 'period_end' => '2027-02-28',
            'opening_balance' => 1000, 'bank_ending_balance' => 1100,
        ]);
        $item = $next->discrepancies()->where('type', 'deposit_in_transit')->firstOrFail();
        BankLedgerEntry::query()->create([
            'statement_id' => 'DEP-JAN-001-BANK', 'reference_number' => 'DEP-JAN-001',
            'description' => 'Cleared deposit in transit', 'amount' => 100,
            'transaction_date' => '2027-02-05', 'transaction_type' => 'deposit', 'status' => 'unmatched',
        ]);

        app(ReconciliationPeriodService::class)->recalculate($next);
        $item->refresh();
        $this->assertSame('resolved', $item->resolution_status);
        $this->assertNotNull($item->cleared_bank_ledger_entry_id);
        $this->assertSame('reconciled', BankLedgerEntry::query()->findOrFail($item->cleared_bank_ledger_entry_id)->status);
        app(ReconciliationPeriodService::class)->recalculate($next);
        $this->assertDatabaseCount('reconciliation_discrepancies', 2);
    }

    public function test_timing_items_adjust_the_bank_side_with_standard_accounting_signs(): void
    {
        $period = app(ReconciliationPeriodService::class)->create([
            'period_start' => '2027-03-01', 'period_end' => '2027-03-31',
            'opening_balance' => 1100, 'bank_ending_balance' => 1000,
        ]);
        $user = User::factory()->create();

        foreach ([
            ['type' => 'deposit_in_transit', 'amount' => 200, 'reference' => 'DIT-MAR'],
            ['type' => 'outstanding_check', 'amount' => 100, 'reference' => 'OC-MAR'],
        ] as $item) {
            app(ReconciliationDiscrepancyService::class)->create($period, [
                'type' => $item['type'], 'classification' => 'timing_difference',
                'amount' => $item['amount'], 'explanation' => 'Documented timing item',
                'evidence_reference' => $item['reference'], 'resolution_status' => 'unresolved',
                'adjustment_amount' => 0, 'carry_forward' => true,
            ], $user);
        }

        $period->refresh();
        $this->assertSame(1100.0, (float) $period->adjusted_bank_balance);
        $this->assertSame(1100.0, (float) $period->adjusted_book_balance);
        $this->assertSame(0.0, (float) $period->reconciliation_difference);
    }

    public function test_book_expenses_use_payment_date_and_bank_account(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $makePayment = function (string $date, float $amount, string $account) use ($user, $admin): void {
            $request = CashDisbursement::query()->create([
                'amount' => $amount, 'purpose' => 'Payment date regression',
                'requested_by' => $user->id, 'approved_by' => $admin->id,
                'status' => 'approved', 'approved_at' => '2026-09-15',
                'created_at' => '2026-09-01', 'updated_at' => '2026-09-01',
            ]);
            $request->payment()->create([
                'bank_account' => $account, 'payment_date' => $date, 'amount' => $amount,
                'payment_method' => 'bank_transfer', 'released_by' => $user->id, 'released_at' => $date,
            ]);
        };

        $makePayment('2026-10-05', 250, 'LANDBANK Barangay Operating Account');
        $makePayment('2026-10-06', 400, 'LANDBANK Special Account');
        $makePayment('2026-11-01', 800, 'LANDBANK Barangay Operating Account');
        BankLedgerEntry::query()->create([
            'bank_account' => 'LANDBANK Barangay Operating Account', 'statement_id' => 'PRIMARY-OCT',
            'description' => 'Primary account deposit', 'amount' => 500, 'transaction_date' => '2026-10-10',
            'transaction_type' => 'deposit', 'status' => 'reconciled',
        ]);
        BankLedgerEntry::query()->create([
            'bank_account' => 'LANDBANK Special Account', 'statement_id' => 'SPECIAL-OCT',
            'description' => 'Special account deposit', 'amount' => 900, 'transaction_date' => '2026-10-10',
            'transaction_type' => 'deposit', 'status' => 'reconciled',
        ]);

        $period = app(ReconciliationPeriodService::class)->create([
            'period_start' => '2026-10-01', 'period_end' => '2026-10-31',
            'opening_balance' => 1000, 'bank_ending_balance' => 750,
            'bank_account' => 'LANDBANK Barangay Operating Account',
        ]);

        $this->assertSame(250.0, (float) $period->financial_ledger_cash_out);
        $this->assertSame(500.0, (float) $period->calculated_bank_cash_in);
        $this->assertSame(750.0, (float) $period->adjusted_book_balance);
    }

    public function test_next_period_has_separate_bank_and_book_opening_balances(): void
    {
        BankReconciliation::query()->create([
            'date' => '2027-03-31', 'period_start' => '2027-03-01', 'period_end' => '2027-03-31',
            'opening_balance' => 900, 'book_opening_balance' => 900,
            'bank_ending_balance' => 1000, 'adjusted_book_balance' => 1100,
            'financial_ledger_ending_balance' => 1100, 'status' => 'approved_locked',
        ]);

        $next = app(ReconciliationPeriodService::class)->create([
            'period_start' => '2027-04-01', 'period_end' => '2027-04-30',
            'opening_balance' => 1000, 'bank_ending_balance' => 1000,
        ]);

        $this->assertSame(1000.0, (float) $next->opening_balance);
        $this->assertSame(1100.0, (float) $next->book_opening_balance);
    }

    public function test_import_rejects_duplicate_rows_without_reference_numbers(): void
    {
        BankLedgerEntry::query()->create([
            'statement_id' => 'existing-no-ref', 'description' => 'ATM fee', 'amount' => 50,
            'transaction_date' => '2026-10-05', 'transaction_type' => 'withdrawal', 'status' => 'unreconciled',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Services\BankReconciliation\ImportBankStatement::class)->handle(
            UploadedFile::fake()->createWithContent('duplicate.csv', "statement_id,description,amount,transaction_date,transaction_type\nnew-no-ref,ATM fee,50,2026-10-05,withdrawal\n")
        );
    }

    public function test_treasurer_submits_and_independent_admin_approves_and_locks_period(): void
    {
        $treasurer = $this->userWithRole('staff');
        $admin = $this->userWithRole('admin');
        $period = app(ReconciliationPeriodService::class)->create([
            'period_start' => '2026-11-01', 'period_end' => '2026-11-30',
            'opening_balance' => 1000, 'bank_ending_balance' => 1000,
            'prepared_by' => $treasurer->id,
        ]);

        $this->actingAs($treasurer)->post(route('bank-reconciliation.periods.submit', $period))
            ->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('bank_reconciliations', ['id' => $period->id, 'status' => 'ready_for_review']);

        $this->actingAs($treasurer)->post(route('bank-reconciliation.periods.approve', $period))
            ->assertForbidden();
        $this->actingAs($admin)->post(route('bank-reconciliation.periods.approve', $period))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('bank_reconciliations', [
            'id' => $period->id, 'status' => 'approved_locked', 'approved_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('security_activity_events', ['action' => 'bank_reconciliation_submitted']);
        $this->assertDatabaseHas('security_activity_events', ['action' => 'bank_reconciliation_approved']);
    }

    public function test_approval_rejects_unexplained_difference_even_when_period_is_submitted(): void
    {
        $treasurer = $this->userWithRole('staff');
        $admin = $this->userWithRole('admin');
        $period = app(ReconciliationPeriodService::class)->create([
            'period_start' => '2026-12-01', 'period_end' => '2026-12-31',
            'opening_balance' => 1000, 'bank_ending_balance' => 1000,
            'prepared_by' => $treasurer->id,
        ]);

        $this->actingAs($treasurer)->post(route('bank-reconciliation.periods.submit', $period));
        $period->forceFill(['bank_ending_balance' => 1100])->save();
        $this->actingAs($admin)->post(route('bank-reconciliation.periods.approve', $period))
            ->assertSessionHasErrors('reconciliation_difference');
        $this->assertDatabaseHas('bank_reconciliations', ['id' => $period->id, 'status' => 'ready_for_review']);
    }

    public function test_locked_period_guard_blocks_mutation(): void
    {
        $period = BankReconciliation::query()->create([
            'date' => '2026-10-31', 'period_start' => '2026-10-01', 'period_end' => '2026-10-31',
            'opening_balance' => 1000, 'bank_ending_balance' => 1000,
            'status' => 'approved_locked', 'locked_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        app(\App\Services\BankReconciliation\ReconciliationPeriodGuard::class)->assertMutableDate('2026-10-15');
    }

    private function userWithRole(string $role): User
    {
        $roleModel = Role::query()->firstOrCreate(['name' => $role], config("auth_defaults.roles.{$role}"));
        return User::factory()->create(['role_id' => $roleModel->id]);
    }
}
