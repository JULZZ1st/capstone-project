<?php

namespace Tests\Feature;

use App\Models\Donor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CashDonationDocumentValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_below_five_thousand_only_requires_official_receipt_documents(): void
    {
        Storage::fake('local');

        $response = $this->actingAs(User::factory()->create())
            ->post(route('donations.store'), $this->cashPayload(4999.99));

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('contributions', 1);
    }

    public function test_cash_at_exactly_five_thousand_requires_both_additional_documents(): void
    {
        $payload = $this->cashPayload(5000);
        unset($payload['certificate_of_acceptance']);

        $response = $this->actingAs(User::factory()->create())
            ->post(route('donations.store'), $payload);

        $response->assertSessionHasErrors('deed_of_donation');
        $this->assertDatabaseCount('contributions', 0);
    }

    public function test_cash_at_five_thousand_saves_when_both_additional_documents_are_uploaded(): void
    {
        Storage::fake('local');

        $payload = $this->cashPayload(5000);
        $payload['deed_of_donation'] = UploadedFile::fake()->create('deed-of-donation.pdf', 20, 'application/pdf');
        $payload['certificate_of_acceptance'] = UploadedFile::fake()->create('certificate-of-acceptance.pdf', 20, 'application/pdf');

        $response = $this->actingAs(User::factory()->create())
            ->post(route('donations.store'), $payload);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('contributions', 1);
    }

    public function test_certificate_request_below_threshold_does_not_make_deed_mandatory(): void
    {
        $payload = $this->cashPayload(4999.99);
        $payload['certificate_requested'] = '1';

        $response = $this->actingAs(User::factory()->create())
            ->post(route('donations.store'), $payload);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('contributions', 1);
    }

    public function test_deed_exception_saves_as_pending_with_staff_reason(): void
    {
        $payload = $this->cashPayload(5000);
        $payload['deed_declined'] = '1';
        $payload['deed_decline_reason'] = 'Donor declined after the Deed was explained.';

        $response = $this->actingAs(User::factory()->create())
            ->post(route('donations.store'), $payload);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('contributions', [
            'document_review_status' => 'missing_deed_admin_review',
            'deed_declined' => true,
            'deed_decline_reason' => $payload['deed_decline_reason'],
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('security_activity_events', ['action' => 'cash_donation_deed_exception_recorded']);
    }

    public function test_deed_exception_requires_a_reason(): void
    {
        $payload = $this->cashPayload(5000);
        $payload['deed_declined'] = '1';

        $response = $this->actingAs(User::factory()->create())
            ->post(route('donations.store'), $payload);

        $response->assertSessionHasErrors('deed_decline_reason');
        $this->assertDatabaseCount('contributions', 0);
    }

    public function test_admin_cannot_verify_a_deed_exception_without_the_deed(): void
    {
        $payload = $this->cashPayload(5000);
        $payload['deed_declined'] = '1';
        $payload['deed_decline_reason'] = 'Donor declined in writing.';
        $this->actingAs(User::factory()->create())->post(route('donations.store'), $payload)->assertRedirect();
        $donation = \App\Models\Contribution::query()->firstOrFail();
        $admin = User::factory()->create(['role_id' => Role::query()->where('name', 'admin')->value('id')]);

        $this->actingAs($admin)
            ->patch(route('donations.verify', $donation))
            ->assertSessionHasErrors('deed_of_donation');

        $this->assertDatabaseHas('contributions', ['id' => $donation->id, 'status' => 'pending']);
    }

    public function test_admin_can_request_missing_documents_and_the_action_is_audited(): void
    {
        $payload = $this->cashPayload(1000);
        $this->actingAs(User::factory()->create())->post(route('donations.store'), $payload)->assertRedirect();
        $donation = \App\Models\Contribution::query()->firstOrFail();
        $admin = User::factory()->create(['role_id' => Role::query()->where('name', 'admin')->value('id')]);

        $this->actingAs($admin)
            ->patch(route('donations.request-documents', $donation), [
                'document_request_reason' => 'Please provide the signed Deed for the review file.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('contributions', [
            'id' => $donation->id,
            'document_request_reason' => 'Please provide the signed Deed for the review file.',
            'documents_requested_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('security_activity_events', ['action' => 'cash_donation_documents_requested']);
    }

    public function test_cash_always_requires_official_receipt_number_and_upload(): void
    {
        $payload = $this->cashPayload(1000);
        unset($payload['official_receipt_number'], $payload['official_receipt_document']);

        $response = $this->actingAs(User::factory()->create())
            ->post(route('donations.store'), $payload);

        $response->assertSessionHasErrors([
            'official_receipt_number',
            'official_receipt_document',
        ]);
        $this->assertDatabaseCount('contributions', 0);
    }

    public function test_cash_always_requires_certificate_of_acceptance(): void
    {
        $payload = $this->cashPayload(1000);
        unset($payload['certificate_of_acceptance']);

        $response = $this->actingAs(User::factory()->create())
            ->post(route('donations.store'), $payload);

        $response->assertSessionHasErrors('certificate_of_acceptance');
        $this->assertDatabaseCount('contributions', 0);
    }

    private function cashPayload(float $amount): array
    {
        return [
            'donation_type' => 'cash',
            'donor_id' => Donor::query()->create([
                'donor_type' => 'individual',
                'full_name' => 'Validation Donor',
                'address' => 'Barangay Hall',
                'contact_number' => '09170000000',
                'email' => 'validation@example.com',
                'status' => config('donors.defaults.status'),
            ])->id,
            'amount' => $amount,
            'payment_method' => 'cash',
            'official_receipt_number' => 'OR-VALIDATION-001',
            'official_receipt_document' => UploadedFile::fake()->create('official-receipt.pdf', 20, 'application/pdf'),
            'certificate_of_acceptance' => UploadedFile::fake()->create('certificate-of-acceptance.pdf', 20, 'application/pdf'),
        ];
    }
}
