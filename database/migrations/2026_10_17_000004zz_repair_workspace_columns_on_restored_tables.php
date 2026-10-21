<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['donors', 'inventory_items'] as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'workspace_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('workspace_id')->nullable()->after('id');
                $table->index('workspace_id');
                $table->foreign('workspace_id')->references('id')->on('workspaces')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(['donors', 'inventory_items']) as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'workspace_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['workspace_id']);
                $table->dropIndex(['workspace_id']);
                $table->dropColumn('workspace_id');
            });
        }
    }
};
