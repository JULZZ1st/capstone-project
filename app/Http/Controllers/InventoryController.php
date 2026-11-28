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
use App\Services\Inventory\UpdateInventoryItem;
use App\Services\Reports\InventoryReportBuilder;
use App\Services\Reports\InventoryReportPdfExporter;
use App\Support\Authorization\ReportAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(InventoryIndexRequest $request): View|JsonResponse
    {
        $filters = $request->validated();

        $items = InventoryItem::query()
            ->select([
                'id',
                'workspace_id',
                'name',
                'category',
                'inventory_subcategory_id',
                'description',
                'unit',
                'current_quantity',
                'minimum_reserve_quantity',
                'estimated_unit_value',
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

        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('inventory.partials.results', compact('items'))->render(),
            ])->header('Cache-Control', 'no-store, private');
        }

        return view('inventory.index', [
            'items' => $items,
            'filters' => $filters,
            ...$this->referenceData(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', InventoryItem::class);

        return view('inventory.create', $this->referenceData());
    }

    private function referenceData(?InventoryItem $item = null): array
    {
        return [
            'categories' => InventoryCategory::query()
                ->where(fn ($query) => $query->where('is_active', true)
                    ->when($item, fn ($query) => $query->orWhere('key', $item->category)))
                ->with(['subcategories' => fn ($query) => $query
                    ->where(fn ($query) => $query->where('is_active', true)
                        ->when($item?->inventory_subcategory_id, fn ($query) => $query->orWhere('id', $item->inventory_subcategory_id)))
                    ->orderBy('label')])
                ->orderBy('label')
                ->get(),
            'units' => InventoryUnit::query()
                ->where(fn ($query) => $query->where('is_active', true)
                    ->when($item, fn ($query) => $query->orWhere('key', $item->unit)))
                ->orderBy('label')->get(),
        ];
    }

    public function exportPdf(InventoryIndexRequest $request, InventoryReportBuilder $builder, InventoryReportPdfExporter $exporter): Response
    {
        app(ReportAccess::class)->authorize($request->user(), 'inventory');
        $report = $builder->build($request->validated());

        $filename = sprintf('inventory-summary-report-%s.pdf', now()->toDateString());

        return $exporter->handle($report, $filename);
    }

    public function store(StoreInventoryItemRequest $request, CreateInventoryItem $creator, ResolveInventoryClassification $resolver): RedirectResponse
    {
        $item = $creator->handle($request->validated(), $request->user(), $resolver);

        return redirect()
            ->route('inventory.index')
            ->with('success', 'Inventory item created successfully.')
            ->with('highlight_inventory_item', $item->id);
    }

    public function show(Request $request, InventoryItem $inventoryItem): View|JsonResponse
    {
        Gate::authorize('view', $inventoryItem);

        $inventoryItem->load([
            'creator:id,first_name,last_name,name',
            'updater:id,first_name,last_name,name',
            'subcategory:id,key,label',
        ]);

        $movements = $inventoryItem->stockMovements()
            ->with([
                'creator:id,first_name,last_name,name',
                'contributionItem.contribution:id,receipt_number,donor_id',
                'contributionItem.contribution.donor:id,full_name',
            ])
            ->latest()
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('inventory.partials.details', compact('inventoryItem', 'movements'))->render(),
            ])->header('Cache-Control', 'no-store, private');
        }

        return view('inventory.show', compact('inventoryItem', 'movements'));
    }

    public function edit(InventoryItem $inventoryItem): View
    {
        Gate::authorize('update', $inventoryItem);

        $inventoryItem->load('subcategory:id,key,label');

        return view('inventory.edit', [
            'inventoryItem' => $inventoryItem,
            ...$this->referenceData($inventoryItem),
        ]);
    }

    public function update(UpdateInventoryItemRequest $request, InventoryItem $inventoryItem, ResolveInventoryClassification $resolver, UpdateInventoryItem $updater): RedirectResponse
    {
        $updater->handle($inventoryItem, $request->validated(), $request->user(), $resolver);

        return redirect()
            ->route('inventory.show', $inventoryItem)
            ->with('success', 'Inventory item updated successfully.');
    }
}
