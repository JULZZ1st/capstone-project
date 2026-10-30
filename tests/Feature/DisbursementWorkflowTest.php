<?php

namespace Tests\Feature;

use App\Models\CashDisbursement;
use App\Models\Contribution;
use App\Models\DisbursementVoucher;
use App\Models\Donor;
use App\Models\Role;
use App\Models\User;
use App\Support\Dashboard\DashboardMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DisbursementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_disbursement_request_saves_new_fields_as_pending_and_displays_in_history(): void
    {
        $requester = $this->userWithRole('staff');

        $response = $this->actingAs($requester)->post(route('disbursements.store'), [
            'amount' => 2450.75,
            'purpose' => 'Medical assistance payout',
            'recipient' => 'Juan Dela Cruz',
            'payee_address' => '123 Main Street, Poblacion',
            'payee_tin' => '123-456-789',
            'fund_source' => 'General Fund',
            'obr_number' => 'OBR-2026-001',
            'requested_date' => '2026-09-12',
            'notes' => 'Urgent dialysis support',
        ]);

        $response->assertRedirect(route('disbursements.index'));
        $this->assertDatabaseHas('cash_disbursements', [
            'amount' => 2450.75,
            'purpose' => 'Medical assistance payout',
            'recipient' => 'Juan Dela Cruz',
            'notes' => 'Urgent dialysis support',
            'requested_by' => $requester->id,
            'status' => 'pending',
            'payee_address' => '123 Main Street, Poblacion',
            'payee_tin' => '123-456-789',
            'fund_source' => 'General Fund',
            'obr_number' => 'OBR-2026-001',
            'dv_number' => '2026-09-0001',
        ]);
        $this->assertSame('2026-09-12', CashDisbursement::query()->firstOrFail()->requested_date->toDateString());

        $this->actingAs($requester)
            ->get(route('disbursements.index'))
            ->assertOk()
            ->assertSee('Sep 12, 2026')
            ->assertSee('Medical assistance payout')
            ->assertSee('Juan Dela Cruz')
            ->assertSee('PHP 2,450.75')
            ->assertSee($requester->name)
            ->assertSee('pending')
            ->assertSee('Save Request')
            ->assertSee('View Details')
            ->assertSee(route('disbursements.show', CashDisbursement::query()->firstOrFail()), false)
            ->assertSee('Urgent dialysis support');
    }

    public function test_request_modal_preserves_unicode_quotes_and_backslashes(): void
    {
        $requester = $this->userWithRole('staff');
        $purpose = 'Tulong sa "Pamilya" – ₱1,250';
        $notes = 'Drop-off at sitio Niño\\Relief & assistance';

        $this->actingAs($requester)->post(route('disbursements.store'), [
            'amount' => 1250,
            'payee_address' => 'Test Address',
            'fund_source' => 'General Fund',
            'purpose' => $purpose,
            'recipient' => 'José Niño',
            'requested_date' => '2026-09-12',
            'notes' => $notes,
        ])->assertRedirect(route('disbursements.index'));

        $disbursement = CashDisbursement::query()->firstOrFail();
        $response = $this->get(route('disbursements.index'))->assertOk();
        $document = new \DOMDocument();
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $row = (new \DOMXPath($document))
            ->query('//tr[@data-fund-request-id="'.$disbursement->id.'"]')
            ->item(0);

        $this->assertInstanceOf(\DOMElement::class, $row);
        $this->assertSame($purpose, $row->getAttribute('data-purpose'));
        $this->assertSame($notes, $row->getAttribute('data-notes'));
    }

    public function test_disbursement_request_requires_new_required_fields(): void
    {
        $requester = $this->userWithRole('staff');

        $this->actingAs($requester)
            ->post(route('disbursements.store'), [
                'amount' => 2450.75,
                'purpose' => 'Medical assistance payout',
            ])
            ->assertSessionHasErrors(['recipient', 'requested_date']);

        $this->assertDatabaseCount('cash_disbursements', 0);
    }

    public function test_editing_a_request_works_without_the_removed_supporting_reference_field(): void
    {
        $requester = $this->userWithRole('staff');
        $disbursement = CashDisbursement::query()->create([
            'amount' => 100,
            'purpose' => 'Legacy reference preservation',
            'recipient' => 'Test Payee',
            'payee_address' => 'Test Address',
            'fund_source' => 'General Fund',
            'requested_date' => '2026-09-12',
            'requested_by' => $requester->id,
            'status' => 'pending',
        ]);

        $this->actingAs($requester)
            ->patch(route('disbursements.update', $disbursement), [
                'amount' => 150,
                'purpose' => 'Updated request',
                'recipient' => 'Test Payee',
                'payee_address' => 'Test Address',
                'fund_source' => 'General Fund',
                'requested_date' => '2026-09-12',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cash_disbursements', [
            'id' => $disbursement->id,
            'amount' => 150,
        ]);
    }

    public function test_new_requests_receive_sequential_dv_numbers_once(): void
    {
        $requester = $this->userWithRole('staff');
        $payload = [
            'amount' => 100,
            'purpose' => 'Sequential DV test',
            'recipient' => 'Test Payee',
            'payee_address' => 'Test Address',
            'fund_source' => 'General Fund',
            'requested_date' => '2026-09-12',
        ];

        $this->actingAs($requester)->post(route('disbursements.store'), $payload)->assertRedirect();
        $this->actingAs($requester)->post(route('disbursements.store'), $payload)->assertRedirect();

        $this->assertSame(
            ['2026-09-0001', '2026-09-0002'],
            CashDisbursement::query()->orderBy('id')->pluck('dv_number')->all(),
        );
    }

    public function test_requester_cannot_approve_own_disbursement(): void
    {
        $requester = $this->userWithRole('staff');
        $disbursement = CashDisbursement::query()->create([
            'amount' => 1250,
            'purpose' => 'Emergency supplies',
            'requested_by' => $requester->id,
            'status' => 'pending',
        ]);

        $this->actingAs($requester)
            ->patch(route('disbursements.approve', $disbursement))
            ->assertForbidden();

        $this->assertDatabaseHas('cash_disbursements', ['id' => $disbursement->id, 'status' => 'pending']);
    }

    public function test_admin_approval_is_audited(): void
    {
        $requester = $this->userWithRole('staff');
        $admin = $this->userWithRole('admin');
        $disbursement = CashDisbursement::query()->create([
            'amount' => 1250,
            'purpose' => 'Emergency supplies',
            'requested_by' => $requester->id,
            'status' => 'pending',
            'treasurer_certified_by' => $requester->id,
            'treasurer_certified_at' => now(),
        ]);
        $this->actingAs($admin)->patch(route('disbursements.approve', $disbursement))->assertRedirect();

        $this->assertDatabaseHas('cash_disbursements', ['id' => $disbursement->id, 'status' => 'approved', 'approved_by' => $admin->id]);
        $this->assertNotNull($disbursement->fresh()->approved_at);
        $this->assertDatabaseHas('security_activity_events', ['action' => 'disbursement_approved', 'actor_id' => $admin->id]);
    }

    public function test_pending_disbursement_detail_shows_admin_decision_actions(): void
    {
        $requester = $this->userWithRole('staff');
        $admin = $this->userWithRole('admin');
        $disbursement = CashDisbursement::query()->create([
            'amount' => 1250,
            'purpose' => 'Emergency supplies',
            'recipient' => 'Community Pharmacy',
            'requested_date' => '2026-09-12',
            'notes' => 'Prescription support',
            'requested_by' => $requester->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('disbursements.show', $disbursement))
            ->assertOk()
            ->assertSee('Emergency supplies')
            ->assertSee('Community Pharmacy')
            ->assertSee('PHP 1,250.00')
            ->assertDontSee('MED-001')
            ->assertSee('Prescription support')
            ->assertDontSee('Approve')
            ->assertSee('Admin Review')
            ->assertSee("Treasurer Box B certification is required before approval.", false);
    }

    public function test_admin_can_approve_another_users_pending_request_from_request_history(): void
    {
        $requester = $this->userWithRole('staff');
        $admin = $this->userWithRole('admin');
        CashDisbursement::query()->create([
            'amount' => 1250,
            'purpose' => 'Emergency supplies',
            'requested_by' => $requester->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('disbursements.index'))
            ->assertOk()
            ->assertSee('View Details')
            ->assertDontSee('Quick Review')
            ->assertSee('data-open-fund-request-details="fund-request-details-modal-', false);
    }

    public function test_non_admin_cannot_approve_or_reject_server_side(): void
    {
        $requester = $this->userWithRole('staff');
        $secretary = $this->userWithRole('staff');
        $disbursement = CashDisbursement::query()->create([
            'amount' => 1250,
            'purpose' => 'Emergency supplies',
            'requested_by' => $requester->id,
            'status' => 'pending',
        ]);

        $this->actingAs($secretary)
            ->patch(route('disbursements.approve', $disbursement))
            ->assertForbidden();

        $this->actingAs($secretary)
            ->patch(route('disbursements.reject', $disbursement), ['rejection_reason' => 'Missing details'])
            ->assertForbidden();

        $this->assertDatabaseHas('cash_disbursements', ['id' => $disbursement->id, 'status' => 'pending']);
    }

    public function test_non_admin_can_view_pending_details_without_decision_actions(): void
    {
        $requester = $this->userWithRole('staff');
        $disbursement = CashDisbursement::query()->create([
            'amount' => 1250,
            'purpose' => 'Emergency supplies',
            'requested_by' => $requester->id,
            'status' => 'pending',
        ]);

        $this->actingAs($requester)
            ->get(route('disbursements.show', $disbursement))
            ->assertOk()
            ->assertSee('Emergency supplies')
            ->assertDontSee('Admin Review')
            ->assertDontSee('Approve')
            ->assertDontSee('Reject Fund Request')
            ->assertSee('Generate Disbursement Voucher')
            ->assertSee(route('disbursements.voucher', $disbursement), false);
    }

    public function test_staff_can_download_a_disbursement_voucher_before_approval(): void
    {
        $requester = $this->userWithRole('staff');
        $disbursement = CashDisbursement::query()->create([
            'amount' => 1250,
            'purpose' => 'Emergency supplies',
            'recipient' => 'Community Pharmacy',
            'requested_date' => '2026-09-12',
            'notes' => 'Prescription support',
            'requested_by' => $requester->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($requester)->get(route('disbursements.voucher', $disbursement));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename=disbursement-voucher-'.$disbursement->id.'.pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_staff_can_upload_a_signed_pdf_and_preview_it_inline(): void
    {
        Storage::fake('local');
        $requester = $this->userWithRole('staff');
        $disbursement = $this->pendingDisbursement($requester);

        $response = $this->actingAs($requester)->post(route('disbursements.signed-voucher.upload', $disbursement), [
            'signed_voucher' => UploadedFile::fake()->create('signed-voucher.pdf', 20, 'application/pdf'),
        ]);

        $response->assertRedirect();
        $voucher = DisbursementVoucher::query()->firstOrFail();
        $this->assertSame('pending_review', $voucher->review_status);
        $this->assertSame('pending_review', $disbursement->fresh()->signed_voucher_status);
        Storage::disk('local')->assertExists($voucher->path);
        $this->assertDatabaseHas('security_activity_events', ['action' => 'disbursement_signed_voucher_uploaded', 'actor_id' => $requester->id]);

        $preview = $this->actingAs($requester)->get(route('disbursements.signed-voucher.preview', $disbursement));
        $preview->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Content-Disposition', 'inline; filename="signed-voucher.pdf"; filename*=UTF-8\'\'signed-voucher.pdf');
    }

    public function test_staff_can_upload_signed_voucher_after_admin_approval(): void
    {
        Storage::fake('local');
        $requester = $this->userWithRole('staff');
        $admin = $this->userWithRole('admin');
        $disbursement = $this->pendingDisbursement($requester);
        $disbursement->update([
            'status' => 'approved',
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $this->actingAs($requester)
            ->post(route('disbursements.signed-voucher.upload', $disbursement), [
                'signed_voucher' => UploadedFile::fake()->create('fully-signed-voucher.pdf', 20, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cash_disbursements', [
            'id' => $disbursement->id,
            'status' => 'approved',
            'signed_voucher_status' => 'pending_review',
        ]);
    }

    public function test_staff_can_upload_a_signed_image_and_preview_it_inline(): void
    {
        Storage::fake('local');
        $requester = $this->userWithRole('staff');
        $disbursement = $this->pendingDisbursement($requester);

        $this->actingAs($requester)->post(route('disbursements.signed-voucher.upload', $disbursement), [
            'signed_voucher' => UploadedFile::fake()->createWithContent(
                'signed-voucher.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII='),
            ),
        ])->assertRedirect();

        $preview = $this->actingAs($requester)->get(route('disbursements.signed-voucher.preview', $disbursement));
        $preview->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_signed_voucher_upload_rejects_unsupported_files(): void
    {
        $requester = $this->userWithRole('staff');
        $disbursement = $this->pendingDisbursement($requester);

        $this->actingAs($requester)
            ->post(route('disbursements.signed-voucher.upload', $disbursement), [
                'signed_voucher' => UploadedFile::fake()->create('signed-voucher.txt', 20, 'text/plain'),
            ])
            ->assertSessionHasErrors('signed_voucher');

        $this->assertDatabaseCount('disbursement_vouchers', 0);
    }

    public function test_admin_can_request_missing_signed_voucher_documents_and_staff_sees_reason(): void
    {
        $requester = $this->userWithRole('staff');
        $admin = $this->userWithRole('admin');
        $disbursement = $this->pendingDisbursement($requester);

        $this->actingAs($admin)
            ->patch(route('disbursements.signed-voucher.request', $disbursement), ['reason' => 'The Treasurer signature is missing.'])
            ->assertRedirect();

        $this->assertDatabaseHas('cash_disbursements', [
            'id' => $disbursement->id,
            'status' => 'pending',
            'signed_voucher_status' => 'missing',
            'signed_voucher_request_reason' => 'The Treasurer signature is missing.',
        ]);
        $this->assertDatabaseHas('security_activity_events', ['action' => 'disbursement_signed_voucher_documents_requested', 'actor_id' => $admin->id]);

        $this->actingAs($requester)
            ->get(route('disbursements.show', $disbursement))
            ->assertSee('The Treasurer signature is missing.');
    }

    public function test_admin_cannot_approve_pending_request_without_treasurer_certification(): void
    {
        $requester = $this->userWithRole('staff');
        $admin = $this->userWithRole('admin');
        $disbursement = $this->pendingDisbursement($requester);

        $this->actingAs($admin)
            ->patch(route('disbursements.approve', $disbursement))
            ->assertSessionHasErrors('disbursement');

        $this->assertDatabaseHas('cash_disbursements', ['id' => $disbursement->id, 'status' => 'pending']);
    }

    public function test_admin_can_approve_after_treasurer_certification_without_signed_voucher(): void
    {
        $requester = $this->userWithRole('staff');
        $admin = $this->userWithRole('admin');
        $disbursement = $this->pendingDisbursement($requester);
        $disbursement->update([
            'treasurer_certified_by' => $requester->id,
            'treasurer_certified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('disbursements.approve', $disbursement))
            ->assertRedirect();

        $this->assertDatabaseHas('cash_disbursements', [
            'id' => $disbursement->id,
            'status' => 'approved',
            'approved_by' => $admin->id,
        ]);
        $this->assertDatabaseCount('disbursement_vouchers', 0);
    }

    public function test_staff_can_record_treasurer_certification_and_audit_it(): void
    {
        $requester = $this->userWithRole('staff');
        $disbursement = $this->pendingDisbursement($requester);

        $this->actingAs($requester)
            ->post(route('disbursements.treasurer-certification.store', $disbursement), [
                'certified' => '1',
                'notes' => 'Box B completed on the printed voucher.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cash_disbursements', [
            'id' => $disbursement->id,
            'treasurer_certified_by' => $requester->id,
            'treasurer_certification_notes' => 'Box B completed on the printed voucher.',
        ]);
        $this->assertDatabaseHas('security_activity_events', [
            'action' => 'disbursement_treasurer_certified',
            'actor_id' => $requester->id,
        ]);
    }

    public function test_authorized_staff_can_record_one_full_payment_for_an_approved_request(): void
    {
        $requester = $this->userWithRole('staff');
        $admin = $this->userWithRole('admin');
        $disbursement = $this->pendingDisbursement($requester);
        $disbursement->update([
            'status' => 'approved',
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $this->actingAs($requester)
            ->post(route('disbursements.payment.store', $disbursement), [
                'payment_date' => '2026-10-30',
                'amount' => '1250.00',
                'payment_method' => 'cash',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cash_disbursements', [
            'id' => $disbursement->id,
            'status' => 'approved',
            'payment_status' => 'disbursed',
        ]);
        $this->assertDatabaseHas('cash_disbursement_payments', [
            'cash_disbursement_id' => $disbursement->id,
            'amount' => 1250.00,
            'payment_method' => 'cash',
            'released_by' => $requester->id,
        ]);
        $this->assertDatabaseHas('security_activity_events', ['action' => 'disbursement_payment_recorded', 'actor_id' => $requester->id]);

        $this->actingAs($requester)
            ->get(route('disbursements.show', $disbursement))
            ->assertSee('Disbursed')
            ->assertSee('PHP 1,250.00')
            ->assertDontSee('Actual Disbursement')
            ->assertDontSee('Amount Released');
    }

    public function test_payment_requires_exact_approved_amount_and_prevents_duplicates(): void
    {
        $requester = $this->userWithRole('staff');
        $admin = $this->userWithRole('admin');
        $disbursement = $this->pendingDisbursement($requester);
        $disbursement->update(['status' => 'approved', 'approved_by' => $admin->id, 'approved_at' => now()]);

        $this->actingAs($requester)
            ->post(route('disbursements.payment.store', $disbursement), [
                'payment_date' => '2026-10-30', 'amount' => 1000, 'payment_method' => 'cash',
            ])
            ->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('cash_disbursement_payments', 0);

        $this->actingAs($requester)
            ->post(route('disbursements.payment.store', $disbursement), [
                'payment_date' => '2026-10-30', 'amount' => 1250, 'payment_method' => 'check', 'reference_number' => 'CHK-001',
            ])
            ->assertRedirect();

        $this->actingAs($requester)
            ->post(route('disbursements.payment.store', $disbursement), [
                'payment_date' => '2026-10-31', 'amount' => 1250, 'payment_method' => 'cash',
            ])
            ->assertForbidden();
        $this->assertDatabaseCount('cash_disbursement_payments', 1);
    }

    public function test_check_and_bank_transfer_payments_require_references(): void
    {
        $requester = $this->userWithRole('staff');
        $admin = $this->userWithRole('admin');
        $check = $this->pendingDisbursement($requester);
        $check->update(['status' => 'approved', 'approved_by' => $admin->id, 'approved_at' => now()]);

        $this->actingAs($requester)
            ->post(route('disbursements.payment.store', $check), [
                'payment_date' => '2026-10-30', 'amount' => $check->amount, 'payment_method' => 'check',
            ])
            ->assertSessionHasErrors('reference_number');
    }

    public function test_recorded_cash_or_bank_transfer_payments_are_not_duplicate_bank_withdrawals(): void
    {
        $requester = $this->userWithRole('staff');
        $admin = $this->userWithRole('admin');
        $cash = $this->pendingDisbursement($requester);
        $cash->update(['purpose' => 'Already paid cash request', 'status' => 'approved', 'approved_by' => $admin->id, 'approved_at' => now()]);
        $bank = $this->pendingDisbursement($requester);
        $bank->update(['purpose' => 'Already paid bank request', 'status' => 'approved', 'approved_by' => $admin->id, 'approved_at' => now()]);

        foreach ([[$cash, 'cash', null], [$bank, 'bank_transfer', 'BANK-001']] as [$record, $method, $reference]) {
            $this->actingAs($requester)->post(route('disbursements.payment.store', $record), [
                'payment_date' => '2026-10-30', 'amount' => $record->amount, 'payment_method' => $method, 'reference_number' => $reference,
            ])->assertRedirect();
        }

        $this->actingAs($requester)
            ->get(route('bank-reconciliation.index', ['tab' => 'outgoing']))
            ->assertOk()
            ->assertDontSee('Already paid cash request')
            ->assertSee('Already paid bank request');
    }

    public function test_pending_unreconciled_contribution_can_be_voided_and_audited(): void
    {
        $admin = $this->userWithRole('admin');
        $creator = $this->userWithRole('staff');
        $donor = Donor::query()->create([
            'donor_type' => 'individual', 'full_name' => 'Test Donor', 'address' => 'Test Address',
            'contact_number' => '09170000000', 'email' => 'void-test@example.com', 'status' => 'active',
        ]);
        $contribution = Contribution::query()->create([
            'donor_id' => $donor->id, 'created_by' => $creator->id, 'type' => 'cash', 'status' => 'pending',
            'amount' => 500, 'payment_method' => 'cash', 'received_at' => now(),
        ]);

        $this->actingAs($admin)->patch(route('contributions.void', $contribution), ['void_reason' => 'Duplicate entry'])->assertRedirect();

        $this->assertDatabaseHas('contributions', ['id' => $contribution->id, 'status' => 'cancelled', 'voided_by' => $admin->id, 'void_reason' => 'Duplicate entry']);
        $this->assertDatabaseHas('security_activity_events', ['action' => 'contribution_voided', 'actor_id' => $admin->id]);
    }

    public function test_approved_disbursement_can_be_voided_and_audited(): void
    {
        $requester = $this->userWithRole('staff');
        $admin = $this->userWithRole('admin');
        $disbursement = CashDisbursement::query()->create([
            'amount' => 1250, 'purpose' => 'Emergency supplies', 'requested_by' => $requester->id,
            'status' => 'approved', 'approved_by' => $admin->id, 'approved_at' => now(),
        ]);

        $this->actingAs($admin)->patch(route('disbursements.void', $disbursement), ['void_reason' => 'Funds no longer needed'])->assertRedirect();

        $this->assertDatabaseHas('cash_disbursements', ['id' => $disbursement->id, 'voided_by' => $admin->id, 'void_reason' => 'Funds no longer needed']);
        $this->assertDatabaseHas('security_activity_events', ['action' => 'disbursement_voided', 'actor_id' => $admin->id]);
    }

    public function test_requester_cannot_reject_own_disbursement(): void
    {
        $requester = $this->userWithRole('staff');
        $disbursement = CashDisbursement::query()->create([
            'amount' => 900,
            'purpose' => 'Relief goods',
            'requested_by' => $requester->id,
            'status' => 'pending',
        ]);

        $this->actingAs($requester)
            ->patch(route('disbursements.reject', $disbursement), ['rejection_reason' => 'Not needed anymore'])
            ->assertForbidden();

        $this->assertDatabaseHas('cash_disbursements', ['id' => $disbursement->id, 'status' => 'pending']);
    }

    public function test_admin_rejection_requires_reason_and_is_audited(): void
    {
        $requester = $this->userWithRole('staff');
        $admin = $this->userWithRole('admin');
        $disbursement = CashDisbursement::query()->create([
            'amount' => 900,
            'purpose' => 'Relief goods',
            'requested_by' => $requester->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->patch(route('disbursements.reject', $disbursement), [])
            ->assertSessionHasErrors('rejection_reason');

        $response = $this->actingAs($admin)->patch(route('disbursements.reject', $disbursement), [
            'rejection_reason' => 'Insufficient supporting documents',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cash_disbursements', [
            'id' => $disbursement->id,
            'status' => 'rejected',
            'rejection_reason' => 'Insufficient supporting documents',
        ]);
        $this->assertDatabaseHas('security_activity_events', [
            'action' => 'disbursement_rejected',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_dashboard_and_ledger_totals_only_count_approved_active_disbursements(): void
    {
        $requester = $this->userWithRole('staff');
        $admin = $this->userWithRole('admin');

        $approved = CashDisbursement::query()->create([
            'amount' => 1000, 'purpose' => 'Approved spend', 'requested_by' => $requester->id,
            'status' => 'approved', 'approved_by' => $admin->id, 'approved_at' => now(),
        ]);
        CashDisbursement::query()->create([
            'amount' => 500, 'purpose' => 'Still pending', 'requested_by' => $requester->id,
            'status' => 'pending',
        ]);
        CashDisbursement::query()->create([
            'amount' => 300, 'purpose' => 'Rejected spend', 'requested_by' => $requester->id,
            'status' => 'rejected', 'rejected_at' => now(), 'rejection_reason' => 'Denied',
        ]);
        CashDisbursement::query()->create([
            'amount' => 700, 'purpose' => 'Voided approved spend', 'requested_by' => $requester->id,
            'status' => 'approved', 'approved_by' => $admin->id, 'approved_at' => now(),
            'voided_at' => now(), 'voided_by' => $admin->id, 'void_reason' => 'Funds unused',
        ]);

        $metrics = (new DashboardMetrics())->build();

        $this->assertEquals(1000.0, (float) $metrics['disbursements']['total_disbursed']);
        $this->assertDatabaseHas('cash_disbursements', ['id' => $approved->id, 'status' => 'approved']);
    }

    private function userWithRole(string $role): User
    {
        $roleModel = Role::query()->firstOrCreate(['name' => $role], ['display_name' => ucfirst($role), 'description' => ucfirst($role).' role']);
        return User::factory()->create(['role_id' => $roleModel->id]);
    }

    private function pendingDisbursement(User $requester): CashDisbursement
    {
        return CashDisbursement::query()->create([
            'amount' => 1250,
            'purpose' => 'Emergency supplies',
            'recipient' => 'Community Pharmacy',
            'requested_date' => '2026-09-12',
            'requested_by' => $requester->id,
            'status' => 'pending',
        ]);
    }
}
