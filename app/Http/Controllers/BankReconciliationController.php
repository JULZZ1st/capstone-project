<?php

namespace App\Http\Controllers;

use App\Http\Requests\BankReconciliation\ImportBankStatementRequest;
use App\Http\Requests\BankReconciliation\ReconcileLedgerEntryRequest;
use App\Models\BankLedgerEntry;
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
            ->needsReview()
            ->latest('transaction_date')
            ->limit(config('bank_reconciliation.limits.feed'))
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
            'unreconciledFeedCount' => BankLedgerEntry::query()->needsReview()->count(),
            'pendingReceiptsCount' => Contribution::query()->eligibleForReconciliation()->count(),
            'history' => $history,
        ]);
    }

    public function import(ImportBankStatementRequest $request, ImportBankStatement $importer): RedirectResponse
    {
        $count = $importer->handle($request->file('statement'), $request->user());

        return redirect()
            ->route('bank-reconciliation.index')
            ->with('success', "{$count} bank transaction(s) imported successfully.");
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
}
