<?php

namespace App\Http\Controllers;

use App\Http\Requests\Donors\StoreDonorRequest;
use App\Http\Requests\Donors\UpdateDonorRequest;
use App\Models\Donor;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DonorController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $type = $request->query('type');

        $donors = Donor::query()
            ->select([
                'id',
                'donor_type',
                'full_name',
                'contact_person',
                'email',
                'contact_number',
                'preferred_contact_method',
                'address',
                'barangay_area',
                'status',
                'notes',
                'created_at',
            ])
            ->search($search)
            ->type($type)
            ->withDonationMetrics()
            ->latest()
            ->paginate(config('donors.pagination.per_page'))
            ->withQueryString();

        return view('donors.index', [
            'donors' => $donors,
            'search' => $search,
            'selectedType' => $type,
        ]);
    }

    public function store(StoreDonorRequest $request)
    {
        Donor::query()->create($request->validated());

        return redirect()
            ->route('donors.index')
            ->with('success', 'Donor profile created successfully.');
    }

    public function show(Donor $donor): View
    {
        $donor->loadCount('donations');

        $donations = $donor->donations()
            ->latest('received_at')
            ->latest()
            ->paginate(config('donors.pagination.history_per_page'));

        return view('donors.show', compact('donor', 'donations'));
    }

    public function update(UpdateDonorRequest $request, Donor $donor)
    {
        $donor->update($request->validated());

        return redirect()
            ->route('donors.index')
            ->with('success', 'Donor profile updated successfully.');
    }
}
