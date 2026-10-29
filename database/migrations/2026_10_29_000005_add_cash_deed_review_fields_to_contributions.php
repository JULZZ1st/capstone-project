<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

        $indexName = 'contrib_doc_review_req_at_idx';
        $hasIndex = collect(Schema::getIndexes('contributions'))->contains(fn (array $index) => $index['name'] === $indexName);
        if (! $hasIndex) {
            Schema::table('contributions', fn (Blueprint $table) => $table->index(['document_review_status', 'documents_requested_at'], $indexName));
        }
    }

    public function down(): void
    {
        Schema::table('contributions', function (Blueprint $table): void {
            $table->dropIndex('contrib_doc_review_req_at_idx');
            $table->dropConstrainedForeignId('documents_requested_by');
            $table->dropColumn([
                'deed_declined',
                'deed_decline_reason',
                'document_review_status',
                'document_request_reason',
                'documents_requested_at',
            ]);
        });
    }
};
