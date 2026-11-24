<?php

namespace Tests\Feature;

use App\Models\BankReconciliation;
use App\Models\BankLedgerEntry;
use App\Models\ReconciliationDiscrepancy;
use App\Models\Role;
use App\Models\SecurityActivityEvent;
use App\Models\User;
use App\Services\BankReconciliation\ReconciliationDiscrepancyService;
use App\Services\BankReconciliation\ReconciliationPeriodService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ReconciliationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_balance_update_cannot_change_a_locked_period(): void
    {
        $period = $this->period();
        BankReconciliation::whereKey($period)->update(['status' => 'approved_locked', 'locked_at' => now()]);

        try {
            app(ReconciliationPeriodService::class)->updateBalances($period, ['bank_ending_balance' => 9000]);
            $this->fail('A stale request changed a locked period.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('period', $exception->errors());
        }

        $this->assertSame('1000.00', $period->fresh()->bank_ending_balance);
    }

    public function test_stale_recalculation_cannot_change_locked_totals(): void
    {
        $period = $this->period();
        BankReconciliation::whereKey($period)->update(['status' => 'approved_locked', 'locked_at' => now(), 'financial_ledger_cash_in' => 123]);

        try {
            app(ReconciliationPeriodService::class)->recalculate($period);
            $this->fail('A stale recalculation changed a locked period.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('period', $exception->errors());
        }

        $this->assertSame('123.00', $period->fresh()->financial_ledger_cash_in);
    }

    public function test_stale_review_cannot_change_a_locked_period(): void
    {
        $period = $this->period();
        $item = $this->discrepancy($period);
        BankReconciliation::whereKey($period)->update(['status' => 'approved_locked', 'locked_at' => now()]);

        try {
            app(ReconciliationDiscrepancyService::class)->review($period, $item, 'accepted', null, $this->admin());
            $this->fail('A stale review changed a locked period.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('discrepancy', $exception->errors());
        }

        $this->assertSame('pending', $item->fresh()->admin_review_status);
        $this->assertDatabaseMissing('security_activity_events', ['action' => 'bank_reconciliation_discrepancy_reviewed']);
    }

    public function test_stale_resolution_rechecks_the_latest_admin_decision(): void
    {
        $period = $this->period();
        $item = $this->discrepancy($period, ['admin_review_status' => 'accepted']);
        ReconciliationDiscrepancy::whereKey($item)->update(['admin_review_status' => 'correction_requested']);

        try {
            app(ReconciliationDiscrepancyService::class)->resolve($period, $item, 'Corrected entry', null, User::factory()->create());
            $this->fail('A stale resolution bypassed the latest review decision.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('discrepancy', $exception->errors());
        }

        $this->assertSame('unresolved', $item->fresh()->resolution_status);
    }

    public function test_changing_an_accepted_explanation_requires_new_admin_review(): void
    {
        $period = $this->period();
        $item = $this->discrepancy($period, [
            'admin_review_status' => 'accepted', 'admin_reviewed_by' => $this->admin()->id,
            'admin_reviewed_at' => now(), 'admin_review_note' => 'Checked original explanation',
        ]);

        app(ReconciliationDiscrepancyService::class)->update($period, $item, [
            ...$this->payload(), 'explanation' => 'A different explanation',
        ], User::factory()->create());

        $item->refresh();
        $this->assertSame('pending', $item->admin_review_status);
        $this->assertNull($item->admin_reviewed_by);
        $this->assertNull($item->admin_reviewed_at);
        $this->assertNull($item->admin_review_note);
        $this->expectException(ValidationException::class);
        app(ReconciliationDiscrepancyService::class)->resolve($period, $item, 'Resolved', null, User::factory()->create());
    }

    public function test_failed_supporting_upload_does_not_create_a_discrepancy(): void
    {
        $period = $this->period();
        $this->failStorage();

        $this->actingAs(User::factory()->create())->post(route('bank-reconciliation.discrepancies.store', $period), [
            ...$this->payload(), 'supporting_evidence' => UploadedFile::fake()->create('proof.pdf', 20, 'application/pdf'),
        ])->assertSessionHasErrors('supporting_evidence');

        $this->assertDatabaseCount('reconciliation_discrepancies', 0);
        $this->assertDatabaseMissing('security_activity_events', ['action' => 'bank_reconciliation_discrepancy_created']);
    }

    public function test_failed_replacement_upload_preserves_the_previous_evidence(): void
    {
        $period = $this->period();
        $item = $this->discrepancy($period, ['evidence_path' => 'previous-proof.pdf']);
        $this->failStorage();

        $this->actingAs(User::factory()->create())->patch(route('bank-reconciliation.discrepancies.update', [$period, $item]), [
            ...$this->payload(), 'supporting_evidence' => UploadedFile::fake()->create('new-proof.pdf', 20, 'application/pdf'),
        ])->assertSessionHasErrors('supporting_evidence');

        $this->assertSame('previous-proof.pdf', $item->fresh()->evidence_path);
    }

    public function test_failed_resolution_upload_does_not_resolve_the_discrepancy(): void
    {
        $period = $this->period();
        $item = $this->discrepancy($period, ['admin_review_status' => 'accepted']);
        $this->failStorage();

        $this->actingAs(User::factory()->create())->post(route('bank-reconciliation.discrepancies.resolve', [$period, $item]), [
            'resolution_explanation' => 'Corrected entry',
            'resolution_evidence' => UploadedFile::fake()->create('resolution.pdf', 20, 'application/pdf'),
        ])->assertSessionHasErrors('resolution_evidence');

        $this->assertSame('unresolved', $item->fresh()->resolution_status);
        $this->assertNull($item->fresh()->resolved_at);
    }

    public function test_uploaded_evidence_is_removed_when_the_database_transaction_fails(): void
    {
        Storage::fake('local');
        $period = $this->period();
        Storage::disk('local')->put('existing.pdf', 'Previous evidence');
        $before = Storage::disk('local')->allFiles();
        SecurityActivityEvent::creating(function (SecurityActivityEvent $event): void {
            if ($event->action === 'bank_reconciliation_discrepancy_created') {
                throw new RuntimeException('Audit unavailable.');
            }
        });

        try {
            $this->withoutExceptionHandling()->actingAs(User::factory()->create())->post(route('bank-reconciliation.discrepancies.store', $period), [
                ...$this->payload(), 'supporting_evidence' => UploadedFile::fake()->create('proof.pdf', 20, 'application/pdf'),
            ]);
            $this->fail('The discrepancy was saved without an audit.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable.', $exception->getMessage());
        }

        $this->assertDatabaseCount('reconciliation_discrepancies', 0);
        $this->assertSame($before, Storage::disk('local')->allFiles());
    }

    public function test_approved_status_alone_is_treated_as_locked(): void
    {
        $period = $this->period();
        $period->update(['status' => 'approved_locked']);

        $this->assertTrue($period->isLocked());
        $this->expectException(ValidationException::class);
        app(ReconciliationPeriodService::class)->recalculate($period);
    }

    public function test_timing_item_without_a_reference_is_not_cleared_by_an_unrelated_bank_row(): void
    {
        $period = $this->period();
        $item = $this->discrepancy($period, [
            'type' => 'deposit_in_transit', 'evidence_reference' => null,
        ]);
        BankLedgerEntry::create([
            'statement_id' => 'UNRELATED-DEPOSIT', 'description' => 'Unrelated bank deposit',
            'amount' => 25, 'transaction_date' => '2026-11-12',
            'transaction_type' => 'deposit', 'reference_number' => null, 'status' => 'unmatched',
        ]);

        app(ReconciliationPeriodService::class)->recalculate($period);

        $this->assertSame('unresolved', $item->fresh()->resolution_status);
        $this->assertNull($item->fresh()->cleared_bank_ledger_entry_id);
    }

    private function period(): BankReconciliation
    {
        return app(ReconciliationPeriodService::class)->create([
            'period_start' => '2026-11-01', 'period_end' => '2026-11-30',
            'opening_balance' => 1000, 'bank_ending_balance' => 1000,
        ]);
    }

    private function discrepancy(BankReconciliation $period, array $overrides = []): ReconciliationDiscrepancy
    {
        return $period->discrepancies()->create([...$this->payload(), ...$overrides]);
    }

    private function payload(): array
    {
        return [
            'type' => 'recording_error', 'classification' => 'timing_difference', 'amount' => 25,
            'explanation' => 'Investigate the receipt', 'resolution_status' => 'unresolved',
            'evidence_reference' => 'PROOF-001', 'adjustment_amount' => 0, 'carry_forward' => false,
        ];
    }

    private function admin(): User
    {
        $role = Role::firstOrCreate(['name' => 'admin'], config('auth_defaults.roles.admin'));

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function failStorage(): void
    {
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFile')->once()->andReturn(false);
        $disk->shouldReceive('putFileAs')->andReturn(false);
        Storage::shouldReceive('disk')->withAnyArgs()->andReturn($disk);
    }
}
