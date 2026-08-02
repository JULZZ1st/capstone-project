<?php

namespace App\Http\Controllers;

use App\Http\Requests\Inventory\StoreInventoryUnitRequest;
use App\Http\Requests\Inventory\UpdateInventoryUnitRequest;
use App\Models\InventoryUnit;
use App\Services\Inventory\ManageInventoryReferenceItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class InventoryUnitController extends Controller
{
    public function store(StoreInventoryUnitRequest $request, ManageInventoryReferenceItem $manager): RedirectResponse
    {
        $manager->create(InventoryUnit::class, $request->validated());

        return redirect()->route('inventory.categories.index')->with('success', 'Unit created successfully.');
    }

    public function update(UpdateInventoryUnitRequest $request, InventoryUnit $inventoryUnit, ManageInventoryReferenceItem $manager): RedirectResponse
    {
        $manager->update($inventoryUnit, $request->validated());

        return redirect()->route('inventory.categories.index')->with('success', 'Unit updated successfully.');
    }

    public function destroy(InventoryUnit $inventoryUnit, ManageInventoryReferenceItem $manager): RedirectResponse
    {
        Gate::authorize('delete', $inventoryUnit);

        $manager->delete($inventoryUnit);

        return redirect()->route('inventory.categories.index')->with('success', 'Unit deleted successfully.');
    }
}
