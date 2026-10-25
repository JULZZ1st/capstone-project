<?php

namespace App\Http\Controllers;

use App\Http\Requests\Donors\StoreDonorRequest;
use App\Http\Requests\Donors\UpdateDonorRequest;
use App\Models\Donor;
use App\Services\Donors\RemoveDonor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
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
                'workspace_id',
                'donor_type',
                'first_name',
                'last_name',
                'full_name',
                'contact_person',
                'email',
                'contact_number',
                'preferred_contact_method',
                'address',
                'barangay',
                'city_municipality',
                'province',
                'barangay_area',
                'status',
                'notes',
                'created_at',
            ])
            ->search($search)
            ->type($type)
            ->withDonationMetrics()
            ->withCount('contributions')
            ->with(['contributions' => fn ($query) => $query
                ->select(['id', 'donor_id', 'type', 'status', 'amount', 'received_at', 'voided_at'])
                ->latest('received_at')
                ->latest()
                ->limit(20)])
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
        $data = $request->donorData();
        $data['status'] = config('donors.defaults.status');

        if (! $request->duplicateConfirmed() && $this->hasLikelyDuplicate($data)) {
            return redirect()
                ->route('donors.index')
                ->withInput($request->except('_confirm_duplicate'))
                ->with('warning', 'A donor with matching name and contact details may already exist. Review the information, then save again only if this should be a separate donor profile.')
                ->with('duplicate_donor_detected', true);
        }

        Donor::query()->create($data);

        return redirect()
            ->route('donors.index')
            ->with('success', 'Donor profile created successfully.');
    }

    public function show(Donor $donor): View
    {
        Gate::authorize('view', $donor);

        $donor->loadCount([
            'contributions',
            'contributions as donations_count' => fn ($query) => $query->whereNull('voided_at'),
        ]);

        $donations = $donor->contributions()
            ->latest('received_at')
            ->latest()
            ->paginate(config('donors.pagination.history_per_page'));

        return view('donors.show', compact('donor', 'donations'));
    }

    public function update(UpdateDonorRequest $request, Donor $donor)
    {
        $donor->update($request->donorData());

        return redirect()
            ->route('donors.index')
            ->with('success', 'Donor profile updated successfully.');
    }

    public function destroy(Request $request, Donor $donor, RemoveDonor $remover): RedirectResponse
    {
        Gate::authorize('delete', $donor);

        $result = $remover->handle($donor, $request->user());

        $message = $result === 'deactivated'
            ? 'Donor has existing donation history, so the profile was deactivated instead of deleted.'
            : 'Donor profile deleted successfully.';

        return redirect()
            ->route('donors.index')
            ->with('success', $message);
    }

    private function hasLikelyDuplicate(array $data): bool
    {
        $email = $this->normalizeEmail($data['email'] ?? null);
        $contactNumber = $this->normalizeContactNumber($data['contact_number'] ?? '');

        if (($email === null && $contactNumber === '') || $this->normalizedDonorIdentity($data) === '') {
            return false;
        }

        return Donor::query()
            ->select(['id', 'donor_type', 'first_name', 'last_name', 'full_name', 'email', 'contact_number'])
            ->get()
            ->contains(function (Donor $donor) use ($data, $email, $contactNumber): bool {
                if ($this->normalizedDonorIdentity($donor->getAttributes()) !== $this->normalizedDonorIdentity($data)) {
                    return false;
                }

                return ($email !== null && $this->normalizeEmail($donor->email) === $email)
                    || ($contactNumber !== '' && $this->normalizeContactNumber($donor->contact_number) === $contactNumber);
            });
    }

    private function normalizedDonorIdentity(array $data): string
    {
        $type = $data['donor_type'] ?? config('donors.defaults.type');

        if ($type === 'individual') {
            $firstName = $this->normalizeName($data['first_name'] ?? '');
            $lastName = $this->normalizeName($data['last_name'] ?? '');

            if ($firstName !== '' || $lastName !== '') {
                return trim($firstName.' '.$lastName);
            }
        }

        return $this->normalizeName($data['full_name'] ?? '');
    }

    private function normalizeName(?string $name): string
    {
        return Str::lower(Str::squish((string) $name));
    }

    private function normalizeEmail(?string $email): ?string
    {
        $email = Str::lower(trim((string) $email));

        return $email === '' ? null : $email;
    }

    private function normalizeContactNumber(?string $contactNumber): string
    {
        return preg_replace('/\D+/', '', (string) $contactNumber) ?? '';
    }
}
