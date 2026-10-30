<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contribution_items', function (Blueprint $table): void {
            $table->string('category_description', 255)->nullable()->after('category');
            $table->date('expiration_date')->nullable()->after('condition');
            $table->string('brand', 150)->nullable()->after('expiration_date');
            $table->string('model', 150)->nullable()->after('brand');
            $table->string('serial_number', 150)->nullable()->after('model');
        });
    }

    public function down(): void
    {
        Schema::table('contribution_items', function (Blueprint $table): void {
            $table->dropColumn(['category_description', 'expiration_date', 'brand', 'model', 'serial_number']);
        });
    }
};
