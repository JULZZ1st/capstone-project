<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contributions') || Schema::hasColumn('contributions', 'payment_method_name')) {
            return;
        }

        Schema::table('contributions', function (Blueprint $table): void {
            $table->string('payment_method_name')->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('contributions', 'payment_method_name')) {
            Schema::table('contributions', function (Blueprint $table): void {
                $table->dropColumn('payment_method_name');
            });
        }
    }
};
