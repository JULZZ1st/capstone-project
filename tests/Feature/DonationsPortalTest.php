<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\ContributionEvidence;
use App\Models\ContributionItem;
use App\Models\Donor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DonationsPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_log_a_cash_contribution(): void
    {
        $donor = $this->donor();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('donations.store'), [
            'donation_type' => 'cash',
            'donor_id' => $donor->id,
            'amount' => 750,
            'payment_method' => 'cash',
            ...$this->cashDocuments(750),
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect(route('donations.index'));
        $this->assertDatabaseHas('contributions', [
            'donor_id' => $donor->id,
            'type' => 'cash',
            'status' => config('donations.default_status.cash'),
            'amount' => 750,
            'payment_method' => 'cash',
            'created_by' => $user->id,
        ]);
    }

    public function test_staff_can_log_a_bank_transfer_contribution_for_an_active_workspace_donor(): void
    {
        $donor = $this->donor();
        $staff = User::factory()->create();

        $response = $this->actingAs($staff)->post(route('donations.store'), [
            'donation_type' => 'cash',
            'donor_id' => $donor->id,
            'amount' => 75000,
            'payment_method' => 'bank_transfer',
            'registered_bank_account' => 'LANDBANK · ending 1234',
            'reference_number' => 'TRF-26001',
            ...$this->cashDocuments(75000),
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect(route('donations.index'));
        $this->assertDatabaseHas('contributions', [
            'donor_id' => $donor->id,
            'amount' => 75000,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'TRF-26001',
            'created_by' => $staff->id,
        ]);
    }

    public function test_staff_can_log_a_check_contribution_with_check_details_and_official_receipt(): void
    {
        $donor = $this->donor();
        $staff = User::factory()->create();

        $response = $this->actingAs($staff)->post(route('donations.store'), [
            'donation_type' => 'cash',
            'donor_id' => $donor->id,
            'amount' => 12500,
            'payment_method' => 'check',
            ...$this->cashDocuments(12500),
            'check_number' => 'CHK-1001',
            'bank_name' => 'LANDBANK',
            'official_receipt_number' => 'OR-2026-1001',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('contributions', [
            'payment_method' => 'check',
            'check_number' => 'CHK-1001',
            'bank_name' => 'LANDBANK',
            'official_receipt_number' => 'OR-2026-1001',
            'reference_number' => null,
        ]);
    }

    public function test_inactive_donor_is_rejected_before_contribution_is_saved(): void
    {
        $donor = $this->donor(['status' => 'inactive']);

        $response = $this->actingAs(User::factory()->create())->post(route('donations.store'), [
            'donation_type' => 'cash',
            'donor_id' => $donor->id,
            'amount' => 75000,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'TRF-INACTIVE-001',
        ]);

        $response->assertSessionHasErrors('donor_id');
        $this->assertDatabaseMissing('contributions', ['reference_number' => 'TRF-INACTIVE-001']);
    }

    public function test_cash_contribution_does_not_require_items(): void
    {
        $donor = $this->donor();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('donations.store'), [
            'donation_type' => 'cash',
            'donor_id' => $donor->id,
            'amount' => 100,
            'payment_method' => 'gcash',
            'reference_number' => 'GC-1001',
            ...$this->cashDocuments(100),
            // No "items" key sent at all, mirroring a genuine cash submission.
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect(route('donations.index'));
        $this->assertSame(0, ContributionItem::query()->count());
    }

    public function test_cash_contribution_does_not_store_an_external_reference(): void
    {
        $contribution = $this->actingAs(User::factory()->create())
            ->post(route('donations.store'), [
                'donation_type' => 'cash',
                'donor_id' => $this->donor()->id,
                'amount' => 100,
                'payment_method' => 'cash',
                'reference_number' => 'SHOULD-NOT-BE-STORED',
                ...$this->cashDocuments(100),
            ]);

        $contribution->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('contributions', [
            'payment_method' => 'cash',
            'reference_number' => null,
        ]);
    }

    public function test_contribution_evidence_is_validated_stored_and_available_from_details(): void
    {
        Storage::fake('local');
        $donor = $this->donor();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('donations.store'), [
            'donation_type' => 'cash',
            'donor_id' => $donor->id,
            'amount' => 750,
            'payment_method' => 'cash',
            'evidence' => [UploadedFile::fake()->create('receipt.pdf', 200, 'application/pdf')],
            ...$this->cashDocuments(750),
        ]);

        $response->assertSessionDoesntHaveErrors();
        $contribution = Contribution::query()->firstOrFail();
        $evidence = ContributionEvidence::query()->where('contribution_id', $contribution->id)->firstOrFail();

        Storage::disk('local')->assertExists($evidence->path);
        $this->assertSame('receipt.pdf', $evidence->original_name);
        $this->actingAs($user)->get(route('donations.show', $contribution))->assertSee('receipt.pdf');
        $this->actingAs($user)->get(route('donations.evidence', [$contribution, $evidence]))
            ->assertOk()
            ->assertHeader('Content-Disposition');
    }

    public function test_contribution_evidence_rejects_unsupported_or_oversized_files(): void
    {
        Storage::fake('local');
        $payload = [
            'donation_type' => 'cash',
            'donor_id' => $this->donor()->id,
            'amount' => 750,
            'payment_method' => 'cash',
            'evidence' => [UploadedFile::fake()->create('receipt.exe', 11, 'application/octet-stream')],
        ];

        $this->actingAs(User::factory()->create())
            ->post(route('donations.store'), $payload)
            ->assertSessionHasErrors('evidence.0');

        $this->assertDatabaseCount('contributions', 0);
        $this->assertDatabaseCount('contribution_evidence', 0);
    }

    public function test_non_cash_payment_methods_require_external_references(): void
    {
        foreach (['gcash', 'maya', 'bank_transfer', 'check'] as $paymentMethod) {
            $response = $this->actingAs(User::factory()->create())->post(route('donations.store'), [
                'donation_type' => 'cash',
                'donor_id' => $this->donor()->id,
                'amount' => 100,
                'payment_method' => $paymentMethod,
            ]);

            $response->assertSessionHasErrors('reference_number');
        }
    }

    public function test_other_payment_method_requires_a_name_but_not_an_external_reference(): void
    {
        $donor = $this->donor();
        $user = User::factory()->create();

        $missingName = $this->actingAs($user)->post(route('donations.store'), [
            'donation_type' => 'cash',
            'donor_id' => $donor->id,
            'amount' => 500,
            'payment_method' => 'other',
        ]);

        $missingName->assertSessionHasErrors('payment_method_name');
        $this->assertDatabaseCount('contributions', 0);

        $response = $this->actingAs($user)->post(route('donations.store'), [
            'donation_type' => 'cash',
            'donor_id' => $donor->id,
            'amount' => 500,
            'payment_method' => 'other',
            'payment_method_name' => 'PayMaya QR',
            ...$this->cashDocuments(500),
        ]);

        $response->assertSessionDoesntHaveErrors();
        $contribution = Contribution::query()->firstOrFail();

        $this->assertSame('PayMaya QR', $contribution->payment_method_name);
        $this->assertNull($contribution->reference_number);
        $this->get(route('donations.index'))->assertSee('PayMaya QR');
        $this->get(route('donations.show', $contribution))->assertSee('PayMaya QR');
    }

    public function test_money_order_is_not_a_new_payment_method_but_historical_rows_remain_visible(): void
    {
        $donor = $this->donor();
        $user = User::factory()->create();
        $historical = Contribution::query()->create([
            'donor_id' => $donor->id,
            'created_by' => $user->id,
            'type' => 'cash',
            'status' => 'verified',
            'amount' => 500,
            'payment_method' => 'money_order',
            'reference_number' => 'MO-1001',
        ]);

        $this->actingAs($user)->get(route('donations.index'))
            ->assertSee('Money Order')
            ->assertDontSee('value="money_order"', false)
            ->assertDontSee('value="gcash"', false)
            ->assertDontSee('value="maya"', false);
        $this->actingAs($user)->post(route('donations.store'), [
            'donation_type' => 'cash',
            'donor_id' => $donor->id,
            'amount' => 500,
            'payment_method' => 'money_order',
            'reference_number' => 'MO-1002',
        ])->assertSessionHasErrors('payment_method');

        $this->assertDatabaseHas('contributions', ['id' => $historical->id, 'payment_method' => 'money_order']);
    }

    public function test_in_kind_contribution_still_requires_at_least_one_item(): void
    {
        $donor = $this->donor();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('donations.store'), [
            'donation_type' => 'in_kind',
            'donor_id' => $donor->id,
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseCount('contributions', 0);
    }

    public function test_in_kind_contribution_can_still_be_logged_with_items(): void
    {
        $donor = $this->donor();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('donations.store'), [
            'donation_type' => 'in_kind',
            'donor_id' => $donor->id,
            'items' => [
                ['name' => 'Rice', 'category' => 'food_relief', 'quantity' => 10, 'unit' => 'sack', 'condition' => 'new'],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect(route('donations.index'));
        $this->assertDatabaseHas('contributions', [
            'donor_id' => $donor->id,
            'type' => 'in_kind',
        ]);
        $this->assertDatabaseHas('contribution_items', [
            'name' => 'Rice',
            'category' => 'food_relief',
            'subcategory' => null,
        ]);
    }

    public function test_in_kind_contribution_documents_are_stored_with_labeled_document_types(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $donor = $this->donor();

        $response = $this->actingAs($user)->post(route('donations.store'), [
            'donation_type' => 'in_kind',
            'donor_id' => $donor->id,
            'items' => [
                ['name' => 'Rice', 'category' => 'food_relief', 'quantity' => 10, 'unit' => 'sack', 'condition' => 'new'],
            ],
            'donation_item_list' => UploadedFile::fake()->create('item-list.pdf', 20, 'application/pdf'),
            'deed_of_donation_acceptance' => UploadedFile::fake()->create('deed.pdf', 20, 'application/pdf'),
            'par_ics_acknowledgement' => UploadedFile::fake()->create('par-ics.pdf', 20, 'application/pdf'),
        ]);

        $response->assertSessionDoesntHaveErrors();
        $donation = Contribution::query()->latest('id')->firstOrFail();

        $this->assertDatabaseHas('contribution_evidence', ['contribution_id' => $donation->id, 'document_type' => 'donation_item_list']);
        $this->assertDatabaseHas('contribution_evidence', ['contribution_id' => $donation->id, 'document_type' => 'deed_of_donation_acceptance']);
        $this->assertDatabaseHas('contribution_evidence', ['contribution_id' => $donation->id, 'document_type' => 'par_ics_acknowledgement']);

        foreach ($donation->evidence as $evidence) {
            Storage::disk('local')->assertExists($evidence->path);
        }
    }

    public function test_in_kind_certificate_request_requires_admin_issuance(): void
    {
        Storage::fake('local');
        $staff = User::factory()->create();
        $donor = $this->donor();

        $this->actingAs($staff)->post(route('donations.store'), [
            'donation_type' => 'in_kind',
            'donor_id' => $donor->id,
            'items' => [
                ['name' => 'Rice', 'category' => 'food_relief', 'quantity' => 10, 'unit' => 'sack', 'condition' => 'new'],
            ],
            'certificate_requested' => '1',
        ])->assertRedirect();

        $donation = Contribution::query()->latest('id')->firstOrFail();
        $this->assertTrue($donation->certificate_requested);
        $this->assertSame('pending', $donation->certificate_status);

        $this->actingAs($staff)
            ->patch(route('donations.certificate.issue', $donation), [
                'certificate_of_donation' => UploadedFile::fake()->create('certificate.pdf', 20, 'application/pdf'),
            ])
            ->assertForbidden();

        $admin = User::factory()->create(['role_id' => Role::query()->where('name', 'admin')->firstOrFail()->id]);
        $this->actingAs($admin)
            ->patch(route('donations.certificate.issue', $donation), [
                'certificate_of_donation' => UploadedFile::fake()->create('certificate.pdf', 20, 'application/pdf'),
            ])
            ->assertRedirect();

        $donation->refresh();
        $this->assertSame('issued', $donation->certificate_status);
        $this->assertSame($admin->id, $donation->certificate_issued_by);
        $this->assertDatabaseHas('contribution_evidence', ['contribution_id' => $donation->id, 'document_type' => 'certificate_of_donation']);
    }

    public function test_in_kind_item_with_others_category_requires_and_stores_description(): void
    {
        $donor = $this->donor();

        $missingDescription = $this->actingAs(User::factory()->create())->post(route('donations.store'), [
            'donation_type' => 'in_kind',
            'donor_id' => $donor->id,
            'items' => [
                ['name' => 'Mystery Item', 'category' => 'others', 'quantity' => 1, 'unit' => 'piece', 'condition' => 'new'],
            ],
        ]);

        $missingDescription->assertSessionHasErrors('items.0.category_description');

        $response = $this->actingAs(User::factory()->create())->post(route('donations.store'), [
            'donation_type' => 'in_kind',
            'donor_id' => $donor->id,
            'items' => [
                ['name' => 'Mystery Item', 'category' => 'others', 'category_description' => 'Assorted hardware parts', 'quantity' => 1, 'unit' => 'piece', 'condition' => 'new'],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('contribution_items', [
            'name' => 'Mystery Item',
            'category' => 'others',
            'category_description' => 'Assorted hardware parts',
        ]);
    }

    public function test_in_kind_expiration_date_is_accepted_for_perishable_categories(): void
    {
        $donor = $this->donor();

        $response = $this->actingAs(User::factory()->create())->post(route('donations.store'), [
            'donation_type' => 'in_kind',
            'donor_id' => $donor->id,
            'items' => [
                ['name' => 'Canned Goods', 'category' => 'food_relief', 'quantity' => 20, 'unit' => 'can', 'condition' => 'new', 'expiration_date' => '2027-06-30'],
                ['name' => 'Paracetamol', 'category' => 'medical_health', 'quantity' => 5, 'unit' => 'box', 'condition' => 'new', 'expiration_date' => '2026-12-31'],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame('2027-06-30', ContributionItem::where('name', 'Canned Goods')->firstOrFail()->expiration_date->format('Y-m-d'));
        $this->assertSame('2026-12-31', ContributionItem::where('name', 'Paracetamol')->firstOrFail()->expiration_date->format('Y-m-d'));
    }

    public function test_in_kind_brand_model_and_serial_number_are_accepted_for_equipment_categories(): void
    {
        $donor = $this->donor();

        $response = $this->actingAs(User::factory()->create())->post(route('donations.store'), [
            'donation_type' => 'in_kind',
            'donor_id' => $donor->id,
            'items' => [
                [
                    'name' => 'Laptop',
                    'category' => 'it_office_equipment',
                    'quantity' => 1,
                    'unit' => 'piece',
                    'condition' => 'new',
                    'brand' => 'Acme',
                    'model' => 'X200',
                    'serial_number' => 'SN-00123',
                ],
                [
                    'name' => 'Office Chairs',
                    'category' => 'furniture_fixtures',
                    'quantity' => 4,
                    'unit' => 'piece',
                    'condition' => 'good',
                    'brand' => 'ChairCo',
                    'model' => 'Ergo-9',
                ],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('contribution_items', [
            'name' => 'Laptop',
            'brand' => 'Acme',
            'model' => 'X200',
            'serial_number' => 'SN-00123',
        ]);
        $this->assertDatabaseHas('contribution_items', [
            'name' => 'Office Chairs',
            'brand' => 'ChairCo',
            'model' => 'Ergo-9',
            'serial_number' => null,
        ]);
    }

    public function test_validation_error_restores_selected_category_state(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->from(route('donations.index'))
            ->post(route('donations.store'), [
                'donation_type' => 'in_kind',
                'donor_id' => $this->donor()->id,
                'items' => [
                    ['name' => 'Rice', 'category' => 'food_relief', 'quantity' => null, 'unit' => 'sack', 'condition' => 'new'],
                ],
            ]);

        $response->assertSessionHasErrors('items.0.quantity');
        $response->assertSessionHasInput('items.0.category', 'food_relief');

        $this->followingRedirects()
            ->actingAs(User::factory()->create())
            ->get(route('donations.index'))
            ->assertSee('"category":"food_relief"', false);
    }

    public function test_existing_records_without_subcategory_remain_valid_on_detail_page(): void
    {
        $user = User::factory()->create();
        $contribution = Contribution::query()->create([
            'donor_id' => $this->donor()->id,
            'created_by' => $user->id,
            'type' => 'in_kind',
            'status' => config('donations.default_status.in_kind'),
            'received_at' => now(),
        ]);

        $contribution->items()->create([
            'name' => 'Legacy Rice',
            'category' => 'food',
            'quantity' => 1,
            'unit' => 'sack',
            'condition' => 'good',
        ]);

        $this->actingAs($user)
            ->get(route('donations.show', $contribution))
            ->assertOk()
            ->assertSee('Legacy Rice')
            ->assertSee('Food');
    }


    public function test_contribution_receipt_numbers_are_generated_from_persisted_ids(): void
    {
        Carbon::setTestNow('2026-09-05 10:15:00');

        $donor = $this->donor();
        $user = User::factory()->create();

        try {
            $this->actingAs($user)->post(route('donations.store'), [
                'donation_type' => 'cash',
                'donor_id' => $donor->id,
                'amount' => 100,
                'payment_method' => 'cash',
                ...$this->cashDocuments(100),
            ])->assertSessionDoesntHaveErrors();

            $this->actingAs($user)->post(route('donations.store'), [
                'donation_type' => 'cash',
                'donor_id' => $donor->id,
                'amount' => 200,
                'payment_method' => 'cash',
                ...$this->cashDocuments(200),
            ])->assertSessionDoesntHaveErrors();
        } finally {
            Carbon::setTestNow();
        }

        $contributions = Contribution::query()->orderBy('id')->get();

        $this->assertCount(2, $contributions);
        $this->assertSame(
            sprintf('DON-20260905-%06d', $contributions[0]->id),
            $contributions[0]->receipt_number
        );
        $this->assertSame(
            sprintf('DON-20260905-%06d', $contributions[1]->id),
            $contributions[1]->receipt_number
        );
        $this->assertNotSame($contributions[0]->receipt_number, $contributions[1]->receipt_number);
    }

    public function test_electronic_payment_requires_external_reference_number(): void
    {
        $response = $this->actingAs(User::factory()->create())->post(route('donations.store'), [
            'donation_type' => 'cash',
            'donor_id' => $this->donor()->id,
            'amount' => 500,
            'payment_method' => 'gcash',
        ]);

        $response->assertSessionHasErrors('reference_number');
        $this->assertDatabaseCount('contributions', 0);
    }

    public function test_external_reference_number_remains_unique_per_payment_method(): void
    {
        $donor = $this->donor();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('donations.store'), [
            'donation_type' => 'cash',
            'donor_id' => $donor->id,
            'amount' => 500,
            'payment_method' => 'gcash',
            'reference_number' => 'GCASH-1001',
            ...$this->cashDocuments(500),
        ])->assertSessionDoesntHaveErrors();

        $response = $this->actingAs($user)->post(route('donations.store'), [
            'donation_type' => 'cash',
            'donor_id' => $donor->id,
            'amount' => 600,
            'payment_method' => 'gcash',
            'reference_number' => 'GCASH-1001',
            ...$this->cashDocuments(600),
        ]);

        $response->assertSessionHasErrors('reference_number');
        $this->assertDatabaseCount('contributions', 1);
    }

    public function test_cash_documents_are_stored_and_certificate_request_requires_admin_issuance(): void
    {
        Storage::fake('local');
        $staff = User::factory()->create();
        $donor = $this->donor();

        $this->actingAs($staff)->post(route('donations.store'), [
            'donation_type' => 'cash',
            'donor_id' => $donor->id,
            'amount' => 1250,
            'payment_method' => 'cash',
            'official_receipt_number' => 'OR-2026-77',
            'official_receipt_document' => UploadedFile::fake()->create('official-receipt.pdf', 20, 'application/pdf'),
            'deed_of_donation' => UploadedFile::fake()->create('deed.pdf', 20, 'application/pdf'),
            'certificate_of_acceptance' => UploadedFile::fake()->create('acceptance.pdf', 20, 'application/pdf'),
            'certificate_requested' => '1',
        ])->assertRedirect();

        $donation = Contribution::query()->latest('id')->firstOrFail();
        $this->assertTrue($donation->certificate_requested);
        $this->assertSame('pending', $donation->certificate_status);
        $this->assertDatabaseHas('contribution_evidence', ['contribution_id' => $donation->id, 'document_type' => 'official_receipt']);
        $this->assertDatabaseHas('contribution_evidence', ['contribution_id' => $donation->id, 'document_type' => 'deed_of_donation']);
        $this->assertDatabaseHas('contribution_evidence', ['contribution_id' => $donation->id, 'document_type' => 'certificate_of_acceptance']);

        $this->actingAs($staff)
            ->patch(route('donations.certificate.issue', $donation), [
                'certificate_of_donation' => UploadedFile::fake()->create('certificate.pdf', 20, 'application/pdf'),
            ])
            ->assertForbidden();

        $admin = User::factory()->create(['role_id' => Role::query()->where('name', 'admin')->firstOrFail()->id]);
        $this->actingAs($admin)
            ->patch(route('donations.certificate.issue', $donation), [
                'certificate_of_donation' => UploadedFile::fake()->create('certificate.pdf', 20, 'application/pdf'),
            ])
            ->assertRedirect();

        $donation->refresh();
        $this->assertSame('issued', $donation->certificate_status);
        $this->assertSame($admin->id, $donation->certificate_issued_by);
        $this->assertDatabaseHas('contribution_evidence', ['contribution_id' => $donation->id, 'document_type' => 'certificate_of_donation']);
    }

    public function test_admin_can_verify_pending_donation_for_reconciliation(): void
    {
        $admin = User::factory()->create();
        $admin->update(['role_id' => Role::query()->where('name', 'admin')->firstOrFail()->id]);
        $donation = Contribution::query()->create([
            'donor_id' => $this->donor()->id,
            'created_by' => User::factory()->create()->id,
            'type' => 'cash',
            'status' => 'pending',
            'amount' => 1250,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'BANK-VERIFY-001',
            'official_receipt_number' => 'OR-BANK-VERIFY-001',
            'received_at' => now(),
        ]);
        $donation->evidence()->create([
            'document_type' => 'bank_transfer_proof',
            'disk' => 'local',
            'path' => 'test/bank-proof.pdf',
            'original_name' => 'bank-proof.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'uploaded_by' => $donation->created_by,
        ]);
        foreach ([
            ['document_type' => 'official_receipt', 'original_name' => 'official-receipt.pdf'],
            ['document_type' => 'certificate_of_acceptance', 'original_name' => 'acceptance.pdf'],
        ] as $document) {
            $donation->evidence()->create([
                ...$document,
                'disk' => 'local',
                'path' => 'test/'.$document['original_name'],
                'mime_type' => 'application/pdf',
                'size' => 100,
                'uploaded_by' => $donation->created_by,
            ]);
        }

        $this->actingAs($admin)
            ->patch(route('donations.verify', $donation))
            ->assertRedirect();

        $donation->refresh();
        $this->assertSame('verified', $donation->status);
        $this->assertSame($admin->id, $donation->verified_by);
        $this->assertNotNull($donation->verified_at);
        $this->assertTrue(Contribution::query()->whereKey($donation)->eligibleForReconciliation()->exists());
    }

    public function test_bank_transfer_cannot_be_verified_without_bank_proof(): void
    {
        $admin = User::factory()->create(['role_id' => Role::query()->where('name', 'admin')->firstOrFail()->id]);
        $donation = Contribution::query()->create([
            'donor_id' => $this->donor()->id,
            'created_by' => User::factory()->create()->id,
            'type' => 'cash',
            'status' => 'pending',
            'amount' => 1250,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'BANK-NO-PROOF-001',
            'received_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('donations.verify', $donation))
            ->assertSessionHasErrors('bank_transfer_proof');

        $this->assertDatabaseHas('contributions', ['id' => $donation->id, 'status' => 'pending']);
    }

    public function test_staff_cannot_verify_or_reject_a_pending_donation(): void
    {
        $staff = User::factory()->create();
        $donation = Contribution::query()->create([
            'donor_id' => $this->donor()->id,
            'created_by' => $staff->id,
            'type' => 'cash',
            'status' => 'pending',
            'amount' => 1250,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'BANK-STAFF-001',
            'received_at' => now(),
        ]);

        $this->actingAs($staff)
            ->patch(route('donations.verify', $donation))
            ->assertForbidden();

        $this->actingAs($staff)
            ->patch(route('donations.update', $donation), ['status' => 'rejected'])
            ->assertForbidden();

        $this->assertDatabaseHas('contributions', ['id' => $donation->id, 'status' => 'pending']);
    }

    public function test_admin_can_reject_pending_donation_and_it_is_not_reconcilable(): void
    {
        $admin = User::factory()->create();
        $admin->update(['role_id' => Role::query()->where('name', 'admin')->firstOrFail()->id]);
        $donation = Contribution::query()->create([
            'donor_id' => $this->donor()->id,
            'created_by' => User::factory()->create()->id,
            'type' => 'cash',
            'status' => 'pending',
            'amount' => 1250,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'BANK-REJECT-001',
            'received_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('donations.update', $donation), ['status' => 'rejected'])
            ->assertRedirect(route('donations.show', $donation));

        $this->assertDatabaseHas('contributions', ['id' => $donation->id, 'status' => 'rejected']);
        $this->assertFalse(Contribution::query()->whereKey($donation)->eligibleForReconciliation()->exists());
    }

    private function donor(array $overrides = []): Donor
    {
        return Donor::query()->create(array_merge([
            'donor_type' => 'individual',
            'full_name' => 'Maria Santos',
            'address' => 'Brgy. San Isidro',
            'contact_number' => '09171234567',
            'email' => 'maria@example.com',
            'status' => config('donors.defaults.status'),
        ], $overrides));
    }

    private function cashDocuments(int $amount): array
    {
        static $sequence = 0;

        $documents = [
            'official_receipt_number' => 'TEST-OR-'.(++$sequence),
            'official_receipt_document' => UploadedFile::fake()->create('official-receipt.pdf', 20, 'application/pdf'),
            'certificate_of_acceptance' => UploadedFile::fake()->create('acceptance.pdf', 20, 'application/pdf'),
        ];

        if ($amount >= 5000) {
            $documents['deed_of_donation'] = UploadedFile::fake()->create('deed.pdf', 20, 'application/pdf');
        }

        return $documents;
    }
}
