<?php

namespace App\Http\Controllers;

use App\Http\Requests\Disbursements\RejectDisbursementRequest;
use App\Http\Requests\Disbursements\RequestMissingSignedVoucherRequest;
use App\Http\Requests\Disbursements\RecordDisbursementPaymentRequest;
use App\Http\Requests\Disbursements\RecordTreasurerCertificationRequest;
use App\Http\Requests\Disbursements\StoreDisbursementRequest;
use App\Http\Requests\Disbursements\UpdateDisbursementRequest;
use App\Http\Requests\Disbursements\UploadSignedVoucherRequest;
use App\Http\Requests\Disbursements\VoidContributionRequest;
use App\Http\Requests\Disbursements\VoidDisbursementRequest;
use App\Models\CashDisbursement;
use App\Models\Contribution;
use App\Models\SecurityActivityEvent;
use App\Services\Disbursements\ApproveDisbursement;
use App\Services\Disbursements\RejectDisbursement;
use App\Services\Disbursements\RequestDisbursement;
use App\Services\Disbursements\UpdateDisbursement;
use App\Services\Disbursements\RecordDisbursementPayment;
use App\Services\Disbursements\VoidContribution;
use App\Services\Disbursements\VoidDisbursement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Schoolees\Psgc\Models\Province;

class DisbursementController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', CashDisbursement::class);

        $disbursements = CashDisbursement::query()
            ->with(['requester', 'approver', 'voider', 'treasurerCertifier', 'latestVoucher.uploader', 'latestVoucher.reviewer', 'payment.releaser'])
            ->latest()
            ->paginate(5)
            ->withQueryString();

        $disbursements->getCollection()->each(fn (CashDisbursement $disbursement) => $this->attachAuditHistory($disbursement));

        return view('disbursements.index', [
            'disbursements' => $disbursements,
            'fundRequestProvinces' => Province::query()->orderBy('name')->get(['code', 'name']),
            'fundRequestLocationDefaults' => [
                'province' => old('province', config('disbursements.barangay.province') ?: 'Cebu'),
                'city_municipality' => old('city_municipality', config('disbursements.barangay.city_municipality')),
                'barangay' => old('barangay', config('disbursements.barangay.location_barangay')),
            ],
        ]);
    }

    public function store(StoreDisbursementRequest $request, RequestDisbursement $creator): RedirectResponse
    {
        $creator->handle($request->validated(), $request->user());
        return redirect()->route('disbursements.index')->with('success', 'Fund request submitted.');
    }

    public function update(UpdateDisbursementRequest $request, CashDisbursement $disbursement, UpdateDisbursement $updater): RedirectResponse
    {
        $updater->handle($disbursement, $request->validated());

        $this->audit(
            $request->user(),
            'disbursement_updated',
            $disbursement,
            "{$request->user()->name} updated fund request #{$disbursement->id}.",
            ['dv_number' => $disbursement->dv_number]
        );

        return back()->with('success', 'Fund request updated.');
    }

    public function show(CashDisbursement $disbursement): View
    {
        Gate::authorize('view', $disbursement);

        $disbursement->load(['requester', 'approver', 'voider', 'treasurerCertifier', 'latestVoucher.uploader', 'latestVoucher.reviewer', 'payment.releaser']);
        $this->attachAuditHistory($disbursement);

        return view('disbursements.show-modal', [
            'disbursement' => $disbursement,
            'fundRequestProvinces' => Province::query()->orderBy('name')->get(['code', 'name']),
        ]);
    }

    private function attachAuditHistory(CashDisbursement $disbursement): void
    {
        $disbursement->setRelation('auditEvents', SecurityActivityEvent::query()
            ->whereJsonContains('metadata->disbursement_id', $disbursement->id)
            ->latest('occurred_at')
            ->limit(20)
            ->get());
    }

    public function voucher(CashDisbursement $disbursement): Response
    {
        Gate::authorize('view', $disbursement);

        $disbursement->load('requester');

        return Pdf::loadView('disbursements.pdf.voucher', [
            'disbursement' => $disbursement,
            'barangayProfile' => config('disbursements.barangay', []),
        ])->setPaper('a4', 'portrait')->download(
            sprintf('disbursement-voucher-%d.pdf', $disbursement->id)
        );
    }

    public function uploadSignedVoucher(UploadSignedVoucherRequest $request, CashDisbursement $disbursement): RedirectResponse
    {
        $file = $request->file('signed_voucher');
        $path = Storage::disk('local')->putFile("disbursements/{$disbursement->id}/signed-vouchers", $file);

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'signed_voucher' => 'The signed voucher could not be stored. Please try uploading it again.',
            ]);
        }

        $document = $disbursement->vouchers()->create([
            'document_type' => 'signed_voucher',
            'review_status' => 'pending_review',
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        $disbursement->forceFill([
            'signed_voucher_status' => 'pending_review',
            'signed_voucher_request_reason' => null,
            'signed_voucher_requested_by' => null,
            'signed_voucher_requested_at' => null,
        ])->save();

        $this->audit(
            $request->user(),
            'disbursement_signed_voucher_uploaded',
            $disbursement,
            "{$request->user()->name} uploaded signed voucher for fund request #{$disbursement->id}.",
            ['voucher_id' => $document->id, 'original_name' => $document->original_name, 'mime_type' => $document->mime_type]
        );

        return back()->with('success', 'Signed voucher uploaded and submitted for Admin review.');
    }

    public function previewSignedVoucher(CashDisbursement $disbursement): StreamedResponse
    {
        Gate::authorize('view', $disbursement);

        $document = $disbursement->latestVoucher;
        abort_unless($document && Storage::disk($document->disk)->exists($document->path), 404);
        abort_unless(in_array($document->mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true), 415);

        $stream = Storage::disk($document->disk)->readStream($document->path);
        abort_unless(is_resource($stream), 404);

        $filename = str_replace(['\\', '"'], '', basename($document->original_name ?: 'signed-voucher'));

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'inline; filename="'.$filename.'"; filename*=UTF-8\'\''.rawurlencode($filename),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function requestMissingSignedVoucher(RequestMissingSignedVoucherRequest $request, CashDisbursement $disbursement): RedirectResponse
    {
        $reason = $request->validated('reason');
        $disbursement->forceFill([
            'signed_voucher_status' => 'missing',
            'signed_voucher_request_reason' => $reason,
            'signed_voucher_requested_by' => $request->user()->id,
            'signed_voucher_requested_at' => now(),
        ])->save();

        if ($disbursement->latestVoucher) {
            $disbursement->latestVoucher->forceFill([
                'review_status' => 'missing',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_reason' => $reason,
            ])->save();
        }

        $this->audit(
            $request->user(),
            'disbursement_signed_voucher_documents_requested',
            $disbursement,
            "{$request->user()->name} requested a missing or corrected signed voucher for fund request #{$disbursement->id}: {$reason}",
            ['reason' => $reason]
        );

        return back()->with('success', 'Missing signed voucher documents request recorded.');
    }

    public function recordPayment(RecordDisbursementPaymentRequest $request, CashDisbursement $disbursement, RecordDisbursementPayment $recorder): RedirectResponse
    {
        $recorder->handle($disbursement, $request->validated(), $request->user());

        return back()->with('success', 'Actual fund payment recorded.');
    }

    public function recordTreasurerCertification(RecordTreasurerCertificationRequest $request, CashDisbursement $disbursement): RedirectResponse
    {
        $disbursement->forceFill([
            'treasurer_certified_by' => $request->user()->id,
            'treasurer_certified_at' => now(),
            'treasurer_certification_notes' => $request->validated('notes'),
        ])->save();

        $this->audit(
            $request->user(),
            'disbursement_treasurer_certified',
            $disbursement,
            "{$request->user()->name} recorded the Treasurer's physical certification for fund request #{$disbursement->id}.",
            ['notes' => $disbursement->treasurer_certification_notes]
        );

        return back()->with('success', "Treasurer's physical certification recorded.");
    }

    public function approve(CashDisbursement $disbursement, ApproveDisbursement $approver): RedirectResponse
    {
        Gate::authorize('approve', $disbursement);
        $approver->handle($disbursement, request()->user());
        return redirect()->route('disbursements.index')->with('success', 'Fund request approved.');
    }

    public function reject(RejectDisbursementRequest $request, CashDisbursement $disbursement, RejectDisbursement $rejector): RedirectResponse
    {
        $rejector->handle($disbursement, $request->validated('rejection_reason'), $request->user());
        return redirect()->route('disbursements.index')->with('success', 'Fund request rejected.');
    }

    public function voidContribution(VoidContributionRequest $request, Contribution $contribution, VoidContribution $voider): RedirectResponse
    {
        $voider->handle($contribution, $request->validated('void_reason'), $request->user());
        return back()->with('success', 'Contribution voided.');
    }

    public function voidDisbursement(VoidDisbursementRequest $request, CashDisbursement $disbursement, VoidDisbursement $voider): RedirectResponse
    {
        $voider->handle($disbursement, $request->validated('void_reason'), $request->user());
        return back()->with('success', 'Fund request cancelled.');
    }

    private function audit($actor, string $action, CashDisbursement $disbursement, string $summary, array $metadata = []): void
    {
        SecurityActivityEvent::query()->create([
            'user_id' => $disbursement->requested_by,
            'actor_id' => $actor->id,
            'action' => $action,
            'summary' => $summary,
            'metadata' => ['disbursement_id' => $disbursement->id, ...$metadata],
            'ip_address' => request()->ip(),
            'occurred_at' => now(),
        ]);
    }
}
