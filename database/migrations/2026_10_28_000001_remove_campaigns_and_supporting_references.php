<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Campaigns and supporting references are no longer exposed by the current
        // workflows, but their schema and historical records must remain intact.
        // This migration is intentionally a no-op for existing installations.
    }

    public function down(): void
    {
        // See up(): no schema change was made.
    }
};
