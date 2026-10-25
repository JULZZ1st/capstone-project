<?php

namespace Tests\Feature;

use App\Models\BankLedgerEntry;
use App\Models\CashTransaction;
use App\Models\Contribution;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_total_cash_ledger_sums_only_verified_cash_contributions(): void
    {
        $donor = $this->donor();

        Contribution::query()->create([
            'donor_id' => $donor->id,
            'type' => 'cash',
            'status' => 'verified',
            'amount' => 1000,
            'payment_method' => 'cash',
            'received_at' => now(),
        ]);
        Contribution::query()->create([
            'donor_id' => $donor->id,
            'type' => 'cash',
            'status' => 'pending',
            'amount' => 500,
            'payment_method' => 'cash',
            'received_at' => now(),
        ]);
        Contribution::query()->create([
            'donor_id' => $donor->id,
            'type' => 'in_kind',
            'status' => 'received',
            'received_at' => now(),
        ]);

        $response = $this->actingAs(User::factory()->create())->get('/');

        $response->assertOk();
        $response->assertSee('1,000.00');
        $response->assertDontSee('1,500.00');
    }

    public function test_pending_cash_verification_counts_only_pending_cash_contributions(): void
    {
        $donor = $this->donor();

        Contribution::query()->create([
            'donor_id' => $donor->id,
            'type' => 'cash',
            'status' => 'pending',
            'amount' => 250,
            'payment_method' => 'cash',
            'received_at' => now(),
        ]);
        Contribution::query()->create([
            'donor_id' => $donor->id,
            'type' => 'cash',
            'status' => 'verified',
            'amount' => 999,
            'payment_method' => 'cash',
            'received_at' => now(),
        ]);

        $response = $this->actingAs(User::factory()->create())->get('/');

        $response->assertOk();
        $response->assertSeeInOrder(['Pending Cash Verification', '1']);
    }

    public function test_dashboard_keeps_verified_cash_totals_without_campaigns(): void
    {
        $donor = $this->donor();

        Contribution::query()->create([
            'donor_id' => $donor->id,
            'type' => 'cash',
            'status' => 'verified',
            'amount' => 2000,
            'payment_method' => 'cash',
            'received_at' => now(),
        ]);

        $response = $this->actingAs(User::factory()->create())->get('/');

        $response->assertOk();
        $response->assertSee('2,000.00');
        $response->assertDontSee('Funding Distribution');
    }

    public function test_recent_donations_stream_shows_cash_and_in_kind_contributions(): void
    {
        $donor = $this->donor();

        Contribution::query()->create([
            'donor_id' => $donor->id,
            'type' => 'cash',
            'status' => 'verified',
            'amount' => 400,
            'payment_method' => 'cash',
            'received_at' => now(),
        ]);

        $inKind = Contribution::query()->create([
            'donor_id' => $donor->id,
            'type' => 'in_kind',
            'status' => 'received',
            'received_at' => now(),
        ]);
        $inKind->items()->create([
            'name' => 'Canned Goods',
            'category' => 'food',
            'quantity' => 10,
            'unit' => 'can',
            'condition' => 'new',
        ]);

        $response = $this->actingAs(User::factory()->create())->get('/');

        $response->assertOk();
        $response->assertSee($donor->full_name);
        $response->assertSee('1 item(s)');
    }

    public function test_treasurer_action_items_reflect_bank_ledger_reconciliation_state(): void
    {
        $donor = $this->donor();
        $contribution = Contribution::query()->create([
            'donor_id' => $donor->id,
            'type' => 'cash',
            'status' => 'received',
            'amount' => 5000,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'REF-DASH-1',
            'received_at' => now(),
        ]);

        BankLedgerEntry::query()->create([
            'statement_id' => 'stmt-dash-1',
            'description' => 'Bank transfer receipt',
            'reference_number' => 'REF-DASH-1',
            'amount' => 5000,
            'transaction_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'status' => 'reconciled',
            'matched_contribution_id' => $contribution->id,
            'matched_by' => User::factory()->create()->id,
            'matched_at' => now(),
        ]);

        BankLedgerEntry::query()->create([
            'statement_id' => 'stmt-dash-2',
            'description' => 'Unmatched ledger entry',
            'reference_number' => 'REF-DASH-2',
            'amount' => 750,
            'transaction_date' => now()->toDateString(),
            'payment_method' => 'gcash',
            'status' => 'unmatched',
        ]);

        $response = $this->actingAs(User::factory()->create())->get('/');

        $response->assertOk();
        $response->assertSee('Bank ledger entries awaiting review');
        $response->assertSeeInOrder(['Bank ledger entries awaiting review', '1']);
        $response->assertSee('Latest reconciliation');
    }

    public function test_treasurer_action_items_show_outgoing_reconciliation_work(): void
    {
        BankLedgerEntry::query()->create([
            'statement_id' => 'stmt-dash-withdrawal-1',
            'description' => 'Withdrawal awaiting review',
            'reference_number' => 'WD-DASH-1',
            'amount' => 10000,
            'transaction_date' => now()->toDateString(),
            'transaction_type' => 'withdrawal',
            'status' => 'unreconciled',
        ]);

        BankLedgerEntry::query()->create([
            'statement_id' => 'stmt-dash-withdrawal-2',
            'description' => 'Withdrawal with discrepancy',
            'reference_number' => 'WD-DASH-2',
            'amount' => 8000,
            'transaction_date' => now()->toDateString(),
            'transaction_type' => 'withdrawal',
            'status' => 'unreconciled',
            'discrepancy_amount' => 500,
            'discrepancy_reason' => 'Pending liquidation.',
        ]);

        $response = $this->actingAs(User::factory()->create())->get('/');

        $response->assertOk()
            ->assertSee('Bank Withdrawals Awaiting Reconciliation')
            ->assertSee('Discrepancies Requiring Review')
            ->assertSee('Pending Liquidation / Unresolved Difference')
            ->assertSeeInOrder(['Bank Withdrawals Awaiting Reconciliation', '2'])
            ->assertSeeInOrder(['Discrepancies Requiring Review', '1'])
            ->assertSee('PHP 500.00')
            ->assertSee(route('bank-reconciliation.index', ['tab' => 'outgoing']));
    }

    public function test_dashboard_totals_ignore_legacy_cash_transactions_and_donations_tables(): void
    {
        CashTransaction::query()->create([
            'donor_name' => 'Legacy Donor',
            'amount' => 999999,
            'purpose' => 'legacy',
            'received_at' => now(),
            'status' => 'verified',
        ]);

        $donor = $this->donor();
        Donation::query()->create([
            'donor_id' => $donor->id,
            'donation_type' => 'cash',
            'amount' => 888888,
            'description' => 'Legacy donation',
            'received_at' => now(),
        ]);

        $response = $this->actingAs(User::factory()->create())->get('/');

        $response->assertOk();
        $response->assertDontSee('999,999.00');
        $response->assertDontSee('888,888.00');
        $response->assertDontSee('Legacy Donor');
    }

    private function donor(): Donor
    {
        return Donor::query()->create([
            'donor_type' => 'individual',
            'full_name' => 'Dashboard Test Donor',
            'address' => 'Brgy. San Isidro',
            'contact_number' => '09171234567',
            'email' => 'dashboard-donor-'.random_int(1, 1000000).'@example.com',
            'status' => config('donors.defaults.status'),
        ]);
    }
}
