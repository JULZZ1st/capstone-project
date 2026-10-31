<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contributions', function (Blueprint $table): void {
            $table->boolean('certificate_requested')->default(false)->after('official_receipt_number');
            $table->string('certificate_status', 30)->default('not_requested')->after('certificate_requested');
            $table->foreignId('certificate_issued_by')->nullable()->after('certificate_status')->constrained('users')->nullOnDelete();
            $table->timestamp('certificate_issued_at')->nullable()->after('certificate_issued_by');
            $table->index(['certificate_requested', 'certificate_status']);
        });

        Schema::table('contribution_evidence', function (Blueprint $table): void {
            $table->string('document_type', 50)->default('supporting_evidence')->after('contribution_id');
            $table->index(['contribution_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::table('contribution_evidence', function (Blueprint $table): void {
            $table->dropIndex(['contribution_id', 'document_type']);
            $table->dropColumn('document_type');
        });

        Schema::table('contributions', function (Blueprint $table): void {
            $table->dropIndex(['certificate_requested', 'certificate_status']);
            $table->dropConstrainedForeignId('certificate_issued_by');
            $table->dropColumn(['certificate_requested', 'certificate_status', 'certificate_issued_at']);
        });
    }
};
