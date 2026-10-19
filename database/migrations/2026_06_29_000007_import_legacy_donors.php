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

        foreach (DB::table('legacy_donors')->orderBy('id')->get() as $donor) {
            $fullName = trim((string) ($donor->full_name ?? ''));

            if ($fullName === '') {
                $firstName = trim((string) ($donor->first_name ?? ''));
                $lastName = trim((string) ($donor->last_name ?? ''));
                $fullName = trim($firstName.' '.$lastName);
            }

            DB::table('donors')->insertOrIgnore([
                'id' => $donor->id,
                'full_name' => $fullName !== '' ? $fullName : 'Unknown donor',
                'address' => $donor->address ?? '',
                'contact_number' => $donor->contact_number ?? '',
                'email' => $donor->email ?? null,
                'notes' => null,
                'created_at' => $donor->created_at,
                'updated_at' => $donor->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('legacy_donors')) {
            DB::table('donors')->whereIn('id', DB::table('legacy_donors')->select('id'))->delete();
        }
    }
};
