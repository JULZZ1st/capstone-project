<?php

namespace Tests\Feature;

use App\Models\BankLedgerEntry;
use App\Models\CashDisbursement;
use App\Models\Contribution;
use App\Models\Donor;
use App\Models\User;
use App\Services\BankReconciliation\ReconciliationPeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AutomaticTimingClearanceTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('sourceCases')]
    public function test_automatic_clearance_checks_the_source_before_matching(string $kind, array $overrides, bool $eligible): void
    {
        $period = app(ReconciliationPeriodService::class)->create([
            'period_start' => '2026-11-01', 'period_end' => '2026-11-30',
            'opening_balance' => 1000, 'bank_ending_balance' => 1000,
        ]);
        $actor = User::factory()->create();
        $existingMatch = $overrides['existing_match'] ?? false;
        unset($overrides['existing_match']);

        if ($kind === 'contribution') {
            $donor = Donor::create([
                'donor_type' => 'individual', 'full_name' => 'Timing Test Donor',
                'address' => 'Poblacion', 'contact_number' => '09170000000', 'status' => 'active',
            ]);
            $source = Contribution::create([
                'donor_id' => $donor->id, 'type' => 'cash', 'status' => 'verified',
                'amount' => 25, 'payment_method' => 'bank_transfer', 'received_at' => '2026-11-10',
                ...$overrides,
            ]);
        } else {
            $payment = $overrides['payment'] ?? [];
            unset($overrides['payment']);
            $source = CashDisbursement::create([
                'requested_by' => $actor->id, 'amount' => 25, 'purpose' => 'Timing Test Payment',
                'status' => 'approved', 'approved_at' => '2026-11-01', ...$overrides,
            ]);
            if ($payment !== false) {
                $source->payment()->create([
                    'payment_date' => '2026-11-10', 'amount' => 25, 'payment_method' => 'bank_transfer',
                    'released_by' => $actor->id, 'released_at' => '2026-11-10', ...$payment,
                ]);
            }
        }

        if ($existingMatch) {
            $original = BankLedgerEntry::create([
                'statement_id' => 'ORIGINAL-MATCH', 'description' => 'Already matched bank entry',
                'amount' => 25, 'transaction_date' => '2026-11-11', 'status' => 'reconciled',
                'transaction_type' => $kind === 'contribution' ? 'deposit' : 'withdrawal',
                'matched_contribution_id' => $kind === 'contribution' ? $source->id : null,
            ]);
            if ($kind === 'cash_disbursement') {
                $original->matchedDisbursements()->attach($source->id, ['workspace_id' => $period->workspace_id]);
            }
        }

        $item = $period->discrepancies()->create([
            'type' => $kind === 'contribution' ? 'deposit_in_transit' : 'outstanding_check',
            'classification' => 'timing_difference', 'amount' => 25,
            'explanation' => 'Awaiting bank clearance', 'evidence_reference' => 'TIMING-001',
            'system_record_type' => $kind, 'system_record_id' => $source->id,
            'resolution_status' => 'unresolved', 'adjustment_amount' => 0,
        ]);
        $entry = BankLedgerEntry::create([
            'statement_id' => 'TIMING-001', 'reference_number' => 'TIMING-001',
            'description' => 'Bank clearance', 'amount' => 25, 'transaction_date' => '2026-11-12',
            'transaction_type' => $kind === 'contribution' ? 'deposit' : 'withdrawal',
            'status' => $kind === 'contribution' ? 'unmatched' : 'unreconciled',
        ]);

        app(ReconciliationPeriodService::class)->recalculate($period);

        $this->assertSame($eligible ? 'resolved' : 'unresolved', $item->fresh()->resolution_status);
        $this->assertSame($eligible ? $entry->id : null, $item->fresh()->cleared_bank_ledger_entry_id);
        if ($kind === 'contribution') {
            $this->assertSame($eligible ? $source->id : null, $entry->fresh()->matched_contribution_id);
        } else {
            $this->assertSame($eligible, $entry->matchedDisbursements()->whereKey($source->id)->exists());
        }
        $this->assertSame($eligible, $entry->fresh()->isReconciled());
    }

    public static function sourceCases(): array
    {
        return [
            'verified electronic receipt' => ['contribution', [], true],
            'pending receipt' => ['contribution', ['status' => 'pending'], false],
            'voided receipt' => ['contribution', ['voided_at' => '2026-11-11'], false],
            'cash receipt' => ['contribution', ['payment_method' => 'cash'], false],
            'future receipt' => ['contribution', ['received_at' => '2026-11-13'], false],
            'receipt for another bank' => ['contribution', ['reconciliation_bank_account' => 'LANDBANK Special Account'], false],
            'receipt amount differs' => ['contribution', ['amount' => 30], false],
            'receipt already matched' => ['contribution', ['existing_match' => true], false],
            'paid bank transfer' => ['cash_disbursement', [], true],
            'pending request' => ['cash_disbursement', ['status' => 'pending'], false],
            'voided request' => ['cash_disbursement', ['voided_at' => '2026-11-11'], false],
            'unpaid request' => ['cash_disbursement', ['payment' => false], false],
            'cash payment' => ['cash_disbursement', ['payment' => ['payment_method' => 'cash']], false],
            'future payment' => ['cash_disbursement', ['payment' => ['payment_date' => '2026-11-13']], false],
            'payment for another bank' => ['cash_disbursement', ['payment' => ['bank_account' => 'LANDBANK Special Account']], false],
            'request amount differs' => ['cash_disbursement', ['amount' => 30], false],
            'request already matched' => ['cash_disbursement', ['existing_match' => true], false],
        ];
    }

    public function test_automatic_clearance_preserves_existing_withdrawal_matches(): void
    {
        $period = app(ReconciliationPeriodService::class)->create([
            'period_start' => '2026-11-01', 'period_end' => '2026-11-30',
            'opening_balance' => 1000, 'bank_ending_balance' => 1000,
        ]);
        $actor = User::factory()->create();
        $original = CashDisbursement::create([
            'requested_by' => $actor->id, 'amount' => 25, 'purpose' => 'Existing match', 'status' => 'approved',
        ]);
        $entry = BankLedgerEntry::create([
            'statement_id' => 'PARTIAL-MATCH', 'reference_number' => 'PARTIAL-MATCH',
            'description' => 'Withdrawal awaiting final reconciliation', 'amount' => 25,
            'transaction_date' => '2026-11-12', 'transaction_type' => 'withdrawal', 'status' => 'unreconciled',
        ]);
        $entry->matchedDisbursements()->attach($original->id, ['workspace_id' => $period->workspace_id]);
        $item = $period->discrepancies()->create([
            'type' => 'outstanding_check', 'classification' => 'timing_difference', 'amount' => 25,
            'explanation' => 'Separate outstanding check with the same reference',
            'evidence_reference' => 'PARTIAL-MATCH', 'resolution_status' => 'unresolved', 'adjustment_amount' => 0,
        ]);

        app(ReconciliationPeriodService::class)->recalculate($period);

        $this->assertSame('unreconciled', $entry->fresh()->status);
        $this->assertSame('unresolved', $item->fresh()->resolution_status);
        $this->assertSame([$original->id], $entry->matchedDisbursements()->pluck('cash_disbursements.id')->all());
    }
}
