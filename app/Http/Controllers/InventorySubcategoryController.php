<?php

namespace App\Http\Controllers;

use App\Http\Requests\Inventory\StoreInventorySubcategoryRequest;
use App\Http\Requests\Inventory\UpdateInventorySubcategoryRequest;
use App\Models\ContributionItem;
use App\Models\InventoryItem;
use App\Models\InventorySubcategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
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

        DB::transaction(function () use ($inventorySubcategory, $data) {
            $inventorySubcategory = $inventorySubcategory->newQuery()->lockForUpdate()->findOrFail($inventorySubcategory->getKey());

            $inUse = $this->isReferenced($inventorySubcategory);
            $changesStoredReference = $inventorySubcategory->key !== $data['key']
                || (int) $inventorySubcategory->inventory_category_id !== (int) $data['inventory_category_id'];

            if ($inUse && $changesStoredReference) {
                throw ValidationException::withMessages([
                    'key' => 'This subcategory is used by existing records. Deactivate it instead of renaming or moving it.',
                ]);
            }

            $inventorySubcategory->update($data);
        });

        return redirect()->route('inventory.categories.index')->with('success', 'Subcategory updated successfully.');
    }

    public function destroy(InventorySubcategory $inventorySubcategory): RedirectResponse
    {
        Gate::authorize('delete', $inventorySubcategory->category);

        DB::transaction(function () use ($inventorySubcategory) {
            $inventorySubcategory = $inventorySubcategory->newQuery()->lockForUpdate()->findOrFail($inventorySubcategory->getKey());

            if ($this->isReferenced($inventorySubcategory)) {
                throw ValidationException::withMessages([
                    'key' => 'This subcategory is used by existing records. Deactivate it instead of deleting.',
                ]);
            }

            $inventorySubcategory->delete();
        });

        return redirect()->route('inventory.categories.index')->with('success', 'Subcategory deleted successfully.');
    }

    public function deactivate(InventorySubcategory $inventorySubcategory): RedirectResponse
    {
        Gate::authorize('update', $inventorySubcategory->category);

        DB::transaction(function () use ($inventorySubcategory) {
            $inventorySubcategory->newQuery()->lockForUpdate()->findOrFail($inventorySubcategory->getKey())
                ->update(['is_active' => false]);
        });

        return redirect()->route('inventory.categories.index')->with('success', 'Subcategory deactivated successfully.');
    }

    public function reactivate(InventorySubcategory $inventorySubcategory): RedirectResponse
    {
        Gate::authorize('update', $inventorySubcategory->category);

        DB::transaction(function () use ($inventorySubcategory) {
            $inventorySubcategory->newQuery()->lockForUpdate()->findOrFail($inventorySubcategory->getKey())
                ->update(['is_active' => true]);
        });

        return redirect()->route('inventory.categories.index')->with('success', 'Subcategory reactivated successfully.');
    }

    private function isReferenced(InventorySubcategory $inventorySubcategory): bool
    {
        $usedByContribution = ContributionItem::query()
            ->where('category', $inventorySubcategory->category->key)
            ->where('subcategory', $inventorySubcategory->key)
            ->exists();

        $usedByInventoryItem = InventoryItem::withoutGlobalScope('active_workspace')
            ->where('inventory_subcategory_id', $inventorySubcategory->id)
            ->exists();

        return $usedByContribution || $usedByInventoryItem;
    }
}

