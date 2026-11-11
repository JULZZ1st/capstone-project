<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        if (! Schema::hasTable('contributions')
            || ! Schema::hasTable('donors')
            || ! Schema::hasColumn('contributions', 'donor_id')) {
            return;
        }

        $constraints = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'contributions')
            ->where('COLUMN_NAME', 'donor_id')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->get(['CONSTRAINT_NAME', 'REFERENCED_TABLE_NAME']);

        if ($constraints->contains(fn ($constraint): bool => $constraint->REFERENCED_TABLE_NAME === 'donors')) {
            return;
        }

        if ($constraints->count() > 1) {
            throw new \RuntimeException('Ambiguous contributions donor foreign-key state; refusing to alter constraints.');
        }

        $constraint = $constraints->first();
        if ($constraint !== null) {
            $name = (string) $constraint->CONSTRAINT_NAME;

            if (! preg_match('/^[A-Za-z0-9_]+$/', $name)) {
                throw new \RuntimeException('Unexpected contributions donor foreign-key name.');
            }

            DB::statement("ALTER TABLE `contributions` DROP FOREIGN KEY `{$name}`");
        }

        $referencesDonors = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'contributions')
            ->where('COLUMN_NAME', 'donor_id')
            ->where('REFERENCED_TABLE_NAME', 'donors')
            ->exists();

        if (! $referencesDonors) {
            Schema::table('contributions', function (Blueprint $table): void {
                $table->foreign('donor_id', 'contributions_donor_id_foreign')
                    ->references('id')
                    ->on('donors')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Intentionally data-preserving. The application must continue to use
        // the workspace-owned donors table after this repair.
    }
};
