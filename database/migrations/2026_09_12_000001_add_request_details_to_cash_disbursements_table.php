<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_disbursements', function (Blueprint $table) {
            $table->string('recipient')->nullable()->after('purpose');
            $table->date('requested_date')->nullable()->after('recipient');
            $table->text('notes')->nullable()->after('requested_date');
            $table->string('supporting_reference')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('cash_disbursements', function (Blueprint $table) {
            $table->dropColumn([
                'recipient',
                'requested_date',
                'notes',
                'supporting_reference',
            ]);
        });
    }
};
