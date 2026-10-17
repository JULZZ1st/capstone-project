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

        foreach (DB::table('legacy_donors')->orderBy('id')->get() as $legacyDonor) {
            $legacyName = trim((string) ($legacyDonor->full_name ?? ''));
            $organization = trim((string) ($legacyDonor->contact_person ?? ''));
            $contactName = $legacyName !== '' ? $legacyName : ($organization !== '' ? $organization : 'Donor');
            $displayName = $organization !== '' ? $organization : $legacyName;
            $nameParts = preg_split('/\s+/', $contactName, 2);

            DB::table('donors')->where('id', $legacyDonor->id)->update([
                'full_name' => $displayName !== '' ? $displayName : 'Unknown donor',
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