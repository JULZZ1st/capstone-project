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

        Schema::table('contributions', function (Blueprint $table) {
            $table->string('payment_method_name')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('contributions') || ! Schema::hasColumn('contributions', 'payment_method_name')) {
            return;
        }

        Schema::table('contributions', function (Blueprint $table) {
            $table->dropColumn('payment_method_name');
        });
    }
};
