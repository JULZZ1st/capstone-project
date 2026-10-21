<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->repairDonors();
        $this->repairInventoryItems();
    }

    public function down(): void
    {
        // The repair only restores canonical destinations and preserves data.
        // It is intentionally not reversed by a later rollback.
    }

    private function repairDonors(): void
    {
        if (! Schema::hasTable('donors')) {
            $this->createDonorsTable();
        }

        $this->ensureDonorColumns();
        $this->ensureWorkspaceColumn('donors');

        if (! Schema::hasTable('legacy_donors')) {
            return;
        }

        foreach (DB::table('legacy_donors')->orderBy('id')->get() as $legacyDonor) {
            $fullName = trim((string) ($legacyDonor->full_name ?? ''));
            $firstName = trim((string) ($legacyDonor->first_name ?? ''));
            $lastName = trim((string) ($legacyDonor->last_name ?? ''));
            $contactPerson = trim((string) ($legacyDonor->contact_person ?? ''));

            if ($fullName === '') {
                $fullName = trim($firstName.' '.$lastName);
            }

            DB::table('donors')->insertOrIgnore([
                'id' => $legacyDonor->id,
                'donor_type' => $this->legacyValue($legacyDonor, 'donor_type') ?: ($contactPerson !== '' ? 'organization' : 'individual'),
                'first_name' => $firstName !== '' ? $firstName : null,
                'last_name' => $lastName !== '' ? $lastName : null,
                'full_name' => $fullName !== '' ? $fullName : 'Unknown donor',
                'contact_person' => $contactPerson !== '' ? $contactPerson : null,
                'address' => $this->legacyValue($legacyDonor, 'address') ?? '',
                'barangay' => $this->legacyValue($legacyDonor, 'barangay'),
                'city_municipality' => $this->legacyValue($legacyDonor, 'city_municipality'),
                'province' => $this->legacyValue($legacyDonor, 'province'),
                'barangay_area' => $this->legacyValue($legacyDonor, 'barangay_area'),
                'status' => $this->legacyValue($legacyDonor, 'status') ?: config('donors.defaults.status'),
                'contact_number' => $this->legacyValue($legacyDonor, 'contact_number') ?? '',
                'preferred_contact_method' => $this->legacyValue($legacyDonor, 'preferred_contact_method'),
                'email' => $this->legacyValue($legacyDonor, 'email'),
                'notes' => $this->legacyValue($legacyDonor, 'notes'),
                'created_at' => $legacyDonor->created_at ?? now(),
                'updated_at' => $legacyDonor->updated_at ?? now(),
            ]);
        }
    }

    private function repairInventoryItems(): void
    {
        if (! Schema::hasTable('inventory_items')) {
            if (
                Schema::hasTable('legacy_inventory_items')
                && Schema::hasColumn('legacy_inventory_items', 'current_quantity')
                && Schema::hasColumn('legacy_inventory_items', 'name')
            ) {
                Schema::rename('legacy_inventory_items', 'inventory_items');

                $this->ensureWorkspaceColumn('inventory_items');

                return;
            }

            $this->createInventoryItemsTable();
        }

        $this->ensureWorkspaceColumn('inventory_items');

        if (! Schema::hasTable('legacy_inventory_items')) {
            return;
        }

        foreach (DB::table('legacy_inventory_items')->orderBy('id')->get() as $legacyItem) {
            $name = trim((string) ($legacyItem->name ?? $legacyItem->item_name ?? ''));

            DB::table('inventory_items')->insertOrIgnore([
                'id' => $legacyItem->id,
                'name' => $name !== '' ? $name : 'Unnamed item',
                'category' => $this->legacyValue($legacyItem, 'category') ?: 'other',
                'inventory_subcategory_id' => $this->legacyValue($legacyItem, 'inventory_subcategory_id'),
                'unit' => $this->legacyValue($legacyItem, 'unit') ?: config('inventory.defaults.unit'),
                'quantity' => $legacyItem->quantity ?? 0,
                'current_quantity' => $legacyItem->current_quantity ?? $legacyItem->quantity ?? 0,
                'minimum_reserve_quantity' => $legacyItem->minimum_reserve_quantity ?? 0,
                'estimated_unit_value' => $this->legacyValue($legacyItem, 'estimated_unit_value'),
                'expires_at' => $this->legacyValue($legacyItem, 'expires_at'),
                'batch_number' => $this->legacyValue($legacyItem, 'batch_number'),
                'storage_location' => $this->legacyValue($legacyItem, 'storage_location') ?: 'Main Storage',
                'notes' => $this->legacyValue($legacyItem, 'notes'),
                'description' => $this->legacyValue($legacyItem, 'description'),
                'received_at' => $this->legacyValue($legacyItem, 'received_at'),
                'last_audited_at' => $this->legacyValue($legacyItem, 'last_audited_at'),
                'last_movement_at' => $this->legacyValue($legacyItem, 'last_movement_at') ?: $this->legacyValue($legacyItem, 'created_at'),
                'status' => $this->legacyValue($legacyItem, 'status') ?: config('inventory.defaults.status'),
                'created_by' => $this->legacyValue($legacyItem, 'created_by'),
                'updated_by' => $this->legacyValue($legacyItem, 'updated_by'),
                'created_at' => $legacyItem->created_at ?? now(),
                'updated_at' => $legacyItem->updated_at ?? now(),
            ]);
        }
    }

    private function createDonorsTable(): void
    {
        Schema::create('donors', function (Blueprint $table): void {
            $table->id();
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
            $table->string('status', 50)->default(config('donors.defaults.status'));
            $table->string('contact_number');
            $table->string('preferred_contact_method', 50)->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['donor_type', 'status']);
            $table->index('full_name');
            $table->index('contact_number');
            $table->index(['first_name', 'last_name']);
        });
    }

    private function ensureDonorColumns(): void
    {
        Schema::table('donors', function (Blueprint $table): void {
            if (! Schema::hasColumn('donors', 'donor_type')) {
                $table->string('donor_type', 50)->default(config('donors.defaults.type'));
            }
            if (! Schema::hasColumn('donors', 'first_name')) {
                $table->string('first_name')->nullable();
            }
            if (! Schema::hasColumn('donors', 'last_name')) {
                $table->string('last_name')->nullable();
            }
            if (! Schema::hasColumn('donors', 'contact_person')) {
                $table->string('contact_person')->nullable();
            }
            if (! Schema::hasColumn('donors', 'barangay')) {
                $table->string('barangay')->nullable();
            }
            if (! Schema::hasColumn('donors', 'city_municipality')) {
                $table->string('city_municipality')->nullable();
            }
            if (! Schema::hasColumn('donors', 'province')) {
                $table->string('province')->nullable();
            }
            if (! Schema::hasColumn('donors', 'barangay_area')) {
                $table->string('barangay_area')->nullable();
            }
            if (! Schema::hasColumn('donors', 'status')) {
                $table->string('status', 50)->default(config('donors.defaults.status'));
            }
            if (! Schema::hasColumn('donors', 'preferred_contact_method')) {
                $table->string('preferred_contact_method', 50)->nullable();
            }
        });
    }

    private function createInventoryItemsTable(): void
    {
        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->id();
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
            $table->text('notes')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('last_audited_at')->nullable();
            $table->timestamp('last_movement_at')->nullable();
            $table->string('status')->default(config('inventory.defaults.status'));
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('category');
            $table->index('status');
            $table->index('storage_location');
            $table->index('expires_at');
            $table->index('last_audited_at');
            $table->index('last_movement_at');
            $table->index(['category', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    private function ensureWorkspaceColumn(string $tableName): void
    {
        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'workspace_id')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->foreignId('workspace_id')->nullable()->after('id');
            $table->index('workspace_id');
            $table->foreign('workspace_id')->references('id')->on('workspaces')->nullOnDelete();
        });
    }

    private function legacyValue(object $record, string $column): mixed
    {
        return property_exists($record, $column) ? $record->{$column} : null;
    }
};
