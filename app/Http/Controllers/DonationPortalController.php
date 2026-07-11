<?php

namespace App\Http\Controllers;

use App\Http\Requests\Donations\DonationPortalIndexRequest;
use App\Http\Requests\Donations\StoreContributionRequest;
use App\Http\Requests\Donations\UpdateContributionRequest;
use App\Http\Requests\Donations\VerifyContributionRequest;
use App\Models\Campaign;
use App\Models\Contribution;
use App\Models\Donor;
use App\Services\Contributions\LogContribution;
use Illuminate\Http\RedirectResponse;
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
                'reference_number',
                'notes',
                'received_at',
                'verified_at',
                'created_at',
            ])
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

        return view('donations.index', [
            'contributions' => $contributions,
            'filters' => $filters,
            'donors' => Donor::query()
                ->active()
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'contact_number']),
            'campaigns' => Campaign::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(StoreContributionRequest $request, LogContribution $logger): RedirectResponse
    {
        $contribution = $logger->handle($request->validated(), $request->user());

        return redirect()
            ->route('donations.index')
            ->with('success', 'Contribution logged successfully.')
            ->with('highlight_contribution', $contribution->id);
    }

    public function show(Contribution $contribution): View
    {
        $this->authorize('view', $contribution);

        $contribution->load([
            'donor',
            'campaign',
            'creator.role',
            'verifier.role',
            'items',
        ]);

        return view('donations.show', compact('contribution'));
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
