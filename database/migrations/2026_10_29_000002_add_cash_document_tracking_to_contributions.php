<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contributions')) {
            throw new RuntimeException('Cannot add cash document tracking: contributions table is missing.');
        }
        if (! Schema::hasTable('contribution_evidence')) {
            throw new RuntimeException('Cannot add cash document tracking: contribution_evidence table is missing.');
        }

        Schema::table('contributions', function (Blueprint $table): void {
            if (! Schema::hasColumn('contributions', 'certificate_requested')) {
                $table->boolean('certificate_requested')->default(false)->after('official_receipt_number');
            }
            if (! Schema::hasColumn('contributions', 'certificate_status')) {
                $table->string('certificate_status', 30)->default('not_requested')->after('certificate_requested');
            }
            if (! Schema::hasColumn('contributions', 'certificate_issued_by')) {
                $table->foreignId('certificate_issued_by')->nullable()->after('certificate_status')->constrained('users')->nullOnDelete();
            } elseif (! $this->hasForeignKey('contributions', 'contributions_certificate_issued_by_foreign')) {
                $table->foreign('certificate_issued_by', 'contributions_certificate_issued_by_foreign')
                    ->references('id')->on('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('contributions', 'certificate_issued_at')) {
                $table->timestamp('certificate_issued_at')->nullable()->after('certificate_issued_by');
            }
            if (! Schema::hasIndex('contributions', ['certificate_requested', 'certificate_status'])) {
                $table->index(['certificate_requested', 'certificate_status']);
            }
        });

        Schema::table('contribution_evidence', function (Blueprint $table): void {
            if (! Schema::hasColumn('contribution_evidence', 'document_type')) {
                $table->string('document_type', 50)->default('supporting_evidence')->after('contribution_id');
            }
            if (! Schema::hasIndex('contribution_evidence', ['contribution_id', 'document_type'])) {
                $table->index(['contribution_id', 'document_type']);
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('contribution_evidence')) {
            Schema::table('contribution_evidence', function (Blueprint $table): void {
                if (Schema::hasIndex('contribution_evidence', ['contribution_id', 'document_type'])) {
                    $table->dropIndex(['contribution_id', 'document_type']);
                }
                if (Schema::hasColumn('contribution_evidence', 'document_type')) {
                    $table->dropColumn('document_type');
                }
            });
        }

        if (Schema::hasTable('contributions')) {
            Schema::table('contributions', function (Blueprint $table): void {
                if (Schema::hasIndex('contributions', ['certificate_requested', 'certificate_status'])) {
                    $table->dropIndex(['certificate_requested', 'certificate_status']);
                }
                if ($this->hasForeignKey('contributions', 'contributions_certificate_issued_by_foreign')) {
                    $table->dropForeign('contributions_certificate_issued_by_foreign');
                }
                $columns = array_values(array_filter(
                    ['certificate_requested', 'certificate_status', 'certificate_issued_by', 'certificate_issued_at'],
                    fn (string $column): bool => Schema::hasColumn('contributions', $column),
                ));
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }
    }

    private function hasForeignKey(string $table, string $name): bool
    {
        return collect(Schema::getForeignKeys($table))
            ->contains(fn (array $foreignKey): bool => ($foreignKey['name'] ?? null) === $name);
    }
};
