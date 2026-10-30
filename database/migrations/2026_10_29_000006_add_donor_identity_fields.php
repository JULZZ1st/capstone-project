<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donors', function (Blueprint $table): void {
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('suffix')->nullable()->after('middle_name');
            $table->string('tin', 30)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('donors', function (Blueprint $table): void {
            $table->dropColumn(['middle_name', 'suffix', 'tin']);
        });
    }
};
