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
            $donor = DB::table('donors')->where('id', $legacyDonor->id)->first();

            if (! $donor) {
                continue;
            }

            $fullName = trim((string) ($legacyDonor->full_name ?? ''));
            $firstName = trim((string) ($legacyDonor->first_name ?? ''));
            $lastName = trim((string) ($legacyDonor->last_name ?? ''));
            $contactPerson = trim((string) ($legacyDonor->contact_person ?? ''));
            $updates = [];

            if (in_array(trim((string) $donor->full_name), ['', 'Unknown donor'], true)) {
                $updates['full_name'] = $fullName !== '' ? $fullName : 'Unknown donor';
            }
            if (trim((string) ($donor->first_name ?? '')) === '') {
                $updates['first_name'] = $firstName !== '' ? $firstName : 'Donor';
            }
            if (trim((string) ($donor->last_name ?? '')) === '') {
                $updates['last_name'] = $lastName;
            }
            if (($donor->contact_person ?? null) === null && $contactPerson !== '') {
                $updates['contact_person'] = $contactPerson;
            }
            if (trim((string) ($donor->donor_type ?? '')) === '') {
                $updates['donor_type'] = $legacyDonor->donor_type ?: 'individual';
            }

            if ($updates !== []) {
                DB::table('donors')->where('id', $legacyDonor->id)->update($updates);
            }
        }
    }

    public function down(): void
    {
    }
};
