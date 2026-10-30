<?php

namespace App\Http\Controllers;

use App\Http\Requests\Donations\DonationPortalIndexRequest;
use App\Http\Requests\Donations\IssueCertificateRequest;
use App\Http\Requests\Donations\RequestContributionDocumentsRequest;
use App\Http\Requests\Donations\StoreContributionRequest;
use App\Http\Requests\Donations\UpdateContributionRequest;
use App\Http\Requests\Donations\VerifyContributionRequest;
use App\Models\Campaign;
use App\Models\Contribution;
use App\Models\ContributionEvidence;
use App\Models\Donor;
use App\Models\InventoryCategory;
use App\Models\InventorySubcategory;
use App\Models\SecurityActivityEvent;
use App\Services\Contributions\LogContribution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\QueryException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DonationPortalController extends Controller
{
    public function index(DonationPortalIndexRequest $request): View
    {
        $filters = $request->validated();

        $contributions = Contribution::query()
            ->select([
                'id',
                'workspace_id',
                'donor_id',
                'campaign_id',
                'created_by',
                'verified_by',
                'type',
                'status',
                'amount',
                'purpose_type',
                'purpose_name',
                'payment_method',
                'payment_method_name',
                'receipt_number',
                'reference_number',
                'check_number',
                'bank_name',
                'registered_bank_account',
                'official_receipt_number',
                'certificate_requested',
                'certificate_status',
                'deed_declined',
                'document_review_status',
                'donor_tin',
                'notes',
                'received_at',
                'verified_at',
                'created_at',
            ])
            ->with([
                'donor:id,full_name,contact_number,email,status',
                'campaign:id,name,status',
                'creator:id,first_name,last_name,name',
                'items',
                'evidence',
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

        return view('donations.index', [
            'contributions' => $contributions,
            'filters' => $filters,
            'summary' => $summary,
            'donors' => Donor::query()
                ->active()
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'contact_number']),
            'donorDirectory' => Donor::query()
                ->orderBy('full_name')
                ->get([
                    'id',
                    'donor_type',
                    'first_name',
                    'middle_name',
                    'last_name',
                    'suffix',
                    'full_name',
                    'contact_person',
                    'contact_number',
                    'email',
                    'preferred_contact_method',
                    'tin',
                    'address',
                    'barangay',
                    'barangay_area',
                    'city_municipality',
                    'province',
                    'region',
                    'notes',
                    'status',
                ]),
            'campaigns' => Campaign::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(StoreContributionRequest $request, LogContribution $logger): RedirectResponse
    {
        try {
            $contribution = $logger->handle($request->validated(), $request->user());
        } catch (QueryException $exception) {
            $message = $exception->getMessage();

            if (
                $exception->getCode() !== '23000'
                || ! str_contains($message, '1452')
                || (! str_contains($message, 'donor_id') && ! str_contains($message, 'contributions_donor_id_foreign'))
            ) {
                throw $exception;
            }

            return back()
                ->withInput()
                ->withErrors(['donor_id' => 'The selected donor is no longer available in this workspace. Please select an active donor and try again.']);
        }

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
            'certificateIssuer.role',
            'items',
            'evidence',
        ]);

        $categoryLabels = collect(config('donations.item_main_categories'))
            ->union(InventoryCategory::query()->pluck('label', 'key'));
        $subcategoryLabels = InventorySubcategory::query()
            ->with('category:id,key')
            ->get(['id', 'inventory_category_id', 'key', 'label'])
            ->mapWithKeys(fn (InventorySubcategory $subcategory) => [
                $subcategory->category->key.':'.$subcategory->key => $subcategory->label,
            ]);

        return view('donations.show', compact('contribution', 'categoryLabels', 'subcategoryLabels'));
    }

    public function downloadEvidence(Contribution $contribution, ContributionEvidence $evidence): StreamedResponse
    {
        Gate::authorize('view', $contribution);

        abort_unless($evidence->contribution_id === $contribution->id, 404);
        abort_unless(Storage::disk($evidence->disk)->exists($evidence->path), 404);

        abort_unless(in_array($evidence->mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true), 415);

        $stream = Storage::disk($evidence->disk)->readStream($evidence->path);
        abort_unless(is_resource($stream), 404);

        $filename = str_replace(['\\', '"'], '', basename($evidence->original_name ?: 'document'));

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $evidence->mime_type,
            'Content-Disposition' => 'inline; filename="'.$filename.'"; filename*=UTF-8\'\''.rawurlencode($filename),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function update(UpdateContributionRequest $request, Contribution $contribution): RedirectResponse
    {
        $previousStatus = $contribution->status;
        $contribution->update($request->validated());

        if ($contribution->isCash() && $request->user()->hasRole('admin') && $previousStatus !== $contribution->status) {
            $this->audit($request->user(), 'cash_donation_admin_decision', $contribution, "{$request->user()->name} changed cash donation {$contribution->receipt_number} from {$previousStatus} to {$contribution->status}.");
        }

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

        $this->audit($request->user(), 'cash_donation_verified', $contribution, "{$request->user()->name} verified cash donation {$contribution->receipt_number}.");

        return redirect()
            ->back()
            ->with('success', 'Donation verified successfully.');
    }

    public function requestDocuments(RequestContributionDocumentsRequest $request, Contribution $contribution): RedirectResponse
    {
        $contribution->update([
            'document_request_reason' => $request->validated('document_request_reason'),
            'documents_requested_by' => $request->user()->id,
            'documents_requested_at' => now(),
        ]);

        $this->audit(
            $request->user(),
            'cash_donation_documents_requested',
            $contribution,
            "{$request->user()->name} requested missing documents for cash donation {$contribution->receipt_number}: {$contribution->document_request_reason}"
        );

        return back()->with('success', 'Missing documents request recorded. The donation remains Pending.');
    }

    public function issueCertificate(IssueCertificateRequest $request, Contribution $contribution): RedirectResponse
    {
        $file = $request->file('certificate_of_donation');
        $path = Storage::disk('local')->putFile("contributions/{$contribution->id}/documents", $file);

        $contribution->evidence()->create([
            'document_type' => 'certificate_of_donation',
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        $contribution->update([
            'certificate_status' => 'issued',
            'certificate_issued_by' => $request->user()->id,
            'certificate_issued_at' => now(),
        ]);

        $this->audit($request->user(), 'cash_donation_certificate_issued', $contribution, "{$request->user()->name} marked Certificate of Donation for {$contribution->receipt_number} as issued.");

        return back()->with('success', 'Certificate of Donation marked as issued.');
    }

    private function audit($actor, string $action, Contribution $contribution, string $summary): void
    {
        SecurityActivityEvent::query()->create([
            'actor_id' => $actor->id,
            'action' => $action,
            'summary' => $summary,
            'metadata' => [
                'contribution_id' => $contribution->id,
                'status' => $contribution->status,
                'document_review_status' => $contribution->document_review_status,
            ],
            'ip_address' => request()->ip(),
            'occurred_at' => now(),
        ]);
    }
}
