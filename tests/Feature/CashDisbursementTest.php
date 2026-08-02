<?php

namespace Tests\Feature;

use App\Models\CashDisbursement;
use App\Models\Role;
use App\Models\User;
use App\Services\Disbursements\RecordCashDisbursement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CashDisbursementTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_disbursements_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('cash_disbursements', [
            'id', 'disbursed_to', 'purpose', 'amount', 'payment_method',
            'reference_number', 'notes', 'disbursed_at', 'created_by', 'created_at', 'updated_at',
        ]));
    }

    public function test_disbursement_purposes_are_config_backed(): void
    {
        $this->assertArrayHasKey('aid_payout', config('disbursements.purposes'));
        $this->assertArrayHasKey('operational_expense', config('disbursements.purposes'));
    }

    public function test_cash_disbursement_policy_allows_admin_secretary_and_treasurer(): void
    {
        foreach (['admin', 'secretary', 'treasurer'] as $role) {
            $user = $this->userWithRole($role);

            $this->assertTrue($user->can('viewAny', CashDisbursement::class));
            $this->assertTrue($user->can('create', CashDisbursement::class));
        }
    }

    public function test_record_cash_disbursement_service_creates_a_disbursement(): void
    {
        $user = User::factory()->create();
        $service = app(RecordCashDisbursement::class);

        $disbursement = $service->handle([
            'disbursed_to' => 'Juan Dela Cruz',
            'purpose' => 'aid_payout',
            'amount' => 1500.50,
            'payment_method' => 'cash',
            'reference_number' => null,
            'notes' => 'Typhoon relief aid',
        ], $user);

        $this->assertDatabaseHas('cash_disbursements', [
            'id' => $disbursement->id,
            'disbursed_to' => 'Juan Dela Cruz',
            'purpose' => 'aid_payout',
            'amount' => 1500.50,
            'created_by' => $user->id,
        ]);
        $this->assertNotNull($disbursement->disbursed_at);
    }

    public function test_guest_is_redirected_from_cash_disbursements_index(): void
    {
        $this->get(route('cash-disbursements.index'))->assertRedirect(route('login'));
    }

    public function test_authorized_roles_can_view_and_record_a_disbursement(): void
    {
        foreach (['admin', 'secretary', 'treasurer'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('cash-disbursements.index'))->assertOk();

            $response = $this->actingAs($user)->post(route('cash-disbursements.store'), [
                'disbursed_to' => 'Recipient '.$role,
                'purpose' => 'operational_expense',
                'amount' => 250,
                'payment_method' => 'cash',
            ]);

            $response->assertRedirect(route('cash-disbursements.index'));
            $this->assertDatabaseHas('cash_disbursements', [
                'disbursed_to' => 'Recipient '.$role,
                'created_by' => $user->id,
            ]);
        }
    }

    public function test_disbursement_validation_rejects_invalid_amount_and_purpose(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('cash-disbursements.store'), [
            'disbursed_to' => 'Someone',
            'purpose' => 'not_a_real_purpose',
            'amount' => 0,
        ]);

        $response->assertSessionHasErrors(['purpose', 'amount']);
        $this->assertSame(0, CashDisbursement::query()->count());
    }

    public function test_index_filters_by_search_and_purpose(): void
    {
        $user = User::factory()->create();

        CashDisbursement::query()->create([
            'disbursed_to' => 'Maria Clara',
            'purpose' => 'aid_payout',
            'amount' => 500,
            'disbursed_at' => now(),
            'created_by' => $user->id,
        ]);
        CashDisbursement::query()->create([
            'disbursed_to' => 'Office Supplies Co.',
            'purpose' => 'supplies_purchase',
            'amount' => 800,
            'disbursed_at' => now(),
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('cash-disbursements.index', ['search' => 'Maria']));

        $response->assertOk();
        $response->assertSee('Maria Clara');
        $response->assertDontSee('Office Supplies Co.');
    }

    public function test_show_page_renders_a_single_disbursement(): void
    {
        $user = User::factory()->create();
        $disbursement = CashDisbursement::query()->create([
            'disbursed_to' => 'Pedro Penduko',
            'purpose' => 'honorarium',
            'amount' => 1200,
            'disbursed_at' => now(),
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('cash-disbursements.show', $disbursement));

        $response->assertOk();
        $response->assertSee('Pedro Penduko');
    }

    public function test_cash_disbursements_index_renders_record_button_and_table(): void
    {
        $user = User::factory()->create();
        CashDisbursement::query()->create([
            'disbursed_to' => 'Sample Recipient',
            'purpose' => 'aid_payout',
            'amount' => 300,
            'disbursed_at' => now(),
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('cash-disbursements.index'));

        $response->assertOk();
        $response->assertSee('Record Disbursement');
        $response->assertSee('Sample Recipient');
    }

    public function test_sidebar_links_to_cash_disbursements(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(route('cash-disbursements.index'), false);
    }

    public function test_dashboard_reflects_recorded_disbursement_totals(): void
    {
        $user = User::factory()->create();
        CashDisbursement::query()->create([
            'disbursed_to' => 'Dashboard Test Recipient',
            'purpose' => 'aid_payout',
            'amount' => 750,
            'disbursed_at' => now(),
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSee('Total cash disbursed');
        $response->assertSee('750.00');
        $response->assertSee('Dashboard Test Recipient');
    }

    private function userWithRole(string $role): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['name' => $role],
            config("auth_defaults.roles.{$role}"),
        );

        return User::factory()->create(['role_id' => $roleModel->id]);
    }
}
