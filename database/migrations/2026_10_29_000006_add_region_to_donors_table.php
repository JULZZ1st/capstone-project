<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donors', function (Blueprint $table): void {
            if (! Schema::hasColumn('donors', 'region')) {
                $table->string('region')->nullable()->after('province');
            }
        });
    }

    public function down(): void
    {
        Schema::table('donors', function (Blueprint $table): void {
            if (Schema::hasColumn('donors', 'region')) {
                $table->dropColumn('region');
            }
        });
    }
};
