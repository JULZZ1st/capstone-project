<?php

namespace Tests\Feature;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventorySubcategory;
use App\Models\Role;
use App\Models\SecurityActivityEvent;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class DefenseReadinessRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivation_ends_an_existing_session_before_it_can_read_records(): void
    {
        $staff = User::factory()->create();
        $admin = $this->admin();

        $this->actingAs($staff)->get(route('donors.index'))->assertOk();
        $this->actingAs($admin)->patch(route('system-security.users.deactivate', $staff))
            ->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->actingAs($staff->fresh())->withSession(['active_workspace_id' => $staff->workspaceMemberships()->firstOrFail()->workspace_id])
            ->get(route('donors.index'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNull(session('active_workspace_id'));
    }

    public function test_deactivated_account_cannot_change_password_through_an_existing_session(): void
    {
        $staff = User::factory()->create(['status' => 'deactivated', 'must_change_password' => true]);
        $passwordHash = $staff->password;

        $this->actingAs($staff)->post(route('password.change.store'), [
            'current_password' => 'password',
            'password' => 'ReplacementPassword123!',
            'password_confirmation' => 'ReplacementPassword123!',
        ])->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame($passwordHash, $staff->fresh()->password);
        $this->assertTrue($staff->fresh()->must_change_password);
    }

    public function test_deactivated_account_cannot_access_json_or_write_inventory(): void
    {
        $staff = User::factory()->create(['status' => 'deactivated']);

        $this->actingAs($staff)->getJson(route('donors.index'))->assertUnauthorized();
        $this->assertGuest();
        $this->actingAs($staff)->postJson(route('inventory.store'), $this->inventoryData())
            ->assertUnauthorized();
        $this->assertDatabaseMissing('inventory_items', ['name' => 'Classification regression']);
    }

    public function test_deactivated_account_restored_by_remember_cookie_is_rejected(): void
    {
        $staff = User::factory()->create();
        $cookieName = Auth::guard('web')->getRecallerName();
        $response = $this->post(route('login.store'), [
            'email' => $staff->email, 'password' => 'password', 'remember' => '1',
        ])->assertRedirect();
        $cookie = $response->getCookie($cookieName);
        $this->assertNotNull($cookie);
        $staff->update(['status' => 'deactivated']);
        $this->flushSession();
        Auth::forgetGuards();

        $this->withCookie($cookieName, $cookie->getValue())->get(route('donors.index'))
            ->assertRedirect(route('login'))->assertCookieExpired($cookieName);
        $this->assertGuest();
    }

    public function test_editing_staff_status_keeps_the_account_visible_and_reactivatable(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create();
        $data = [
            'first_name' => $staff->first_name, 'last_name' => $staff->last_name,
            'email' => $staff->email, 'role' => 'staff', 'status' => 'deactivated',
        ];

        $this->actingAs($admin)->patch(route('system-security.users.update', $staff), $data)
            ->assertRedirect()->assertSessionDoesntHaveErrors();
        $personnel = $this->get(route('system-security.index'))->assertOk()->viewData('personnel');
        $this->assertTrue($personnel->contains('id', $staff->id));
        $this->patch(route('system-security.users.update', $staff), [...$data, 'status' => config('auth_defaults.user_status')])
            ->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->assertSame(config('auth_defaults.user_status'), $staff->fresh()->status);
        $this->assertSame('active', $staff->workspaceMemberships()->firstOrFail()->status);
    }

    public function test_staff_status_rejects_unknown_values(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create();

        $this->actingAs($admin)->patch(route('system-security.users.update', $staff), [
            'first_name' => $staff->first_name, 'last_name' => $staff->last_name,
            'email' => $staff->email, 'role' => 'staff', 'status' => 'arbitrary-status',
        ])->assertSessionHasErrors('status');
        $this->assertSame(config('auth_defaults.user_status'), $staff->fresh()->status);
    }

    public function test_deactivation_audit_records_the_previous_status(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create();

        $this->actingAs($admin)->patch(route('system-security.users.deactivate', $staff))->assertRedirect();

        $event = SecurityActivityEvent::query()->where('action', 'staff_account_deactivated')->firstOrFail();
        $this->assertSame(config('auth_defaults.user_status'), $event->metadata['previous_status']);
    }

    public function test_revoked_memberships_cannot_be_reactivated_from_staff_management(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create(['status' => 'deactivated']);
        $staff->workspaceMemberships()->update(['status' => 'inactive']);

        $personnel = $this->actingAs($admin)->get(route('system-security.index'))->assertOk()->viewData('personnel');
        $this->assertFalse($personnel->contains('id', $staff->id));
        $this->patch(route('system-security.users.update', $staff), [
            'first_name' => $staff->first_name, 'last_name' => $staff->last_name,
            'email' => $staff->email, 'role' => 'staff', 'status' => config('auth_defaults.user_status'),
        ])->assertForbidden();
        $this->assertSame('deactivated', $staff->fresh()->status);
    }

    public function test_deactivated_staff_in_another_workspace_cannot_be_managed(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create(['status' => 'deactivated']);
        $other = Workspace::query()->create(['name' => 'Other workspace', 'slug' => 'other-defense', 'status' => 'active']);
        $staff->workspaceMemberships()->update(['workspace_id' => $other->id, 'status' => 'deactivated']);

        $personnel = $this->actingAs($admin)->get(route('system-security.index'))->assertOk()->viewData('personnel');
        $this->assertFalse($personnel->contains('id', $staff->id));
        $this->patch(route('system-security.users.update', $staff), [
            'first_name' => $staff->first_name, 'last_name' => $staff->last_name,
            'email' => $staff->email, 'role' => 'staff', 'status' => config('auth_defaults.user_status'),
        ])->assertForbidden();
        $this->assertSame('deactivated', $staff->fresh()->status);
    }

    public function test_inventory_list_offers_working_stock_actions_only_for_active_items_in_the_workspace(): void
    {
        $staff = User::factory()->create();
        $data = [
            'name' => 'Active local stock', 'category' => 'food', 'unit' => 'piece',
            'quantity' => 5, 'current_quantity' => 5, 'minimum_reserve_quantity' => 0,
            'storage_location' => 'Main Storage', 'status' => 'active',
        ];
        $active = InventoryItem::query()->create($data);
        $archived = InventoryItem::query()->create([...$data, 'name' => 'Archived stock', 'status' => 'archived']);
        $other = Workspace::query()->create(['name' => 'Other workspace', 'slug' => 'other-stock', 'status' => 'active']);
        $foreign = InventoryItem::query()->create([...$data, 'name' => 'Foreign stock', 'workspace_id' => $other->id]);

        $page = $this->actingAs($staff)->get(route('inventory.index'))->assertOk();
        $page->assertSee('data-dialog-open="adjust-stock-dialog-'.$active->id.'"', false)
            ->assertSee('id="adjust-stock-dialog-'.$active->id.'"', false)
            ->assertDontSee('data-dialog-open="adjust-stock-dialog-'.$archived->id.'"', false)
            ->assertDontSee($foreign->name);

        $document = new \DOMDocument;
        @$document->loadHTML($page->getContent());
        $xpath = new \DOMXPath($document);
        $form = $xpath->query('//dialog[@id="adjust-stock-dialog-'.$active->id.'"]/form')->item(0);
        $this->assertNotNull($form);
        $this->assertSame(route('inventory.adjustments.store', $active), $form->getAttribute('action'));

        $this->post(route('inventory.adjustments.store', $active), [
            'movement_type' => 'stock_out', 'quantity' => 2, 'reason' => 'distribution',
        ])->assertRedirect()->assertSessionDoesntHaveErrors();
        $this->assertSame('3.00', $active->fresh()->current_quantity);
    }

    public function test_custom_categories_can_resolve_multiple_slug_collisions(): void
    {
        $admin = $this->admin();
        foreach (['Relief Kits', 'Relief-Kits', 'Relief_Kits'] as $label) {
            $this->actingAs($admin)->post(route('inventory.store'), [
                ...$this->inventoryData(), 'category' => '__other__', 'custom_category' => $label,
            ])->assertRedirect()->assertSessionDoesntHaveErrors();
        }

        $this->assertSame(['relief_kits', 'relief_kits_2', 'relief_kits_3'],
            InventoryCategory::query()->whereIn('label', ['Relief Kits', 'Relief-Kits', 'Relief_Kits'])->orderBy('id')->pluck('key')->all());
    }

    public function test_custom_subcategories_can_resolve_multiple_slug_collisions(): void
    {
        $admin = $this->admin();
        foreach (['Relief Kits', 'Relief-Kits', 'Relief_Kits'] as $label) {
            $this->actingAs($admin)->post(route('inventory.store'), [
                ...$this->inventoryData(), 'subcategory_selection' => '__other__', 'custom_subcategory' => $label,
            ])->assertRedirect()->assertSessionDoesntHaveErrors();
        }

        $this->assertSame(['relief_kits', 'relief_kits_2', 'relief_kits_3'],
            InventorySubcategory::query()->whereIn('label', ['Relief Kits', 'Relief-Kits', 'Relief_Kits'])->orderBy('id')->pluck('key')->all());
    }

    private function inventoryData(): array
    {
        return [
            'name' => 'Classification regression', 'category' => 'food', 'unit' => 'piece',
            'initial_quantity' => 1, 'minimum_reserve_quantity' => 0,
            'storage_location' => 'Main Storage', 'status' => 'active',
        ];
    }

    private function admin(): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'admin'], config('auth_defaults.roles.admin'));

        return User::factory()->create(['role_id' => $role->id]);
    }
}
