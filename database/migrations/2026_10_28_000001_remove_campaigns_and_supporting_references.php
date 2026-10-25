<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('security_activity_events')
            ->whereNotNull('metadata')
            ->orderBy('id')
            ->chunkById(500, function ($events): void {
                foreach ($events as $event) {
                    $metadata = json_decode((string) $event->metadata, true, flags: JSON_THROW_ON_ERROR);

                    if (! is_array($metadata) || ! array_key_exists('supporting_reference', $metadata)) {
                        continue;
                    }

                    unset($metadata['supporting_reference']);
                    DB::table('security_activity_events')
                        ->where('id', $event->id)
                        ->update(['metadata' => json_encode($metadata, JSON_THROW_ON_ERROR)]);
                }
            });

        Schema::table('cash_disbursements', function (Blueprint $table): void {
            $table->dropColumn('supporting_reference');
        });

        Schema::table('contributions', function (Blueprint $table): void {
            $table->dropIndex('contributions_campaign_id_status_index');
            $table->dropIndex('contributions_campaign_id_index');
            $table->dropForeign(['campaign_id']);
            $table->dropColumn('campaign_id');
        });

        Schema::dropIfExists('campaigns');
    }

    public function down(): void
    {
        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('status')->index();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['workspace_id', 'slug'], 'campaigns_workspace_slug_unique');
        });

        Schema::table('contributions', function (Blueprint $table): void {
            $table->foreignId('campaign_id')->nullable()->after('donor_id')->constrained()->nullOnDelete();
            $table->index('campaign_id');
            $table->index(['campaign_id', 'status']);
        });

        Schema::table('cash_disbursements', function (Blueprint $table): void {
            $table->string('supporting_reference')->nullable()->after('notes');
        });
    }
};
