<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Services\Inventory\LowStockDonorRecommendationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryDonorRecommendationController extends Controller
{
    public function show(InventoryItem $inventoryItem, LowStockDonorRecommendationService $recommendations): View
    {
        abort_unless((int) $inventoryItem->workspace_id === (int) app(\App\Support\Workspaces\WorkspaceContext::class)->requireId(), 404);
        abort_unless((float) $inventoryItem->current_quantity <= (float) $inventoryItem->minimum_reserve_quantity, 404);
        $recommendations->syncAlerts();

        return view('inventory.donor-recommendations', [
            'inventoryItem' => $inventoryItem,
            ...$recommendations->forItem($inventoryItem),
        ]);
    }

    public function update(Request $request, InventoryItem $inventoryItem, LowStockDonorRecommendationService $recommendations): RedirectResponse
    {
        abort_unless((int) $inventoryItem->workspace_id === (int) app(\App\Support\Workspaces\WorkspaceContext::class)->requireId(), 404);
        $data = $request->validate(['status' => ['required', 'in:handled,dismissed']]);
        $recommendations->syncAlerts();
        $alert = $recommendations->forItem($inventoryItem)['alert'];
        $recommendations->handle($alert, $data['status'], (int) $request->user()->id);

        return redirect()->route('inventory.donors.suggested', $inventoryItem)
            ->with('success', $data['status'] === 'handled' ? 'Donor recommendation marked as handled.' : 'Donor recommendation dismissed.');
    }
}
