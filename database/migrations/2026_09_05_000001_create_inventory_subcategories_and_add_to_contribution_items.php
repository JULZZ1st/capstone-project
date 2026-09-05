<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_category_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['inventory_category_id', 'key']);
            $table->index(['inventory_category_id', 'is_active']);
        });

        Schema::table('contribution_items', function (Blueprint $table) {
            $table->string('subcategory')->nullable()->after('category')->index();
        });

        $now = now();
        $categoryIds = DB::table('inventory_categories')->pluck('id', 'key');
        $rows = [];

        foreach (config('inventory.subcategories', []) as $categoryKey => $subcategories) {
            if (! $categoryIds->has($categoryKey)) {
                continue;
            }

            foreach ($subcategories as $key => $label) {
                $rows[] = [
                    'inventory_category_id' => $categoryIds[$categoryKey],
                    'key' => $key,
                    'label' => $label,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows !== []) {
            DB::table('inventory_subcategories')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::table('contribution_items', function (Blueprint $table) {
            $table->dropIndex(['subcategory']);
            $table->dropColumn('subcategory');
        });

        Schema::dropIfExists('inventory_subcategories');
    }
};
