<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\CashDisbursement;
use App\Models\Donor;
use App\Models\InventoryCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class UiWorkflowRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_verification_returns_to_the_donation_with_actionable_errors(): void
    {
        $donation = $this->donation();
        $response = $this->actingAs($this->user('admin'))->from(route('donations.index'))
            ->patch(route('donations.verify', $donation));

        $response->assertRedirect(route('donations.show', $donation))
            ->assertSessionHasErrors(['official_receipt_document', 'certificate_of_acceptance', 'deed_of_donation']);
        $page = $this->get(route('donations.show', $donation))->assertOk()
            ->assertSee('Official Receipt upload is required before verification.')
            ->assertSee('Certificate of Acceptance is required before verification.')
            ->assertDontSee('id="log-contribution-dialog"', false);
        $xpath = $this->xpath($page->getContent());
        $this->assertSame(1, $xpath->query('//form[@data-donation-verification]//button[@disabled]')->length);
        $this->assertSame('pending', $donation->fresh()->status);
        $this->assertDatabaseMissing('security_activity_events', ['action' => 'cash_donation_verified']);
    }

    public function test_unrelated_validation_does_not_open_add_donation_or_overwrite_records(): void
    {
        $donation = $this->donation();
        $this->validationSession(['_modal' => 'update-contribution-'.$donation->id, 'notes' => 'Invalid update'], ['status' => 'Invalid status']);
        $xpath = $this->xpath($this->actingAs($this->user('admin'))->get(route('donations.index'))->assertOk()->getContent());
        $this->assertSame(0, $xpath->query('//dialog[@id="log-contribution-dialog"][@open]')->length);
        $this->assertUniqueIds($xpath);
    }

    public function test_verification_rechecks_documents_after_request_validation(): void
    {
        $donation = $this->donation();
        foreach (['official_receipt', 'certificate_of_acceptance', 'deed_of_donation'] as $type) {
            $donation->evidence()->create(['document_type' => $type, 'disk' => 'local', 'path' => $type.'.pdf',
                'original_name' => $type.'.pdf', 'mime_type' => 'application/pdf', 'size' => 100]);
        }
        $checks = 0;
        Gate::after(function (User $user, string $ability) use (&$checks, $donation): void {
            if ($ability === 'verify' && ++$checks === 2) {
                $donation->evidence()->where('document_type', 'official_receipt')->delete();
            }
        });

        $this->actingAs($this->user('admin'))->patch(route('donations.verify', $donation))
            ->assertRedirect(route('donations.show', $donation))
            ->assertSessionHasErrors('official_receipt_document');
        $this->assertSame('pending', $donation->fresh()->status);
        $this->assertNull($donation->fresh()->verified_at);
    }

    public function test_donation_creation_validation_reopens_its_own_form(): void
    {
        $this->validationSession(['_modal' => 'log-contribution', 'donation_type' => 'cash', 'amount' => '100'], ['donor_id' => 'Select a donor']);
        $xpath = $this->xpath($this->actingAs($this->user('staff'))->get(route('donations.index'))->assertOk()->getContent());
        $this->assertSame(1, $xpath->query('//dialog[@id="log-contribution-dialog"][@open]')->length);
    }

    public function test_donor_directory_actions_resolve_to_authorized_dialogs(): void
    {
        $donation = $this->donation();
        foreach (['admin', 'staff'] as $role) {
            $xpath = $this->xpath($this->actingAs($this->user($role))->get(route('donations.index'))->assertOk()->getContent());
            foreach ($xpath->query('//*[@data-dialog-open]') as $button) {
                $id = $button->getAttribute('data-dialog-open');
                $this->assertSame(1, $xpath->query('//dialog[@id="'.$id.'"]')->length, 'Missing dialog '.$id);
            }
            $this->assertSame(1, $xpath->query('//dialog[@id="view-donor-modal-'.$donation->donor_id.'"]')->length);
        }
    }

    public function test_staff_update_forms_only_offer_permitted_statuses(): void
    {
        $staff = $this->user('staff');
        $donation = $this->donation(['created_by' => $staff->id]);
        foreach ([route('donations.index'), route('donations.show', $donation)] as $url) {
            $xpath = $this->xpath($this->actingAs($staff)->get($url)->assertOk()->getContent());
            $this->assertSame(0, $xpath->query('//form[@method="POST"]//select[@name="status"]/option[@value="rejected" or @value="verified"]')->length);
        }
    }

    public function test_bank_matched_update_form_locks_status_but_keeps_notes_editable(): void
    {
        $donation = $this->donation(['status' => 'verified']);
        $donation->ledgerEntry()->create(['statement_id' => 'UI-STATEMENT', 'description' => 'Matched deposit',
            'amount' => $donation->amount, 'transaction_date' => now(), 'transaction_type' => 'deposit', 'status' => 'reconciled']);
        $xpath = $this->xpath($this->actingAs($this->user('admin'))->get(route('donations.index'))->assertOk()->getContent());
        $this->assertSame(1, $xpath->query('//dialog[@id="update-contribution-'.$donation->id.'"]//select[@name="status"]/option')->length);
        $this->assertSame('verified', $xpath->evaluate('string(//dialog[@id="update-contribution-'.$donation->id.'"]//select[@name="status"]/option/@value)'));
        $this->assertSame(1, $xpath->query('//dialog[@id="update-contribution-'.$donation->id.'"]//textarea[@name="notes"]')->length);
    }

    public function test_category_validation_reopens_only_the_submitted_form_and_preserves_other_values(): void
    {
        $category = InventoryCategory::query()->where('key', 'food')->firstOrFail();
        $other = InventoryCategory::query()->where('key', 'clothing')->firstOrFail();
        $this->validationSession(['_modal' => 'category-edit-'.$category->id, 'key' => 'invalid', 'label' => 'Submitted label', 'is_active' => '0'], ['key' => 'Duplicate key']);
        $xpath = $this->xpath($this->actingAs($this->user('admin'))->get(route('inventory.categories.index'))->assertOk()->getContent());
        $this->assertSame(1, $xpath->query('//dialog[@open]')->length);
        $this->assertSame('category-edit-'.$category->id, $xpath->evaluate('string(//dialog[@open]/@id)'));
        $this->assertSame($other->label, $xpath->evaluate('string(//dialog[@id="category-edit-'.$other->id.'"]//input[@name="label"]/@value)'));
        $this->assertSame('', $xpath->evaluate('string(//dialog[@id="category-create"]//input[@name="label"]/@value)'));
        $this->assertUniqueIds($xpath);
    }

    public function test_inventory_search_and_create_fields_have_unique_ids(): void
    {
        $xpath = $this->xpath($this->actingAs($this->user('admin'))->get(route('inventory.index'))->assertOk()->getContent());
        $this->assertUniqueIds($xpath);
    }

    public function test_failed_staff_edit_keeps_other_accounts_and_create_form_untouched(): void
    {
        $admin = $this->user('admin');
        $edited = $this->user('staff');
        $other = $this->user('staff');
        $this->actingAs($admin)->from(route('system-security.index'))->patch(route('system-security.users.update', $edited), [
            '_modal' => 'edit-'.$edited->id, 'first_name' => 'Submitted name', 'last_name' => 'Submitted surname',
            'email' => 'invalid', 'role' => 'staff', 'status' => 'active',
        ])->assertRedirect(route('system-security.index'))->assertSessionHasErrors('email');

        $xpath = $this->xpath($this->get(route('system-security.index'))->assertOk()->getContent());
        $this->assertSame('Submitted name', $xpath->evaluate('string(//input[@id="staff-edit-'.$edited->id.'-first_name"]/@value)'));
        $this->assertSame($other->first_name, $xpath->evaluate('string(//input[@id="staff-edit-'.$other->id.'-first_name"]/@value)'));
        $this->assertSame('', $xpath->evaluate('string(//input[@id="staff-create-first_name"]/@value)'));
        $this->assertSame(1, $xpath->query('//details[@open]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="staff-edit-'.$edited->id.'-email-error"]')->length);
        $this->assertUniqueIds($xpath);
    }

    public function test_failed_fund_edit_preserves_values_and_reopens_the_record_editor(): void
    {
        $staff = $this->user('staff');
        $fund = CashDisbursement::create(['amount' => 500, 'purpose' => 'Original purpose', 'recipient' => 'Original supplier',
            'requested_by' => $staff->id, 'requested_date' => now(), 'status' => 'pending',
            'payee_address' => 'Original address', 'fund_source' => config('disbursements.fund_sources')[0]]);

        $page = $this->xpath($this->actingAs($staff)->get(route('disbursements.index'))->assertOk()->getContent());
        $modalKey = $page->evaluate('string(//dialog[@id="fund-request-details-modal-'.$fund->id.'-edit"]//input[@name="_modal"]/@value)');
        $this->assertSame('edit-fund-request-'.$fund->id, $modalKey);

        $this->actingAs($staff)->from(route('disbursements.index'))->patch(route('disbursements.update', $fund), [
            '_modal' => $modalKey, 'amount' => 0, 'purpose' => 'Edited purpose', 'recipient' => 'Edited supplier',
            'payee_address' => 'Edited address', 'fund_source' => $fund->fund_source, 'requested_date' => now()->toDateString(),
        ])->assertRedirect(route('disbursements.show', $fund))->assertSessionHasErrors('amount');

        $xpath = $this->xpath($this->get(route('disbursements.show', $fund))->assertOk()->getContent());
        $this->assertSame('Edited supplier', $xpath->evaluate('string(//dialog[@id="fund-request-details-modal-edit"]//input[@name="recipient"]/@value)'));
        $this->assertSame('0', $xpath->evaluate('string(//dialog[@id="fund-request-details-modal-edit"]//input[@name="amount"]/@value)'));
        $this->assertSame(1, $xpath->query('//dialog[@id="fund-request-details-modal-edit"][@data-reopen="true"]')->length);
        $this->assertSame('Original supplier', $fund->fresh()->recipient);
        $this->assertUniqueIds($xpath);
    }

    private function donation(array $overrides = []): Contribution
    {
        $donor = Donor::create(['donor_type' => 'individual', 'full_name' => 'UI Review Donor',
            'address' => 'Test address', 'contact_number' => '09170000000', 'status' => 'active']);

        return Contribution::create(array_merge(['donor_id' => $donor->id, 'created_by' => $this->user('staff')->id,
            'type' => 'cash', 'status' => 'pending', 'amount' => 6000, 'payment_method' => 'cash',
            'official_receipt_number' => 'OR-UI-REVIEW', 'received_at' => now()], $overrides));
    }

    private function user(string $role): User
    {
        $role = Role::query()->firstOrCreate(['name' => $role], config('auth_defaults.roles.'.$role));

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function validationSession(array $old, array $messages): void
    {
        $this->withSession(['_old_input' => $old, 'errors' => (new ViewErrorBag())->put('default', new MessageBag($messages))]);
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);

        return new \DOMXPath($dom);
    }

    private function assertUniqueIds(\DOMXPath $xpath): void
    {
        $ids = [];
        foreach ($xpath->query('//*[@id]') as $element) {
            $id = $element->getAttribute('id');
            $this->assertArrayNotHasKey($id, $ids, 'Duplicate id '.$id);
            $ids[$id] = true;
        }
    }
}
