<?php

namespace App\Services\Reports;

use App\Models\ContributionItem;
use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use Illuminate\Support\Carbon;

class InventoryReportBuilder
{
    /**
     * @param  array{search?: string|null, category?: string|null, status?: string|null}  $filters
     */
    public function build(array $filters = []): array
    {
        $periodStart = Carbon::create((int) ($filters['year'] ?? now()->year), (int) ($filters['month'] ?? now()->month), 1)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();

        $items = InventoryItem::query()
            ->with('subcategory:id,key,label')
            ->search($filters['search'] ?? null)
            ->category($filters['category'] ?? null)
            ->status($filters['status'] ?? null)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $summary = InventoryItem::query()
            ->active()
            ->selectRaw(<<<'SQL'
                COUNT(*) as total_items,
                SUM(CASE WHEN current_quantity <= 0 THEN 1 ELSE 0 END) as out_of_stock,
                SUM(CASE WHEN current_quantity > 0 AND current_quantity <= minimum_reserve_quantity THEN 1 ELSE 0 END) as low_stock,
                SUM(CASE WHEN current_quantity > minimum_reserve_quantity THEN 1 ELSE 0 END) as in_stock
            SQL)
            ->first();

        $inKindReceived = ContributionItem::query()
            ->whereHas('contribution', fn ($query) => $query
                ->where('type', 'in_kind')
                ->whereBetween('received_at', [$periodStart, $periodEnd]))
            ->sum('quantity');

        $movementTotals = InventoryStockMovement::query()
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->selectRaw("SUM(CASE WHEN movement_type = 'stock_in' THEN quantity ELSE 0 END) as stock_in")
            ->selectRaw("SUM(CASE WHEN movement_type = 'stock_out' THEN quantity ELSE 0 END) as stock_out")
            ->first();

        return [
            'items' => $items,
            'summary' => $summary,
            'movement_totals' => [
                'in_kind_received' => (float) $inKindReceived,
                'stock_in' => (float) ($movementTotals->stock_in ?? 0),
                'stock_out' => (float) ($movementTotals->stock_out ?? 0),
                'current_stock' => (float) $items->sum('current_quantity'),
                'low_stock' => (int) InventoryItem::query()->active()->lowStock()->count(),
                'critical_stock' => (int) InventoryItem::query()->active()->criticalStock()->count(),
                'expired' => (int) InventoryItem::query()->expired()->count(),
            ],
            'period' => $periodStart,
            'filters' => $filters,
        ];
    }
}
