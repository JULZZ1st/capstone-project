<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reconciliation_discrepancies')) {
            throw new RuntimeException('Cannot add resolution details: reconciliation_discrepancies table is missing.');
        }
        if (! Schema::hasColumn('reconciliation_discrepancies', 'evidence_path')) {
            throw new RuntimeException('Cannot add resolution details: evidence_path must be added first.');
        }

        Schema::table('reconciliation_discrepancies', function (Blueprint $table): void {
            if (! Schema::hasColumn('reconciliation_discrepancies', 'resolution_explanation')) {
                $table->text('resolution_explanation')->nullable()->after('explanation');
            }
            if (! Schema::hasColumn('reconciliation_discrepancies', 'resolution_evidence_path')) {
                $table->string('resolution_evidence_path')->nullable()->after('evidence_path');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('reconciliation_discrepancies')) {
            return;
        }

        Schema::table('reconciliation_discrepancies', function (Blueprint $table): void {
            foreach (['resolution_evidence_path', 'resolution_explanation'] as $column) {
                if (Schema::hasColumn('reconciliation_discrepancies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
