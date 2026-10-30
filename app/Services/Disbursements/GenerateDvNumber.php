<?php

namespace App\Services\Disbursements;

use App\Models\DvNumberSequence;
use App\Models\Workspace;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class GenerateDvNumber
{
    public function handle(int $workspaceId, CarbonInterface $date): string
    {
        return DB::transaction(function () use ($workspaceId, $date): string {
            // Lock the workspace row so first-use sequence creation is serialized too.
            Workspace::query()->whereKey($workspaceId)->lockForUpdate()->firstOrFail();

            $period = $date->format(config('disbursements.dv_number.period_format', 'Y-m'));
            $sequence = DvNumberSequence::query()
                ->where('workspace_id', $workspaceId)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                $sequence = DvNumberSequence::query()->create([
                    'workspace_id' => $workspaceId,
                    'period' => $period,
                    'last_number' => 0,
                ]);
            }

            $sequence->increment('last_number');
            $sequence->refresh();

            return sprintf(
                '%s-%0'.(int) config('disbursements.dv_number.sequence_width', 4).'d',
                $period,
                $sequence->last_number,
            );
        });
    }
}
