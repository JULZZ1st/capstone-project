<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_disbursements', function (Blueprint $table): void {
            if (! Schema::hasColumn('cash_disbursements', 'province')) {
                $table->string('province')->nullable()->after('payee_address');
            }

            if (! Schema::hasColumn('cash_disbursements', 'city_municipality')) {
                $table->string('city_municipality')->nullable()->after('province');
            }

            if (! Schema::hasColumn('cash_disbursements', 'barangay')) {
                $table->string('barangay')->nullable()->after('city_municipality');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cash_disbursements', function (Blueprint $table): void {
            foreach (['barangay', 'city_municipality', 'province'] as $column) {
                if (Schema::hasColumn('cash_disbursements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
