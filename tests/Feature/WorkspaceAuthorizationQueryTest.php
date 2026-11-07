<?php

namespace Tests\Feature;

use App\Models\Donor;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Workspaces\WorkspaceAuthorizer;
use App\Support\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkspaceAuthorizationQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_checks_reuse_the_resolved_workspace_without_requerying_it(): void
    {
        $admin = $this->user('admin');
        $admin->load('role');
        $workspace = app(WorkspaceContext::class)->get();
        $authorizer = app(WorkspaceAuthorizer::class);

        DB::enableQueryLog();
        try {
            for ($record = 0; $record < 10; $record++) {
                $this->assertTrue($authorizer->canAccessRecord($admin, new Donor(['workspace_id' => $workspace->id])));
            }
            $this->assertCount(0, DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    public function test_missing_other_and_inactive_workspaces_remain_denied(): void
    {
        $admin = $this->user('admin');
        $context = app(WorkspaceContext::class);
        $workspace = $context->get();
        $donor = new Donor(['workspace_id' => $workspace->id]);
        $authorizer = app(WorkspaceAuthorizer::class);

        $context->clear();
        $this->assertFalse($authorizer->canAccessRecord($admin, $donor));
        $otherWorkspace = Workspace::query()->create(['name' => 'Other', 'slug' => 'other']);
        $context->set($otherWorkspace);
        $this->assertFalse($authorizer->canAccessRecord($admin, $donor));
        $workspace->update(['status' => 'inactive']);
        $context->set($workspace);
        $this->assertFalse($authorizer->canAccessRecord($admin, $donor));
    }

    public function test_staff_membership_is_still_checked_after_revocation(): void
    {
        $staff = $this->user('staff');
        $workspace = app(WorkspaceContext::class)->get();
        $donor = new Donor(['workspace_id' => $workspace->id]);
        $authorizer = app(WorkspaceAuthorizer::class);

        $this->assertTrue($authorizer->canAccessRecord($staff, $donor));
        $staff->workspaceMemberships()->update(['status' => 'inactive']);
        $this->assertFalse($authorizer->canAccessRecord($staff, $donor));
    }

    private function user(string $role): User
    {
        $roleModel = Role::query()->firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)]);

        return User::factory()->create(['role_id' => $roleModel->id]);
    }
}
