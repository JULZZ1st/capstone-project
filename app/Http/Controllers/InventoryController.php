<?php

namespace App\Http\Controllers;

use App\Http\Requests\Inventory\InventoryIndexRequest;
use App\Http\Requests\Inventory\StoreInventoryItemRequest;
use App\Http\Requests\Inventory\UpdateInventoryItemRequest;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use App\Services\Inventory\CreateInventoryItem;
use App\Services\Inventory\ResolveInventoryClassification;
use App\Services\Inventory\InventoryAlertQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(InventoryIndexRequest $request, InventoryAlertQuery $alertQuery): View
    {
        $filters = $request->validated();

        $items = InventoryItem::query()
            ->select([
                'id',
                'name',
                'category',
                'inventory_subcategory_id',
                'description',
                'unit',
                'current_quantity',
                'minimum_reserve_quantity',
                'expires_at',
                'batch_number',
                'storage_location',
                'notes',
                'last_audited_at',
                'updated_at',
                'status',
            ])
            ->with('subcategory:id,key,label')
            ->search($filters['search'] ?? null)
            ->category($filters['category'] ?? null)
            ->status($filters['status'] ?? null)
            ->latest('updated_at')
            ->paginate(config('inventory.pagination.per_page'))
            ->withQueryString();

        $stockSummary = InventoryItem::query()
            ->active()
            ->selectRaw(<<<'SQL'
                COUNT(*) as total_items,
                SUM(CASE WHEN current_quantity <= 0 THEN 1 ELSE 0 END) as out_of_stock,
                SUM(CASE WHEN current_quantity > 0 AND current_quantity <= minimum_reserve_quantity THEN 1 ELSE 0 END) as low_stock,
                SUM(CASE WHEN current_quantity > minimum_reserve_quantity THEN 1 ELSE 0 END) as in_stock
            SQL)
            ->first();

        return view('inventory.index', [
            'items' => $items,
            'filters' => $filters,
            'alerts' => $alertQuery->get(),
            'stockSummary' => $stockSummary,
            'categories' => InventoryCategory::query()
                ->active()
                ->with(['subcategories' => fn ($query) => $query->active()->orderBy('label')])
                ->orderBy('label')
                ->get(),
            'units' => InventoryUnit::query()->active()->orderBy('label')->get(),
        ]);
    }

    public function store(StoreInventoryItemRequest $request, CreateInventoryItem $creator, ResolveInventoryClassification $resolver): RedirectResponse
    {
        $item = $creator->handle($request->validated(), $request->user(), $resolver);

        return redirect()
            ->route('inventory.index')
            ->with('success', 'Inventory item created successfully.')
            ->with('highlight_inventory_item', $item->id);
    }

    public function show(InventoryItem $inventoryItem): View
    {
        Gate::authorize('view', $inventoryItem);

        $inventoryItem->load([
            'creator:id,first_name,last_name,name',
            'updater:id,first_name,last_name,name',
            'subcategory:id,key,label',
            'stockMovements' => fn ($query) => $query
                ->with('creator:id,first_name,last_name,name')
                ->latest()
                ->limit(25),
        ]);

        return view('inventory.show', compact('inventoryItem'));
    }

    public function update(UpdateInventoryItemRequest $request, InventoryItem $inventoryItem, ResolveInventoryClassification $resolver): RedirectResponse
    {
        $data = $request->validated();
        $category = $resolver->category($request->user(), $data['category'], $data['custom_category'] ?? null);
        $subcategory = $resolver->subcategory($request->user(), $category, $data['subcategory_selection'] ?? null, $data['custom_subcategory'] ?? null);

        $inventoryItem->update([
            ...$data,
            'category' => $category->key,
            'inventory_subcategory_id' => $subcategory?->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('inventory.show', $inventoryItem)
            ->with('success', 'Inventory item updated successfully.');
    }
}
