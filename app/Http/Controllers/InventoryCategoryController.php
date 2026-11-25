<?php

namespace App\Http\Controllers;

use App\Http\Requests\Inventory\StoreInventoryCategoryRequest;
use App\Http\Requests\Inventory\UpdateInventoryCategoryRequest;
use App\Models\ContributionItem;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use App\Services\Inventory\ManageInventoryReferenceItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InventoryCategoryController extends Controller
{
    public function index(): View
    {
        Gate::authorize('create', InventoryCategory::class);

        $categories = InventoryCategory::query()
            ->with(['subcategories' => fn ($query) => $query->orderBy('label')])
            ->orderBy('label')
            ->get();

        $units = InventoryUnit::query()->orderBy('label')->get();

        // Categories and units are shared references, so dependency counts must match
        // ManageInventoryReferenceItem::isInUse()'s cross-workspace scope — otherwise a row
        // could show "0 dependents" while its delete is still blocked by another workspace.
        $categoryItemCounts = InventoryItem::withoutGlobalScope('active_workspace')
            ->selectRaw('category, count(*) as aggregate')
            ->groupBy('category')
            ->pluck('aggregate', 'category');

        $unitItemCounts = InventoryItem::withoutGlobalScope('active_workspace')
            ->selectRaw('unit, count(*) as aggregate')
            ->groupBy('unit')
            ->pluck('aggregate', 'unit');

        $subcategoryItemCounts = InventoryItem::withoutGlobalScope('active_workspace')
            ->whereNotNull('inventory_subcategory_id')
            ->selectRaw('inventory_subcategory_id, count(*) as aggregate')
            ->groupBy('inventory_subcategory_id')
            ->pluck('aggregate', 'inventory_subcategory_id');

        $subcategoryDonationCounts = ContributionItem::withoutGlobalScope('active_workspace')
            ->whereNotNull('subcategory')
            ->selectRaw('category, subcategory, count(*) as aggregate')
            ->groupBy('category', 'subcategory')
            ->get()
            ->mapWithKeys(fn ($row) => ["{$row->category}|{$row->subcategory}" => $row->aggregate]);

        foreach ($categories as $category) {
            $category->inventory_item_count = (int) ($categoryItemCounts[$category->key] ?? 0);
            $category->subcategory_count = $category->subcategories->count();

            foreach ($category->subcategories as $subcategory) {
                $subcategory->inventory_item_count = (int) ($subcategoryItemCounts[$subcategory->id] ?? 0);
                $subcategory->donation_item_count = (int) ($subcategoryDonationCounts["{$category->key}|{$subcategory->key}"] ?? 0);
            }
        }

        foreach ($units as $unit) {
            $unit->inventory_item_count = (int) ($unitItemCounts[$unit->key] ?? 0);
        }

        return view('inventory.categories', compact('categories', 'units'));
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

    public function deactivate(InventoryCategory $inventoryCategory, ManageInventoryReferenceItem $manager): RedirectResponse
    {
        Gate::authorize('update', $inventoryCategory);

        $manager->deactivate($inventoryCategory);

        return redirect()->route('inventory.categories.index')->with('success', 'Category deactivated successfully.');
    }

    public function reactivate(InventoryCategory $inventoryCategory, ManageInventoryReferenceItem $manager): RedirectResponse
    {
        Gate::authorize('update', $inventoryCategory);

        $manager->reactivate($inventoryCategory);

        return redirect()->route('inventory.categories.index')->with('success', 'Category reactivated successfully.');
    }
}
