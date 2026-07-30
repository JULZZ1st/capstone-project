<?php

namespace App\Http\Controllers;

use App\Http\Requests\Inventory\StoreStockAdjustmentRequest;
use App\Models\InventoryItem;
use App\Services\Inventory\AdjustInventoryStock;
use Illuminate\Http\RedirectResponse;

class InventoryStockAdjustmentController extends Controller
{
    public function store(
        StoreStockAdjustmentRequest $request,
        InventoryItem $inventoryItem,
        AdjustInventoryStock $adjuster
    ): RedirectResponse {
        $adjuster->handle($inventoryItem, $request->validated(), $request->user());

        return redirect()
            ->route('inventory.index')
            ->with('success', 'Stock adjusted successfully.')
            ->with('highlight_inventory_item', $inventoryItem->id);
    }
}
