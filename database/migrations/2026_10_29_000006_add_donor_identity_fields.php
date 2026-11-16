<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('donors')) {
            throw new RuntimeException('Cannot add donor identity fields: donors table is missing.');
        }

        Schema::table('donors', function (Blueprint $table): void {
            if (! Schema::hasColumn('donors', 'middle_name')) {
                $table->string('middle_name')->nullable()->after('first_name');
            }
            if (! Schema::hasColumn('donors', 'suffix')) {
                $table->string('suffix')->nullable()->after('middle_name');
            }
            if (! Schema::hasColumn('donors', 'tin')) {
                $table->string('tin', 30)->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('donors')) {
            return;
        }

        Schema::table('donors', function (Blueprint $table): void {
            $columns = array_values(array_filter(
                ['middle_name', 'suffix', 'tin'],
                fn (string $column): bool => Schema::hasColumn('donors', $column),
            ));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
