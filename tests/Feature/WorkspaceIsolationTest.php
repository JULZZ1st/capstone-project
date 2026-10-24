<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\Donor;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WorkspaceIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_routes_and_reports_cannot_cross_workspace_boundaries(): void
    {
        [$admin, $workspaceA, $workspaceB] = $this->workspaces();

        app(WorkspaceContext::class)->set($workspaceA);
        $donorA = Donor::query()->create(['first_name' => 'A', 'last_name' => 'Donor', 'full_name' => 'Workspace A Donor', 'donor_type' => 'individual', 'status' => 'active', 'address' => 'A Address', 'contact_number' => '09000000001']);
        Contribution::query()->create([
            'donor_id' => $donorA->id,
            'type' => 'cash',
            'status' => 'verified',
            'amount' => 125,
            'payment_method' => 'cash',
            'received_at' => Carbon::now(),
            'created_by' => $admin->id,
        ]);

        app(WorkspaceContext::class)->set($workspaceB);
        $donorB = Donor::query()->create(['first_name' => 'B', 'last_name' => 'Donor', 'full_name' => 'Workspace B Donor', 'donor_type' => 'individual', 'status' => 'active', 'address' => 'B Address', 'contact_number' => '09000000002']);
        Contribution::query()->create([
            'donor_id' => $donorB->id,
            'type' => 'cash',
            'status' => 'verified',
            'amount' => 900,
            'payment_method' => 'cash',
            'received_at' => Carbon::now(),
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->withSession(['active_workspace_id' => $workspaceA->id]);

        $this->get(route('donors.index'))
            ->assertOk()
            ->assertSee('Workspace A Donor')
            ->assertDontSee('Workspace B Donor');

        $this->get(route('donors.show', $donorB))
            ->assertNotFound();

        $this->get(route('reports.index', [
            'tab' => 'philanthropy-roster',
            'year' => Carbon::now()->year,
            'month' => Carbon::now()->month,
        ]))
            ->assertOk()
            ->assertSee('Workspace A Donor')
            ->assertDontSee('Workspace B Donor');
    }

    public function test_admin_with_memberships_resolves_a_workspace_for_operational_records(): void
    {
        [$admin, $workspaceA] = $this->workspaces();

        $this->actingAs($admin)
            ->get(route('donors.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->withSession(['active_workspace_id' => $workspaceA->id])
            ->get(route('donors.index'))
            ->assertOk();
    }

    private function workspaces(): array
    {
        $role = Role::query()->firstOrCreate(['name' => 'admin'], [
            'display_name' => 'Administrator',
            'description' => 'Administrator',
        ]);
        $admin = User::factory()->create(['role_id' => $role->id]);
        $workspaceA = Workspace::query()->create(['name' => 'Workspace A', 'slug' => 'workspace-a']);
        $workspaceB = Workspace::query()->create(['name' => 'Workspace B', 'slug' => 'workspace-b']);

        $workspaceA->memberships()->create(['user_id' => $admin->id, 'role_id' => $role->id, 'status' => 'active']);
        $workspaceB->memberships()->create(['user_id' => $admin->id, 'role_id' => $role->id, 'status' => 'active']);

        return [$admin, $workspaceA, $workspaceB];
    }
}
