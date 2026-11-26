<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cash_disbursements') || Schema::hasColumn('cash_disbursements', 'emergency_category')) {
            return;
        }

        Schema::table('cash_disbursements', function (Blueprint $table): void {
            $table->string('emergency_category')->default('Other Emergency')->after('purpose');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cash_disbursements') || ! Schema::hasColumn('cash_disbursements', 'emergency_category')) {
            return;
        }

        Schema::table('cash_disbursements', function (Blueprint $table): void {
            $table->dropColumn('emergency_category');
        });
    }
};
