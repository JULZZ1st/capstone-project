<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class RepairMissingBusinessTables extends Command
{
    protected $signature = 'db:repair-missing-business-tables
        {--database= : Database connection name}
        {--apply : Create missing tables after all safety checks pass}';

    protected $description = 'Safely reconstruct missing donors and inventory_items tables before pending migrations';

    private const DONOR_COLUMNS = [
        'id', 'donor_type', 'first_name', 'last_name', 'full_name', 'contact_person',
        'address', 'barangay', 'city_municipality', 'province', 'barangay_area',
        'status', 'contact_number', 'preferred_contact_method', 'email', 'notes',
        'created_at', 'updated_at',
    ];

    private const INVENTORY_COLUMNS = [
        'id', 'name', 'category', 'inventory_subcategory_id', 'unit', 'quantity',
        'current_quantity', 'minimum_reserve_quantity', 'estimated_unit_value',
        'expires_at', 'batch_number', 'storage_location', 'notes', 'description',
        'received_at', 'last_audited_at', 'last_movement_at', 'status', 'created_by',
        'updated_by', 'created_at', 'updated_at',
    ];

    public function handle(): int
    {
        $connection = $this->option('database') ?: config('database.default');
        $this->setConnection($connection);

        $database = DB::connection()->getDatabaseName();
        $this->info("Repair target: {$database} (connection {$connection})");
        $this->preflight($database);

        if (! $this->option('apply')) {
            $this->warn('Preflight passed. No tables were changed. Re-run with --apply to execute.');

            return self::SUCCESS;
        }

        $this->repairDonors();
        $this->repairInventoryItems();
        $this->validateFinalSchemas($database);

        $this->info('Missing business-table repair completed successfully.');

        return self::SUCCESS;
    }

    private function setConnection(string $connection): void
    {
        config(['database.default' => $connection]);
        DB::purge($connection);
        DB::setDefaultConnection($connection);
    }

    private function preflight(string $database): void
    {
        foreach (['migrations', 'users', 'inventory_subcategories'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Required prerequisite table is missing: {$table}");
            }
        }

        if (Schema::hasTable('workspaces')) {
            throw new RuntimeException('workspaces already exists; run this repair before workspace migrations.');
        }

        $workspaceMigrations = DB::table('migrations')
            ->whereIn('migration', [
                '2026_10_17_000001_create_workspaces_and_memberships',
                '2026_10_17_000003_add_workspace_ids_to_business_tables',
            ])
            ->pluck('migration')
            ->all();

        if ($workspaceMigrations !== []) {
            throw new RuntimeException('Workspace migrations already ran; refusing to reconstruct pre-workspace tables.');
        }

        foreach (['donors', 'inventory_items'] as $table) {
            if (Schema::hasColumn($table, 'workspace_id')) {
                throw new RuntimeException("{$table}.workspace_id already exists; refusing to apply out-of-order repair.");
            }
        }

        $this->validateIfPresent('donors', self::DONOR_COLUMNS, $this->donorIndexes(), []);
        $this->validateIfPresent('inventory_items', self::INVENTORY_COLUMNS, $this->inventoryIndexes(), $this->inventoryForeignKeys());

        $this->line("Preflight schema checks passed for {$database}.");
    }

    private function repairDonors(): void
    {
        if (! Schema::hasTable('donors')) {
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
    }

    private function repairInventoryItems(): void
    {
        if (! Schema::hasTable('inventory_items')) {
            Schema::create('inventory_items', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('category');
                $table->unsignedBigInteger('inventory_subcategory_id')->nullable();
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
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->foreign('inventory_subcategory_id', 'repair_inventory_items_subcategory_fk')
                    ->references('id')->on('inventory_subcategories')->nullOnDelete();
                $table->foreign('created_by', 'repair_inventory_items_created_by_fk')
                    ->references('id')->on('users')->nullOnDelete();
                $table->foreign('updated_by', 'repair_inventory_items_updated_by_fk')
                    ->references('id')->on('users')->nullOnDelete();

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
    }

    private function validateFinalSchemas(string $database): void
    {
        $this->validateIfPresent('donors', self::DONOR_COLUMNS, $this->donorIndexes(), []);
        $this->validateIfPresent('inventory_items', self::INVENTORY_COLUMNS, $this->inventoryIndexes(), $this->inventoryForeignKeys());
        $this->line("Validated repaired schemas in {$database}; no records were inserted.");
    }

    private function validateIfPresent(string $table, array $columns, array $indexes, array $foreignKeys): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $actualColumns = Schema::getColumnListing($table);
        sort($actualColumns);
        $expectedColumns = $columns;
        sort($expectedColumns);

        if ($actualColumns !== $expectedColumns) {
            throw new RuntimeException("Unexpected columns in {$table}: expected ".implode(',', $expectedColumns).'; found '.implode(',', $actualColumns));
        }

        $actualIndexes = collect(Schema::getIndexes($table))
            ->pluck('name')
            ->map(fn (string $name): string => strtolower($name))
            ->sort()
            ->values()
            ->all();
        $expectedIndexes = collect(array_merge(['PRIMARY'], $indexes))
            ->map(fn (string $name): string => strtolower($name))
            ->sort()
            ->values()
            ->all();

        if ($actualIndexes !== $expectedIndexes) {
            throw new RuntimeException("Unexpected indexes in {$table}: expected ".implode(',', $expectedIndexes).'; found '.implode(',', $actualIndexes));
        }

        foreach ($foreignKeys as [$column, $referencedTable, $referencedColumn]) {
            $exists = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->where('COLUMN_NAME', $column)
                ->where('REFERENCED_TABLE_NAME', $referencedTable)
                ->where('REFERENCED_COLUMN_NAME', $referencedColumn)
                ->exists();

            if (! $exists) {
                throw new RuntimeException("Missing foreign key {$table}.{$column} -> {$referencedTable}.{$referencedColumn}");
            }
        }
    }

    private function donorIndexes(): array
    {
        return [
            'donors_donor_type_status_index',
            'donors_full_name_index',
            'donors_contact_number_index',
            'donors_first_name_last_name_index',
        ];
    }

    private function inventoryIndexes(): array
    {
        return [
            'inventory_items_category_index',
            'inventory_items_status_index',
            'inventory_items_storage_location_index',
            'inventory_items_expires_at_index',
            'inventory_items_last_audited_at_index',
            'inventory_items_last_movement_at_index',
            'inventory_items_category_status_index',
            'inventory_items_status_expires_at_index',
            'repair_inventory_items_subcategory_fk',
            'repair_inventory_items_created_by_fk',
            'repair_inventory_items_updated_by_fk',
        ];
    }

    private function inventoryForeignKeys(): array
    {
        return [
            ['inventory_subcategory_id', 'inventory_subcategories', 'id'],
            ['created_by', 'users', 'id'],
            ['updated_by', 'users', 'id'],
        ];
    }
}
