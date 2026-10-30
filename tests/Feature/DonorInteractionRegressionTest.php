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
