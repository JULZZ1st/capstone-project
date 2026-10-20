<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\ContributionItem;
use App\Models\Donor;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Workspaces\WorkspaceBackfill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_assigns_root_and_child_records_and_preserves_financial_totals(): void
    {
        $role = Role::query()->create([
            'name' => 'backfill-admin',
            'display_name' => 'Backfill Admin',
            'description' => 'Test role',
        ]);
        $user = User::factory()->create(['role_id' => $role->id]);
        $donor = Donor::query()->create([
            'full_name' => 'Legacy Donor',
            'address' => 'Barangay A',
            'contact_number' => '09170000000',
        ]);
        $campaign = \App\Models\Campaign::query()->create([
            'name' => 'Legacy Campaign',
            'slug' => 'legacy-campaign-test',
            'status' => 'active',
        ]);
        $contribution = Contribution::query()->create([
            'donor_id' => $donor->id,
            'campaign_id' => $campaign->id,
            'created_by' => $user->id,
            'type' => 'cash',
            'status' => 'verified',
            'amount' => 125.50,
            'payment_method' => 'cash',
            'received_at' => now(),
        ]);
        $item = ContributionItem::query()->create([
            'contribution_id' => $contribution->id,
            'name' => 'Rice',
            'category' => 'food',
            'quantity' => 10,
            'unit' => 'sack',
            'condition' => 'good',
        ]);

        $before = [
            'contributions' => (float) Contribution::query()->sum('amount'),
            'items' => ContributionItem::query()->count(),
        ];

        $result = app(WorkspaceBackfill::class)->run();

        $legacyWorkspace = Workspace::query()->where('slug', 'legacy')->firstOrFail();
        $this->assertSame($legacyWorkspace->id, Donor::query()->findOrFail($donor->id)->workspace_id);
        $this->assertSame($legacyWorkspace->id, Contribution::query()->findOrFail($contribution->id)->workspace_id);
        $this->assertSame($legacyWorkspace->id, ContributionItem::query()->findOrFail($item->id)->workspace_id);
        $this->assertSame($before, [
            'contributions' => (float) Contribution::query()->sum('amount'),
            'items' => ContributionItem::query()->count(),
        ]);
        $this->assertSame($result['financial_totals_before'], $result['financial_totals_after']);
        $this->assertDatabaseHas('workspace_memberships', [
            'workspace_id' => $legacyWorkspace->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);
    }

    public function test_backfill_reports_inconsistent_existing_ownership_and_is_idempotent(): void
    {
        $otherWorkspace = Workspace::query()->create([
            'name' => 'Other Workspace',
            'slug' => 'other-workspace',
        ]);
        $donor = Donor::query()->create([
            'full_name' => 'Mismatch Donor',
            'address' => 'Barangay B',
            'contact_number' => '09171111111',
        ]);
        $contribution = Contribution::query()->create([
            'workspace_id' => $otherWorkspace->id,
            'donor_id' => $donor->id,
            'type' => 'cash',
            'status' => 'received',
            'amount' => 50,
            'payment_method' => 'cash',
            'received_at' => now(),
        ]);

        $first = app(WorkspaceBackfill::class)->run();
        $issueCount = $first['issues'];
        $second = app(WorkspaceBackfill::class)->run();

        $this->assertGreaterThan(0, $issueCount);
        $this->assertSame($issueCount, $second['issues']);
        $this->assertDatabaseHas('workspace_backfill_issues', [
            'table_name' => 'contributions',
            'record_id' => $contribution->id,
            'issue_type' => 'existing_workspace_mismatch',
        ]);
        $this->assertSame($otherWorkspace->id, Contribution::withoutGlobalScope('active_workspace')->findOrFail($contribution->id)->workspace_id);
    }
}
