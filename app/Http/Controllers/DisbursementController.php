<?php

namespace App\Http\Controllers;

use App\Http\Requests\Disbursements\RejectDisbursementRequest;
use App\Http\Requests\Disbursements\StoreDisbursementRequest;
use App\Http\Requests\Disbursements\VoidContributionRequest;
use App\Http\Requests\Disbursements\VoidDisbursementRequest;
use App\Models\CashDisbursement;
use App\Models\Contribution;
use App\Services\Disbursements\ApproveDisbursement;
use App\Services\Disbursements\RejectDisbursement;
use App\Services\Disbursements\RequestDisbursement;
use App\Services\Disbursements\VoidContribution;
use App\Services\Disbursements\VoidDisbursement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DisbursementController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', CashDisbursement::class);

        return view('disbursements.index', [
            'disbursements' => CashDisbursement::query()
                ->with(['requester', 'approver'])
                ->latest()
                ->paginate(5)
                ->withQueryString(),
        ]);
    }

    public function store(StoreDisbursementRequest $request, RequestDisbursement $creator): RedirectResponse
    {
        $creator->handle($request->validated(), $request->user());
        return redirect()->route('disbursements.index')->with('success', 'Fund request submitted.');
    }

    public function show(CashDisbursement $disbursement): View
    {
        Gate::authorize('view', $disbursement);

        $disbursement->load(['requester', 'approver', 'voider']);

        return view('disbursements.show', [
            'disbursement' => $disbursement,
        ]);
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
}
