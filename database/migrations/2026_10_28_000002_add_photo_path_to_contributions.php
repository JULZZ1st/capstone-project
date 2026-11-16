<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contributions') || Schema::hasColumn('contributions', 'photo_path')) {
            return;
        }

        Schema::table('contributions', function (Blueprint $table): void {
            $table->string('photo_path')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('contributions') || ! Schema::hasColumn('contributions', 'photo_path')) {
            return;
        }

        Schema::table('contributions', function (Blueprint $table): void {
            $table->dropColumn('photo_path');
        });
    }
};
