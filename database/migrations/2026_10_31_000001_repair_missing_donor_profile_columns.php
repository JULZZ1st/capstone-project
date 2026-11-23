<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restore nullable profile columns if a database's schema drifted from its
     * recorded migration history. Existing donor data is left untouched.
     */
    public function up(): void
    {
        if (! Schema::hasTable('donors')) {
            return;
        }

        Schema::table('donors', function (Blueprint $table): void {
            if (! Schema::hasColumn('donors', 'middle_name')) {
                $table->string('middle_name')->nullable();
            }
            if (! Schema::hasColumn('donors', 'suffix')) {
                $table->string('suffix', 30)->nullable();
            }
            if (! Schema::hasColumn('donors', 'tin')) {
                $table->string('tin', 30)->nullable();
            }
            if (! Schema::hasColumn('donors', 'region')) {
                $table->string('region')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Intentionally forward-only: these nullable columns may predate this
        // repair, so rolling back must not remove donor profile data.
    }
};
