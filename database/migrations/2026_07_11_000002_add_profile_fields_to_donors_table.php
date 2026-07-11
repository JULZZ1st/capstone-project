<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donors', function (Blueprint $table) {
            $table->string('donor_type', 50)->default(config('donors.defaults.type'))->after('id');
            $table->string('contact_person')->nullable()->after('full_name');
            $table->string('preferred_contact_method', 50)->nullable()->after('contact_number');
            $table->string('barangay_area')->nullable()->after('address');
            $table->string('status', 50)->default(config('donors.defaults.status'))->after('barangay_area');

            $table->index(['donor_type', 'status']);
            $table->index('full_name');
            $table->index('contact_number');
        });
    }

    public function down(): void
    {
        Schema::table('donors', function (Blueprint $table) {
            $table->dropIndex(['donor_type', 'status']);
            $table->dropIndex(['full_name']);
            $table->dropIndex(['contact_number']);
            $table->dropColumn([
                'donor_type',
                'contact_person',
                'preferred_contact_method',
                'barangay_area',
                'status',
            ]);
        });
    }
};
