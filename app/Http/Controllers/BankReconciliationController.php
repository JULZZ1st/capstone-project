<?php

namespace App\Http\Controllers;

use App\Http\Requests\BankReconciliation\ImportBankStatementRequest;
use App\Http\Requests\BankReconciliation\ReconcileLedgerEntryRequest;
use App\Http\Requests\BankReconciliation\StoreBankWithdrawalRequest;
use App\Http\Requests\BankReconciliation\StoreWithdrawalDisbursementMatchRequest;
use App\Models\BankLedgerEntry;
use App\Models\CashDisbursement;
use App\Models\Contribution;
use App\Services\BankReconciliation\LedgerMatchFinder;
use App\Services\BankReconciliation\ImportBankStatement;
use App\Services\BankReconciliation\ReconcileLedgerEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
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

        $suggestions = $ledgerEntries->mapWithKeys(
            fn (BankLedgerEntry $entry) => [$entry->id => $matchFinder->suggest($entry, $pendingReceipts)]
        );

        return view('bank-reconciliation', [
            'ledgerEntries' => $ledgerEntries,
            'pendingReceipts' => $pendingReceipts,
            'suggestions' => $suggestions,
            'unreconciledFeedCount' => BankLedgerEntry::query()->incoming()->needsReview()->count(),
            'pendingReceiptsCount' => Contribution::query()->eligibleForReconciliation()->count(),
            'withdrawals' => $withdrawals,
            'eligibleDisbursements' => $eligibleDisbursements,
            'withdrawalsToReviewCount' => BankLedgerEntry::query()->withdrawalsToReview()->count(),
            'history' => $history,
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

    public function storeWithdrawal(StoreBankWithdrawalRequest $request): RedirectResponse
    {
        $data = $request->validated();

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

    public function storeWithdrawalMatches(StoreWithdrawalDisbursementMatchRequest $request, BankLedgerEntry $withdrawal): RedirectResponse
    {
        Gate::authorize('matchWithdrawal', $withdrawal);

        $withdrawal->matchedDisbursements()->sync($request->disbursementIds());

        return redirect()
            ->route('bank-reconciliation.index', ['tab' => 'outgoing'])
            ->with('success', 'Withdrawal match selection updated.');
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
