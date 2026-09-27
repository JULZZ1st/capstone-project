<?php

namespace App\Services\Donors;

use App\Models\Donor;
use App\Models\SecurityActivityEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RemoveDonor
{
    /**
     * @return string 'deactivated' if the donor had contribution history, 'deleted' otherwise.
     */
    public function handle(Donor $donor, User $actor): string
    {
        return DB::transaction(function () use ($donor, $actor) {
            if ($donor->contributions()->exists()) {
                $donor->forceFill(['status' => 'inactive'])->save();

                $this->audit($donor, $actor, 'donor_deactivated', "{$actor->name} deactivated donor profile {$donor->full_name} (contribution history exists).");

                return 'deactivated';
            }

            $this->audit($donor, $actor, 'donor_deleted', "{$actor->name} permanently deleted donor profile {$donor->full_name}.");

            $donor->delete();

            return 'deleted';
        });
    }

    private function audit(Donor $donor, User $actor, string $action, string $summary): void
    {
        SecurityActivityEvent::query()->create([
            'actor_id' => $actor->id,
            'action' => $action,
            'summary' => $summary,
            'metadata' => ['donor_id' => $donor->id, 'donor_name' => $donor->full_name],
            'ip_address' => request()->ip(),
            'occurred_at' => now(),
        ]);
    }
}
