<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $existingIndexes = collect(Schema::getIndexes('donors'))
            ->pluck('name')
            ->all();

        Schema::table('donors', function (Blueprint $table) use ($existingIndexes) {
            if (! Schema::hasColumn('donors', 'donor_type')) {
                $table->string('donor_type', 50)->default(config('donors.defaults.type'))->after('id');
            }
            if (! Schema::hasColumn('donors', 'contact_person')) {
                $table->string('contact_person')->nullable()->after('full_name');
            }
            if (! Schema::hasColumn('donors', 'preferred_contact_method')) {
                $table->string('preferred_contact_method', 50)->nullable()->after('contact_number');
            }
            if (! Schema::hasColumn('donors', 'barangay_area')) {
                $table->string('barangay_area')->nullable()->after('address');
            }
            if (! Schema::hasColumn('donors', 'status')) {
                $table->string('status', 50)->default(config('donors.defaults.status'))->after('barangay_area');
            }

            if (! in_array('donors_donor_type_status_index', $existingIndexes, true)) {
                $table->index(['donor_type', 'status']);
            }
            if (! in_array('donors_full_name_index', $existingIndexes, true)) {
                $table->index('full_name');
            }
            if (! in_array('donors_contact_number_index', $existingIndexes, true)) {
                $table->index('contact_number');
            }
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
