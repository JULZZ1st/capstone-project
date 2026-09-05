<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('contributions', 'receipt_number')) {
            Schema::table('contributions', function (Blueprint $table) {
                $table->string('receipt_number', 32)->nullable()->unique()->after('id');
            });
        }

        DB::table('contributions')
            ->whereNull('receipt_number')
            ->orderBy('id')
            ->select(['id', 'received_at', 'created_at'])
            ->chunkById(100, function ($contributions) {
                foreach ($contributions as $contribution) {
                    $date = Carbon::parse($contribution->received_at ?? $contribution->created_at ?? now())->format('Ymd');

                    DB::table('contributions')
                        ->where('id', $contribution->id)
                        ->update(['receipt_number' => sprintf('DON-%s-%06d', $date, $contribution->id)]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('contributions', 'receipt_number')) {
            return;
        }

        Schema::table('contributions', function (Blueprint $table) {
            $table->dropUnique(['receipt_number']);
            $table->dropColumn('receipt_number');
        });
    }
};