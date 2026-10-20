<?php

use App\Services\Workspaces\WorkspaceBackfill;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(WorkspaceBackfill::class)->run();
    }

    public function down(): void
    {
        // Workspace ownership is intentionally retained on rollback. The preceding
        // schema migration removes the nullable columns without deleting records.
    }
};
