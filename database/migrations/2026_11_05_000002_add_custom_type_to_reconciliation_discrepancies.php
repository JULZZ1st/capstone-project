<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('reconciliation_discrepancies', 'custom_type')) {
            Schema::table('reconciliation_discrepancies', function (Blueprint $table): void {
                $table->string('custom_type', 120)->nullable()->after('type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('reconciliation_discrepancies', 'custom_type')) {
            Schema::table('reconciliation_discrepancies', function (Blueprint $table): void {
                $table->dropColumn('custom_type');
            });
        }
    }
};
