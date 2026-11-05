<?php

namespace Tests\Feature;

use App\Models\BankLedgerEntry;
use App\Models\CashDisbursement;
use App\Models\Contribution;
use App\Models\Donor;
use App\Models\Role;
use App\Models\SecurityActivityEvent;
use App\Models\Workspace;
use App\Models\User;
use App\Services\BankReconciliation\MatchWithdrawalDisbursements;
use App\Services\Contributions\LogContribution;
use App\Services\Contributions\UploadContributionDeed;
use App\Services\Disbursements\RecordDisbursementPayment;
use App\Services\Disbursements\VoidDisbursement;
use App\Services\Reports\FinancialLedgerReportBuilder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class VerifiedWorkflowBugsTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_requests_cannot_be_cancelled_and_remain_in_totals(): void
    {
        [$staff, $fund] = $this->approvedFund();
        app(RecordDisbursementPayment::class)->handle($fund, $this->paymentData('cash'), $staff);
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('disbursements.void', $fund), ['void_reason' => 'Already paid'])
            ->assertForbidden();

        try {
            app(VoidDisbursement::class)->handle($fund, 'Stale cancellation', $admin);
            $this->fail('A paid fund request was cancelled.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('disbursement', $exception->errors());
        }

        $this->assertFalse($fund->fresh()->isVoided());
        $this->assertTrue($fund->payment()->exists());
        $report = app(FinancialLedgerReportBuilder::class)->build((int) now()->year, (int) now()->month);
        $this->assertEquals(1250, $report['summary']['disbursement_total']);
    }

    public function test_bank_matched_unpaid_requests_cannot_be_cancelled(): void
    {
        [$staff, $fund] = $this->approvedFund();
        $withdrawal = $this->withdrawal();
        $withdrawal->matchedDisbursements()->attach($fund->id, ['workspace_id' => $fund->workspace_id]);
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('disbursements.void', $fund), ['void_reason' => 'Already matched'])
            ->assertForbidden();

        try {
            app(VoidDisbursement::class)->handle($fund, 'Stale cancellation', $admin);
            $this->fail('A bank-matched request was cancelled.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('disbursement', $exception->errors());
        }

        $this->assertFalse($fund->fresh()->isVoided());
        $this->assertSame([$fund->id], $withdrawal->matchedDisbursements()->pluck('cash_disbursements.id')->all());
    }

    public function test_cash_and_check_payments_cannot_be_submitted_to_either_matching_endpoint(): void
    {
        foreach (['cash', 'check'] as $method) {
            [$staff, $fund] = $this->approvedFund();
            app(RecordDisbursementPayment::class)->handle($fund, $this->paymentData($method), $staff);
            $withdrawal = $this->withdrawal();

            foreach (['bank-reconciliation.withdrawals.matches.store', 'bank-reconciliation.withdrawals.reconcile'] as $route) {
                $this->actingAs($staff)->postJson(route($route, $withdrawal), [
                    'disbursement_ids' => [$fund->id], 'finalize' => 1,
                ])->assertUnprocessable()->assertJsonValidationErrors('disbursement_ids');
            }

            $this->assertSame('unreconciled', $withdrawal->fresh()->status);
            $this->assertSame(0, $withdrawal->matchedDisbursements()->count());
        }
    }

    public function test_matching_service_rechecks_payment_and_existing_match_state(): void
    {
        [$staff, $fund] = $this->approvedFund();
        $first = $this->withdrawal();
        $second = $this->withdrawal();
        app(MatchWithdrawalDisbursements::class)->handle($first, [$fund->id], $staff);

        try {
            app(MatchWithdrawalDisbursements::class)->handle($second, [$fund->id], $staff);
            $this->fail('A stale selection reused a matched request.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('disbursement_ids', $exception->errors());
        }

        $this->assertSame(0, $second->matchedDisbursements()->count());
        $this->assertSame([$fund->id], $first->matchedDisbursements()->pluck('cash_disbursements.id')->all());

        // Model a payment completed after the original request was validated.
        $fund->payment()->create($this->paymentData('cash') + ['released_by' => $staff->id, 'released_at' => now()]);
        try {
            app(MatchWithdrawalDisbursements::class)->handle($first, [$fund->id], $staff, null, true);
            $this->fail('A stale selection accepted a cash payment.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('disbursement_ids', $exception->errors());
        }
        $this->assertSame('unreconciled', $first->fresh()->status);
    }

    public function test_a_finalized_withdrawal_cannot_be_overwritten_through_a_stale_model(): void
    {
        [$staff, $fund] = $this->approvedFund();
        [, $other] = $this->approvedFund();
        $stale = $this->withdrawal();
        app(MatchWithdrawalDisbursements::class)->handle($stale, [$fund->id], $staff, null, true);

        try {
            app(MatchWithdrawalDisbursements::class)->handle($stale, [$other->id], $staff);
            $this->fail('A reconciled withdrawal was overwritten.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('withdrawal', $exception->errors());
        }

        $this->assertSame([$fund->id], $stale->matchedDisbursements()->pluck('cash_disbursements.id')->all());
    }

    public function test_payment_method_must_remain_compatible_with_an_existing_bank_match(): void
    {
        [$staff, $fund] = $this->approvedFund();
        $withdrawal = $this->withdrawal();
        $withdrawal->matchedDisbursements()->attach($fund->id, ['workspace_id' => $fund->workspace_id]);

        $this->actingAs($staff)->postJson(route('disbursements.payment.store', $fund), $this->paymentData('cash'))
            ->assertUnprocessable()->assertJsonValidationErrors('payment_method');
        $this->assertFalse($fund->payment()->exists());

        $this->post(route('disbursements.payment.store', $fund), $this->paymentData('bank_transfer'))
            ->assertRedirect()->assertSessionDoesntHaveErrors();
        $this->assertSame('bank_transfer', $fund->payment()->firstOrFail()->payment_method);
    }

    public function test_missing_deed_can_be_uploaded_then_the_donation_verified(): void
    {
        Storage::fake('local');
        [$staff, $donation] = $this->deedException();
        $this->actingAs($staff)->get(route('donations.index'))->assertOk()
            ->assertSee(route('donations.deed.upload', $donation), false)
            ->assertSee('Donor was unavailable');
        $this->get(route('donations.show', $donation))->assertOk()
            ->assertSee(route('donations.deed.upload', $donation), false);

        $this->patch(route('donations.deed.upload', $donation), [
            'deed_of_donation' => UploadedFile::fake()->create('signed-deed.pdf', 20, 'application/pdf'),
        ])->assertRedirect(route('donations.show', $donation))->assertSessionDoesntHaveErrors();

        $document = $donation->evidence()->where('document_type', 'deed_of_donation')->firstOrFail();
        Storage::disk('local')->assertExists($document->path);
        $this->assertSame($staff->id, $document->uploaded_by);
        $this->assertSame('complete', $donation->fresh()->document_review_status);
        $this->assertSame('Donor was unavailable', $donation->fresh()->deed_decline_reason);
        $this->assertDatabaseHas('security_activity_events', ['action' => 'cash_donation_deed_uploaded', 'actor_id' => $staff->id]);

        $this->actingAs($this->admin())->patch(route('donations.verify', $donation))
            ->assertRedirect()->assertSessionDoesntHaveErrors();
        $this->assertSame('verified', $donation->fresh()->status);
    }

    public function test_deed_upload_validation_redirects_to_the_record_with_scoped_errors(): void
    {
        [$staff, $donation] = $this->deedException();
        $this->actingAs($staff)->from(route('donations.index'))->patch(route('donations.deed.upload', $donation), [
            'deed_of_donation' => UploadedFile::fake()->create('deed.txt', 20, 'text/plain'),
        ])->assertRedirect(route('donations.show', $donation))
            ->assertSessionHasErrors('deed_of_donation', null, 'deedUpload');

        $this->get(route('donations.show', $donation))->assertOk()->assertSee('deed_of_donation-error');
        $this->assertFalse($donation->evidence()->where('document_type', 'deed_of_donation')->exists());
    }

    public function test_failed_deed_storage_preserves_the_pending_review(): void
    {
        [$staff, $donation] = $this->deedException();
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFile')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        $this->actingAs($staff)->patch(route('donations.deed.upload', $donation), [
            'deed_of_donation' => UploadedFile::fake()->create('deed.pdf', 20, 'application/pdf'),
        ])->assertRedirect(route('donations.show', $donation))
            ->assertSessionHasErrors('deed_of_donation', null, 'deedUpload');

        $this->assertSame('missing_deed_admin_review', $donation->fresh()->document_review_status);
        $this->assertFalse($donation->evidence()->where('document_type', 'deed_of_donation')->exists());
        $this->assertDatabaseMissing('security_activity_events', ['action' => 'cash_donation_deed_uploaded']);
    }

    public function test_deed_upload_rejects_other_staff_and_inactive_records(): void
    {
        [$staff, $donation] = $this->deedException();
        $payload = fn () => ['deed_of_donation' => UploadedFile::fake()->create('deed.pdf', 20, 'application/pdf')];
        $this->actingAs(User::factory()->create())->patch(route('donations.deed.upload', $donation), $payload())->assertForbidden();

        $admin = $this->admin();
        foreach (['verified', 'rejected', 'cancelled'] as $status) {
            $donation->update(['status' => $status]);
            $this->actingAs($admin)->patch(route('donations.deed.upload', $donation), $payload())->assertForbidden();
        }
        $donation->update(['status' => 'pending', 'voided_at' => now()]);
        $this->patch(route('donations.deed.upload', $donation), $payload())->assertForbidden();
        $this->assertFalse($donation->evidence()->where('document_type', 'deed_of_donation')->exists());
    }

    public function test_donation_list_preserves_voided_state_and_hides_update_actions(): void
    {
        [$staff, $donation] = $this->deedException();
        $donation->update(['status' => 'cancelled', 'voided_at' => now(), 'void_reason' => 'Duplicate']);
        $response = $this->actingAs($this->admin())->get(route('donations.index'))->assertOk();
        $listed = $response->viewData('contributions')->firstWhere('id', $donation->id);

        $this->assertTrue($listed->isVoided());
        $this->assertSame('Donor was unavailable', $listed->deed_decline_reason);
        $response->assertDontSee('id="update-contribution-'.$donation->id.'"', false)
            ->assertDontSee(route('donations.deed.upload', $donation), false);
    }

    public function test_paid_bank_transfers_can_still_be_reconciled(): void
    {
        [$staff, $fund] = $this->approvedFund();
        app(RecordDisbursementPayment::class)->handle($fund, $this->paymentData('bank_transfer'), $staff);
        $withdrawal = $this->withdrawal();

        $this->actingAs($staff)->post(route('bank-reconciliation.withdrawals.reconcile', $withdrawal), [
            'disbursement_ids' => [$fund->id], 'finalize' => 1,
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->assertSame('reconciled', $withdrawal->fresh()->status);
        $this->assertDatabaseHas('bank_withdrawal_disbursement_matches', [
            'bank_ledger_entry_id' => $withdrawal->id,
            'cash_disbursement_id' => $fund->id,
            'workspace_id' => $fund->workspace_id,
        ]);
    }

    public function test_deed_upload_rolls_back_and_removes_the_file_when_the_audit_fails(): void
    {
        [$staff, $donation] = $this->deedException();
        $before = Storage::disk('local')->allFiles();
        SecurityActivityEvent::creating(function (SecurityActivityEvent $event): void {
            if ($event->action === 'cash_donation_deed_uploaded') {
                throw new RuntimeException('Audit unavailable.');
            }
        });

        try {
            app(UploadContributionDeed::class)->handle(
                $donation, UploadedFile::fake()->create('deed.pdf', 20, 'application/pdf'), $staff
            );
            $this->fail('The document upload succeeded without its audit.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable.', $exception->getMessage());
        }

        $this->assertSame('missing_deed_admin_review', $donation->fresh()->document_review_status);
        $this->assertFalse($donation->evidence()->where('document_type', 'deed_of_donation')->exists());
        $this->assertSame($before, Storage::disk('local')->allFiles());
    }

    public function test_deed_upload_cannot_cross_workspace_boundaries(): void
    {
        [$staff, $donation] = $this->deedException();
        $otherWorkspace = Workspace::create(['name' => 'Other workspace', 'slug' => 'other-review', 'status' => 'active']);
        $donation->update(['workspace_id' => $otherWorkspace->id]);

        $this->actingAs($staff)->withSession(['active_workspace_id' => $staff->workspaceMemberships()->firstOrFail()->workspace_id])
            ->patch(route('donations.deed.upload', $donation), [
                'deed_of_donation' => UploadedFile::fake()->create('deed.pdf', 20, 'application/pdf'),
            ])->assertNotFound();

        $this->assertDatabaseMissing('contribution_evidence', [
            'contribution_id' => $donation->id, 'document_type' => 'deed_of_donation',
        ]);
    }

    private function approvedFund(): array
    {
        $staff = User::factory()->create();

        return [$staff, CashDisbursement::create([
            'requested_by' => $staff->id, 'amount' => 1250, 'purpose' => 'Medical supplies',
            'recipient' => 'Community pharmacy', 'status' => 'approved', 'approved_at' => now(),
        ])];
    }

    private function paymentData(string $method): array
    {
        return ['amount' => 1250, 'payment_date' => now()->toDateString(),
            'payment_method' => $method, 'reference_number' => $method === 'cash' ? null : 'REF-REVIEW'];
    }

    private function withdrawal(): BankLedgerEntry
    {
        return BankLedgerEntry::create([
            'statement_id' => fake()->uuid(), 'description' => 'Bank withdrawal', 'amount' => 1250,
            'transaction_date' => now(), 'transaction_type' => 'withdrawal', 'status' => 'unreconciled',
        ]);
    }

    private function deedException(): array
    {
        Storage::fake('local');
        $staff = User::factory()->create();
        $donor = Donor::create(['donor_type' => 'individual', 'full_name' => 'Review Donor',
            'address' => 'Poblacion', 'contact_number' => '09170000001', 'status' => 'active']);
        $donation = app(LogContribution::class)->handle([
            'donation_type' => 'cash', 'donor_id' => $donor->id, 'amount' => 6000,
            'payment_method' => 'cash', 'official_receipt_number' => 'OR-REVIEW',
            'official_receipt_document' => UploadedFile::fake()->create('receipt.pdf', 20, 'application/pdf'),
            'certificate_of_acceptance' => UploadedFile::fake()->create('acceptance.pdf', 20, 'application/pdf'),
            'deed_declined' => true, 'deed_decline_reason' => 'Donor was unavailable',
        ], $staff);

        return [$staff, $donation];
    }

    private function admin(): User
    {
        $role = Role::firstOrCreate(['name' => Role::ADMIN], config('auth_defaults.roles.admin'));

        return User::factory()->create(['role_id' => $role->id]);
    }
}
