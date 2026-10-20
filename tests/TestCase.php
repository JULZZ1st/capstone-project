<?php

namespace Tests;

use App\Models\Workspace;
use App\Support\Workspaces\WorkspaceContext;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    //
    protected function setUp(): void
    {
        parent::setUp();

        if (\Illuminate\Support\Facades\Schema::hasTable('workspaces')) {
            $workspace = Workspace::query()->firstOrCreate(
                ['slug' => 'legacy'],
                ['name' => 'Legacy Workspace', 'status' => 'active'],
            );
            app(WorkspaceContext::class)->set($workspace);
        }
    }
}
