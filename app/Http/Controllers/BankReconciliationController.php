<?php

namespace App\Http\Controllers;

use App\Http\Requests\BankReconciliation\ImportBankStatementRequest;
use App\Http\Requests\BankReconciliation\CreateReconciliationPeriodRequest;
use App\Http\Requests\BankReconciliation\UpdateReconciliationPeriodRequest;
use App\Http\Requests\BankReconciliation\CreateReconciliationDiscrepancyRequest;
use App\Http\Requests\BankReconciliation\ReconciliationCorrectionRequest;
use App\Http\Requests\BankReconciliation\ReviewReconciliationDiscrepancyRequest;
use App\Http\Requests\BankReconciliation\ResolveReconciliationDiscrepancyRequest;
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
use App\Services\BankReconciliation\MatchWithdrawalDisbursements;
use App\Services\BankReconciliation\ReconcileLedgerEntry;
use App\Services\BankReconciliation\ReconciliationPeriodService;
use App\Services\BankReconciliation\ReconciliationPeriodGuard;
use App\Services\BankReconciliation\ReconciliationDiscrepancyService;
use App\Services\BankReconciliation\ReconciliationApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use App\Support\Workspaces\WorkspaceContext;
use Illuminate\View\View;

class BankReconciliationController extends Controller
{
    public function index(LedgerMatchFinder $matchFinder): View
    {
        Gate::authorize('viewAny', BankLedgerEntry::class);

        $selectedPeriod = request()->integer('period_id')
            ? BankReconciliation::query()->with(['discrepancies.reporter', 'discrepancies.adminReviewer', 'discrepancies.resolver'])->findOrFail(request()->integer('period_id'))
            : null;
        $activePeriod = $selectedPeriod ?: BankReconciliation::query()->with(['discrepancies.reporter', 'discrepancies.adminReviewer', 'discrepancies.resolver'])->latest('period_end')->first();
        $confirmedPeriods = BankReconciliation::query()
            ->whereIn('status', ['locked', 'approved_locked'])
            ->whereNotNull('bank_ending_balance')
            ->whereNotNull('period_end')
            ->orderByDesc('period_end')
            ->get(['bank_account', 'period_end', 'bank_ending_balance']);
        $reconciliationPeriods = BankReconciliation::query()->orderByDesc('period_end')->get();

        $ledgerEntries = BankLedgerEntry::query()
            ->incoming()
            ->needsReview()
            ->with(['discrepancies.reporter', 'discrepancies.adminReviewer', 'discrepancies.resolver'])
            ->latest('transaction_date')
            ->limit(config('bank_reconciliation.limits.feed'))
            ->get();

        $withdrawals = BankLedgerEntry::query()
            ->withdrawalsToReview()
            ->with(['matchedDisbursements.requester:id,name,first_name,last_name', 'discrepancies.reporter', 'discrepancies.adminReviewer', 'discrepancies.resolver'])
            ->latest('transaction_date')
            ->limit(config('bank_reconciliation.limits.feed'))
            ->get();

        $eligibleDisbursements = CashDisbursement::query()
            ->eligibleForWithdrawalMatching()
            ->with('requester:id,name,first_name,last_name')
            ->latest('approved_at')
            ->get();

        $pendingReceipts = Contribution::query()
            ->eligibleForReconciliation()
            ->with('donor:id,full_name')
            ->latest('received_at')
            ->limit(config('bank_reconciliation.limits.pending_receipts'))
            ->get();

        $history = BankLedgerEntry::query()
            ->where('status', 'reconciled')
            ->with([
                'matchedContribution.donor:id,full_name',
                'matcher:id,first_name,last_name,name',
                'discrepancies.reporter', 'discrepancies.adminReviewer', 'discrepancies.resolver',
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
                'discrepancies.reporter', 'discrepancies.adminReviewer', 'discrepancies.resolver',
            ])
            ->latest('reconciled_at')
            ->limit(config('bank_reconciliation.limits.history', 20))
            ->get();

        $suggestions = $ledgerEntries->mapWithKeys(
            fn (BankLedgerEntry $entry) => [$entry->id => $matchFinder->suggest($entry, $pendingReceipts)]
        );
        $boardBankTransactions = $activePeriod
            ? BankLedgerEntry::query()->whereDate('transaction_date', '>=', $activePeriod->period_start)->whereDate('transaction_date', '<=', $activePeriod->period_end)->with(['matchedContribution', 'matchedDisbursements'])->orderBy('transaction_date')->get()
            : collect();
        $boardContributions = Contribution::query()->where('type', 'cash')->whereIn('payment_method', config('donations.electronic_payment_methods'))->where('status', 'verified')->whereNull('voided_at')->with(['donor:id,full_name', 'ledgerEntry'])->when($activePeriod, fn ($query) => $query->whereDate('received_at', '>=', $activePeriod->period_start)->whereDate('received_at', '<=', $activePeriod->period_end))->orderBy('received_at')->get();
        $boardDisbursements = CashDisbursement::query()
            ->approvedActive()
            ->where(function ($query) {
                $query->whereDoesntHave('payment')
                    ->orWhereHas('payment', fn ($payment) => $payment->where('payment_method', 'bank_transfer'));
            })
            ->with(['requester:id,first_name,last_name,name', 'withdrawalMatch'])
            ->when($activePeriod, fn ($query) => $query->whereDate('approved_at', '>=', $activePeriod->period_start)->whereDate('approved_at', '<=', $activePeriod->period_end))
            ->orderBy('approved_at')
            ->get();

        return view('bank-reconciliation', [
            'bankAccounts' => config('bank_reconciliation.bank_accounts', []),
            'latestReconciliationPeriod' => $activePeriod,
            'reconciliationPeriods' => $reconciliationPeriods,
            'confirmedPeriods' => $confirmedPeriods,
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
            'boardBankTransactions' => $boardBankTransactions,
            'boardContributions' => $boardContributions,
            'boardDisbursements' => $boardDisbursements,
        ]);
    }

    public function createPeriod(CreateReconciliationPeriodRequest $request, ReconciliationPeriodService $periods): RedirectResponse
    {
        $data = $request->validated();
        if (filled($data['opening_balance_explanation'] ?? null)) {
            $data['notes'] = $data['notes'] ?? $data['opening_balance_explanation'];
        }
        unset($data['opening_balance_explanation']);
        $period = $periods->create([...$data, 'prepared_by' => $request->user()->id]);

        return redirect()
            ->route('bank-reconciliation.index', ['period_id' => $period->id])
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
        $data['custom_type'] = $data['type'] === 'other' ? trim((string) ($data['custom_type'] ?? '')) : null;
        $data['reported_by'] = $request->user()->id;
        abort_unless((int) $period->workspace_id === (int) app(WorkspaceContext::class)->requireId(), 404);
        if (! empty($data['bank_ledger_entry_id'])) {
            $entry = BankLedgerEntry::query()->whereKey($data['bank_ledger_entry_id'])->firstOrFail();
            abort_unless($entry->workspace_id === $period->workspace_id && $entry->transaction_date?->between($period->period_start, $period->period_end), 422);
            if ($entry->status === 'reconciled') {
                throw ValidationException::withMessages(['bank_ledger_entry_id' => 'A reconciled bank transaction cannot receive a new discrepancy report.']);
            }
        }
        if (! empty($data['system_record_type']) && ! empty($data['system_record_id'])) {
            $record = $data['system_record_type'] === 'contribution'
                ? Contribution::query()->findOrFail($data['system_record_id'])
                : CashDisbursement::query()->findOrFail($data['system_record_id']);
            abort_unless($record->workspace_id === $period->workspace_id, 422);
        }
        if ($request->hasFile('supporting_evidence')) {
            $data['evidence_path'] = $request->file('supporting_evidence')->store('bank-reconciliation/discrepancies/'.$period->id);
        }
        unset($data['supporting_evidence']);
        $data['adjustment_amount'] = $data['classification'] === 'accounting_adjustment'
            ? (float) ($data['adjustment_amount'] ?? 0)
            : 0;
        $data['carry_forward'] = $data['classification'] === 'timing_difference' && $request->boolean('carry_forward');
        $discrepancies->create($period, $data, $request->user());

        return redirect()->route('bank-reconciliation.index', ['period_id' => $period->id])->with('success', 'Unmatched record reported for investigation.');
    }

    public function updateDiscrepancy(CreateReconciliationDiscrepancyRequest $request, BankReconciliation $period, ReconciliationDiscrepancy $discrepancy, ReconciliationDiscrepancyService $discrepancies): RedirectResponse
    {
        abort_unless((int) $period->workspace_id === (int) app(WorkspaceContext::class)->requireId(), 404);
        $data = $request->validated();
        $data['custom_type'] = $data['type'] === 'other' ? trim((string) ($data['custom_type'] ?? '')) : null;
        $data['adjustment_amount'] = $data['classification'] === 'accounting_adjustment' ? (float) ($data['adjustment_amount'] ?? 0) : 0;
        $data['carry_forward'] = $data['classification'] === 'timing_difference' && $request->boolean('carry_forward');
        if ($request->hasFile('supporting_evidence')) $data['evidence_path'] = $request->file('supporting_evidence')->store('bank-reconciliation/discrepancies/'.$period->id);
        unset($data['supporting_evidence'], $data['bank_ledger_entry_id'], $data['system_record_type'], $data['system_record_id']);
        $discrepancies->update($period, $discrepancy, $data, $request->user());
        return redirect()->route('bank-reconciliation.index', ['period_id' => $period->id])->with('success', 'Discrepancy report updated.');
    }

    public function reviewDiscrepancy(ReviewReconciliationDiscrepancyRequest $request, BankReconciliation $period, ReconciliationDiscrepancy $discrepancy, string $decision, ReconciliationDiscrepancyService $discrepancies): RedirectResponse
    {
        Gate::authorize('reviewDiscrepancy', $period);
        abort_unless((int) $period->workspace_id === (int) app(WorkspaceContext::class)->requireId(), 404);

        $discrepancies->review($period, $discrepancy, $decision, $request->validated('note'), $request->user());

        return redirect()->route('bank-reconciliation.index', ['period_id' => $period->id])
            ->with('success', $decision === 'accepted' ? 'Discrepancy explanation accepted for Admin review.' : 'Correction requested from Staff for this discrepancy.');
    }

    public function resolveDiscrepancy(ResolveReconciliationDiscrepancyRequest $request, BankReconciliation $period, ReconciliationDiscrepancy $discrepancy, ReconciliationDiscrepancyService $discrepancies): RedirectResponse
    {
        Gate::authorize('resolveDiscrepancy', $period);
        abort_unless((int) $period->workspace_id === (int) app(WorkspaceContext::class)->requireId(), 404);
        $path = $request->hasFile('resolution_evidence')
            ? $request->file('resolution_evidence')->store('bank-reconciliation/discrepancies/'.$period->id.'/resolutions')
            : null;
        $discrepancies->resolve($period, $discrepancy, $request->validated('resolution_explanation'), $path, $request->user());
        return redirect()->route('bank-reconciliation.index', ['period_id' => $period->id])->with('success', 'Discrepancy resolution saved.');
    }

    public function discrepancyResolutionEvidence(BankReconciliation $period, ReconciliationDiscrepancy $discrepancy)
    {
        Gate::authorize('viewAny', BankLedgerEntry::class);
        abort_unless((int) $period->workspace_id === (int) app(WorkspaceContext::class)->requireId(), 404);
        abort_unless((int) $discrepancy->bank_reconciliation_id === (int) $period->id && filled($discrepancy->resolution_evidence_path), 404);
        $storage = Storage::disk('local');
        abort_unless($storage->exists($discrepancy->resolution_evidence_path), 404);
        return $storage->response($discrepancy->resolution_evidence_path);
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
        $period = $request->integer('period_id')
            ? BankReconciliation::query()->findOrFail($request->integer('period_id'))
            : null;
        if ($period !== null) {
            Gate::authorize('update', $period);
        }
        $count = $importer->handle($request->file('statement'), $request->user(), $period);
        $routeParameters = $request->input('return_tab') === 'outgoing'
            ? ['tab' => 'outgoing']
            : [];
        if ($period !== null) {
            $routeParameters['period_id'] = $period->id;
        }

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

    public function storeWithdrawalMatches(StoreWithdrawalDisbursementMatchRequest $request, BankLedgerEntry $withdrawal, MatchWithdrawalDisbursements $matcher): RedirectResponse
    {
        Gate::authorize('matchWithdrawal', $withdrawal);
        $matcher->handle($withdrawal, $request->disbursementIds(), $request->user(), $request->validated('discrepancy_reason'));

        return redirect()
            ->route('bank-reconciliation.index', ['tab' => 'outgoing'])
            ->with('success', 'Withdrawal match selection updated.');
    }

    public function finalizeWithdrawal(StoreWithdrawalDisbursementMatchRequest $request, BankLedgerEntry $withdrawal, MatchWithdrawalDisbursements $matcher): RedirectResponse
    {
        Gate::authorize('matchWithdrawal', $withdrawal);
        $matcher->handle($withdrawal, $request->disbursementIds(), $request->user(), $request->validated('discrepancy_reason'), true);

        return redirect()
            ->route('bank-reconciliation.index', ['tab' => 'outgoing'])
            ->with('success', 'Match confirmed successfully. The bank transaction and disbursement records were updated.');
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
            ->with('success', 'Match confirmed successfully. The bank transaction and system record were updated.');
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
