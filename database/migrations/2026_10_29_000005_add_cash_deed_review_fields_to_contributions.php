<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contributions')) {
            throw new RuntimeException('Cannot add cash deed review fields: contributions table is missing.');
        }

        $columns = [
            'deed_declined' => fn (Blueprint $table) => $table->boolean('deed_declined')->default(false)->after('certificate_issued_at'),
            'deed_decline_reason' => fn (Blueprint $table) => $table->text('deed_decline_reason')->nullable()->after('deed_declined'),
            'document_review_status' => fn (Blueprint $table) => $table->string('document_review_status', 40)->default('not_required')->after('deed_decline_reason'),
            'document_request_reason' => fn (Blueprint $table) => $table->text('document_request_reason')->nullable()->after('document_review_status'),
            'documents_requested_by' => fn (Blueprint $table) => $table->foreignId('documents_requested_by')->nullable()->after('document_request_reason')->constrained('users')->nullOnDelete(),
            'documents_requested_at' => fn (Blueprint $table) => $table->timestamp('documents_requested_at')->nullable()->after('documents_requested_by'),
        ];

        foreach ($columns as $column => $definition) {
            if (! Schema::hasColumn('contributions', $column)) {
                Schema::table('contributions', $definition);
            }
        }

        if (Schema::hasColumn('contributions', 'documents_requested_by')
            && ! $this->hasForeignKey('contributions', 'contributions_documents_requested_by_foreign')) {
            Schema::table('contributions', function (Blueprint $table): void {
                $table->foreign('documents_requested_by', 'contributions_documents_requested_by_foreign')
                    ->references('id')->on('users')->nullOnDelete();
            });
        }

        $indexName = 'contrib_doc_review_req_at_idx';
        if (! $this->hasIndex('contributions', $indexName)) {
            Schema::table('contributions', fn (Blueprint $table) => $table->index(['document_review_status', 'documents_requested_at'], $indexName));
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('contributions')) {
            return;
        }

        Schema::table('contributions', function (Blueprint $table): void {
            if ($this->hasIndex('contributions', 'contrib_doc_review_req_at_idx')) {
                $table->dropIndex('contrib_doc_review_req_at_idx');
            }
            if ($this->hasForeignKey('contributions', 'contributions_documents_requested_by_foreign')) {
                $table->dropForeign('contributions_documents_requested_by_foreign');
            }
            $columns = array_values(array_filter(
                [
                    'deed_declined',
                    'deed_decline_reason',
                    'document_review_status',
                    'document_request_reason',
                    'documents_requested_by',
                    'documents_requested_at',
                ],
                fn (string $column): bool => Schema::hasColumn('contributions', $column),
            ));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    private function hasIndex(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === $name);
    }

    private function hasForeignKey(string $table, string $name): bool
    {
        return collect(Schema::getForeignKeys($table))
            ->contains(fn (array $foreignKey): bool => ($foreignKey['name'] ?? null) === $name);
    }
};
