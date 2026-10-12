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

        foreach (DB::table('legacy_donors')->orderBy('id')->get() as $donor) {
            DB::table('donors')->insertOrIgnore([
                'id' => $donor->id,
                'full_name' => $donor->name ?: 'Unknown donor',
                'address' => $donor->address ?? '',
                'contact_number' => $donor->contact_number ?? '',
                'email' => null,
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