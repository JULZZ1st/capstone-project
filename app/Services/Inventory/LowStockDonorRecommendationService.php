<?php

namespace App\Services\Inventory;

use App\Models\ContributionItem;
use App\Models\InventoryCategory;
use App\Models\InventoryDonorAlert;
use App\Models\InventoryItem;
use App\Support\Workspaces\WorkspaceContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LowStockDonorRecommendationService
{
    public function syncAlerts(): void
    {
        $workspaceId = app(WorkspaceContext::class)->requireId();
        $items = InventoryItem::query()->forWorkspace($workspaceId)->active()
            ->where(function ($query): void {
                $query->where('current_quantity', '<=', 0)
                    ->orWhere(function ($query): void {
                        $query->where('current_quantity', '>', 0)
                            ->whereColumn('current_quantity', '<=', 'minimum_reserve_quantity');
                    });
            })
            ->get(['id', 'workspace_id']);
        $qualifyingIds = $items->pluck('id');

        foreach ($items as $item) {
            $alert = InventoryDonorAlert::query()->forWorkspace($workspaceId)
                ->where('inventory_item_id', $item->id)->first();

            if (! $alert) {
                InventoryDonorAlert::query()->create([
                    'workspace_id' => $workspaceId,
                    'inventory_item_id' => $item->id,
                    'status' => 'active',
                    'triggered_at' => now(),
                ]);
            } elseif ($alert->status === 'resolved') {
                $alert->update(['status' => 'active', 'triggered_at' => now(), 'handled_at' => null, 'handled_by' => null]);
            }
        }

        InventoryDonorAlert::query()->forWorkspace($workspaceId)
            ->whereNotIn('inventory_item_id', $qualifyingIds->isEmpty() ? [0] : $qualifyingIds->all())
            ->where('status', '!=', 'resolved')
            ->update(['status' => 'resolved', 'updated_at' => now()]);
    }

    /** @return array{alert: InventoryDonorAlert, recommendations: Collection, has_history: bool} */
    public function forItem(InventoryItem $item): array
    {
        $workspaceId = app(WorkspaceContext::class)->requireId();
        abort_unless((int) $item->workspace_id === $workspaceId, 404);

        $alert = InventoryDonorAlert::query()->forWorkspace($workspaceId)
            ->where('inventory_item_id', $item->id)->firstOrFail();
        $categoryLabels = InventoryCategory::query()->forWorkspace($workspaceId)
            ->pluck('label', 'key')->all();
        $categoryLabel = $categoryLabels[$item->category] ?? Str::headline((string) $item->category);

        // Only received or distributed in-kind contributions count as donation history.
        // The workspace predicates on each join guard against inconsistent foreign keys.
        $history = ContributionItem::query()->withoutGlobalScopes()
            ->join('contributions', function ($join): void {
                $join->on('contributions.id', '=', 'contribution_items.contribution_id')
                    ->on('contributions.workspace_id', '=', 'contribution_items.workspace_id');
            })
            ->join('donors', function ($join): void {
                $join->on('donors.id', '=', 'contributions.donor_id')
                    ->on('donors.workspace_id', '=', 'contributions.workspace_id');
            })
            ->where('contribution_items.workspace_id', $workspaceId)
            ->where('contributions.workspace_id', $workspaceId)
            ->where('donors.workspace_id', $workspaceId)
            ->where('donors.status', config('donors.defaults.status'))
            ->where('contributions.type', 'in_kind')
            ->whereIn('contributions.status', ['received', 'distributed'])
            ->whereNull('contributions.voided_at')
            ->select([
                'donors.id as donor_id', 'donors.full_name', 'donors.donor_type', 'donors.contact_person',
                'donors.contact_number', 'donors.email', 'contributions.id as contribution_id',
                'contributions.received_at', 'contributions.created_at as contribution_created_at',
                'contribution_items.name as donated_item_name', 'contribution_items.category as donated_category',
                'contribution_items.category_description as donated_category_description',
            ])
            ->get();

        $normalize = static fn (?string $value): string => Str::lower(Str::squish((string) $value));
        $inventoryName = $normalize($item->name);
        $inventoryCategory = $normalize($item->category);
        $inventoryCategoryLabel = $normalize($categoryLabel);

        $recommendations = $history->groupBy('donor_id')->map(function (Collection $rows) use ($normalize, $inventoryName, $inventoryCategory, $inventoryCategoryLabel) {
            $exact = $rows->filter(fn ($row) => $normalize($row->donated_item_name) === $inventoryName);
            $category = $rows->filter(function ($row) use ($normalize, $inventoryCategory, $inventoryCategoryLabel): bool {
                return $normalize($row->donated_category) === $inventoryCategory
                    || ($inventoryCategoryLabel !== '' && (
                        $normalize($row->donated_category) === $inventoryCategoryLabel
                        || $normalize($row->donated_category_description) === $inventoryCategoryLabel
                    ));
            });
            $matches = $exact->isNotEmpty() ? $exact : $category;
            if ($matches->isEmpty()) {
                return null;
            }

            $donor = $rows->first();
            $dates = $rows->map(fn ($row) => $row->received_at ?? $row->contribution_created_at)->filter()->sortDesc();
            $level = $exact->isNotEmpty() ? 'Strong Match' : 'Good Match';
            $count = $rows->pluck('contribution_id')->unique()->count();

            return (object) [
                'id' => $donor->donor_id,
                'full_name' => $donor->full_name,
                'donor_type' => $donor->donor_type,
                'contact_person' => $donor->contact_person,
                'contact_number' => $donor->contact_number,
                'email' => $donor->email,
                'match_level' => $level,
                'donation_count' => $count,
                'last_donation_at' => $dates->first(),
                'previous_items' => $rows->pluck('donated_item_name')->filter()->unique()->values(),
                'reason' => $exact->isNotEmpty()
                    ? 'Previously donated this exact inventory item.'
                    : 'Previously donated items in the same inventory category.',
                'rank' => $exact->isNotEmpty() ? 0 : 1,
            ];
        })->filter()->sort(function ($a, $b): int {
            return ($a->rank <=> $b->rank)
                ?: ($b->donation_count <=> $a->donation_count)
                ?: (($b->last_donation_at?->timestamp ?? 0) <=> ($a->last_donation_at?->timestamp ?? 0));
        })->values();

        return ['alert' => $alert, 'recommendations' => $recommendations, 'has_history' => $recommendations->isNotEmpty()];
    }

    public function handle(InventoryDonorAlert $alert, string $status, int $userId): void
    {
        abort_unless(in_array($status, ['handled', 'dismissed'], true), 422);
        $alert->update(['status' => $status, 'handled_at' => now(), 'handled_by' => $userId]);
    }
}
