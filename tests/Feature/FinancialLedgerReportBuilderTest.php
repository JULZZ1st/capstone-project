<?php

namespace Tests\Feature;

use App\Models\BankLedgerEntry;
use App\Models\CashDisbursement;
use App\Models\Contribution;
use App\Models\Donor;
use App\Models\Role;
use App\Models\User;
use App\Services\Reports\FinancialLedgerReportBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialLedgerReportBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_summarizes_reconciled_pending_and_recorded_totals(): void
    {
        $reconciled = $this->cashContribution(['amount' => 15000, 'payment_method' => 'bank_transfer', 'received_at' => '2026-06-05']);
        $this->reconcile($reconciled);

        $this->cashContribution(['amount' => 10000, 'payment_method' => 'gcash', 'received_at' => '2026-06-10']);
        $this->cashContribution(['amount' => 5000, 'payment_method' => 'cash', 'received_at' => '2026-06-12']);
        $this->cashContribution(['amount' => 99999, 'status' => 'cancelled', 'received_at' => '2026-06-15']);
        $this->cashContribution(['amount' => 8000, 'payment_method' => 'gcash', 'received_at' => '2026-05-20']);

        $report = app(FinancialLedgerReportBuilder::class)->build(2026, 6);

        $this->assertSame(15000.0, $report['summary']['reconciled_total']);
        $this->assertSame(10000.0, $report['summary']['pending_clearing_total']);
        $this->assertSame(30000.0, $report['summary']['recorded_total']);
    }

    public function test_it_breaks_down_cash_intake_by_wire_source_with_relative_percentages(): void
    {
        $this->cashContribution(['amount' => 55000, 'payment_method' => 'gcash', 'received_at' => '2026-06-05']);
        $this->cashContribution(['amount' => 115000, 'payment_method' => 'bank_transfer', 'received_at' => '2026-06-06']);

        $report = app(FinancialLedgerReportBuilder::class)->build(2026, 6);
        $sources = $report['wire_sources']->keyBy('method');

        $this->assertSame(55000.0, $sources['gcash']['amount']);
        $this->assertSame(115000.0, $sources['bank_transfer']['amount']);
        $this->assertSame(100, $sources['bank_transfer']['percentage']);
        $this->assertSame(48, $sources['gcash']['percentage']);
        $this->assertFalse($sources->has('check'));
    }

    public function test_ledger_rows_carry_status_labels_and_are_ordered_newest_first(): void
    {
        $reconciled = $this->cashContribution(['amount' => 15000, 'payment_method' => 'bank_transfer', 'received_at' => '2026-06-05', 'donor_name' => 'Dr. Manuel Co']);
        $this->reconcile($reconciled);
        $this->cashContribution(['amount' => 100000, 'payment_method' => 'gcash', 'received_at' => '2026-06-20', 'donor_name' => 'Enrique Razon']);

        $report = app(FinancialLedgerReportBuilder::class)->build(2026, 6);
        $ledger = $report['ledger']->values();

        $this->assertSame('Enrique Razon', $ledger[0]['donor_name']);
        $this->assertSame('pending', $ledger[0]['status_key']);
        $this->assertSame('Pending Ledger Clear', $ledger[0]['status_label']);
        $this->assertSame('Dr. Manuel Co', $ledger[1]['donor_name']);
        $this->assertSame('reconciled', $ledger[1]['status_key']);
        $this->assertSame('Reconciled', $ledger[1]['status_label']);
    }

    public function test_voided_contribution_is_excluded_from_totals_but_shown_as_voided_in_ledger(): void
    {
        $voider = User::factory()->create(['role_id' => Role::query()->firstOrCreate(['name' => 'admin'])->id]);
        $this->cashContribution(['amount' => 15000, 'payment_method' => 'bank_transfer', 'received_at' => '2026-06-05']);
        $voided = $this->cashContribution([
            'amount' => 50000,
            'payment_method' => 'gcash',
            'received_at' => '2026-06-10',
            'status' => 'cancelled',
            'voided_at' => now(),
            'voided_by' => $voider->id,
            'void_reason' => 'Duplicate entry',
        ]);

        $report = app(FinancialLedgerReportBuilder::class)->build(2026, 6);

        $this->assertSame(15000.0, $report['summary']['recorded_total']);
        $voidedRow = $report['ledger']->firstWhere('id', $voided->id);
        $this->assertSame('voided', $voidedRow['status_key']);
        $this->assertSame('Voided', $voidedRow['status_label']);
    }

    public function test_disbursement_total_only_counts_approved_active_disbursements(): void
    {
        $requester = User::factory()->create(['role_id' => Role::query()->firstOrCreate(['name' => 'treasurer'])->id]);
        $admin = User::factory()->create(['role_id' => Role::query()->firstOrCreate(['name' => 'admin'])->id]);

        CashDisbursement::query()->create([
            'amount' => 2000, 'purpose' => 'Approved', 'requested_by' => $requester->id,
            'status' => 'approved', 'approved_by' => $admin->id, 'approved_at' => '2026-06-01',
            'created_at' => '2026-06-01',
        ]);
        CashDisbursement::query()->create([
            'amount' => 1000, 'purpose' => 'Pending', 'requested_by' => $requester->id,
            'status' => 'pending', 'created_at' => '2026-06-02',
        ]);
        CashDisbursement::query()->create([
            'amount' => 3000, 'purpose' => 'Voided approved', 'requested_by' => $requester->id,
            'status' => 'approved', 'approved_by' => $admin->id, 'approved_at' => '2026-06-03',
            'voided_at' => '2026-06-03', 'voided_by' => $admin->id, 'void_reason' => 'Unused',
            'created_at' => '2026-06-03',
        ]);

        $report = app(FinancialLedgerReportBuilder::class)->build(2026, 6);

        $this->assertSame(2000.0, $report['summary']['disbursement_total']);
    }

    private function cashContribution(array $overrides = []): Contribution
    {
        $donor = Donor::query()->create([
            'donor_type' => 'individual',
            'full_name' => $overrides['donor_name'] ?? 'Test Donor '.random_int(1, 100000),
            'address' => 'Brgy. San Jose',
            'contact_number' => '09171234567',
            'email' => 'donor'.random_int(1, 100000).'@example.com',
            'status' => config('donors.defaults.status'),
        ]);

        return Contribution::query()->create(array_merge([
            'donor_id' => $donor->id,
            'type' => 'cash',
            'status' => 'received',
            'amount' => 1000,
            'payment_method' => 'cash',
            'reference_number' => 'REF-'.random_int(100000, 999999),
            'received_at' => now(),
        ], collect($overrides)->except('donor_name')->all()));
    }

    private function reconcile(Contribution $contribution): BankLedgerEntry
    {
        return BankLedgerEntry::query()->create([
            'statement_id' => 'bt_'.random_int(1000, 9999),
            'description' => 'Bank credit',
            'reference_number' => $contribution->reference_number,
            'amount' => $contribution->amount,
            'transaction_date' => $contribution->received_at->toDateString(),
            'payment_method' => $contribution->payment_method,
            'status' => 'reconciled',
            'matched_contribution_id' => $contribution->id,
            'matched_at' => now(),
        ]);
    }
}
