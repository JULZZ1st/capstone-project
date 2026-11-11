<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contribution_items')) {
            throw new RuntimeException('Cannot add item detail fields: contribution_items table is missing.');
        }

        Schema::table('contribution_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('contribution_items', 'category_description')) {
                $table->string('category_description', 255)->nullable()->after('category');
            }
            if (! Schema::hasColumn('contribution_items', 'expiration_date')) {
                $table->date('expiration_date')->nullable()->after('condition');
            }
            if (! Schema::hasColumn('contribution_items', 'brand')) {
                $table->string('brand', 150)->nullable()->after('expiration_date');
            }
            if (! Schema::hasColumn('contribution_items', 'model')) {
                $table->string('model', 150)->nullable()->after('brand');
            }
            if (! Schema::hasColumn('contribution_items', 'serial_number')) {
                $table->string('serial_number', 150)->nullable()->after('model');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('contribution_items')) {
            return;
        }

        Schema::table('contribution_items', function (Blueprint $table): void {
            $columns = array_values(array_filter(
                ['category_description', 'expiration_date', 'brand', 'model', 'serial_number'],
                fn (string $column): bool => Schema::hasColumn('contribution_items', $column),
            ));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
