<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('donors')) {
            $this->createDonorsTable();
        }
        $this->createDonorIndexes();
        $this->importLegacyDonors();

        if (! Schema::hasTable('inventory_items')) {
            $this->createInventoryItemsTable();
        }
        $this->createInventoryItemIndexes();
        $this->importLegacyInventoryItems();
    }

    public function down(): void
    {
        // Recovered tables and imported records must be retained on rollback.
    }

    private function createDonorsTable(): void
    {
        Schema::create('donors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->string('donor_type', 50)->default(config('donors.defaults.type'));
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('full_name');
            $table->string('contact_person')->nullable();
            $table->string('address');
            $table->string('barangay')->nullable();
            $table->string('city_municipality')->nullable();
            $table->string('province')->nullable();
            $table->string('barangay_area')->nullable();
            $table->string('contact_number');
            $table->string('preferred_contact_method', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('status', 50)->default(config('donors.defaults.status'));
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    private function createDonorIndexes(): void
    {
        if (! Schema::hasIndex('donors', ['donor_type', 'status'])) {
            Schema::table('donors', fn (Blueprint $table) => $table->index(
                ['donor_type', 'status'],
                'restored_donors_type_status_index',
            ));
        }
        if (! Schema::hasIndex('donors', ['full_name'])) {
            Schema::table('donors', fn (Blueprint $table) => $table->index('full_name', 'restored_donors_full_name_index'));
        }
        if (! Schema::hasIndex('donors', ['contact_number'])) {
            Schema::table('donors', fn (Blueprint $table) => $table->index('contact_number', 'restored_donors_contact_number_index'));
        }
    }

    private function importLegacyDonors(): void
    {
        if (! Schema::hasTable('legacy_donors')) {
            return;
        }

        foreach (DB::table('legacy_donors')->orderBy('id')->get() as $legacyDonor) {
            $fullName = trim((string) ($legacyDonor->full_name ?? ''));
            $firstName = trim((string) ($legacyDonor->first_name ?? ''));
            $lastName = trim((string) ($legacyDonor->last_name ?? ''));

            if ($fullName === '') {
                $fullName = trim($firstName.' '.$lastName);
            }

            $nameParts = preg_split('/\s+/', $fullName, 2) ?: [];

            DB::table('donors')->insertOrIgnore([
                'id' => $legacyDonor->id,
                'donor_type' => $legacyDonor->donor_type ?? config('donors.defaults.type'),
                'first_name' => $firstName !== '' ? $firstName : ($nameParts[0] ?? null),
                'last_name' => $lastName !== '' ? $lastName : ($nameParts[1] ?? null),
                'full_name' => $fullName !== '' ? $fullName : 'Unknown donor',
                'contact_person' => $legacyDonor->contact_person ?? null,
                'address' => $legacyDonor->address ?? '',
                'barangay' => $legacyDonor->barangay ?? null,
                'city_municipality' => $legacyDonor->city_municipality ?? null,
                'province' => $legacyDonor->province ?? null,
                'barangay_area' => $legacyDonor->barangay_area ?? null,
                'contact_number' => $legacyDonor->contact_number ?? '',
                'preferred_contact_method' => $legacyDonor->preferred_contact_method ?? null,
                'email' => $legacyDonor->email ?? null,
                'status' => $legacyDonor->status ?? config('donors.defaults.status'),
                'notes' => $legacyDonor->notes ?? null,
                'created_at' => $legacyDonor->created_at ?? now(),
                'updated_at' => $legacyDonor->updated_at ?? now(),
            ]);
        }
    }

    private function createInventoryItemsTable(): void
    {
        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('category');
            $table->foreignId('inventory_subcategory_id')->nullable()->constrained('inventory_subcategories')->nullOnDelete();
            $table->string('unit')->default(config('inventory.defaults.unit'));
            $table->integer('quantity')->default(0);
            $table->decimal('current_quantity', 12, 2)->default(0);
            $table->decimal('minimum_reserve_quantity', 12, 2)->default(0);
            $table->decimal('estimated_unit_value', 12, 2)->nullable();
            $table->date('expires_at')->nullable();
            $table->string('batch_number', 100)->nullable();
            $table->string('storage_location')->default('Main Storage');
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('status')->default(config('inventory.defaults.status'));
            $table->timestamp('last_audited_at')->nullable();
            $table->timestamp('last_movement_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    private function createInventoryItemIndexes(): void
    {
        foreach ([
            [['category'], 'restored_inventory_items_category_index'],
            [['status'], 'restored_inventory_items_status_index'],
            [['storage_location'], 'restored_inventory_items_storage_location_index'],
            [['expires_at'], 'restored_inventory_items_expires_at_index'],
            [['last_audited_at'], 'restored_inventory_items_last_audited_at_index'],
            [['last_movement_at'], 'restored_inventory_items_last_movement_at_index'],
        ] as [$columns, $indexName]) {
            if (! Schema::hasIndex('inventory_items', $columns)) {
                $column = $columns[0];
                Schema::table('inventory_items', fn (Blueprint $table) => $table->index($column, $indexName));
            }
        }

        foreach ([
            [['category', 'status'], 'restored_inventory_items_category_status_index'],
            [['status', 'expires_at'], 'restored_inventory_items_status_expires_at_index'],
        ] as [$columns, $indexName]) {
            if (! Schema::hasIndex('inventory_items', $columns)) {
                Schema::table('inventory_items', fn (Blueprint $table) => $table->index($columns, $indexName));
            }
        }
    }

    private function importLegacyInventoryItems(): void
    {
        if (! Schema::hasTable('legacy_inventory_items')) {
            return;
        }

        foreach (DB::table('legacy_inventory_items')->orderBy('id')->get() as $legacyItem) {
            $quantity = $legacyItem->quantity ?? 0;

            DB::table('inventory_items')->insertOrIgnore([
                'id' => $legacyItem->id,
                'name' => $legacyItem->item_name ?: 'Unnamed item',
                'category' => $this->category($legacyItem->category ?? null),
                'unit' => $this->unit($legacyItem->unit ?? null),
                'quantity' => $quantity,
                'current_quantity' => $quantity,
                'minimum_reserve_quantity' => 0,
                'storage_location' => 'Main Storage',
                'status' => config('inventory.defaults.status'),
                'last_movement_at' => $legacyItem->created_at,
                'created_at' => $legacyItem->created_at,
                'updated_at' => $legacyItem->updated_at,
            ]);
        }
    }

    private function category(?string $category): string
    {
        $category = strtolower(trim((string) $category));
        $category = $category === 'medicine' ? 'medical_supplies' : $category;

        return array_key_exists($category, config('inventory.categories')) ? $category : 'other';
    }

    private function unit(?string $unit): string
    {
        $unit = strtolower(trim((string) $unit));
        $unit = [
            'pcs' => 'piece',
            'sacks' => 'sack',
            'boxes' => 'box',
            'sets' => 'set',
        ][$unit] ?? $unit;

        return array_key_exists($unit, config('inventory.units')) ? $unit : 'other';
    }
};
