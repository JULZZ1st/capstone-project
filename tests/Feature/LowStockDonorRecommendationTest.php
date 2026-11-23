<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\ContributionItem;
use App\Models\Donor;
use App\Models\InventoryDonorAlert;
use App\Models\InventoryItem;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Inventory\LowStockDonorRecommendationService;
use App\Support\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LowStockDonorRecommendationTest extends TestCase
{
    use RefreshDatabase;

    public function test_low_stock_and_out_of_stock_items_open_one_alert_each(): void
    {
        [$workspace] = $this->setupWorkspace();
        app(WorkspaceContext::class)->set($workspace);
        $low = $this->inventory(['name' => 'Rice', 'current_quantity' => 4, 'minimum_reserve_quantity' => 5]);
        $out = $this->inventory(['name' => 'Blankets', 'current_quantity' => 0, 'minimum_reserve_quantity' => 5]);

        $service = app(LowStockDonorRecommendationService::class);
        $service->syncAlerts();
        $service->syncAlerts();

        $this->assertDatabaseHas('inventory_donor_alerts', ['inventory_item_id' => $low->id, 'status' => 'active']);
        $this->assertDatabaseHas('inventory_donor_alerts', ['inventory_item_id' => $out->id, 'status' => 'active']);
        $this->assertSame(2, InventoryDonorAlert::query()->count());
    }

    public function test_exact_item_donors_rank_ahead_of_category_donors(): void
    {
        [$workspace] = $this->setupWorkspace();
        app(WorkspaceContext::class)->set($workspace);
        $item = $this->inventory(['name' => 'Canned Beans', 'category' => 'food', 'current_quantity' => 2, 'minimum_reserve_quantity' => 5]);
        $exact = $this->donor('Exact Donor');
        $category = $this->donor('Category Donor');
        $this->donation($exact, [['name' => 'Canned Beans', 'category' => 'food']]);
        $this->donation($category, [['name' => 'Rice', 'category' => 'food']]);
        app(LowStockDonorRecommendationService::class)->syncAlerts();

        $result = app(LowStockDonorRecommendationService::class)->forItem($item);
        $this->assertSame([$exact->id, $category->id], $result['recommendations']->pluck('id')->all());
        $this->assertSame('Strong Match', $result['recommendations']->first()->match_level);
        $this->assertSame('Good Match', $result['recommendations']->last()->match_level);
    }

    public function test_no_history_is_reported_and_no_donor_is_invented(): void
    {
        [$workspace] = $this->setupWorkspace();
        app(WorkspaceContext::class)->set($workspace);
        $item = $this->inventory(['current_quantity' => 0, 'minimum_reserve_quantity' => 5]);
        $this->donor('Donor without donation history');
        $service = app(LowStockDonorRecommendationService::class);
        $service->syncAlerts();

        $result = $service->forItem($item);
        $this->assertFalse($result['has_history']);
        $this->assertCount(0, $result['recommendations']);
    }

    public function test_recommendations_and_alerts_are_isolated_by_workspace(): void
    {
        [$workspaceA] = $this->setupWorkspace();
        $workspaceB = Workspace::query()->create(['name' => 'Other Workspace', 'slug' => 'other-workspace']);
        app(WorkspaceContext::class)->set($workspaceA);
        $item = $this->inventory(['name' => 'Rice', 'category' => 'food', 'current_quantity' => 0, 'minimum_reserve_quantity' => 5]);
        app(WorkspaceContext::class)->set($workspaceB);
        $foreignDonor = $this->donor('Foreign Donor');
        // Deliberately mismatched workspace IDs simulate stale or inconsistent legacy references.
        $foreignContribution = Contribution::query()->create([
            'workspace_id' => $workspaceA->id,
            'donor_id' => $foreignDonor->id,
            'type' => 'in_kind', 'status' => 'received', 'received_at' => now(),
        ]);
        ContributionItem::query()->create([
            'workspace_id' => $workspaceA->id,
            'contribution_id' => $foreignContribution->id,
            'name' => 'Rice', 'category' => 'food', 'quantity' => 3, 'unit' => 'sack', 'condition' => 'good',
        ]);

        app(WorkspaceContext::class)->set($workspaceA);
        $service = app(LowStockDonorRecommendationService::class);
        $service->syncAlerts();
        $this->assertFalse($service->forItem($item)['has_history']);
        $this->assertSame(1, InventoryDonorAlert::query()->count());
    }

    public function test_recommendation_page_is_admin_only(): void
    {
        [$workspace, $admin] = $this->setupWorkspace();
        app(WorkspaceContext::class)->set($workspace);
        $item = $this->inventory(['current_quantity' => 0, 'minimum_reserve_quantity' => 5]);
        app(LowStockDonorRecommendationService::class)->syncAlerts();

        $this->actingAs($admin)->withSession(['active_workspace_id' => $workspace->id])
            ->get(route('inventory.donors.suggested', $item))
            ->assertOk()->assertSee('No matching donor history is available');
    }

    private function setupWorkspace(): array
    {
        $role = Role::query()->firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrator', 'description' => 'Administrator']);
        $admin = User::factory()->create(['role_id' => $role->id]);
        $workspace = Workspace::query()->create(['name' => 'Test Workspace', 'slug' => 'test-workspace-'.uniqid()]);
        $workspace->memberships()->create(['user_id' => $admin->id, 'role_id' => $role->id, 'status' => 'active']);

        return [$workspace, $admin];
    }

    private function inventory(array $attributes = []): InventoryItem
    {
        return InventoryItem::query()->create(array_merge([
            'name' => 'Test Item', 'category' => 'food', 'unit' => 'piece', 'quantity' => 0,
            'current_quantity' => 0, 'minimum_reserve_quantity' => 5, 'storage_location' => 'Main Storage',
            'status' => config('inventory.defaults.status'),
        ], $attributes));
    }

    private function donor(string $name): Donor
    {
        return Donor::query()->create([
            'donor_type' => 'individual', 'first_name' => $name, 'last_name' => 'Test', 'full_name' => $name,
            'address' => 'Test Address', 'contact_number' => '09123456789', 'status' => 'active',
        ]);
    }

    private function donation(Donor $donor, array $items): void
    {
        $contribution = Contribution::query()->create([
            'donor_id' => $donor->id, 'type' => 'in_kind', 'status' => 'received', 'received_at' => now(),
        ]);
        foreach ($items as $item) {
            ContributionItem::query()->create([
                'contribution_id' => $contribution->id, 'name' => $item['name'], 'category' => $item['category'],
                'quantity' => 2, 'unit' => 'piece', 'condition' => 'good',
            ]);
        }
    }
}
