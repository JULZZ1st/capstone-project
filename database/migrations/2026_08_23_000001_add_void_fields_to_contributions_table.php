<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contributions', function (Blueprint $table) {
            if (! Schema::hasColumn('contributions', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->index();
            }

            if (! Schema::hasColumn('contributions', 'voided_by')) {
                $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('contributions', 'void_reason')) {
                $table->text('void_reason')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('contributions', function (Blueprint $table) {
            if (Schema::hasColumn('contributions', 'voided_by')) {
                $table->dropConstrainedForeignId('voided_by');
            }

            $columns = [];

            foreach (['voided_at', 'void_reason'] as $column) {
                if (Schema::hasColumn('contributions', $column)) {
                    $columns[] = $column;
                }
            }

            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};