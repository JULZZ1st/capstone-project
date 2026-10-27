<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contributions', function (Blueprint $table): void {
            $table->string('payment_method_name')->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('contributions', function (Blueprint $table): void {
            $table->dropColumn('payment_method_name');
        });
    }
};
