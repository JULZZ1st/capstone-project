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
use App\Services\Reports\InventoryReportBuilder;
use App\Services\Reports\InventoryReportPdfExporter;
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

    private function referenceData(): array
    {
        return [
            'categories' => InventoryCategory::query()
                ->active()
                ->with(['subcategories' => fn ($query) => $query->active()->orderBy('label')])
                ->orderBy('label')
                ->get(),
            'units' => InventoryUnit::query()->active()->orderBy('label')->get(),
        ];
    }

    public function exportPdf(InventoryIndexRequest $request, InventoryReportBuilder $builder, InventoryReportPdfExporter $exporter): Response
    {
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
            'stockMovements' => fn ($query) => $query
                ->with([
                    'creator:id,first_name,last_name,name',
                    'contributionItem.contribution:id,receipt_number,donor_id',
                    'contributionItem.contribution.donor:id,full_name',
                ])
                ->latest()
                ->limit(25),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('inventory.partials.details', compact('inventoryItem'))->render(),
            ])->header('Cache-Control', 'no-store, private');
        }

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
