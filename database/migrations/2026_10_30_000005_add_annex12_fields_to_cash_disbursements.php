<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cash_disbursements')) {
            throw new RuntimeException('Cannot add Annex 12 fields: cash_disbursements table is missing.');
        }

        Schema::table('cash_disbursements', function (Blueprint $table): void {
            if (! Schema::hasColumn('cash_disbursements', 'payee_address')) {
                $table->string('payee_address')->nullable()->after('recipient');
            }
            if (! Schema::hasColumn('cash_disbursements', 'payee_tin')) {
                $table->string('payee_tin', 30)->nullable()->after('payee_address');
            }
            if (! Schema::hasColumn('cash_disbursements', 'fund_source')) {
                $table->string('fund_source')->nullable()->after('payee_tin');
            }
            if (! Schema::hasColumn('cash_disbursements', 'obr_number')) {
                $table->string('obr_number')->nullable()->after('fund_source');
            }
            if (! Schema::hasColumn('cash_disbursements', 'dv_number')) {
                $table->string('dv_number', 30)->nullable()->after('obr_number');
            }
            if (! Schema::hasColumn('cash_disbursements', 'treasurer_certified_by')) {
                $table->foreignId('treasurer_certified_by')->nullable()->after('dv_number')->constrained('users')->nullOnDelete();
            } elseif (! $this->hasForeignKey('cash_disbursements', 'cash_disbursements_treasurer_certified_by_foreign')) {
                $table->foreign('treasurer_certified_by', 'cash_disbursements_treasurer_certified_by_foreign')
                    ->references('id')->on('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('cash_disbursements', 'treasurer_certified_at')) {
                $table->timestamp('treasurer_certified_at')->nullable()->after('treasurer_certified_by');
            }
            if (! Schema::hasColumn('cash_disbursements', 'treasurer_certification_notes')) {
                $table->text('treasurer_certification_notes')->nullable()->after('treasurer_certified_at');
            }
            if (! Schema::hasIndex('cash_disbursements', ['workspace_id', 'dv_number'], 'unique')) {
                $table->unique(['workspace_id', 'dv_number']);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cash_disbursements')) {
            return;
        }

        Schema::table('cash_disbursements', function (Blueprint $table): void {
            if (Schema::hasIndex('cash_disbursements', ['workspace_id', 'dv_number'], 'unique')) {
                $table->dropUnique(['workspace_id', 'dv_number']);
            }
            if ($this->hasForeignKey('cash_disbursements', 'cash_disbursements_treasurer_certified_by_foreign')) {
                $table->dropForeign('cash_disbursements_treasurer_certified_by_foreign');
            }
            $columns = array_values(array_filter([
                'payee_address', 'payee_tin', 'fund_source', 'obr_number', 'dv_number',
                'treasurer_certified_by', 'treasurer_certified_at', 'treasurer_certification_notes',
            ], fn (string $column): bool => Schema::hasColumn('cash_disbursements', $column)));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    private function hasForeignKey(string $table, string $name): bool
    {
        return collect(Schema::getForeignKeys($table))
            ->contains(fn (array $foreignKey): bool => ($foreignKey['name'] ?? null) === $name);
    }
};
