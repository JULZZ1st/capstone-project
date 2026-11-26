<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InputValidationRegressionTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('malformedTextRequests')]
    public function test_malformed_text_returns_validation_errors(string $method, string $route, array $payload, string $field): void
    {
        if ($route !== 'login.store') {
            $role = Role::firstOrCreate(['name' => 'admin'], config('auth_defaults.roles.admin'));
            $this->actingAs(User::factory()->create(['role_id' => $role->id]));
        }

        $this->json($method, route($route), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public static function malformedTextRequests(): array
    {
        return [
            'login email' => ['POST', 'login.store', ['email' => ['bad'], 'password' => 'password'], 'email'],
            'donor search' => ['GET', 'donors.index', ['search' => ['bad']], 'search'],
            'donor type' => ['GET', 'donors.index', ['type' => ['bad']], 'type'],
            'donation search' => ['GET', 'donations.index', ['search' => ['bad']], 'search'],
            'inventory search' => ['GET', 'inventory.index', ['search' => ['bad']], 'search'],
            'security search' => ['GET', 'system-security.index', ['search' => ['bad']], 'search'],
            'report tab' => ['GET', 'reports.index', ['tab' => ['bad']], 'tab'],
            'staff first name' => ['POST', 'system-security.users.store', ['first_name' => ['bad']], 'first_name'],
            'province query' => ['GET', 'locations.provinces', ['region_code' => ['bad']], 'region_code'],
            'city query' => ['GET', 'locations.cities', ['province_code' => ['bad']], 'province_code'],
            'barangay query' => ['GET', 'locations.barangays', ['city_code' => ['bad']], 'city_code'],
            'donor name' => ['POST', 'donors.store', ['donor_type' => 'individual', 'first_name' => ['bad']], 'first_name'],
            'inventory name' => ['POST', 'inventory.store', ['name' => ['bad']], 'name'],
            'donation item name' => ['POST', 'donations.store', ['donation_type' => 'in_kind', 'items' => [['name' => ['bad']]]], 'items.0.name'],
            'fund request purpose' => ['POST', 'disbursements.store', ['purpose' => ['bad']], 'purpose'],
            'withdrawal description' => ['POST', 'bank-reconciliation.withdrawals.store', ['description' => ['bad']], 'description'],
            'category label' => ['POST', 'inventory.categories.store', ['label' => ['bad']], 'label'],
        ];
    }

    public function test_malformed_staff_update_returns_a_validation_error(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin'], config('auth_defaults.roles.admin'));
        $admin = User::factory()->create(['role_id' => $role->id]);
        $staff = User::factory()->create();

        $this->actingAs($admin)->patchJson(route('system-security.users.update', $staff), [
            'first_name' => ['invalid'], 'last_name' => $staff->last_name,
            'email' => $staff->email, 'role' => 'staff',
        ])->assertUnprocessable()->assertJsonValidationErrors('first_name');
    }
}
