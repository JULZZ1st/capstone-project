<?php

namespace App\Http\Controllers;

use App\Http\Requests\Inventory\InventoryIndexRequest;
use App\Http\Requests\Inventory\StoreInventoryItemRequest;
use App\Http\Requests\Inventory\UpdateInventoryItemRequest;
use App\Models\InventoryItem;
use App\Services\Inventory\CreateInventoryItem;
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
            ->search($filters['search'] ?? null)
            ->category($filters['category'] ?? null)
            ->status($filters['status'] ?? null)
            ->latest('updated_at')
            ->paginate(config('inventory.pagination.per_page'))
            ->withQueryString();

        return view('inventory.index', [
            'items' => $items,
            'filters' => $filters,
            'alerts' => $alertQuery->get(),
        ]);
    }

    public function store(StoreInventoryItemRequest $request, CreateInventoryItem $creator): RedirectResponse
    {
        $item = $creator->handle($request->validated(), $request->user());

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
            'stockMovements' => fn ($query) => $query
                ->with('creator:id,first_name,last_name,name')
                ->latest()
                ->limit(25),
        ]);

        return view('inventory.show', compact('inventoryItem'));
    }

    public function update(UpdateInventoryItemRequest $request, InventoryItem $inventoryItem): RedirectResponse
    {
        $inventoryItem->update($request->validated() + [
            'updated_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('inventory.show', $inventoryItem)
            ->with('success', 'Inventory item updated successfully.');
    }
}
