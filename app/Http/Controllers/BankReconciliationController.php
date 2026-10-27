<?php

namespace App\Http\Controllers;

use App\Http\Requests\BankReconciliation\ImportBankStatementRequest;
use App\Http\Requests\BankReconciliation\CreateReconciliationPeriodRequest;
use App\Http\Requests\BankReconciliation\UpdateReconciliationPeriodRequest;
use App\Http\Requests\BankReconciliation\CreateReconciliationDiscrepancyRequest;
use App\Http\Requests\BankReconciliation\ReconciliationCorrectionRequest;
use App\Http\Requests\BankReconciliation\ReconcileLedgerEntryRequest;
use App\Http\Requests\BankReconciliation\StoreBankWithdrawalRequest;
use App\Http\Requests\BankReconciliation\StoreWithdrawalDisbursementMatchRequest;
use App\Models\BankLedgerEntry;
use App\Models\BankReconciliation;
use App\Models\CashDisbursement;
use App\Models\Contribution;
use App\Models\ReconciliationDiscrepancy;
use App\Services\BankReconciliation\ImportBankStatement;
use App\Services\BankReconciliation\LedgerMatchFinder;
use App\Services\BankReconciliation\ReconcileLedgerEntry;
use App\Services\BankReconciliation\ReconciliationPeriodService;
use App\Services\BankReconciliation\ReconciliationPeriodGuard;
use App\Services\BankReconciliation\ReconciliationDiscrepancyService;
use App\Services\BankReconciliation\ReconciliationApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use App\Support\Workspaces\WorkspaceContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BankReconciliationController extends Controller
{
    public function index(LedgerMatchFinder $matchFinder): View
    {
        Gate::authorize('viewAny', BankLedgerEntry::class);

        $ledgerEntries = BankLedgerEntry::query()
            ->incoming()
            ->needsReview()
            ->latest('transaction_date')
            ->limit(config('bank_reconciliation.limits.feed'))
            ->get();

        $withdrawals = BankLedgerEntry::query()
            ->withdrawalsToReview()
            ->with(['matchedDisbursements.requester:id,name,first_name,last_name'])
            ->latest('transaction_date')
            ->limit(config('bank_reconciliation.limits.feed'))
            ->get();

        $eligibleDisbursements = CashDisbursement::query()
            ->approvedActive()
            ->whereDoesntHave('withdrawalMatch')
            ->with('requester:id,name,first_name,last_name')
            ->latest('approved_at')
            ->get();

        $pendingReceipts = Contribution::query()
            ->eligibleForReconciliation()
            ->with(['donor:id,full_name', 'campaign:id,name'])
            ->latest('received_at')
            ->limit(config('bank_reconciliation.limits.pending_receipts'))
            ->get();

        $history = BankLedgerEntry::query()
            ->where('status', 'reconciled')
            ->with([
                'matchedContribution.donor:id,full_name',
                'matcher:id,first_name,last_name,name',
            ])
            ->latest('matched_at')
            ->limit(config('bank_reconciliation.limits.history', 20))
            ->get();

        $withdrawalHistory = BankLedgerEntry::query()
            ->where('transaction_type', 'withdrawal')
            ->where('status', 'reconciled')
            ->with([
                'matchedDisbursements',
                'reconciler:id,first_name,last_name,name',
            ])
            ->latest('reconciled_at')
            ->limit(config('bank_reconciliation.limits.history', 20))
            ->get();

        $suggestions = $ledgerEntries->mapWithKeys(
            fn (BankLedgerEntry $entry) => [$entry->id => $matchFinder->suggest($entry, $pendingReceipts)]
        );

        return view('bank-reconciliation', [
            'latestReconciliationPeriod' => BankReconciliation::query()->with('discrepancies')->latest('period_end')->first(),
            'ledgerEntries' => $ledgerEntries,
            'pendingReceipts' => $pendingReceipts,
            'suggestions' => $suggestions,
            'unreconciledFeedCount' => BankLedgerEntry::query()->incoming()->needsReview()->count(),
            'pendingReceiptsCount' => Contribution::query()->eligibleForReconciliation()->count(),
            'withdrawals' => $withdrawals,
            'eligibleDisbursements' => $eligibleDisbursements,
            'withdrawalsToReviewCount' => BankLedgerEntry::query()->withdrawalsToReview()->count(),
            'history' => $history,
            'withdrawalHistory' => $withdrawalHistory,
        ]);
    }

    public function createPeriod(CreateReconciliationPeriodRequest $request, ReconciliationPeriodService $periods): RedirectResponse
    {
        $periods->create([...$request->validated(), 'prepared_by' => $request->user()->id]);

        return redirect()
            ->route('bank-reconciliation.index')
            ->with('success', 'Reconciliation period created and calculated from current records.');
    }

    public function submitPeriod(BankReconciliation $period, ReconciliationApprovalService $approvals): RedirectResponse
    {
        Gate::authorize('submit', $period);
        $approvals->submit($period, request()->user());
        return back()->with('success', 'Reconciliation period submitted for independent review.');
    }

    public function updatePeriod(UpdateReconciliationPeriodRequest $request, BankReconciliation $period, ReconciliationPeriodService $periods): RedirectResponse
    {
        Gate::authorize('update', $period);
        $periods->updateBalances($period, $request->validated());
        return back()->with('success', 'Draft reconciliation balances updated and recalculated.');
    }

    public function approvePeriod(BankReconciliation $period, ReconciliationApprovalService $approvals): RedirectResponse
    {
        Gate::authorize('approve', $period);
        $approvals->approve($period, request()->user());
        return back()->with('success', 'Reconciliation period approved and locked.');
    }

    public function rejectPeriod(ReconciliationCorrectionRequest $request, BankReconciliation $period, ReconciliationApprovalService $approvals): RedirectResponse
    {
        Gate::authorize('reject', $period);
        $approvals->reject($period, $request->user(), $request->validated('reason'));
        return back()->with('success', 'Reconciliation period returned for correction.');
    }

    public function reopenPeriod(ReconciliationCorrectionRequest $request, BankReconciliation $period, ReconciliationApprovalService $approvals): RedirectResponse
    {
        Gate::authorize('reopen', $period);
        $approvals->reopen($period, $request->user(), $request->validated('reason'));
        return back()->with('success', 'Reconciliation period reopened under an audited correction workflow.');
    }

    public function createDiscrepancy(CreateReconciliationDiscrepancyRequest $request, BankReconciliation $period, ReconciliationDiscrepancyService $discrepancies): RedirectResponse
    {
        $data = $request->validated();
        $data['adjustment_amount'] = $data['classification'] === 'accounting_adjustment'
            ? (float) ($data['adjustment_amount'] ?? 0)
            : 0;
        $data['carry_forward'] = $data['classification'] === 'timing_difference' && $request->boolean('carry_forward');
        $discrepancies->create($period, $data, $request->user());

        return redirect()->route('bank-reconciliation.index')->with('success', 'Discrepancy recorded for investigation.');
    }

    public function withdrawalDetails(BankLedgerEntry $withdrawal): View
    {
        Gate::authorize('viewAny', BankLedgerEntry::class);

        abort_unless($withdrawal->isWithdrawal() && $withdrawal->isReconciled(), 404);

        $withdrawal->load([
            'matchedDisbursements',
            'reconciler:id,first_name,last_name,name',
        ]);

        return view('bank-reconciliation.withdrawal-details', [
            'withdrawal' => $withdrawal,
            'matchedTotal' => $withdrawal->matchedDisbursements->sum(fn ($disbursement) => (float) $disbursement->amount),
        ]);
    }

    public function import(ImportBankStatementRequest $request, ImportBankStatement $importer): RedirectResponse
    {
        $count = $importer->handle($request->file('statement'), $request->user());
        $routeParameters = $request->input('return_tab') === 'outgoing'
            ? ['tab' => 'outgoing']
            : [];

        return redirect()
            ->route('bank-reconciliation.index', $routeParameters)
            ->with('success', "{$count} bank transaction(s) imported successfully.");
    }

    public function storeWithdrawal(StoreBankWithdrawalRequest $request, ReconciliationPeriodGuard $periodGuard): RedirectResponse
    {
        $data = $request->validated();
        $periodGuard->assertMutableDate($data['transaction_date']);

        BankLedgerEntry::query()->create([
            'statement_id' => $this->withdrawalStatementId($data),
            'description' => $data['description'],
            'reference_number' => $data['reference_number'],
            'amount' => $data['amount'],
            'transaction_date' => $data['transaction_date'],
            'transaction_type' => 'withdrawal',
            'status' => 'unreconciled',
        ]);

        return redirect()
            ->route('bank-reconciliation.index', ['tab' => 'outgoing'])
            ->with('success', 'Bank withdrawal entry recorded for review.');
    }

    public function storeWithdrawalMatches(StoreWithdrawalDisbursementMatchRequest $request, BankLedgerEntry $withdrawal, ReconciliationPeriodGuard $periodGuard): RedirectResponse
    {
        Gate::authorize('matchWithdrawal', $withdrawal);

        $periodGuard->assertMutableEntry($withdrawal);
        $disbursementIds = $request->disbursementIds();
        DB::transaction(function () use ($disbursementIds, $request, $withdrawal): void {
            $lockedWithdrawal = BankLedgerEntry::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
            $disbursements = CashDisbursement::query()->whereIn('id', $disbursementIds)->lockForUpdate()->get();
            $selectedTotal = $disbursements->sum('amount');
            $difference = (float) $lockedWithdrawal->amount - (float) $selectedTotal;
            $lockedWithdrawal->matchedDisbursements()->syncWithPivotValues(
                $disbursementIds,
                ['workspace_id' => app(WorkspaceContext::class)->requireId()],
            );
            $lockedWithdrawal->forceFill([
                'discrepancy_amount' => abs($difference) >= 0.005 ? $difference : null,
                'discrepancy_reason' => abs($difference) >= 0.005 ? trim($request->input('discrepancy_reason')) : null,
            ])->save();
        });

        return redirect()
            ->route('bank-reconciliation.index', ['tab' => 'outgoing'])
            ->with('success', 'Withdrawal match selection updated.');
    }

    public function finalizeWithdrawal(StoreWithdrawalDisbursementMatchRequest $request, BankLedgerEntry $withdrawal): RedirectResponse
    {
        Gate::authorize('matchWithdrawal', $withdrawal);

        $disbursementIds = $request->disbursementIds();

        DB::transaction(function () use ($disbursementIds, $request, $withdrawal): void {
            $withdrawal = BankLedgerEntry::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
            $selectedTotal = CashDisbursement::query()->whereIn('id', $disbursementIds)->sum('amount');
            $difference = (float) $withdrawal->amount - (float) $selectedTotal;

            if ($withdrawal->isReconciled()) {
                throw ValidationException::withMessages(['withdrawal' => 'This bank withdrawal has already been reconciled.']);
            }

            $period = BankReconciliation::query()->whereDate('period_start', '<=', $withdrawal->transaction_date)->whereDate('period_end', '>=', $withdrawal->transaction_date)->first();
            if ($period?->isLocked()) {
                throw ValidationException::withMessages(['period' => 'Transactions in a locked reconciliation period cannot be modified.']);
            }
            if (abs($difference) >= 0.005) {
                throw ValidationException::withMessages(['finalize' => 'The withdrawal can only be reconciled when the difference is zero.']);
            }

            $withdrawal->matchedDisbursements()->sync($disbursementIds);
            $withdrawal->forceFill([
                'status' => 'reconciled',
                'discrepancy_amount' => null,
                'discrepancy_reason' => null,
                'reconciled_by' => $request->user()->id,
                'reconciled_at' => now(),
            ])->save();
        });

        return redirect()
            ->route('bank-reconciliation.index', ['tab' => 'outgoing'])
            ->with('success', 'Bank withdrawal reconciled successfully.');
    }

    public function reconcile(ReconcileLedgerEntryRequest $request, ReconcileLedgerEntry $reconciler): RedirectResponse
    {
        $data = $request->validated();

        $reconciler->handle(
            BankLedgerEntry::findOrFail($data['bank_ledger_entry_id']),
            Contribution::findOrFail($data['contribution_id']),
            $request->user(),
        );

        return redirect()
            ->route('bank-reconciliation.index')
            ->with('success', 'Bank ledger entry reconciled successfully.');
    }

    private function withdrawalStatementId(array $data): string
    {
        return 'withdrawal-'.sha1(implode('|', [
            $data['transaction_date'],
            $data['reference_number'],
            number_format((float) $data['amount'], 2, '.', ''),
        ]));
    }
}
