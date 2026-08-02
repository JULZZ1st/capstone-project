<?php

namespace App\Http\Controllers;

use App\Http\Requests\Inventory\StoreInventoryCategoryRequest;
use App\Http\Requests\Inventory\UpdateInventoryCategoryRequest;
use App\Models\InventoryCategory;
use App\Models\InventoryUnit;
use App\Services\Inventory\ManageInventoryReferenceItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InventoryCategoryController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', InventoryCategory::class);

        return view('inventory.categories', [
            'categories' => InventoryCategory::query()->orderBy('label')->get(),
            'units' => InventoryUnit::query()->orderBy('label')->get(),
        ]);
    }

    public function store(StoreInventoryCategoryRequest $request, ManageInventoryReferenceItem $manager): RedirectResponse
    {
        $manager->create(InventoryCategory::class, $request->validated());

        return redirect()->route('inventory.categories.index')->with('success', 'Category created successfully.');
    }

    public function update(UpdateInventoryCategoryRequest $request, InventoryCategory $inventoryCategory, ManageInventoryReferenceItem $manager): RedirectResponse
    {
        $manager->update($inventoryCategory, $request->validated());

        return redirect()->route('inventory.categories.index')->with('success', 'Category updated successfully.');
    }

    public function destroy(InventoryCategory $inventoryCategory, ManageInventoryReferenceItem $manager): RedirectResponse
    {
        Gate::authorize('delete', $inventoryCategory);

        $manager->delete($inventoryCategory);

        return redirect()->route('inventory.categories.index')->with('success', 'Category deleted successfully.');
    }
}
