<?php

namespace Tests\Feature;

use App\Models\BankLedgerEntry;
use App\Models\CashDisbursement;
use App\Models\Contribution;
use App\Models\Donor;
use App\Models\Role;
use App\Models\User;
use App\Services\BankReconciliation\ReconcileLedgerEntry;
use App\Services\Contributions\LogContribution;
use App\Services\Disbursements\UpdateDisbursement;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class WorkflowIntegrityRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_donations_can_be_found_by_official_receipt_and_check_number(): void
    {
        $donation = $this->contribution(['official_receipt_number' => 'OR-SEARCH-778', 'check_number' => 'CHK-SEARCH-991']);

        foreach (['OR-SEARCH-778', 'CHK-SEARCH-991'] as $reference) {
            $this->assertSame([$donation->id], Contribution::query()->search($reference)->pluck('id')->all());
        }
    }

    public function test_changing_certified_fund_request_details_requires_new_certification(): void
    {
        [$staff, $request] = $this->certifiedRequest();

        $this->actingAs($staff)->patch(route('disbursements.update', $request), $this->requestPayload(['amount' => 2500]))
            ->assertSessionDoesntHaveErrors()->assertRedirect();

        $request->refresh();
        $this->assertNull($request->treasurer_certified_at);
        $this->assertNull($request->treasurer_certified_by);
        $this->assertNull($request->treasurer_certification_notes);
        $this->assertSame('2026-09-0001', $request->dv_number);

        $this->actingAs($this->admin())->patch(route('disbursements.approve', $request))
            ->assertSessionHasErrors('disbursement');
        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_notes_only_changes_keep_the_existing_fund_request_certification(): void
    {
        [$staff, $request] = $this->certifiedRequest();
        $certifiedAt = $request->treasurer_certified_at;

        $this->actingAs($staff)->patch(route('disbursements.update', $request), $this->requestPayload(['notes' => 'Contact payee before collection.']))
            ->assertSessionDoesntHaveErrors()->assertRedirect();

        $this->assertSame($staff->id, $request->fresh()->treasurer_certified_by);
        $this->assertTrue($certifiedAt->equalTo($request->fresh()->treasurer_certified_at));
    }

    public function test_a_stale_pending_request_cannot_change_an_already_approved_amount(): void
    {
        [$staff, $staleRequest] = $this->certifiedRequest();
        CashDisbursement::query()->whereKey($staleRequest)->update(['status' => 'approved']);

        try {
            app(UpdateDisbursement::class)->handle($staleRequest, $this->requestPayload(['amount' => 9000]));
            $this->fail('A stale update changed an approved fund request.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('disbursement', $exception->errors());
        }

        $this->assertSame('1250.00', $staleRequest->fresh()->amount);
    }

    public function test_voided_donations_cannot_be_reopened(): void
    {
        $donation = $this->contribution(['status' => 'cancelled', 'voided_at' => now(), 'void_reason' => 'Duplicate']);

        $this->actingAs($this->admin())->patch(route('donations.update', $donation), ['status' => 'pending'])
            ->assertForbidden();

        $this->assertSame('cancelled', $donation->fresh()->status);
    }

    public function test_matched_donation_status_cannot_be_changed_but_notes_can_be_edited(): void
    {
        $donation = $this->contribution(['status' => 'verified', 'notes' => 'Original note']);
        $this->entry(['matched_contribution_id' => $donation->id, 'status' => 'reconciled']);
        $this->actingAs($this->admin());

        $this->patch(route('donations.update', $donation), ['status' => 'rejected'])
            ->assertSessionHasErrors('status');
        $this->assertSame('verified', $donation->fresh()->status);

        $this->patch(route('donations.update', $donation), ['status' => 'verified', 'notes' => 'Reviewed bank record'])
            ->assertRedirect();
        $this->assertSame('Reviewed bank record', $donation->fresh()->notes);
    }

    public function test_status_only_donation_decisions_preserve_existing_notes(): void
    {
        $donation = $this->contribution(['notes' => 'Donor requested medical assistance.']);

        $this->actingAs($this->admin())->patch(route('donations.update', $donation), ['status' => 'rejected'])
            ->assertRedirect();

        $this->assertSame('Donor requested medical assistance.', $donation->fresh()->notes);
    }

    public function test_voided_historical_donations_are_not_reconciliation_candidates(): void
    {
        $donation = $this->contribution(['status' => 'verified', 'voided_at' => now()]);

        $this->assertFalse(Contribution::query()->eligibleForReconciliation()->whereKey($donation)->exists());
    }

    public function test_reconciliation_rechecks_the_donation_after_locking_it(): void
    {
        $donation = $this->contribution(['status' => 'pending']);
        $entry = $this->entry();

        try {
            app(ReconcileLedgerEntry::class)->handle($entry, $donation, $this->admin());
            $this->fail('An unverified donation was reconciled.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('contribution_id', $exception->errors());
        }

        $this->assertNull($entry->fresh()->matched_contribution_id);
        $this->assertSame('unmatched', $entry->fresh()->status);
    }

    public function test_failed_document_storage_rolls_back_the_donation_and_items(): void
    {
        $staff = User::factory()->create();
        $donor = $this->donor();
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFile')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        try {
            app(LogContribution::class)->handle([
                'donation_type' => 'in_kind', 'donor_id' => $donor->id,
                'items' => [[
                    'name' => 'Relief kit', 'category' => 'food_relief', 'quantity' => 1,
                    'unit' => 'box', 'condition' => 'new',
                ]],
                'evidence' => [UploadedFile::fake()->create('evidence.pdf', 20, 'application/pdf')],
            ], $staff);
            $this->fail('The donation was saved despite failed document storage.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('stored', $exception->getMessage());
        }

        $this->assertDatabaseCount('contributions', 0);
        $this->assertDatabaseCount('contribution_items', 0);
        $this->assertDatabaseCount('contribution_evidence', 0);
    }

    public function test_failed_certificate_upload_does_not_mark_the_certificate_as_issued(): void
    {
        $donation = $this->contribution(['certificate_requested' => true, 'certificate_status' => 'pending']);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFile')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        $this->actingAs($this->admin())->patch(route('donations.certificate.issue', $donation), [
            'certificate_of_donation' => UploadedFile::fake()->create('certificate.pdf', 20, 'application/pdf'),
        ])->assertSessionHasErrors('certificate_of_donation');

        $this->assertSame('pending', $donation->fresh()->certificate_status);
        $this->assertDatabaseCount('contribution_evidence', 0);
    }

    public function test_failed_voucher_upload_does_not_create_a_document_for_review(): void
    {
        [$staff, $request] = $this->certifiedRequest();
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFile')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        $this->actingAs($staff)->post(route('disbursements.signed-voucher.upload', $request), [
            'signed_voucher' => UploadedFile::fake()->create('voucher.pdf', 20, 'application/pdf'),
        ])->assertSessionHasErrors('signed_voucher');

        $this->assertSame('missing', $request->fresh()->signed_voucher_status);
        $this->assertDatabaseCount('disbursement_vouchers', 0);
    }

    public function test_a_stale_bank_entry_cannot_be_matched_a_second_time(): void
    {
        $first = $this->contribution(['status' => 'verified']);
        $second = $this->contribution(['status' => 'verified']);
        $entry = $this->entry(['status' => 'matched', 'matched_contribution_id' => $first->id]);

        try {
            app(ReconcileLedgerEntry::class)->handle($entry, $second, $this->admin());
            $this->fail('An already matched bank entry was reassigned.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('bank_ledger_entry_id', $exception->errors());
        }

        $this->assertSame($first->id, $entry->fresh()->matched_contribution_id);
    }

    private function contribution(array $attributes = []): Contribution
    {
        return Contribution::query()->create(array_merge([
            'donor_id' => $this->donor()->id, 'created_by' => User::factory()->create()->id,
            'type' => 'cash', 'status' => 'pending', 'amount' => 1250,
            'payment_method' => 'bank_transfer', 'received_at' => now(),
        ], $attributes));
    }

    private function donor(): Donor
    {
        return Donor::query()->create([
            'donor_type' => 'individual', 'full_name' => 'Workflow Donor',
            'address' => 'Poblacion', 'contact_number' => '09170000001', 'status' => 'active',
        ]);
    }

    private function entry(array $attributes = []): BankLedgerEntry
    {
        return BankLedgerEntry::query()->create(array_merge([
            'statement_id' => 'WORKFLOW-001', 'description' => 'Deposit',
            'amount' => 1250, 'transaction_date' => now(),
            'transaction_type' => 'deposit', 'payment_method' => 'bank_transfer', 'status' => 'unmatched',
        ], $attributes));
    }

    private function certifiedRequest(): array
    {
        $staff = User::factory()->create();
        $request = CashDisbursement::query()->create(array_merge($this->requestPayload(), [
            'requested_by' => $staff->id, 'status' => 'pending', 'dv_number' => '2026-09-0001',
            'treasurer_certified_by' => $staff->id, 'treasurer_certified_at' => now(),
            'treasurer_certification_notes' => 'Physically certified.',
        ]));

        return [$staff, $request];
    }

    private function requestPayload(array $attributes = []): array
    {
        return array_merge([
            'amount' => 1250, 'purpose' => 'Emergency supplies', 'recipient' => 'Community Pharmacy',
            'payee_address' => 'Poblacion', 'fund_source' => 'General Fund',
            'requested_date' => '2026-09-12',
        ], $attributes);
    }

    private function admin(): User
    {
        $role = Role::query()->firstOrCreate(['name' => Role::ADMIN], config('auth_defaults.roles.admin'));

        return User::factory()->create(['role_id' => $role->id]);
    }
}
