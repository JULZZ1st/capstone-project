<?php

namespace App\Http\Controllers;

use App\Http\Requests\Donations\DonationPortalIndexRequest;
use App\Http\Requests\Donations\StoreContributionRequest;
use App\Http\Requests\Donations\UpdateContributionRequest;
use App\Http\Requests\Donations\VerifyContributionRequest;
use App\Models\Campaign;
use App\Models\Contribution;
use App\Models\Donor;
use App\Models\InventoryCategory;
use App\Models\InventorySubcategory;
use App\Services\Contributions\LogContribution;
use App\Services\Inventory\ResolveInventoryClassification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DonationPortalController extends Controller
{
    public function index(DonationPortalIndexRequest $request): View
    {
        $filters = $request->validated();

        $contributions = Contribution::query()
            ->select([
                'id',
                'donor_id',
                'campaign_id',
                'created_by',
                'verified_by',
                'type',
                'status',
                'amount',
                'payment_method',
                'receipt_number',
                'reference_number',
                'notes',
                'received_at',
                'verified_at',
                'created_at',
            ])
            ->when(Schema::hasColumn('contributions', 'payment_method_name'), fn ($query) => $query->addSelect('payment_method_name'))
            ->with([
                'donor:id,full_name,contact_number,email,status',
                'campaign:id,name,status',
                'creator:id,first_name,last_name,name',
            ])
            ->withCount('items')
            ->withSum('items', 'estimated_value')
            ->search($filters['search'] ?? null)
            ->type($filters['type'] ?? null)
            ->campaign(isset($filters['campaign_id']) ? (int) $filters['campaign_id'] : null)
            ->status($filters['status'] ?? null)
            ->latest('received_at')
            ->latest()
            ->paginate(config('donations.pagination.per_page'))
            ->withQueryString();

        $summary = Contribution::query()
            ->selectRaw(<<<'SQL'
                COUNT(*) as total_contributions,
                SUM(CASE WHEN type = 'cash' THEN 1 ELSE 0 END) as cash_contributions,
                SUM(CASE WHEN type = 'in_kind' THEN 1 ELSE 0 END) as in_kind_contributions,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_verification
            SQL)
            ->first();

        $categoriesForItems = InventoryCategory::query()
            ->active()
            ->with(['subcategories' => fn ($query) => $query->active()->orderBy('label')])
            ->orderBy('label')
            ->get(['id', 'key', 'label']);

        return view('donations.index', [
            'contributions' => $contributions,
            'filters' => $filters,
            'summary' => $summary,
            'donors' => Donor::query()
                ->active()
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'contact_number']),
            'campaigns' => Campaign::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name']),
            'categories' => $categoriesForItems,
            'subcategoriesByCategory' => $categoriesForItems
                ->mapWithKeys(fn (InventoryCategory $category) => [$category->key => $category->subcategories->map(fn (InventorySubcategory $subcategory) => [
                    'key' => $subcategory->key,
                    'label' => $subcategory->label,
                ])->values()])
                ->all(),
        ]);
    }

    public function store(StoreContributionRequest $request, LogContribution $logger, ResolveInventoryClassification $resolver): RedirectResponse
    {
        $contribution = $logger->handle($request->validated(), $request->user(), $resolver);

        return redirect()
            ->route('donations.index')
            ->with('success', 'Contribution logged successfully.')
            ->with('highlight_contribution', $contribution->id);
    }

    public function show(Contribution $contribution): View
    {
        Gate::authorize('view', $contribution);

        $contribution->load([
            'donor',
            'campaign',
            'creator.role',
            'verifier.role',
            'items',
        ]);

        $categoryLabels = InventoryCategory::query()->pluck('label', 'key');
        $subcategoryLabels = InventorySubcategory::query()
            ->with('category:id,key')
            ->get(['id', 'inventory_category_id', 'key', 'label'])
            ->mapWithKeys(fn (InventorySubcategory $subcategory) => [
                $subcategory->category->key.':'.$subcategory->key => $subcategory->label,
            ]);

        return view('donations.show', compact('contribution', 'categoryLabels', 'subcategoryLabels'));
    }

    public function update(UpdateContributionRequest $request, Contribution $contribution): RedirectResponse
    {
        $contribution->update($request->validated());

        return redirect()
            ->route('donations.show', $contribution)
            ->with('success', 'Contribution updated successfully.');
    }

    public function verify(VerifyContributionRequest $request, Contribution $contribution): RedirectResponse
    {
        $contribution->update([
            'status' => 'verified',
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        return redirect()
            ->back()
            ->with('success', 'Cash contribution verified successfully.');
    }
}
