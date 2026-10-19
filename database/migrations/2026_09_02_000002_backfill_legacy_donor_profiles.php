<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('legacy_donors')) {
            return;
        }

        if (! Schema::hasTable('donors')) {
            Schema::create('donors', function (Blueprint $table): void {
                $table->id();
                $table->string('full_name');
                $table->string('address');
                $table->string('contact_number');
                $table->string('email')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('donors', function (Blueprint $table): void {
            if (! Schema::hasColumn('donors', 'donor_type')) {
                $table->string('donor_type', 50)->default(config('donors.defaults.type'))->after('id');
            }
            if (! Schema::hasColumn('donors', 'contact_person')) {
                $table->string('contact_person')->nullable()->after('full_name');
            }
            if (! Schema::hasColumn('donors', 'first_name')) {
                $table->string('first_name')->nullable()->after('donor_type');
            }
            if (! Schema::hasColumn('donors', 'last_name')) {
                $table->string('last_name')->nullable()->after('first_name');
            }
        });

        $legacyHasStructuredProfile = Schema::hasColumn('legacy_donors', 'donor_type')
            || Schema::hasColumn('legacy_donors', 'first_name')
            || Schema::hasColumn('legacy_donors', 'last_name');

        foreach (DB::table('legacy_donors')->orderBy('id')->get() as $legacyDonor) {
            $legacyName = trim((string) ($legacyDonor->full_name ?? ''));
            $donor = DB::table('donors')->where('id', $legacyDonor->id)->first();

            if (! $donor) {
                continue;
            }

            $firstName = trim((string) ($legacyDonor->first_name ?? ''));
            $lastName = trim((string) ($legacyDonor->last_name ?? ''));
            $legacyContactPerson = trim((string) ($legacyDonor->contact_person ?? ''));
            $organization = $legacyHasStructuredProfile ? '' : $legacyContactPerson;
            $contactName = $legacyName !== ''
                ? $legacyName
                : trim($firstName.' '.$lastName);
            $nameParts = preg_split('/\s+/', $contactName, 2) ?: [];
            $updates = [];
            $donorName = trim((string) ($donor->full_name ?? ''));

            if (in_array($donorName, ['', 'Unknown donor'], true)) {
                $updates['full_name'] = $organization !== ''
                    ? $organization
                    : ($legacyName !== '' ? $legacyName : ($contactName !== '' ? $contactName : 'Unknown donor'));
            } elseif ($organization !== '' && $donorName === $contactName) {
                $updates['full_name'] = $organization;
            }
            if (trim((string) ($donor->first_name ?? '')) === '') {
                $updates['first_name'] = $firstName !== '' ? $firstName : ($nameParts[0] ?? 'Donor');
            }
            if (trim((string) ($donor->last_name ?? '')) === '') {
                $updates['last_name'] = $lastName !== '' ? $lastName : ($nameParts[1] ?? '');
            }
            if (($donor->contact_person ?? null) === null || trim((string) $donor->contact_person) === '') {
                if ($organization !== '') {
                    $updates['contact_person'] = $contactName !== '' ? $contactName : null;
                } elseif ($legacyContactPerson !== '') {
                    $updates['contact_person'] = $legacyContactPerson;
                }
            }
            if (trim((string) ($donor->donor_type ?? '')) === '') {
                $updates['donor_type'] = $organization !== ''
                    ? 'organization'
                    : (trim((string) ($legacyDonor->donor_type ?? '')) ?: 'individual');
            } elseif (
                $organization !== ''
                && trim((string) $donor->donor_type) === 'individual'
                && $donorName === $contactName
            ) {
                $updates['donor_type'] = 'organization';
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
