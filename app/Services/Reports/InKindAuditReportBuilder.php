<?php

namespace App\Services\Reports;

use App\Models\InventoryStockMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class InKindAuditReportBuilder
{
    /**
     * @return array{period: Carbon, summary: array{movement_count: int, item_count: int, stock_in_quantity: float, stock_out_quantity: float, adjustment_quantity: float}, movements: Collection}
     */
    public function build(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $movements = InventoryStockMovement::query()
            ->with([
                'inventoryItem:id,name,category,inventory_subcategory_id,unit',
                'inventoryItem.subcategory:id,label',
                'creator:id,name,first_name,last_name',
            ])
            ->whereBetween('created_at', [$start, $end])
            ->latest('created_at')
            ->latest('id')
            ->get();

        return [
            'period' => $start,
            'summary' => [
                'movement_count' => $movements->count(),
                'item_count' => $movements->pluck('inventory_item_id')->unique()->count(),
                'stock_in_quantity' => (float) $movements->where('movement_type', 'stock_in')->sum('quantity'),
                'stock_out_quantity' => (float) $movements->where('movement_type', 'stock_out')->sum('quantity'),
                'adjustment_quantity' => (float) $movements->where('movement_type', 'adjustment')->sum('quantity'),
            ],
            'movements' => $movements,
        ];
    }
}
