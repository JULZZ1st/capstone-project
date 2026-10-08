<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donors', function (Blueprint $table): void {
            if (! Schema::hasColumn('donors', 'barangay')) {
                $table->string('barangay')->nullable()->after('address');
            }
            if (! Schema::hasColumn('donors', 'city_municipality')) {
                $table->string('city_municipality')->nullable()->after('barangay');
            }
            if (! Schema::hasColumn('donors', 'province')) {
                $table->string('province')->nullable()->after('city_municipality');
            }
        });

        if (Schema::hasColumn('donors', 'barangay_area')) {
            DB::table('donors')
                ->whereNull('barangay')
                ->whereNotNull('barangay_area')
                ->update(['barangay' => DB::raw('barangay_area')]);
        }
    }

    public function down(): void
    {
        Schema::table('donors', function (Blueprint $table): void {
            $table->dropColumn(['barangay', 'city_municipality', 'province']);
        });
    }
};
