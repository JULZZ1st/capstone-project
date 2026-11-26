<?php

namespace Tests\Feature;

use App\Models\BankReconciliation;
use App\Models\ReconciliationDiscrepancy;
use App\Models\User;
use App\Services\BankReconciliation\ReconciliationPeriodService;
use App\Services\Reports\Annex11ReportBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Annex11ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_download_annex11_pdf(): void
    {
        $this->get(route('reports.reconciliation.annex11'))->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_download_annex11_pdf(): void
    {
        $period = $this->periodWithBalance();

        $response = $this->actingAs(User::factory()->create())
            ->get(route('reports.reconciliation.annex11', ['period_id' => $period->id]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_missing_period_id_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('reports.reconciliation.annex11'))
            ->assertStatus(422);
    }

    public function test_draft_period_shows_draft_badge_and_approved_period_does_not(): void
    {
        $period = $this->periodWithBalance();

        $draftHtml = view('reports.pdf.annex-11', [
            'report' => app(Annex11ReportBuilder::class)->build($period->id),
        ])->render();
        $this->assertStringContainsString('DRAFT', $draftHtml);

        $period->forceFill(['status' => 'approved_locked', 'approved_at' => now(), 'locked_at' => now()])->save();

        $approvedHtml = view('reports.pdf.annex-11', [
            'report' => app(Annex11ReportBuilder::class)->build($period->id),
        ])->render();
        $this->assertStringNotContainsString('DRAFT', $approvedHtml);
    }

    public function test_builder_categorizes_discrepancies_with_signs_matching_the_calc_engine(): void
    {
        $period = $this->periodWithBalance(bankEndingBalance: 1000);

        ReconciliationDiscrepancy::query()->create([
            'bank_reconciliation_id' => $period->id,
            'type' => 'outstanding_check', 'classification' => 'timing_difference',
            'amount' => 500, 'explanation' => 'Check #1021 to supplier', 'evidence_reference' => 'CHK-1021',
            'resolution_status' => 'unresolved', 'carry_forward' => true,
        ]);
        ReconciliationDiscrepancy::query()->create([
            'bank_reconciliation_id' => $period->id,
            'type' => 'deposit_in_transit', 'classification' => 'timing_difference',
            'amount' => 300, 'explanation' => 'GCash cash-in not yet posted', 'evidence_reference' => 'DEP-55',
            'resolution_status' => 'unresolved', 'carry_forward' => true,
        ]);
        ReconciliationDiscrepancy::query()->create([
            'bank_reconciliation_id' => $period->id,
            'type' => 'bank_charge', 'classification' => 'accounting_adjustment',
            'amount' => 50, 'adjustment_amount' => -50, 'explanation' => 'Monthly service charge', 'evidence_reference' => 'SC-01',
            'resolution_status' => 'accounted',
        ]);
        ReconciliationDiscrepancy::query()->create([
            'bank_reconciliation_id' => $period->id,
            'type' => 'interest', 'classification' => 'accounting_adjustment',
            'amount' => 20, 'adjustment_amount' => 20, 'explanation' => 'Interest earned', 'evidence_reference' => 'INT-01',
            'resolution_status' => 'accounted',
        ]);

        $period = app(ReconciliationPeriodService::class)->recalculate($period->fresh());
        $report = app(Annex11ReportBuilder::class)->build($period->id);

        // Index map of $report['lines']: 2 = Check.../By the Bank, 11 = Deposits.../By the Bank,
        // 19 = Debit Memo, 20 = Credit Memo, 21 = Other Reconciling Items.
        $this->assertSame('By the Bank', $report['lines'][2]['label']);
        $this->assertEqualsWithDelta(-500.0, $report['lines'][2]['bank'], 0.001);
        $this->assertNull($report['lines'][2]['book']);

        $this->assertSame('By the Bank', $report['lines'][11]['label']);
        $this->assertEqualsWithDelta(300.0, $report['lines'][11]['bank'], 0.001);

        $this->assertSame('Debit Memo', $report['lines'][19]['label']);
        $this->assertEqualsWithDelta(-50.0, $report['lines'][19]['book'], 0.001);

        $this->assertSame('Credit Memo', $report['lines'][20]['label']);
        $this->assertEqualsWithDelta(20.0, $report['lines'][20]['book'], 0.001);

        $this->assertSame('Other Reconciling Items', $report['lines'][21]['label']);
        $this->assertEqualsWithDelta(0.0, $report['lines'][21]['book'], 0.001);

        // Untracked rows (no "by the Barangay" / overstated-understated data source) stay blank.
        $this->assertFalse($report['lines'][1]['tracked'] ?? true);
        $this->assertNull($report['lines'][1]['bank']);

        // The builder's own unadjusted/adjusted figures must match the calc engine's stored results.
        $this->assertEqualsWithDelta(0.0, $report['book_unadjusted'], 0.001);
        $this->assertEqualsWithDelta(1000.0, $report['bank_unadjusted'], 0.001);
        $this->assertEqualsWithDelta((float) $period->adjusted_book_balance, $report['book_adjusted'], 0.001);
        $this->assertEqualsWithDelta((float) $period->adjusted_bank_balance, $report['bank_adjusted'], 0.001);
        $this->assertEqualsWithDelta(-30.0, $report['book_adjusted'], 0.001);
        $this->assertEqualsWithDelta(800.0, $report['bank_adjusted'], 0.001);
    }

    public function test_missing_identity_config_renders_clearly_flagged_placeholders(): void
    {
        config(['disbursements.barangay' => ['name' => null, 'city_municipality' => null, 'province' => null, 'location_barangay' => null, 'tel_no' => null]]);
        config(['bank_reconciliation.bank_profiles' => []]);

        $period = $this->periodWithBalance(bankEndingBalance: null);
        $report = app(Annex11ReportBuilder::class)->build($period->id);
        $html = view('reports.pdf.annex-11', ['report' => $report])->render();

        $this->assertStringContainsString('[NOT CONFIGURED]', $html);
        $this->assertStringContainsString('Not yet entered', $html);
        $this->assertStringContainsString('Not yet calculated', $html);
    }

    private function periodWithBalance(?float $bankEndingBalance = 1000): BankReconciliation
    {
        $period = app(ReconciliationPeriodService::class)->create([
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'opening_balance' => 0,
            'bank_account' => config('bank_reconciliation.bank_accounts.0'),
        ]);

        $period->forceFill(['bank_ending_balance' => $bankEndingBalance])->save();

        return app(ReconciliationPeriodService::class)->recalculate($period->fresh());
    }
}
