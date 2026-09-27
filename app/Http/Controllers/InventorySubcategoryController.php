<?php

namespace App\Http\Controllers;

use App\Http\Requests\Inventory\StoreInventorySubcategoryRequest;
use App\Http\Requests\Inventory\UpdateInventorySubcategoryRequest;
use App\Models\ContributionItem;
use App\Models\InventorySubcategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class InventorySubcategoryController extends Controller
{
    public function store(StoreInventorySubcategoryRequest $request): RedirectResponse
    {
        InventorySubcategory::query()->create($request->validated());

        return redirect()->route('inventory.categories.index')->with('success', 'Subcategory created successfully.');
    }

    public function update(UpdateInventorySubcategoryRequest $request, InventorySubcategory $inventorySubcategory): RedirectResponse
    {
        $data = $request->validated();

        $inUse = $this->isReferenced($inventorySubcategory);
        $changesStoredReference = $inventorySubcategory->key !== $data['key']
            || (int) $inventorySubcategory->inventory_category_id !== (int) $data['inventory_category_id'];

        if ($inUse && $changesStoredReference) {
            throw ValidationException::withMessages([
                'key' => 'This subcategory is used by existing contribution items. Deactivate it instead of renaming or moving it.',
            ]);
        }

        $inventorySubcategory->update($data);

        return redirect()->route('inventory.categories.index')->with('success', 'Subcategory updated successfully.');
    }

    public function destroy(InventorySubcategory $inventorySubcategory): RedirectResponse
    {
        Gate::authorize('delete', $inventorySubcategory->category);

        if ($this->isReferenced($inventorySubcategory)) {
            throw ValidationException::withMessages([
                'key' => 'This subcategory is used by existing contribution items. Deactivate it instead of deleting.',
            ]);
        }

        $inventorySubcategory->delete();

        return redirect()->route('inventory.categories.index')->with('success', 'Subcategory deleted successfully.');
    }

    private function isReferenced(InventorySubcategory $inventorySubcategory): bool
    {
        return ContributionItem::query()
            ->where('category', $inventorySubcategory->category->key)
            ->where('subcategory', $inventorySubcategory->key)
            ->exists();
    }
}

