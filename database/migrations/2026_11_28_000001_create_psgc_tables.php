<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('regions')) {
            Schema::create('regions', function (Blueprint $table): void {
            $table->string('code', 10)->primary();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->timestamps();
            });
        }

        if (! Schema::hasTable('provinces')) {
            Schema::create('provinces', function (Blueprint $table): void {
            $table->string('code', 10)->primary();
            $table->string('name');
            $table->string('region_code', 10);
            $table->timestamps();

            $table->index('region_code');
            });
        }

        if (! Schema::hasTable('cities')) {
            Schema::create('cities', function (Blueprint $table): void {
            $table->string('code', 10)->primary();
            $table->string('name');
            $table->string('region_code', 10);
            $table->string('province_code', 10)->nullable();
            $table->boolean('is_city')->default(false);
            $table->string('city_class')->nullable();
            $table->timestamps();

            $table->index(['region_code', 'province_code']);
            });
        }

        if (! Schema::hasTable('barangays')) {
            Schema::create('barangays', function (Blueprint $table): void {
            $table->string('code', 10)->primary();
            $table->string('name');
            $table->string('city_code', 10);
            $table->timestamps();

            $table->index('city_code');
            });
        }
    }

    public function down(): void
    {
        // These tables may be owned by the installed PSGC package. Never drop
        // them when rolling back this fallback migration.
    }
};
