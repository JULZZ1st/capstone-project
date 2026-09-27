<?php

namespace App\Services\Reports;

use App\Models\InventoryItem;

class InventoryReportBuilder
{
    /**
     * @param  array{search?: string|null, category?: string|null, status?: string|null}  $filters
     */
    public function build(array $filters = []): array
    {
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

        return [
            'items' => $items,
            'summary' => $summary,
            'filters' => $filters,
        ];
    }
}
