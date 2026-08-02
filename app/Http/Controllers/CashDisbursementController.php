<?php

namespace App\Http\Controllers;

use App\Http\Requests\Disbursements\CashDisbursementIndexRequest;
use App\Http\Requests\Disbursements\StoreCashDisbursementRequest;
use App\Models\CashDisbursement;
use App\Services\Disbursements\RecordCashDisbursement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CashDisbursementController extends Controller
{
    public function index(CashDisbursementIndexRequest $request): View
    {
        $filters = $request->validated();

        $disbursements = CashDisbursement::query()
            ->select(['id', 'disbursed_to', 'purpose', 'amount', 'payment_method', 'reference_number', 'notes', 'disbursed_at', 'created_by'])
            ->with('creator:id,first_name,last_name,name')
            ->search($filters['search'] ?? null)
            ->purpose($filters['purpose'] ?? null)
            ->dateRange($filters['from'] ?? null, $filters['to'] ?? null)
            ->latest('disbursed_at')
            ->latest()
            ->paginate(config('disbursements.pagination.per_page'))
            ->withQueryString();

        return view('cash-disbursements.index', [
            'disbursements' => $disbursements,
            'filters' => $filters,
        ]);
    }

    public function store(StoreCashDisbursementRequest $request, RecordCashDisbursement $recorder): RedirectResponse
    {
        $disbursement = $recorder->handle($request->validated(), $request->user());

        return redirect()
            ->route('cash-disbursements.index')
            ->with('success', 'Cash disbursement recorded successfully.')
            ->with('highlight_cash_disbursement', $disbursement->id);
    }

    public function show(CashDisbursement $cashDisbursement): View
    {
        Gate::authorize('view', $cashDisbursement);

        $cashDisbursement->load('creator:id,first_name,last_name,name');

        return view('cash-disbursements.show', compact('cashDisbursement'));
    }
}
