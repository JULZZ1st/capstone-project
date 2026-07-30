<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;
use Illuminate\Support\Collection;

class InventoryAlertQuery
{
    public function get(): array
    {
        $limit = (int) config('inventory.alerts.limit');

        return [
            'out_of_stock' => $this->baseQuery()->outOfStock()->limit($limit)->get(),
            'critical_stock' => $this->baseQuery()->criticalStock()->limit($limit)->get(),
            'low_stock' => $this->baseQuery()
                ->lowStock()
                ->whereRaw('current_quantity > minimum_reserve_quantity * ?', [(float) config('inventory.critical_stock_ratio')])
                ->limit($limit)
                ->get(),
            'expired' => $this->baseQuery()->expired()->limit($limit)->get(),
            'expiring_soon' => $this->expiringSoon($limit),
        ];
    }

    private function baseQuery()
    {
        return InventoryItem::query()
            ->select(['id', 'name', 'category', 'current_quantity', 'minimum_reserve_quantity', 'unit', 'expires_at', 'status'])
            ->active()
            ->orderBy('name');
    }

    private function expiringSoon(int $limit): Collection
    {
        return $this->baseQuery()
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '>=', today())
            ->whereDate('expires_at', '<=', today()->addDays((int) config('inventory.expiring_soon_days')))
            ->where('current_quantity', '>', 0)
            ->limit($limit)
            ->get();
    }
}
