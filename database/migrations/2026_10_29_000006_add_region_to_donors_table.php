<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('donors')) {
            throw new RuntimeException('Cannot add donor region: donors table is missing.');
        }

        Schema::table('donors', function (Blueprint $table): void {
            if (! Schema::hasColumn('donors', 'region')) {
                $table->string('region')->nullable()->after('province');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('donors')) {
            return;
        }

        Schema::table('donors', function (Blueprint $table): void {
            if (Schema::hasColumn('donors', 'region')) {
                $table->dropColumn('region');
            }
        });
    }
};
