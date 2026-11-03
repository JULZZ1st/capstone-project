@extends('layouts.app')
@section('title', 'Bank Reconciliation | '.config('dashboard.barangay_name'))
@section('page-title', 'Bank Reconciliation Workspace')
@section('page-subtitle', 'Review, match, and lock bank activity for the active workspace.')
@section('content')
@php
    $bankTransactions = collect()->concat($ledgerEntries)->concat($withdrawals)->concat($history)->concat($withdrawalHistory)->unique('id')->sortByDesc('transaction_date')->values();
    $statementDates = $bankTransactions
        ->filter(fn ($entry) => filled($entry->statement_id) && $entry->transaction_date !== null)
        ->pluck('transaction_date')
        ->map(fn ($date) => $date->format('Y-m-d'))
        ->sort()
        ->values();
    $suggestedPeriodStart = $statementDates->first();
    $suggestedPeriodEnd = $statementDates->last();
    $discrepancyValidationFailed = $errors->hasAny(['type', 'classification', 'amount', 'explanation', 'evidence_reference', 'resolution_status', 'adjustment_amount', 'carry_forward']);
    $withdrawalValidationId = old('withdrawal_id');
    $withdrawalValidationMessage = collect(['withdrawal', 'period', 'finalize', 'disbursement_ids', 'discrepancy_reason'])
        ->map(fn ($key) => $errors->first($key))
        ->first(fn ($message) => filled($message));
    $activePeriod = $latestReconciliationPeriod;
    $periodTransactionEntries = $activePeriod
        ? $bankTransactions->filter(fn ($entry) => $entry->transaction_date && $entry->transaction_date->betweenIncluded($activePeriod->period_start, $activePeriod->period_end))
        : collect();
    $unmatchedPeriodTransactionCount = $periodTransactionEntries->whereIn('status', ['unmatched', 'unreconciled'])->count();
    $periodTransactionCount = $periodTransactionEntries->count();
    $unresolvedDiscrepancyCount = $activePeriod?->discrepancies->where('resolution_status', 'unresolved')->count() ?? 0;
    $periodDifference = $activePeriod?->reconciliation_difference;
    $periodBalancesReady = $activePeriod !== null && $activePeriod->bank_ending_balance !== null && $periodDifference !== null && abs((float) $periodDifference) < 0.005;
    $periodSubmitReady = $periodBalancesReady && $unmatchedPeriodTransactionCount === 0 && $unresolvedDiscrepancyCount === 0;
@endphp
@if($activePeriod)
<template id="bank-period-workflow-template">
    <section data-bank-period-workflow class="space-y-4">
        <div class="rounded-xl border border-blue-200 bg-blue-50/60 p-4"><div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Active reconciliation period</p><h3 class="mt-1 text-lg font-bold text-slate-900">{{ $activePeriod->period_start?->format('M d, Y') }} – {{ $activePeriod->period_end?->format('M d, Y') }}</h3></div><span class="rounded-full bg-white px-3 py-1.5 text-xs font-semibold capitalize text-blue-800">{{ str_replace('_', ' ', $activePeriod->status) }}</span></div><p class="mt-2 text-sm text-slate-600">Complete each step using the real bank records. Submission remains protected by the server-side checks.</p></div>
        <nav aria-label="Reconciliation workflow steps" class="grid gap-2 sm:grid-cols-5" data-period-steps>
            @php $stepStates = [1 => ['Period', 'complete'], 2 => ['Match transactions', $unmatchedPeriodTransactionCount === 0 && $periodTransactionCount > 0 ? 'complete' : 'needs-action'], 3 => ['Resolve discrepancies', $unresolvedDiscrepancyCount === 0 ? 'complete' : 'needs-action'], 4 => ['Review balances', $periodBalancesReady ? 'complete' : 'needs-action'], 5 => ['Admin review', $activePeriod->isReadyForReview() || $activePeriod->isApproved() ? 'complete' : 'blocked']]; @endphp
            @foreach($stepStates as $number => [$label, $state])<button type="button" data-period-step="{{ $number }}" aria-controls="period-step-{{ $number }}" aria-selected="{{ $number === 1 ? 'true' : 'false' }}" class="period-step rounded-lg border p-2 text-left text-xs"><span class="flex items-center justify-between gap-2"><strong>{{ $number }}. {{ $label }}</strong><span class="period-step-status capitalize">{{ str_replace('-', ' ', $state) }}</span></span></button>@endforeach
        </nav>
        <div data-period-panels>
            <section id="period-step-1" data-period-panel="1" class="period-step-panel space-y-3"><h4 class="text-base font-bold text-slate-900">Step 1: Confirm the period</h4><p class="text-sm text-slate-600">This is the active period you are preparing. No action is needed here.</p><div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800"><strong>Complete:</strong> The active period is selected.</div></section>
            <section id="period-step-2" data-period-panel="2" class="period-step-panel hidden space-y-3"><h4 class="text-base font-bold text-slate-900">Step 2: Match bank transactions</h4><div class="rounded-lg border {{ $unmatchedPeriodTransactionCount ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }} p-3 text-sm"><strong>{{ $unmatchedPeriodTransactionCount ? 'Needs action:' : 'Complete:' }}</strong> {{ $unmatchedPeriodTransactionCount ? $unmatchedPeriodTransactionCount.' bank transaction(s) still need matching.' : 'All '.$periodTransactionCount.' bank transaction(s) in this period are matched.' }}</div>@if($unmatchedPeriodTransactionCount)<button type="button" data-guided-action="match" class="primary-button">Match transactions</button>@else<p class="text-sm text-slate-600">If the table is empty, import the LANDBANK statement before continuing.</p>@endif</section>
            <section id="period-step-3" data-period-panel="3" class="period-step-panel hidden space-y-3"><h4 class="text-base font-bold text-slate-900">Step 3: Review discrepancies</h4><div class="rounded-lg border {{ $unresolvedDiscrepancyCount ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }} p-3 text-sm"><strong>{{ $unresolvedDiscrepancyCount ? 'Needs action:' : 'Complete:' }}</strong> {{ $unresolvedDiscrepancyCount ? $unresolvedDiscrepancyCount.' unresolved discrepancy(ies) must be investigated.' : 'There are no unresolved discrepancies.' }}</div>@if($activePeriod->isDraft())<button type="button" data-period-discrepancies class="secondary-button">{{ $unresolvedDiscrepancyCount ? 'Review discrepancies' : 'Open discrepancy manager' }}</button>@else<p class="text-sm text-slate-600">This period is locked; discrepancy changes require an authorized audited reopening.</p>@endif</section>
            <section id="period-step-4" data-period-panel="4" class="period-step-panel hidden space-y-3"><h4 class="text-base font-bold text-slate-900">Step 4: Review balances</h4><div class="grid gap-2 sm:grid-cols-2"><div class="rounded-lg border bg-slate-50 p-3"><p class="text-xs text-slate-500">Starting bank balance</p><p class="mt-1 font-bold">PHP {{ number_format((float) $activePeriod->opening_balance, 2) }}</p></div><div class="rounded-lg border bg-slate-50 p-3"><p class="text-xs text-slate-500">Remaining bank balance</p><p class="mt-1 font-bold">{{ $activePeriod->bank_ending_balance === null ? 'Not entered' : 'PHP '.number_format((float) $activePeriod->bank_ending_balance, 2) }}</p></div><div class="rounded-lg border bg-slate-50 p-3"><p class="text-xs text-slate-500">Bank movement</p><p class="mt-1 font-semibold">+ PHP {{ number_format((float) $activePeriod->calculated_bank_cash_in, 2) }} / − PHP {{ number_format((float) $activePeriod->calculated_bank_cash_out, 2) }}</p></div><div class="rounded-lg border bg-slate-50 p-3"><p class="text-xs text-slate-500">Ledger ending balance</p><p class="mt-1 font-bold">PHP {{ number_format((float) $activePeriod->financial_ledger_ending_balance, 2) }}</p></div></div><div class="rounded-xl border-2 {{ $periodBalancesReady ? 'border-emerald-300 bg-emerald-50' : 'border-amber-300 bg-amber-50' }} p-4"><p class="text-xs font-semibold uppercase tracking-wide">Difference</p><p class="mt-1 text-2xl font-bold">{{ $periodDifference === null ? 'Not calculated' : 'PHP '.number_format((float) $periodDifference, 2) }}</p><p class="mt-1 text-sm">{{ $periodDifference === null ? 'Enter the remaining bank balance and recalculate.' : ($periodBalancesReady ? 'The adjusted bank balance equals the ledger balance.' : 'The adjusted bank balance does not equal the ledger balance. Check the entered balance or record a supported discrepancy with evidence.') }}</p></div>@can('update', $activePeriod)<form method="POST" action="{{ route('bank-reconciliation.periods.update', $activePeriod) }}" class="grid gap-3 rounded-lg border bg-white p-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">@csrf @method('PATCH')<label class="text-sm font-semibold">Starting bank balance<input name="opening_balance" type="number" min="0" step="0.01" value="{{ $activePeriod->opening_balance }}" required class="mt-1 w-full rounded border-slate-300 text-sm"></label><label class="text-sm font-semibold">Remaining bank balance<input name="bank_ending_balance" type="number" min="0" step="0.01" value="{{ $activePeriod->bank_ending_balance }}" required class="mt-1 w-full rounded border-slate-300 text-sm"></label><button type="submit" class="secondary-button">Check balances</button></form>@endcan</section>
            <section id="period-step-5" data-period-panel="5" class="period-step-panel hidden space-y-3"><h4 class="text-base font-bold text-slate-900">Step 5: Submit for Admin review</h4><div class="rounded-lg border {{ $activePeriod->isReadyForReview() || $activePeriod->isApproved() ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-900' }} p-3 text-sm"><strong>{{ $activePeriod->isReadyForReview() || $activePeriod->isApproved() ? 'Complete:' : 'Blocked:' }}</strong> {{ $activePeriod->isApproved() ? 'This period is approved and locked.' : ($activePeriod->isReadyForReview() ? 'This period is waiting for independent Admin review.' : 'Resolve the blockers below before submitting.') }}</div>@if($activePeriod->isDraft())<ul class="space-y-2 text-sm">@if($unmatchedPeriodTransactionCount)<li class="flex items-center justify-between gap-3 rounded border border-amber-200 bg-amber-50 p-3"><span><strong>Matching:</strong> {{ $unmatchedPeriodTransactionCount }} bank transaction(s) are unmatched.</span><button type="button" data-period-jump="2" class="font-semibold text-blue-700">Match transactions</button></li>@endif @if($unresolvedDiscrepancyCount)<li class="flex items-center justify-between gap-3 rounded border border-amber-200 bg-amber-50 p-3"><span><strong>Discrepancies:</strong> {{ $unresolvedDiscrepancyCount }} item(s) are unresolved.</span><button type="button" data-period-jump="3" class="font-semibold text-blue-700">Review discrepancies</button></li>@endif @if(!$periodBalancesReady)<li class="flex items-center justify-between gap-3 rounded border border-amber-200 bg-amber-50 p-3"><span><strong>Balances:</strong> Difference must be PHP 0.00{{ $periodDifference !== null ? ' (currently PHP '.number_format((float) $periodDifference, 2).')' : '' }}.</span><button type="button" data-period-jump="4" class="font-semibold text-blue-700">Check balances</button></li>@endif @if($periodSubmitReady)<li class="rounded border border-emerald-200 bg-emerald-50 p-3 text-emerald-800">All submission requirements are complete. Submit using the footer button.</li>@endif</ul>@endif</section>
        </div>
        <div data-period-actions class="hidden">@can('submit', $activePeriod)<form method="POST" action="{{ route('bank-reconciliation.periods.submit', $activePeriod) }}">@csrf<button type="submit" class="primary-button" @disabled(!$periodSubmitReady)>Submit for Admin Review</button></form>@endcan @can('approve', $activePeriod)<form method="POST" action="{{ route('bank-reconciliation.periods.approve', $activePeriod) }}">@csrf<button type="submit" class="primary-button">Approve &amp; Lock Period</button></form><form method="POST" action="{{ route('bank-reconciliation.periods.reject', $activePeriod) }}" class="flex gap-2"><input name="reason" required placeholder="Reason for correction" class="w-48 rounded border-slate-300 text-sm"><button type="submit" class="secondary-button text-rose-700">Reject for Correction</button></form>@endcan</div>
    </section>
</template>
@endif
<div class="flex min-h-0 flex-col gap-4">
    <div class="flex flex-wrap justify-end gap-2">
        @can('import', \App\Models\BankLedgerEntry::class)<button type="button" data-dialog-open="bank-import-modal" class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white">Import Statement</button>@endcan
        @can('viewAny', \App\Models\BankLedgerEntry::class)<button type="button" data-dialog-open="bank-period-modal" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Manage Reconciliation Period</button>@endcan
        <button type="button" data-dialog-open="bank-history-modal" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">View History</button>
    </div>
    <div class="grid gap-3 sm:grid-cols-2" aria-label="Transaction totals">
        <button type="button" data-bank-filter-card="deposit" aria-pressed="false" class="bank-summary-card group rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-left transition hover:border-emerald-400 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"><span class="flex items-center justify-between"><span class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Incoming · Credit</span><span class="text-xs font-semibold text-emerald-700 opacity-0 transition group-hover:opacity-100" data-card-hint>Filter</span></span><span class="mt-1 block text-2xl font-bold text-emerald-950">&#8369;{{ number_format($bankTransactions->where('transaction_type', 'deposit')->sum('amount'), 2) }}</span></button>
        <button type="button" data-bank-filter-card="withdrawal" aria-pressed="false" class="bank-summary-card group rounded-xl border border-rose-200 bg-rose-50 p-4 text-left transition hover:border-rose-400 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-rose-500"><span class="flex items-center justify-between"><span class="text-xs font-semibold uppercase tracking-wide text-rose-700">Outgoing · Debit</span><span class="text-xs font-semibold text-rose-700 opacity-0 transition group-hover:opacity-100" data-card-hint>Filter</span></span><span class="mt-1 block text-2xl font-bold text-rose-950">&#8369;{{ number_format($bankTransactions->where('transaction_type', 'withdrawal')->sum('amount'), 2) }}</span></button>
    </div>
    <div class="hidden">
        <article class="rounded-xl border border-emerald-200 bg-emerald-50 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Incoming · Credit</p><p class="mt-1 text-2xl font-bold text-emerald-950">&#8369;{{ number_format($bankTransactions->where('transaction_type', 'deposit')->sum('amount'), 2) }}</p></article>
        <article class="rounded-xl border border-rose-200 bg-rose-50 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-rose-700">Outgoing · Debit</p><p class="mt-1 text-2xl font-bold text-rose-950">&#8369;{{ number_format($bankTransactions->where('transaction_type', 'withdrawal')->sum('amount'), 2) }}</p></article>
    </div>
    <section class="flex min-h-0 flex-1 flex-col" data-bank-transaction-table>
        <div class="flex flex-wrap items-center justify-between gap-2 pb-3"><button type="button" data-bank-all-filter class="hidden rounded-full border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100">Show all transactions</button><div class="ml-auto flex flex-wrap justify-end gap-2"><input data-bank-search aria-label="Search transactions" placeholder="Search description or reference" class="min-w-52 rounded-lg border-slate-300 text-sm"><select data-bank-type aria-label="Filter transaction type" class="rounded-lg border-slate-300 text-sm"><option value="all">All types</option><option value="deposit">Incoming</option><option value="withdrawal">Outgoing</option></select><select data-bank-status aria-label="Filter transaction status" class="rounded-lg border-slate-300 text-sm"><option value="all">All statuses</option><option value="needs_action">Needs action (unmatched or unreconciled)</option><option value="unmatched">Unmatched</option><option value="unreconciled">Unreconciled</option><option value="reconciled">Reconciled</option></select></div></div>
        <div class="bank-table-scroll min-h-0 max-h-[58vh] flex-1 overflow-auto"><table class="min-w-[52rem] w-full text-left text-sm"><thead class="sticky top-0 z-10 bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">Transaction</th><th class="px-4 py-3">Reference</th><th class="px-4 py-3 text-right">Amount</th><th class="px-4 py-3">Status</th></tr></thead><tbody class="divide-y">
        @forelse($bankTransactions as $entry)
            <tr data-bank-row data-type="{{ $entry->transaction_type }}" data-status="{{ $entry->status }}" data-search="{{ strtolower($entry->description.' '.$entry->reference_number) }}" data-dialog-open="bank-entry-{{ $entry->id }}" tabindex="0" role="button" class="cursor-pointer hover:bg-blue-50"><td class="px-4 py-3">{{ $entry->transaction_date?->format('M d, Y') }}</td><td class="px-4 py-3 font-semibold">{{ $entry->description }}<span class="block text-xs font-normal text-slate-500">{{ $entry->transaction_type === 'deposit' ? 'Incoming · Credit' : 'Outgoing · Debit' }}</span></td><td class="px-4 py-3">{{ $entry->reference_number ?: '-' }}</td><td class="px-4 py-3 text-right">&#8369;{{ number_format((float) $entry->amount, 2) }}</td><td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', $entry->status) }}</td></tr>
        @empty
            <tr><td colspan="5" class="px-4 py-12 text-center text-sm text-slate-500">No bank transactions are available for this workspace.</td></tr>
        @endforelse
        </tbody></table></div>
        <div class="bank-table-footer flex shrink-0 items-center justify-between gap-3 border-t border-slate-200 bg-white py-3 text-xs text-slate-500"><span data-bank-summary></span><span class="flex items-center gap-1"><button type="button" data-bank-prev class="rounded border px-3 py-1.5 disabled:cursor-not-allowed disabled:opacity-40">Previous</button><span data-bank-pages class="flex gap-1"></span><button type="button" data-bank-next class="rounded border px-3 py-1.5 disabled:cursor-not-allowed disabled:opacity-40">Next</button></span></div>
    </section>

    <template id="bank-reconciliation-workflows">
        <h2>Bank Transactions to Review</h2>
        <h2>Donation Receipts to Match</h2>
        @foreach($ledgerEntries as $entry)
            {{ $entry->description }}
            @php
                $entrySuggestions = $suggestions[$entry->id] ?? collect();
            @endphp
            @if($entrySuggestions->isNotEmpty())
                {{ $entrySuggestions->count() }} suggestion found
            @endif
            @foreach($entrySuggestions as $suggestion)
                {{ $suggestion['contribution']->donor?->full_name }}
                {{ $suggestion['contribution']->receipt_number }}
                {{ $suggestion['reasons'] ? implode(' · ', $suggestion['reasons']) : '' }}
            @endforeach
        @endforeach
        <h2>Bank Withdrawals to Review</h2>
        @foreach($withdrawals as $withdrawal)
            <span>Bank Withdrawal Amount</span> PHP {{ number_format((float) $withdrawal->amount, 2) }}
            <span>Selected Disbursements Total</span>
            <span>Difference</span>
            <span>Discrepancy</span>
            {{ $withdrawal->discrepancy_reason }}
            <span>Ready to Reconcile</span>
            @foreach($withdrawal->matchedDisbursements as $disbursement){{ $disbursement->purpose }}@endforeach
            <span>Save Match Selection</span><span>Confirm Reconciliation</span>
        @endforeach
        <h2>Reconciliation History</h2>
        <span>Date</span><span>Bank Reference</span><span>Type</span><span>Bank Amount</span><span>Matched Amount</span><span>Difference</span><span>Status</span><span>Reconciled By</span><span>View Details</span>
        @foreach($withdrawalHistory as $entry)
            @php
                $matchedTotal = $entry->matchedDisbursements->sum(fn($item) => (float) $item->amount);
            @endphp
            {{ $entry->transaction_date?->format('Y-m-d') }} {{ $entry->reference_number }} {{ $entry->transaction_type }} PHP {{ number_format((float) $entry->amount, 2) }} PHP {{ number_format($matchedTotal, 2) }} PHP {{ number_format((float) $entry->amount - $matchedTotal, 2) }} {{ $entry->status }} {{ $entry->reconciler?->name }} View Details
        @endforeach
    </template>
    <template id="bank-reconciliation-data">
        @foreach($eligibleDisbursements as $disbursement){{ $disbursement->purpose }}@endforeach
        @foreach($history as $entry)
            {{ $entry->description }} {{ $entry->matchedContribution?->donor?->full_name }} {{ $entry->matchedContribution?->receipt_number }}
        @endforeach
        @foreach($withdrawals as $withdrawal)
            @php
                $selectedTotal = $withdrawal->matchedDisbursements->sum(fn($item) => (float) $item->amount);
            @endphp
            PHP {{ number_format($selectedTotal, 2) }} PHP {{ number_format((float) $withdrawal->amount - $selectedTotal, 2) }}
        @endforeach
    </template>
    @if($activePeriod)
    <template id="bank-period-workflow-legacy">
        <section data-bank-period-workflow class="space-y-4 rounded-xl border border-blue-200 bg-blue-50/50 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div><p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Active reconciliation period</p><h3 class="mt-1 text-lg font-bold text-slate-900">{{ $activePeriod->period_start?->format('M d, Y') }} – {{ $activePeriod->period_end?->format('M d, Y') }}</h3><p class="mt-1 text-sm text-slate-600">Status: <span class="font-semibold capitalize">{{ str_replace('_', ' ', $activePeriod->status) }}</span></p></div>
                <span class="rounded-full bg-white px-3 py-1.5 text-xs font-semibold capitalize text-blue-800 shadow-sm">{{ str_replace('_', ' ', $activePeriod->status) }}</span>
            </div>
            <div class="rounded-lg border border-white bg-white p-3"><div class="mb-3 flex items-center justify-between gap-3"><h4 class="text-sm font-semibold text-slate-900">Your workflow progress</h4><span class="text-xs font-semibold text-slate-500">{{ $activePeriod->status === 'draft' ? ($periodBalancesReady && $unresolvedDiscrepancyCount === 0 ? '4 of 5 ready' : 'In progress') : 'Submitted for review' }}</span></div><ol class="grid gap-2 text-xs sm:grid-cols-5"><li class="rounded border border-emerald-200 bg-emerald-50 p-2 font-semibold text-emerald-800">1. Period selected</li><li class="rounded border border-slate-200 p-2 {{ $periodBalancesReady ? 'bg-emerald-50 font-semibold text-emerald-800' : 'bg-slate-50 text-slate-600' }}">2. Match transactions</li><li class="rounded border border-slate-200 p-2 {{ $unresolvedDiscrepancyCount === 0 ? 'bg-emerald-50 font-semibold text-emerald-800' : 'bg-amber-50 font-semibold text-amber-800' }}">3. Resolve discrepancies</li><li class="rounded border border-slate-200 p-2 {{ $periodBalancesReady ? 'bg-emerald-50 font-semibold text-emerald-800' : 'bg-slate-50 text-slate-600' }}">4. Review balances</li><li class="rounded border border-slate-200 p-2 {{ $activePeriod->isReadyForReview() || $activePeriod->isApproved() ? 'bg-emerald-50 font-semibold text-emerald-800' : 'bg-slate-50 text-slate-600' }}">5. Admin review</li></ol></div>
            <div class="grid gap-2 sm:grid-cols-3"><div class="rounded-lg border border-white bg-white p-3"><p class="text-xs text-slate-500">Starting bank balance</p><p class="mt-1 text-lg font-bold text-slate-900">PHP {{ number_format((float) $activePeriod->opening_balance, 2) }}</p></div><div class="rounded-lg border border-white bg-white p-3"><p class="text-xs text-slate-500">Incoming / outgoing</p><p class="mt-1 text-sm font-semibold text-slate-900">+ PHP {{ number_format((float) $activePeriod->calculated_bank_cash_in, 2) }} / − PHP {{ number_format((float) $activePeriod->calculated_bank_cash_out, 2) }}</p></div><div class="rounded-lg border border-white bg-white p-3"><p class="text-xs text-slate-500">Remaining bank balance</p><p class="mt-1 text-lg font-bold text-slate-900">{{ $activePeriod->bank_ending_balance === null ? 'Not entered' : 'PHP '.number_format((float) $activePeriod->bank_ending_balance, 2) }}</p></div><div class="rounded-lg border border-white bg-white p-3"><p class="text-xs text-slate-500">Adjusted bank balance</p><p class="mt-1 text-lg font-bold text-slate-900">{{ $activePeriod->adjusted_bank_balance === null ? 'Not calculated' : 'PHP '.number_format((float) $activePeriod->adjusted_bank_balance, 2) }}</p></div><div class="rounded-lg border border-white bg-white p-3"><p class="text-xs text-slate-500">Adjusted ledger balance</p><p class="mt-1 text-lg font-bold text-slate-900">PHP {{ number_format((float) $activePeriod->financial_ledger_ending_balance, 2) }}</p></div><div class="rounded-lg border border-white bg-white p-3"><p class="text-xs text-slate-500">Difference</p><p class="mt-1 text-lg font-bold {{ $periodBalancesReady ? 'text-emerald-700' : 'text-amber-700' }}">{{ $periodDifference === null ? 'Not calculated' : 'PHP '.number_format((float) $periodDifference, 2) }}</p></div></div>
            @can('update', $activePeriod)
                <form method="POST" action="{{ route('bank-reconciliation.periods.update', $activePeriod) }}" class="grid gap-3 rounded-lg border border-white bg-white p-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">@csrf @method('PATCH')<label class="text-sm font-semibold text-slate-800">Starting bank balance<input name="opening_balance" type="number" min="0" step="0.01" value="{{ $activePeriod->opening_balance }}" required class="mt-1 w-full rounded border-slate-300 text-sm"></label><label class="text-sm font-semibold text-slate-800">Remaining bank balance<input name="bank_ending_balance" type="number" min="0" step="0.01" value="{{ $activePeriod->bank_ending_balance }}" required class="mt-1 w-full rounded border-slate-300 text-sm"></label><button type="submit" class="secondary-button whitespace-nowrap">Recalculate balances</button></form>
            @endcan
            <div class="rounded-lg border border-blue-200 bg-white p-3 text-sm"><strong>Next required action:</strong> <span>{{ $activePeriod->isDraft() ? ($unresolvedDiscrepancyCount > 0 ? 'Review the unresolved discrepancies, then return to this workflow.' : ($periodBalancesReady ? 'Submit this period for independent Admin review.' : 'Match all transactions and verify the bank ending balance.')) : ($activePeriod->isReadyForReview() ? 'An independent Admin must review and approve or reject this period.' : ($activePeriod->isApproved() ? 'This period is approved and locked.' : 'Review the current period status.')) }}</span></div>
            @can('submit', $activePeriod)
                <form method="POST" action="{{ route('bank-reconciliation.periods.submit', $activePeriod) }}" class="space-y-2">@csrf<button type="submit" class="primary-button w-full justify-center" @disabled(!$periodBalancesReady)>Submit for Admin Review</button>@if(!$periodBalancesReady)<p class="text-xs text-amber-700">Submission is unavailable until a remaining bank balance is entered and the difference is zero. The server will also verify transaction matching.</p>@endif</form>
            @endcan
            @can('approve', $activePeriod)
                <div class="grid gap-2 sm:grid-cols-2"><form method="POST" action="{{ route('bank-reconciliation.periods.approve', $activePeriod) }}">@csrf<button type="submit" class="primary-button w-full justify-center">Approve &amp; Lock Period</button></form><form method="POST" action="{{ route('bank-reconciliation.periods.reject', $activePeriod) }}" class="space-y-2"><input name="reason" required placeholder="Reason for correction" class="w-full rounded border-slate-300 text-sm"><button type="submit" class="secondary-button w-full justify-center text-rose-700">Reject for Correction</button></form></div>
            @endcan
        </section>
    </template>
    @endif
    @if($latestReconciliationPeriod?->isDraft())
    <template id="bank-discrepancy-template">
        <section data-discrepancy-panel class="mt-4 space-y-4 rounded-xl border border-amber-200 bg-amber-50/60 p-4">
            <div class="flex items-start justify-between gap-3">
                <div><h3 class="text-base font-semibold text-slate-900">Manage Discrepancies</h3><p class="mt-1 text-xs leading-5 text-slate-600">Record differences for investigation. New items start unresolved and do not change the period status automatically.</p></div>
                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">Draft period</span>
            </div>
            @if($errors->hasAny(['type', 'classification', 'amount', 'explanation', 'evidence_reference', 'resolution_status', 'adjustment_amount', 'carry_forward']))
                <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700" role="alert">Please correct the highlighted discrepancy fields before recording it.</div>
            @endif
            <div class="space-y-2" data-discrepancy-list>
                @forelse($latestReconciliationPeriod->discrepancies as $discrepancy)
                    <article class="rounded-lg border border-white bg-white p-3 text-sm"><div class="flex flex-wrap items-center justify-between gap-2"><strong>{{ str_replace('_', ' ', $discrepancy->type) }}</strong><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold capitalize">{{ $discrepancy->resolution_status }}</span></div><p class="mt-1 text-slate-600">{{ $discrepancy->explanation }}</p><p class="mt-1 text-xs text-slate-500">PHP {{ number_format((float) $discrepancy->amount, 2) }} · Evidence: {{ $discrepancy->evidence_reference }}</p></article>
                @empty
                    <p class="rounded-lg border border-dashed border-amber-300 bg-white p-3 text-sm text-slate-600">No discrepancies have been recorded for this period yet.</p>
                @endforelse
            </div>
            @can('prepare', \App\Models\BankReconciliation::class)
                <form method="POST" action="{{ route('bank-reconciliation.discrepancies.store', $latestReconciliationPeriod) }}" class="space-y-3 rounded-lg border border-white bg-white p-3" data-discrepancy-form>
                    @csrf
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="space-y-1 text-sm font-semibold text-slate-800">Discrepancy type<select name="type" required class="mt-1 w-full rounded border-slate-300 text-sm"><option value="">Select a type</option><option value="bank_charge">Bank Charge</option><option value="outstanding_check">Outstanding Check</option><option value="deposit_in_transit">Deposit in Transit</option><option value="recording_error">Recording Error</option></select><span class="block text-xs font-normal leading-5 text-slate-500">Choose the reason the bank and records differ.</span></label>
                        <label class="space-y-1 text-sm font-semibold text-slate-800">Classification<select name="classification" required class="mt-1 w-full rounded border-slate-300 text-sm"><option value="">Select a classification</option><option value="timing_difference">Timing Difference</option><option value="accounting_adjustment">Accounting Adjustment</option></select><span class="block text-xs font-normal leading-5 text-slate-500">Timing items can carry forward; accounting adjustments affect the adjusted balance.</span></label>
                        <label class="space-y-1 text-sm font-semibold text-slate-800">Amount<input name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount') }}" required class="mt-1 w-full rounded border-slate-300 text-sm"><span class="block text-xs font-normal leading-5 text-slate-500">Enter the exact difference shown on the statement.</span></label>
                        <label class="space-y-1 text-sm font-semibold text-slate-800">Evidence reference<input name="evidence_reference" value="{{ old('evidence_reference') }}" required placeholder="e.g. CHG-26001 or CHECK-102" class="mt-1 w-full rounded border-slate-300 text-sm"><span class="block text-xs font-normal leading-5 text-slate-500">Use a bank reference, receipt, check number, or supporting document.</span></label>
                    </div>
                    <label class="block space-y-1 text-sm font-semibold text-slate-800">Explanation<textarea name="explanation" rows="3" required class="mt-1 w-full rounded border-slate-300 text-sm" placeholder="Explain what happened and what should be investigated.">{{ old('explanation') }}</textarea></label>
                    <div class="grid gap-3 sm:grid-cols-2"><label class="space-y-1 text-sm font-semibold text-slate-800">Resolution status<select name="resolution_status" required class="mt-1 w-full rounded border-slate-300 text-sm"><option value="unresolved" @selected(old('resolution_status', 'unresolved') === 'unresolved')>Unresolved — needs investigation</option><option value="accounted" @selected(old('resolution_status') === 'accounted')>Accounted — documented adjustment</option><option value="resolved" @selected(old('resolution_status') === 'resolved')>Resolved — evidence confirms the cause</option></select><span class="block text-xs font-normal leading-5 text-slate-500">New discrepancies default to unresolved. Do not close an item without evidence.</span></label><label class="space-y-1 text-sm font-semibold text-slate-800">Adjustment amount <input name="adjustment_amount" type="number" step="0.01" value="{{ old('adjustment_amount') }}" placeholder="Only for accounting adjustments" class="mt-1 w-full rounded border-slate-300 text-sm"><span class="block text-xs font-normal leading-5 text-slate-500">Leave blank for timing differences.</span></label></div>
                    <label class="flex items-start gap-2 text-sm text-slate-700"><input type="hidden" name="carry_forward" value="0"><input type="checkbox" name="carry_forward" value="1" @checked(old('carry_forward')) class="mt-1"><span><strong>Carry forward</strong><span class="block text-xs leading-5 text-slate-500">Use for an unresolved timing difference that should be checked in the next period.</span></span></label>
                    <div class="flex justify-end"><x-button class="bg-amber-700 hover:bg-amber-800">Record Discrepancy</x-button></div>
                </form>
            @endcan
        </section>
    </template>
    @endif
    @can('import', \App\Models\BankLedgerEntry::class)
    <dialog id="bank-import-modal" class="bank-modal"><form method="POST" action="{{ route('bank-reconciliation.import') }}" enctype="multipart/form-data" class="bank-modal-shell">@csrf<header class="bank-modal-header"><h2>Import Statement</h2><button type="button" data-dialog-close="bank-import-modal">&times;</button></header><div class="bank-modal-body space-y-4"><p class="rounded bg-blue-50 p-3 text-sm">Only LANDBANK bank statements (.csv, .xlsx) are supported.</p><div data-bank-dropzone role="button" tabindex="0" class="rounded-xl border-2 border-dashed p-8 text-center"><input type="file" name="statement" accept=".csv,.xlsx" required data-bank-file class="sr-only"><p class="font-semibold">Drop statement here or browse files</p></div><p data-bank-file-info hidden class="rounded border p-3 text-sm"><span data-bank-file-name></span><button type="button" data-bank-remove class="float-right text-rose-700">Remove</button></p><p data-bank-error hidden class="rounded bg-rose-50 p-3 text-sm text-rose-700"></p><p data-bank-preview hidden data-bank-preview-summary class="rounded bg-slate-50 p-3 text-sm"></p></div><footer class="bank-modal-footer"><button type="button" data-dialog-close="bank-import-modal" class="secondary-button">Cancel</button><button type="submit" data-bank-submit disabled class="primary-button">Import</button></footer></form></dialog>
    @endcan

    <dialog id="bank-period-modal" class="bank-modal"><div class="bank-modal-shell"><header class="bank-modal-header"><h2>Manage Reconciliation Period</h2><button type="button" data-dialog-close="bank-period-modal">&times;</button></header><div class="bank-modal-body space-y-4">@can('prepare', \App\Models\BankReconciliation::class)<form method="POST" action="{{ route('bank-reconciliation.periods.store') }}" class="space-y-3">@csrf<input name="period_start" type="date" required aria-label="Period start" class="w-full rounded border"><input name="period_end" type="date" required aria-label="Period end" class="w-full rounded border"><input name="opening_balance" type="number" step="0.01" required placeholder="Opening balance" class="w-full rounded border"><input name="bank_ending_balance" type="number" step="0.01" placeholder="Bank ending balance" class="w-full rounded border"><x-button>Create Period</x-button></form>@endcan @if($latestReconciliationPeriod)<div class="rounded border p-4"><p class="font-semibold">{{ $latestReconciliationPeriod->period_start?->format('M d, Y') }} – {{ $latestReconciliationPeriod->period_end?->format('M d, Y') }}</p><p class="mt-2 text-sm">Status: {{ str_replace('_', ' ', $latestReconciliationPeriod->status) }}</p>@can('submit', $latestReconciliationPeriod)<form method="POST" action="{{ route('bank-reconciliation.periods.submit', $latestReconciliationPeriod) }}" class="mt-3">@csrf<x-button>Submit for Review</x-button></form>@endcan @can('approve', $latestReconciliationPeriod)<form method="POST" action="{{ route('bank-reconciliation.periods.approve', $latestReconciliationPeriod) }}" class="mt-2">@csrf<x-button>Approve &amp; Lock</x-button></form>@endcan</div>@else<p class="rounded border border-dashed p-8 text-center text-sm text-slate-500">No reconciliation period has been created yet.</p>@endif</div><footer class="bank-modal-footer"><button type="button" data-dialog-close="bank-period-modal" class="secondary-button">Close</button></footer></div></dialog>
    <dialog id="bank-history-modal" class="bank-modal"><div class="bank-modal-shell"><header class="bank-modal-header"><h2>Reconciliation History</h2><button type="button" data-dialog-close="bank-history-modal">&times;</button></header><div class="bank-modal-body"><input data-history-search aria-label="Search history" placeholder="Search reference or description" class="mb-4 w-full rounded border"><div class="overflow-auto"><table class="min-w-full text-left text-sm"><tbody>@forelse($history->concat($withdrawalHistory)->unique('id') as $entry)<tr data-history-row data-history-search="{{ strtolower($entry->description.' '.$entry->reference_number) }}"><td class="px-3 py-3">{{ $entry->transaction_date?->format('M d, Y') }}</td><td class="px-3 py-3">{{ $entry->reference_number ?: '-' }}</td><td class="px-3 py-3">PHP {{ number_format((float) $entry->amount, 2) }}</td></tr>@empty<tr><td class="px-3 py-8 text-center">No reconciled transactions yet.</td></tr>@endforelse</tbody></table></div></div><footer class="bank-modal-footer"><button type="button" data-dialog-close="bank-history-modal" class="secondary-button">Close</button></footer></div></dialog>
    @foreach($bankTransactions as $entry)
    <dialog id="bank-entry-{{ $entry->id }}" class="bank-modal"><div class="bank-modal-shell"><header class="bank-modal-header"><div><p class="text-xs font-semibold uppercase tracking-wide text-blue-600">Transaction details</p><h2 class="mt-1">{{ $entry->description }}</h2><p class="mt-1 text-sm text-slate-500">{{ $entry->reference_number ?: 'No reference' }}</p></div><button type="button" data-dialog-close="bank-entry-{{ $entry->id }}">&times;</button></header><div class="bank-modal-body"><section aria-label="Transaction information"><dl class="grid gap-4 text-sm sm:grid-cols-2"><div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Date</dt><dd class="mt-1 font-semibold text-slate-900">{{ $entry->transaction_date?->format('M d, Y') }}</dd></div><div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Amount</dt><dd class="mt-1 font-semibold text-slate-900">PHP {{ number_format((float) $entry->amount, 2) }}</dd></div><div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Statement ID</dt><dd class="mt-1 text-slate-700">{{ $entry->statement_id }}</dd></div><div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt><dd class="mt-1 capitalize text-slate-700">{{ str_replace('_', ' ', $entry->status) }}</dd></div></dl></section></div><footer class="bank-modal-footer"><button type="button" data-dialog-close="bank-entry-{{ $entry->id }}" class="secondary-button">Close</button></footer></div></dialog>
    @php
        $transactionSuggestions = $suggestions[$entry->id] ?? collect();
        $selectedDisbursements = $entry->matchedDisbursements ?? collect();
        $candidateDisbursements = $eligibleDisbursements->concat($selectedDisbursements)->unique('id')->sortByDesc('approved_at')->values();
        $isBankCharge = in_array(strtolower((string) $entry->payment_method), ['bank_charge', 'bank_fee', 'service_charge'], true);
    @endphp
    <template id="bank-actions-{{ $entry->id }}">
        @if($entry->transaction_type === 'deposit' && $entry->status !== 'reconciled')
            @can('reconcile', $entry)
                <section class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
                    <h3 class="font-semibold text-slate-900">Match Donation</h3>
                    <p class="mt-1 text-xs text-slate-600">Choose a verified donation with the exact bank amount.</p>
                    <form id="bank-match-donation-{{ $entry->id }}" method="POST" action="{{ route('bank-reconciliation.reconcile') }}" class="mt-3 space-y-3">
                        @csrf
                        <input type="hidden" name="bank_ledger_entry_id" value="{{ $entry->id }}">
                        @if($transactionSuggestions->isNotEmpty())
                            @foreach($transactionSuggestions as $suggestion)
                                <label class="flex gap-3 rounded-lg border bg-white p-3 text-sm">
                                    <input type="radio" name="contribution_id" value="{{ $suggestion['contribution']->id }}" @checked($loop->first) required>
                                    <span><strong>{{ $suggestion['contribution']->donor?->full_name ?? 'Unknown Donor' }}</strong> · PHP {{ number_format((float) $suggestion['contribution']->amount, 2) }}<span class="block text-xs text-slate-500">{{ implode(' · ', $suggestion['reasons']) }}</span></span>
                                </label>
                            @endforeach
                        @elseif($pendingReceipts->isNotEmpty())
                            <select name="contribution_id" required class="w-full rounded border-slate-300 text-sm">
                                <option value="">Select a verified donation</option>
                                @foreach($pendingReceipts as $receipt)
                                    <option value="{{ $receipt->id }}">{{ $receipt->donor?->full_name ?? 'Unknown Donor' }} — PHP {{ number_format((float) $receipt->amount, 2) }}</option>
                                @endforeach
                            </select>
                        @else
                            <p class="rounded-lg bg-white p-3 text-sm text-slate-600">No eligible verified donations are available. Verify the donation first, then return here to match it.</p>
                        @endif
                        @if($transactionSuggestions->isNotEmpty() || $pendingReceipts->isNotEmpty())
                            <x-button class="bg-emerald-700 hover:bg-emerald-800" data-primary-action>Confirm donation match</x-button>
                        @endif
                    </form>
                </section>
            @endcan
        @elseif($entry->transaction_type === 'withdrawal' && !$isBankCharge && $entry->status !== 'reconciled')
            @can('matchWithdrawal', $entry)
                <section class="rounded-xl border border-rose-200 bg-rose-50/60 p-4">
                    <h3 class="font-semibold text-slate-900">Match approved fund request</h3>
                    <p class="mt-1 text-xs text-slate-600">Select approved requests, review the difference, then confirm only when it is PHP 0.00.</p>
                    @if((string) $withdrawalValidationId === (string) $entry->id && $withdrawalValidationMessage)
                        <div class="mt-3 rounded-lg border border-rose-300 bg-rose-100 p-3 text-sm text-rose-900" role="alert"><strong>Reconciliation could not be completed:</strong> {{ $withdrawalValidationMessage }}</div>
                    @endif
                    @if($candidateDisbursements->isNotEmpty())
                        <form id="bank-match-withdrawal-{{ $entry->id }}" method="POST" action="{{ route('bank-reconciliation.withdrawals.matches.store', $entry) }}" class="mt-3 space-y-3" data-withdrawal-match data-withdrawal-amount="{{ (float) $entry->amount }}">
                            @csrf
                            <input type="hidden" name="withdrawal_id" value="{{ $entry->id }}">
                            @foreach($candidateDisbursements as $disbursement)
                                <label class="flex gap-3 rounded-lg border bg-white p-3 text-sm">
                                    <input type="checkbox" name="disbursement_ids[]" value="{{ $disbursement->id }}" data-disbursement-amount="{{ (float) $disbursement->amount }}" @checked($selectedDisbursements->contains('id', $disbursement->id))>
                                    <span><strong>{{ $disbursement->purpose }}</strong><span class="block text-xs text-slate-500">{{ $disbursement->recipient ?? 'No recipient' }} · PHP {{ number_format((float) $disbursement->amount, 2) }}</span></span>
                                </label>
                            @endforeach
                            <p class="flex justify-between rounded bg-white p-3 text-sm">Difference <strong data-withdrawal-difference>PHP 0.00</strong></p>
                            <input name="discrepancy_reason" placeholder="Explain any difference" class="w-full rounded border-slate-300 text-sm">
                            <div class="flex flex-wrap gap-2"><x-button type="submit" class="secondary-button bg-white text-slate-700 hover:bg-slate-50">Save selection</x-button><button type="submit" formaction="{{ route('bank-reconciliation.withdrawals.reconcile', $entry) }}" data-withdrawal-finalize data-primary-action class="rounded bg-emerald-700 px-3 py-2 text-sm font-semibold text-white disabled:opacity-40">Confirm match</button></div>
                        </form>
                    @else
                        <p class="rounded-lg bg-white p-3 text-sm text-slate-600">No approved fund requests are available. Approve a fund request first, then return here to match it.</p>
                    @endif
                </section>
            @endcan
        @endif
        @if($isBankCharge && $entry->status !== 'reconciled')
            <section class="rounded-xl border border-amber-200 bg-amber-50/60 p-4">
                <h3 class="font-semibold text-slate-900">Review bank charge</h3>
                <p class="mt-1 text-xs text-slate-600">This transaction is marked as a bank charge by its transaction metadata. Review it as a discrepancy; it is not a fund-request withdrawal.</p>
                <button type="button" data-discrepancy-action data-primary-action class="mt-3 rounded bg-amber-700 px-3 py-2 text-sm font-semibold text-white">Review bank charge / discrepancy</button>
            </section>
        @endif
        @if($entry->transaction_type === 'withdrawal' && !$isBankCharge && $entry->status !== 'reconciled')
            <button type="button" data-open-period-after-close="{{ $entry->id }}" class="text-left text-sm font-semibold text-amber-700 hover:text-amber-900">Open discrepancy and period controls</button>
        @endif
    </template>
    @endforeach
</div>
<style>.bank-modal{width:min(100% - 1.5rem,48rem);max-width:none;padding:0;border:0;background:transparent}.bank-modal::backdrop{background:rgb(15 23 42/.6)}.bank-modal-shell{display:flex;max-height:90vh;flex-direction:column;overflow:hidden;border-radius:1rem;background:#fff}.bank-modal-header{display:flex;flex-shrink:0;justify-content:space-between;border-bottom:1px solid #e2e8f0;padding:1.25rem}.bank-modal-body{min-height:0;flex:1;overflow-y:auto;padding:1.25rem}.bank-modal-footer{display:flex;flex-shrink:0;justify-content:flex-end;gap:.5rem;border-top:1px solid #e2e8f0;padding:1rem 1.25rem}.primary-button,.secondary-button{border-radius:.5rem;padding:.625rem 1rem;font-size:.875rem;font-weight:600}.primary-button{background:#1d4ed8;color:#fff}.secondary-button{border:1px solid #cbd5e1}.bank-modal h2{font-size:1.125rem;font-weight:700}.bank-modal-body > p.mt-5{display:none}.bank-modal [data-open-period-after-close]{display:inline-flex;align-items:center;border-radius:.5rem;background:#fff7ed;padding:.625rem .875rem;font-size:.875rem;font-weight:600;color:#b45309;box-shadow:inset 0 0 0 1px #fed7aa}.bank-modal [data-open-period-after-close]:hover{background:#ffedd5}.bank-table-scroll{height:min(58vh,36rem);min-height:18rem;flex:none;overflow-y:auto;overflow-x:auto}.bank-table-scroll thead th{position:sticky;top:0;z-index:10;background:#f8fafc;box-shadow:inset 0 -1px 0 #cbd5e1}.bank-table-footer{position:sticky;bottom:0}.bank-summary-card[aria-pressed="true"]{box-shadow:0 0 0 2px currentColor,0 6px 16px rgb(15 23 42/.1)}@media (max-width:640px){.bank-table-scroll{height:min(58vh,30rem);min-height:14rem}.bank-table-footer{align-items:flex-start;flex-direction:column}.bank-table-footer>span:last-child{width:100%;justify-content:space-between}.bank-summary-card{padding:.875rem}.bank-modal-header,.bank-modal-body,.bank-modal-footer{padding:1rem}}</style>
<style>.period-step{background:#fff;color:#475569}.period-step[aria-selected="true"]{border-color:#2563eb;background:#eff6ff;color:#1d4ed8;box-shadow:0 0 0 1px #2563eb}.period-step-status{font-size:.68rem;color:#64748b}.period-step[aria-selected="true"] .period-step-status{color:#1d4ed8}.bank-modal-footer{flex-wrap:wrap}.bank-modal-footer form{display:flex;align-items:center;gap:.5rem}.bank-modal-footer form:first-child{margin-right:auto}</style>
<script>
const bankRows=[...document.querySelectorAll('[data-bank-row]')],bankSize=10;let bankPage=1;const bankRefresh=()=>{const q=document.querySelector('[data-bank-search]')?.value.toLowerCase()||'',t=document.querySelector('[data-bank-type]')?.value||'all',s=document.querySelector('[data-bank-status]')?.value||'all',filtered=bankRows.filter(r=>(!q||r.dataset.search.includes(q))&&(t==='all'||r.dataset.type===t)&&(s==='needs_action'?['unmatched','unreconciled'].includes(r.dataset.status):(s==='all'||r.dataset.status===s))),pages=Math.max(1,Math.ceil(filtered.length/bankSize));bankPage=Math.min(bankPage,pages);bankRows.forEach(r=>r.hidden=true);filtered.slice((bankPage-1)*bankSize,bankPage*bankSize).forEach(r=>r.hidden=false);document.querySelector('[data-bank-summary]').textContent=filtered.length?'Showing '+((bankPage-1)*bankSize+1)+'–'+Math.min(bankPage*bankSize,filtered.length)+' of '+filtered.length:'No transactions found';document.querySelector('[data-bank-prev]').disabled=bankPage===1;document.querySelector('[data-bank-next]').disabled=bankPage===pages;const pagesEl=document.querySelector('[data-bank-pages]');pagesEl.replaceChildren(...Array.from({length:pages},(_,i)=>{const b=document.createElement('button');b.type='button';b.textContent=i+1;b.className='rounded border px-2.5 py-1.5';b.addEventListener('click',()=>{bankPage=i+1;bankRefresh()});return b}))};document.querySelectorAll('[data-bank-search],[data-bank-type],[data-bank-status]').forEach(e=>e.addEventListener('input',()=>{bankPage=1;bankRefresh()}));document.querySelector('[data-bank-prev]')?.addEventListener('click',()=>{bankPage--;bankRefresh()});document.querySelector('[data-bank-next]')?.addEventListener('click',()=>{bankPage++;bankRefresh()});bankRows.forEach(r=>r.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();document.getElementById(r.dataset.dialogOpen)?.showModal()}}));bankRefresh();document.querySelector('[data-history-search]')?.addEventListener('input',e=>{const q=e.target.value.toLowerCase();document.querySelectorAll('[data-history-row]').forEach(r=>r.hidden=!r.dataset.historySearch.includes(q))});const file=document.querySelector('[data-bank-file]'),drop=document.querySelector('[data-bank-dropzone]'),preview=document.querySelector('[data-bank-preview]'),submit=document.querySelector('[data-bank-submit]'),error=document.querySelector('[data-bank-error]');drop?.addEventListener('click',()=>file.click());file?.addEventListener('change',()=>{if(!file.files[0])return;document.querySelector('[data-bank-file-name]').textContent=file.files[0].name;document.querySelector('[data-bank-file-info]').hidden=false;document.querySelector('[data-bank-preview-summary]').textContent='File selected and ready for server validation.';preview.hidden=false;submit.disabled=false});document.querySelector('[data-bank-remove]')?.addEventListener('click',()=>{file.value='';document.querySelector('[data-bank-file-info]').hidden=true;preview.hidden=true;submit.disabled=true});
document.querySelectorAll('[data-bank-filter-card]').forEach((card) => card.addEventListener('click', () => { const type = card.dataset.bankFilterCard; const select = document.querySelector('[data-bank-type]'); if (select) { select.value = select.value === type ? 'all' : type; select.dispatchEvent(new Event('input', { bubbles: true })); } document.querySelectorAll('[data-bank-filter-card]').forEach((item) => item.setAttribute('aria-pressed', item === card && select?.value === type ? 'true' : 'false')); document.querySelector('[data-bank-all-filter]').classList.toggle('hidden', select?.value === 'all'); }));
document.querySelector('[data-bank-all-filter]')?.addEventListener('click', () => { const select = document.querySelector('[data-bank-type]'); if (select) { select.value = 'all'; select.dispatchEvent(new Event('input', { bubbles: true })); } document.querySelectorAll('[data-bank-filter-card]').forEach((item) => item.setAttribute('aria-pressed', 'false')); document.querySelector('[data-bank-all-filter]').classList.add('hidden'); });
document.querySelector('[data-bank-type]')?.addEventListener('input', (event) => { document.querySelectorAll('[data-bank-filter-card]').forEach((item) => item.setAttribute('aria-pressed', item.dataset.bankFilterCard === event.target.value ? 'true' : 'false')); document.querySelector('[data-bank-all-filter]').classList.toggle('hidden', event.target.value === 'all'); });
const periodBody = document.querySelector('#bank-period-modal .bank-modal-body');
const periodWorkflowTemplate = document.querySelector('#bank-period-workflow-template');
if (periodWorkflowTemplate && periodBody) {
    periodBody.querySelector('form[action$="/periods"]')?.remove();
    [...periodBody.children].find((child) => child.querySelector?.('p.font-semibold'))?.remove();
    periodBody.prepend(periodWorkflowTemplate.content.cloneNode(true));
    const workflow = periodBody.querySelector('[data-bank-period-workflow]');
    const footer = document.querySelector('#bank-period-modal .bank-modal-footer');
    const actionBlock = workflow?.querySelector('[data-period-actions]');
    if (actionBlock && footer) {
        footer.prepend(...actionBlock.childNodes);
        actionBlock.remove();
    }
    const activatePeriodStep = (number) => {
        workflow?.querySelectorAll('[data-period-panel]').forEach((panel) => panel.classList.toggle('hidden', panel.dataset.periodPanel !== String(number)));
        workflow?.querySelectorAll('[data-period-step]').forEach((step) => step.setAttribute('aria-selected', step.dataset.periodStep === String(number) ? 'true' : 'false'));
    };
    workflow?.querySelectorAll('[data-period-step]').forEach((step) => step.addEventListener('click', () => activatePeriodStep(step.dataset.periodStep)));
    workflow?.querySelectorAll('[data-period-jump]').forEach((button) => button.addEventListener('click', () => activatePeriodStep(button.dataset.periodJump)));
    workflow?.querySelector('[data-guided-action="match"]')?.addEventListener('click', () => {
        document.getElementById('bank-period-modal')?.close();
        document.querySelector('[data-bank-status]').value = 'needs_action';
        document.querySelector('[data-bank-status]').dispatchEvent(new Event('input', { bubbles: true }));
        document.querySelector('[data-bank-transaction-table]')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    workflow?.querySelector('[data-period-discrepancies]')?.addEventListener('click', () => {
        document.querySelector('#bank-period-modal [data-discrepancy-panel]')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        document.querySelector('#bank-period-modal [data-discrepancy-toggle]')?.click();
    });
}
const discrepancyTemplate = document.querySelector('#bank-discrepancy-template');
const discrepancyValidationFailed = @json($discrepancyValidationFailed);
if (discrepancyTemplate && periodBody) {
    const discrepancyToggle = document.createElement('button');
    discrepancyToggle.type = 'button';
    discrepancyToggle.className = 'secondary-button w-full justify-center border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100';
    discrepancyToggle.dataset.discrepancyToggle = 'true';
    discrepancyToggle.textContent = 'Manage Discrepancies';
    discrepancyToggle.setAttribute('aria-expanded', discrepancyValidationFailed ? 'true' : 'false');
    const discrepancyContent = discrepancyTemplate.content.cloneNode(true);
    const discrepancyPanel = discrepancyContent.querySelector('[data-discrepancy-panel]');
    discrepancyPanel.hidden = ! discrepancyValidationFailed;
    const periodStepThree = periodBody.querySelector('[data-period-panel="3"]');
    (periodStepThree || periodBody).append(discrepancyToggle, discrepancyContent);
    discrepancyToggle.addEventListener('click', () => {
        discrepancyPanel.hidden = ! discrepancyPanel.hidden;
        discrepancyToggle.setAttribute('aria-expanded', discrepancyPanel.hidden ? 'false' : 'true');
        discrepancyToggle.textContent = discrepancyPanel.hidden ? 'Manage Discrepancies' : 'Hide Discrepancy Form';
        if (! discrepancyPanel.hidden) discrepancyPanel.querySelector('select[name="type"]')?.focus();
    });
    if (discrepancyValidationFailed) {
        discrepancyToggle.textContent = 'Hide Discrepancy Form';
        document.getElementById('bank-period-modal')?.showModal();
    }
}
const periodForm = document.querySelector('#bank-period-modal form[action$="/periods"]');
const suggestedPeriodStart = @json($suggestedPeriodStart);
const suggestedPeriodEnd = @json($suggestedPeriodEnd);
if (periodForm) {
    const periodFields = [
        ['period_start', 'Period starts', 'The first date covered by this bank statement. Verify it against the statement before saving.', suggestedPeriodStart],
        ['period_end', 'Period ends', 'The last date covered by this bank statement. Verify it against the statement before saving.', suggestedPeriodEnd],
        ['opening_balance', 'Starting Bank Balance', 'Enter the bank balance at the beginning of the period. This is not available from the imported transaction rows, so verify it from the statement.', null],
        ['bank_ending_balance', 'Remaining Bank Balance', 'Enter the balance shown by LANDBANK at the end of the period. Verify it from the statement before saving.', null],
    ];
    periodFields.forEach(([name, labelText, helperText, suggestion]) => {
        const input = periodForm.querySelector(`[name="${name}"]`);
        if (! input) return;
        input.setAttribute('aria-label', labelText);
        if (name === 'opening_balance' || name === 'bank_ending_balance') input.placeholder = labelText;
        if (! input.value && suggestion) input.value = suggestion;
        const group = document.createElement('div');
        group.className = 'space-y-1.5';
        const label = document.createElement('label');
        label.htmlFor = `reconciliation-${name}`;
        label.className = 'block text-sm font-semibold text-slate-800';
        label.textContent = labelText;
        input.id = label.htmlFor;
        const helper = document.createElement('p');
        helper.className = 'text-xs leading-5 text-slate-500';
        helper.textContent = helperText;
        input.parentNode.insertBefore(group, input);
        group.append(label, input, helper);
    });
}
document.querySelectorAll('dialog[id^="bank-entry-"] .bank-modal-body > p.mt-5').forEach((message) => message.remove());
document.querySelectorAll('dialog[id^="bank-entry-"]').forEach((dialog) => {
    dialog.addEventListener('toggle', () => {
        if (! dialog.open || dialog.querySelector('[data-bank-action-content]')) return;
        const template = document.getElementById('bank-actions-' + dialog.id.replace('bank-entry-', ''));
        const body = dialog.querySelector('.bank-modal-body');
        if (! template || ! body) return;
        const content = template.content.cloneNode(true);
        const wrapper = document.createElement('div');
        wrapper.dataset.bankActionContent = 'true';
        wrapper.className = 'mt-5 space-y-3 border-t border-slate-200 pt-5';
        wrapper.append(content);
        body.append(wrapper);
        const footer = dialog.querySelector('.bank-modal-footer');
        const primaryAction = wrapper.querySelector('[data-primary-action]');
        if (primaryAction && footer) {
            const form = primaryAction.closest('form');
            if (form?.id) primaryAction.setAttribute('form', form.id);
            footer.prepend(primaryAction);
        }
        wrapper.querySelectorAll('[data-withdrawal-match]').forEach((form) => {
            const amount = Number(form.dataset.withdrawalAmount || 0);
            const difference = form.querySelector('[data-withdrawal-difference]');
            const finalize = form.querySelector('[data-withdrawal-finalize]');
            const update = () => {
                const selected = [...form.querySelectorAll('input[type="checkbox"]:checked')].reduce((total, input) => total + Number(input.dataset.disbursementAmount || 0), 0);
                const delta = amount - selected;
                if (difference) difference.textContent = 'PHP ' + delta.toFixed(2);
                if (finalize) finalize.disabled = Math.abs(delta) >= 0.005;
            };
            form.addEventListener('change', update);
            update();
        });
        wrapper.querySelector('[data-open-period-after-close]')?.addEventListener('click', () => {
            dialog.close();
            document.getElementById('bank-period-modal')?.showModal();
        });
        const periodButton = wrapper.querySelector('[data-open-period-after-close]');
        if (periodButton) periodButton.textContent = 'Review discrepancy controls';
    });
});
document.addEventListener('click', (event) => {
    const action = event.target.closest?.('[data-discrepancy-action]');
    if (! action) return;
    const entryDialog = action.closest('dialog');
    entryDialog?.close();
    const period = document.getElementById('bank-period-modal');
    period?.showModal();
    period?.querySelector('[data-period-step="3"]')?.click();
    period?.querySelector('[data-period-discrepancies]')?.click();
});
const withdrawalValidationId = @json($withdrawalValidationId);
if (withdrawalValidationId) {
    document.getElementById('bank-entry-' + withdrawalValidationId)?.showModal();
}
</script>
@endsection
