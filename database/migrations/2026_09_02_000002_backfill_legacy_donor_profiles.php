<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('legacy_donors')) {
            return;
        }

        foreach (DB::table('legacy_donors')->orderBy('id')->get() as $legacyDonor) {
            $legacyName = trim((string) $legacyDonor->name);
            $organization = trim((string) $legacyDonor->organization);
            $contactName = $legacyName;
            $displayName = $organization !== '' ? $organization : $legacyName;
            $nameParts = preg_split('/\s+/', $contactName, 2);

            DB::table('donors')->where('id', $legacyDonor->id)->update([
                'full_name' => $displayName ?: 'Unknown donor',
                'first_name' => $nameParts[0] ?: 'Donor',
                'last_name' => $nameParts[1] ?? '',
                'contact_person' => $organization !== '' ? $legacyName : null,
                'donor_type' => $organization !== '' ? 'organization' : 'individual',
            ]);
        }
    }

    public function down(): void
    {
    }
};