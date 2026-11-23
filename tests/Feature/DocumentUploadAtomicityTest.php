<?php

namespace Tests\Feature;

use App\Models\CashDisbursement;
use App\Models\Contribution;
use App\Models\Donor;
use App\Models\Role;
use App\Models\SecurityActivityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DocumentUploadAtomicityTest extends TestCase
{
    use RefreshDatabase;

    public function test_certificate_upload_rolls_back_records_and_file_when_audit_fails(): void
    {
        Storage::fake('local');
        [$admin, $contribution] = $this->certificateRequest();
        Storage::disk('local')->put('existing-document.pdf', 'Existing document');
        $before = Storage::disk('local')->allFiles();
        $this->failAudit('cash_donation_certificate_issued');

        try {
            $this->withoutExceptionHandling()->actingAs($admin)
                ->patch(route('donations.certificate.issue', $contribution), [
                    'certificate_of_donation' => UploadedFile::fake()->create('certificate.pdf', 20, 'application/pdf'),
                ]);
            $this->fail('Certificate issuance succeeded without its audit.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable.', $exception->getMessage());
        }

        $contribution->refresh();
        $this->assertSame('pending', $contribution->certificate_status);
        $this->assertNull($contribution->certificate_issued_at);
        $this->assertNull($contribution->certificate_issued_by);
        $this->assertDatabaseCount('contribution_evidence', 0);
        $this->assertSame($before, Storage::disk('local')->allFiles());
    }

    public function test_signed_voucher_upload_preserves_previous_review_and_files_when_audit_fails(): void
    {
        Storage::fake('local');
        [$staff, $fund] = $this->fundRequest();
        Storage::disk('local')->put('previous-voucher.pdf', 'Previous document');
        $previous = $fund->vouchers()->create([
            'document_type' => 'signed_voucher', 'review_status' => 'missing', 'disk' => 'local',
            'path' => 'previous-voucher.pdf', 'original_name' => 'previous-voucher.pdf',
            'mime_type' => 'application/pdf', 'size' => 17, 'uploaded_by' => $staff->id,
        ]);
        $before = Storage::disk('local')->allFiles();
        $this->failAudit('disbursement_signed_voucher_uploaded');

        try {
            $this->withoutExceptionHandling()->actingAs($staff)
                ->post(route('disbursements.signed-voucher.upload', $fund), [
                    'signed_voucher' => UploadedFile::fake()->create('voucher.pdf', 20, 'application/pdf'),
                ]);
            $this->fail('Voucher upload succeeded without its audit.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable.', $exception->getMessage());
        }

        $this->assertSame('missing', $fund->fresh()->signed_voucher_status);
        $this->assertSame('Missing signature', $fund->fresh()->signed_voucher_request_reason);
        $this->assertDatabaseCount('disbursement_vouchers', 1);
        $this->assertSame('missing', $previous->fresh()->review_status);
        $this->assertSame($before, Storage::disk('local')->allFiles());
    }

    public function test_certificate_issuance_rechecks_state_before_storing_another_document(): void
    {
        Storage::fake('local');
        [$admin, $contribution] = $this->certificateRequest();
        Gate::after(function (User $user, string $ability, $result) use ($contribution): void {
            if ($ability === 'issueCertificate' && $result === true) {
                // Simulate another request issuing the certificate after initial authorization.
                DB::table('contributions')->where('id', $contribution->id)->update(['certificate_status' => 'issued']);
            }
        });

        $this->actingAs($admin)->patch(route('donations.certificate.issue', $contribution), [
            'certificate_of_donation' => UploadedFile::fake()->create('duplicate.pdf', 20, 'application/pdf'),
        ])->assertForbidden();

        $this->assertSame('issued', $contribution->fresh()->certificate_status);
        $this->assertDatabaseCount('contribution_evidence', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_signed_voucher_upload_rechecks_a_cancelled_request_before_storing(): void
    {
        Storage::fake('local');
        [$staff, $fund] = $this->fundRequest();
        Gate::after(function (User $user, string $ability, $result) use ($fund): void {
            if ($ability === 'uploadSignedVoucher' && $result === true) {
                // Simulate cancellation after the upload request was initially authorized.
                DB::table('cash_disbursements')->where('id', $fund->id)->update(['voided_at' => now()]);
            }
        });

        $this->actingAs($staff)->post(route('disbursements.signed-voucher.upload', $fund), [
            'signed_voucher' => UploadedFile::fake()->create('voucher.pdf', 20, 'application/pdf'),
        ])->assertForbidden();

        $this->assertTrue($fund->fresh()->isVoided());
        $this->assertSame('missing', $fund->fresh()->signed_voucher_status);
        $this->assertDatabaseCount('disbursement_vouchers', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_cancelled_fund_request_cannot_accept_a_signed_voucher(): void
    {
        Storage::fake('local');
        [$staff, $fund] = $this->fundRequest();
        $fund->update(['voided_at' => now(), 'voided_by' => $staff->id, 'void_reason' => 'Duplicate request']);

        $this->actingAs($staff)->post(route('disbursements.signed-voucher.upload', $fund), [
            'signed_voucher' => UploadedFile::fake()->create('voucher.pdf', 20, 'application/pdf'),
        ])->assertForbidden();

        $this->assertSame('missing', $fund->fresh()->signed_voucher_status);
        $this->assertDatabaseCount('disbursement_vouchers', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    private function certificateRequest(): array
    {
        $role = Role::firstOrCreate(['name' => Role::ADMIN], config('auth_defaults.roles.admin'));
        $admin = User::factory()->create(['role_id' => $role->id]);
        $donor = Donor::create([
            'donor_type' => 'individual', 'full_name' => 'Certificate Donor',
            'address' => 'Poblacion', 'contact_number' => '09170000001', 'status' => 'active',
        ]);

        return [$admin, Contribution::create([
            'donor_id' => $donor->id, 'created_by' => $admin->id,
            'type' => 'in_kind', 'status' => 'received', 'received_at' => now(),
            'certificate_requested' => true, 'certificate_status' => 'pending',
        ])];
    }

    private function fundRequest(): array
    {
        $staff = User::factory()->create();

        return [$staff, CashDisbursement::create([
            'requested_by' => $staff->id, 'amount' => 1250, 'purpose' => 'Medical supplies',
            'recipient' => 'Community pharmacy', 'status' => 'approved', 'approved_at' => now(),
            'signed_voucher_status' => 'missing', 'signed_voucher_request_reason' => 'Missing signature',
        ])];
    }

    private function failAudit(string $action): void
    {
        SecurityActivityEvent::creating(function (SecurityActivityEvent $event) use ($action): void {
            if ($event->action === $action) {
                throw new RuntimeException('Audit unavailable.');
            }
        });
    }
}
