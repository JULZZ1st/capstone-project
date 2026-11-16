<?php

namespace Tests\Feature;

use App\Models\Donor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Schoolees\Psgc\Models\City;
use Schoolees\Psgc\Models\Province;
use Schoolees\Psgc\Models\Region;
use Tests\TestCase;

class DonorInteractionRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_creation_returns_to_the_portal_with_the_directory_open(): void
    {
        $response = $this->actingAs($this->admin())->post(route('donors.store'), [
            '_modal' => 'directory-add-donor-modal',
            'donor_type' => 'individual', 'first_name' => 'Maria', 'last_name' => 'Santos',
            'contact_number' => '09170000123',
        ]);

        $response->assertRedirect(route('donations.index'))->assertSessionHas('donor_directory_open', true);
        $this->assertDatabaseHas('donors', ['full_name' => 'Maria Santos']);
        $page = $this->get(route('donations.index'))->assertOk();
        $document = new \DOMDocument();
        $document->loadHTML($page->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//dialog[@id="donor-directory-modal"][@data-reopen="true"]')->length);
        $this->assertSame(0, $xpath->query('//dialog[@id="directory-add-donor-modal"][@open]')->length);
    }

    public function test_directory_validation_keeps_the_form_values_and_both_dialogs_available(): void
    {
        $this->actingAs($this->admin())->post(route('donors.store'), [
            '_modal' => 'directory-add-donor-modal',
            'donor_type' => 'individual', 'first_name' => 'Maria',
            'contact_number' => '09170000123',
        ])->assertRedirect(route('donations.index'))->assertSessionHasErrors('last_name');

        $page = $this->get(route('donations.index'))->assertOk();
        $document = new \DOMDocument();
        $document->loadHTML($page->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//dialog[@id="donor-directory-modal"][@data-reopen="true"]')->length);
        $this->assertSame(1, $xpath->query('//dialog[@id="directory-add-donor-modal"][@open]')->length);
        $this->assertSame('Maria', $xpath->evaluate('string(//dialog[@id="directory-add-donor-modal"]//input[@name="first_name"]/@value)'));
        $this->assertDatabaseCount('donors', 0);
    }

    public function test_duplicate_directory_creation_returns_to_the_same_form_for_review(): void
    {
        $donor = $this->donor();
        $this->actingAs($this->admin())->post(route('donors.store'), [
            '_modal' => 'directory-add-donor-modal',
            'donor_type' => 'individual', 'first_name' => $donor->first_name, 'last_name' => $donor->last_name,
            'contact_number' => $donor->contact_number,
        ])->assertRedirect(route('donations.index'))->assertSessionHas('duplicate_donor_detected', true);

        $this->assertDatabaseCount('donors', 1);
        $this->get(route('donations.index'))->assertOk()->assertSee('name="_confirm_duplicate" value="1"', false);
    }

    public function test_ajax_donor_removal_returns_success_instead_of_a_server_error(): void
    {
        $donor = $this->donor();

        $this->actingAs($this->admin())->deleteJson(route('donors.destroy', $donor))
            ->assertOk()
            ->assertJsonPath('id', $donor->id);
        $this->assertModelMissing($donor);
    }

    public function test_list_edit_forms_keep_middle_name_suffix_and_organization_tin(): void
    {
        $individual = $this->donor(['middle_name' => 'Reyes', 'suffix' => 'jr']);
        $organization = $this->donor([
            'donor_type' => 'organization', 'full_name' => 'Relief Foundation',
            'tin' => '123-456-789', 'contact_person' => 'Ana Cruz',
        ]);
        $xpath = $this->page('donors.index');

        $this->assertSame('Reyes', $this->field($xpath, $individual, 'middle_name')->getAttribute('value'));
        $this->assertSame('jr', $xpath->evaluate('string(//dialog[@id="edit-donor-modal-'.$individual->id.'"]//select[@name="suffix"]/option[@selected]/@value)'));
        $this->assertSame('123-456-789', $this->field($xpath, $organization, 'tin')->getAttribute('value'));
    }

    public function test_failed_edit_does_not_overwrite_other_donor_forms(): void
    {
        $edited = $this->donor(['first_name' => 'Ana', 'email' => 'ana@example.test']);
        $other = $this->donor(['first_name' => 'Maria', 'email' => 'maria@example.test']);
        $this->withSession(['_old_input' => [
            '_modal' => 'edit-'.$edited->id, 'donor_type' => 'individual',
            'first_name' => 'Submitted Name', 'email' => 'submitted@example.test',
        ]]);
        $xpath = $this->page('donors.index');

        $this->assertSame('Submitted Name', $this->field($xpath, $edited, 'first_name')->getAttribute('value'));
        $this->assertSame('Maria', $this->field($xpath, $other, 'first_name')->getAttribute('value'));
        $this->assertSame('maria@example.test', $this->field($xpath, $other, 'email')->getAttribute('value'));
        $this->assertSame('', $xpath->evaluate('string(//dialog[@id="create-donor-modal"]//input[@name="first_name"]/@value)'));
    }

    public function test_existing_unmatched_address_values_are_not_cleared_by_the_edit_form(): void
    {
        $donor = $this->donor([
            'region' => 'Legacy Region', 'province' => 'Legacy Province',
            'city_municipality' => 'Legacy City', 'barangay' => 'Legacy Barangay',
        ]);
        $xpath = $this->page('donors.index');

        foreach (['province' => 'Legacy Province', 'city_municipality' => 'Legacy City', 'barangay' => 'Legacy Barangay'] as $field => $value) {
            $query = '//dialog[@id="edit-donor-modal-'.$donor->id.'"]//select[@name="'.$field.'"]';
            $this->assertSame($value, $xpath->evaluate('string('.$query.'/option[@selected]/@value)'), $field);
            $this->assertSame(0, $xpath->query($query.'[@disabled]')->length, $field.' must be submitted');
        }
    }

    public function test_independent_cities_can_be_selected_without_a_province(): void
    {
        $region = Region::query()->create(['code' => '700000000', 'name' => 'Region VII (Central Visayas)']);
        Province::query()->create(['code' => '722000000', 'name' => 'Cebu', 'region_code' => $region->code]);
        City::query()->create(['code' => '730600000', 'name' => 'City of Cebu', 'region_code' => $region->code, 'province_code' => null]);
        $xpath = $this->page('donors.index');

        $this->assertSame(1, $xpath->query('//dialog[@id="create-donor-modal"]//select[@name="province"]/option[@data-none-province]')->length);
        $this->assertSame($region->code, $xpath->evaluate('string(//dialog[@id="create-donor-modal"]//input[@name="region"]/@data-code)'));
        $this->getJson(route('locations.cities', ['region_code' => $region->code]))
            ->assertOk()->assertJsonFragment(['name' => 'City of Cebu']);
    }

    public function test_donor_forms_have_unique_field_ids_on_both_pages(): void
    {
        $this->donor();
        $this->donor();

        foreach (['donors.index', 'donations.index'] as $route) {
            $xpath = $this->page($route);
            $this->assertSame(0, $xpath->query('//*[starts-with(name(), "x-")]')->length, $route.' contains unrendered Blade components');
            $ids = [];
            foreach ($xpath->query('//input[@id] | //select[@id] | //textarea[@id]') as $field) {
                $id = $field->getAttribute('id');
                $this->assertArrayNotHasKey($id, $ids, $route.' has duplicate field id '.$id);
                $ids[$id] = true;
            }
        }
    }

    public function test_organization_profile_details_are_complete_in_both_directories(): void
    {
        $donor = $this->donor([
            'donor_type' => 'organization', 'full_name' => 'Community Relief Foundation',
            'contact_person' => 'Maria Santos', 'contact_number' => '09170000123',
            'email' => 'community.relief.foundation@example.test', 'preferred_contact_method' => 'email',
            'tin' => '123-456-789', 'address' => '123 Community Road',
            'barangay' => 'Test Barangay', 'city_municipality' => 'Test City',
            'province' => 'Test Province', 'region' => 'Test Region',
            'notes' => "Contact the coordinator.\nAvailable on weekdays.",
        ]);

        foreach (['donors.index', 'donations.index'] as $route) {
            $xpath = $this->page($route);
            $dialog = '//dialog[@id="view-donor-modal-'.$donor->id.'"]';
            $this->assertSame(1, $xpath->query($dialog)->length);
            $this->assertSame($donor->full_name, $xpath->evaluate('string('.$dialog.'//h2)'));

            foreach ([
                'Contact Person' => $donor->contact_person,
                'Contact Number' => $donor->contact_number,
                'Email' => $donor->email,
                'Preferred Contact' => 'Email',
                'TIN' => $donor->tin,
                'Street / Purok / House No.' => $donor->address,
                'Barangay' => $donor->barangay,
                'City / Municipality' => $donor->city_municipality,
                'Province' => $donor->province,
                'Region' => $donor->region,
            ] as $label => $value) {
                $this->assertSame($value, $xpath->evaluate('string('.$dialog.'//dt[normalize-space()="'.$label.'"]/following-sibling::dd[1])'), $route.': '.$label);
            }

            $this->assertSame($donor->notes, $xpath->evaluate('string('.$dialog.'//section[@aria-labelledby="view-donor-modal-'.$donor->id.'-notes"]/p)'));
        }
    }

    private function page(string $route): \DOMXPath
    {
        $response = $this->actingAs($this->admin())->get(route($route))->assertOk();
        $document = new \DOMDocument();
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);

        return new \DOMXPath($document);
    }

    private function field(\DOMXPath $xpath, Donor $donor, string $name): \DOMElement
    {
        $field = $xpath->query('//dialog[@id="edit-donor-modal-'.$donor->id.'"]//input[@name="'.$name.'"]')->item(0);
        $this->assertInstanceOf(\DOMElement::class, $field);

        return $field;
    }

    private function admin(): User
    {
        $role = Role::query()->firstOrCreate(['name' => Role::ADMIN], config('auth_defaults.roles.admin'));

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function donor(array $attributes = []): Donor
    {
        return Donor::query()->create(array_merge([
            'donor_type' => 'individual', 'first_name' => 'Juan', 'last_name' => 'Cruz',
            'full_name' => 'Juan Cruz', 'address' => 'Poblacion', 'contact_number' => '09171234567', 'status' => 'active',
        ], $attributes));
    }
}
