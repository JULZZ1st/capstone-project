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
    $discrepancyValidationFailed = $errors->hasAny(['type', 'custom_type', 'classification', 'amount', 'explanation', 'evidence_reference', 'resolution_status', 'adjustment_amount', 'carry_forward']);
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
    $bankDiscrepancyData = $bankTransactions->flatMap(fn ($entry) => ($entry->discrepancies ?? collect())->map(fn ($report) => [$entry->id => ['type' => $report->typeLabel(), 'explanation' => $report->explanation, 'amount' => 'PHP '.number_format((float) $report->amount, 2), 'evidence' => $report->evidence_reference ?: ($report->evidence_path ? 'Attached file' : 'Not provided'), 'reporter' => $report->reporter?->name ?: 'Not recorded', 'reviewer' => $report->adminReviewer?->name ?: 'Not reviewed', 'reviewed_at' => $report->admin_reviewed_at?->format('M d, Y h:i A') ?: 'Not reviewed', 'decision' => $report->admin_review_status === 'accepted' ? 'Accepted' : ($report->admin_review_status === 'correction_requested' ? 'Correction Required' : 'Pending Review'), 'remarks' => $report->admin_review_note ?: 'No Admin remarks recorded.', 'resolution_status' => ucfirst((string) $report->resolution_status), 'resolution_explanation' => $report->resolution_explanation, 'resolution_evidence' => $report->resolution_evidence_path ? 'Attached resolution evidence' : null, 'resolution_evidence_url' => $report->resolution_evidence_path ? route('bank-reconciliation.discrepancies.resolution-evidence', [$report->bank_reconciliation_id, $report]) : null, 'resolved_by' => $report->resolver?->name ?: null, 'resolved_at' => $report->resolved_at?->format('M d, Y h:i A'), 'resolve_url' => route('bank-reconciliation.discrepancies.resolve', [$report->bank_reconciliation_id, $report])]]) )->collapse()->all();
    $rawBankDiscrepancyData = collect($bankDiscrepancyData)->values();
    $bankDiscrepancyData = [];
    $bankDiscrepancyPayloadIndex = 0;
    foreach ($bankTransactions as $entry) {
        $reports = $entry->discrepancies ?? collect();
        $selectedReport = $reports->first(fn ($report) => $report->resolution_status === 'resolved') ?: $reports->first(fn ($report) => $report->admin_review_status === 'correction_requested') ?: $reports->first(fn ($report) => $report->admin_review_status === 'accepted') ?: $reports->first();
        foreach ($reports as $report) {
            if ($selectedReport?->id === $report->id) {
                $bankDiscrepancyData[$entry->id] = $rawBankDiscrepancyData[$bankDiscrepancyPayloadIndex] ?? null;
            }
            $bankDiscrepancyPayloadIndex++;
        }
    }
    $boardBankRows = $boardBankTransactions->map(fn ($entry) => ['id' => $entry->id, 'type' => $entry->transaction_type === 'withdrawal' ? 'withdrawal' : 'deposit', 'date' => $entry->transaction_date?->toDateString(), 'description' => $entry->description, 'reference' => $entry->reference_number, 'amount' => (float) $entry->amount, 'status' => $entry->status === 'reconciled' ? 'matched' : $entry->status, 'matchUrl' => route('bank-reconciliation.reconcile'), 'outgoingFinalizeUrl' => route('bank-reconciliation.withdrawals.reconcile', $entry)])->values();
    $systemDiscrepancyFor = function (string $type, int $id) use ($activePeriod) {
        $reports = $activePeriod?->discrepancies?->filter(fn ($report) => $report->system_record_type === $type && (int) $report->system_record_id === $id) ?? collect();
        $report = $reports->first(fn ($item) => $item->resolution_status === 'resolved') ?: $reports->first(fn ($item) => $item->admin_review_status === 'correction_requested') ?: $reports->first(fn ($item) => $item->admin_review_status === 'accepted') ?: $reports->first();
        return $report ? ['id' => $report->id, 'status' => $report->resolution_status === 'resolved' ? 'Resolved' : ($report->admin_review_status === 'accepted' ? 'Accepted' : ($report->admin_review_status === 'correction_requested' ? 'Correction Required' : 'Pending Review')), 'data' => ['type' => $report->typeLabel(), 'explanation' => $report->explanation, 'amount' => 'PHP '.number_format((float) $report->amount, 2), 'evidence' => $report->evidence_reference ?: ($report->evidence_path ? 'Attached file' : 'Not provided'), 'reporter' => $report->reporter?->name ?: 'Not recorded', 'reviewer' => $report->adminReviewer?->name ?: 'Not reviewed', 'reviewed_at' => $report->admin_reviewed_at?->format('M d, Y h:i A') ?: 'Not reviewed', 'decision' => $report->admin_review_status === 'accepted' ? 'Accepted' : ($report->admin_review_status === 'correction_requested' ? 'Correction Required' : 'Pending Review'), 'remarks' => $report->admin_review_note ?: 'No Admin remarks recorded.', 'resolution_status' => ucfirst((string) $report->resolution_status), 'resolution_explanation' => $report->resolution_explanation, 'resolution_evidence' => $report->resolution_evidence_path ? 'Attached resolution evidence' : null, 'resolution_evidence_url' => $report->resolution_evidence_path ? route('bank-reconciliation.discrepancies.resolution-evidence', [$report->bank_reconciliation_id, $report]) : null, 'resolved_by' => $report->resolver?->name ?: null, 'resolved_at' => $report->resolved_at?->format('M d, Y h:i A'), 'resolve_url' => route('bank-reconciliation.discrepancies.resolve', [$report->bank_reconciliation_id, $report])]] : null;
    };
    $boardDepositRows = $boardContributions->map(fn ($receipt) => ['id' => $receipt->id, 'date' => $receipt->received_at?->toDateString(), 'label' => $receipt->donor?->full_name ?? 'Verified donation', 'detail' => $receipt->reference_number ?? 'Electronic cash donation', 'reference' => $receipt->reference_number, 'amount' => (float) $receipt->amount, 'status' => $receipt->ledgerEntry ? 'matched' : 'verified', 'discrepancy' => $systemDiscrepancyFor('contribution', (int) $receipt->id)])->values();
    $boardWithdrawalRows = $boardDisbursements->map(fn ($request) => ['id' => $request->id, 'date' => $request->approved_at?->toDateString() ?? $request->requested_date?->toDateString(), 'label' => $request->recipient ?? 'Approved fund request', 'detail' => $request->purpose, 'reference' => $request->supporting_reference, 'amount' => (float) $request->amount, 'status' => $request->withdrawalMatch ? 'matched' : 'approved', 'discrepancy' => $systemDiscrepancyFor('cash_disbursement', (int) $request->id)])->values();
    $confirmedPeriodRows = $confirmedPeriods->map(fn ($period) => ['bank_account' => $period->bank_account ?: config('bank_reconciliation.bank_accounts.0'), 'period_end' => $period->period_end?->toDateString(), 'ending_balance' => (float) $period->bank_ending_balance])->values();
    $periodRows = $reconciliationPeriods->map(fn ($period) => ['bank_account' => $period->bank_account ?: config('bank_reconciliation.bank_accounts.0'), 'period_end' => $period->period_end?->toDateString(), 'status' => $period->status])->values();
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
            <section id="period-step-3" data-period-panel="3" class="period-step-panel hidden space-y-3"><h4 class="text-base font-bold text-slate-900">Step 3: Review discrepancies</h4><div class="rounded-lg border {{ $unresolvedDiscrepancyCount ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }} p-3 text-sm"><strong>{{ $unresolvedDiscrepancyCount ? 'Needs action:' : 'Complete:' }}</strong> {{ $unresolvedDiscrepancyCount ? $unresolvedDiscrepancyCount.' unresolved discrepancy(ies) must be investigated.' : 'There are no unresolved discrepancies.' }}</div>@if($activePeriod->isDraft() || $activePeriod->isReadyForReview())<button type="button" data-period-discrepancies class="secondary-button">{{ $activePeriod->isReadyForReview() ? 'Review discrepancies' : ($unresolvedDiscrepancyCount ? 'Review discrepancies' : 'Open discrepancy manager') }}</button>@else<p class="text-sm text-slate-600">This period is locked; discrepancy changes require an authorized audited reopening.</p>@endif</section>
            <section id="period-step-4" data-period-panel="4" class="period-step-panel hidden space-y-3"><h4 class="text-base font-bold text-slate-900">Step 4: Review balances</h4><div class="grid gap-2 sm:grid-cols-2"><div class="rounded-lg border bg-slate-50 p-3"><p class="text-xs text-slate-500">Starting bank balance</p><p class="mt-1 font-bold">PHP {{ number_format((float) $activePeriod->opening_balance, 2) }}</p></div><div class="rounded-lg border bg-slate-50 p-3"><p class="text-xs text-slate-500">Remaining bank balance</p><p class="mt-1 font-bold">{{ $activePeriod->bank_ending_balance === null ? 'Not entered' : 'PHP '.number_format((float) $activePeriod->bank_ending_balance, 2) }}</p></div><div class="rounded-lg border bg-slate-50 p-3"><p class="text-xs text-slate-500">Bank movement</p><p class="mt-1 font-semibold">+ PHP {{ number_format((float) $activePeriod->calculated_bank_cash_in, 2) }} / − PHP {{ number_format((float) $activePeriod->calculated_bank_cash_out, 2) }}</p></div><div class="rounded-lg border bg-slate-50 p-3"><p class="text-xs text-slate-500">Ledger ending balance</p><p class="mt-1 font-bold">PHP {{ number_format((float) $activePeriod->financial_ledger_ending_balance, 2) }}</p></div></div><div class="rounded-xl border-2 {{ $periodBalancesReady ? 'border-emerald-300 bg-emerald-50' : 'border-amber-300 bg-amber-50' }} p-4"><p class="text-xs font-semibold uppercase tracking-wide">Difference</p><p class="mt-1 text-2xl font-bold">{{ $periodDifference === null ? 'Not calculated' : 'PHP '.number_format((float) $periodDifference, 2) }}</p><p class="mt-1 text-sm">{{ $periodDifference === null ? 'Enter the remaining bank balance and recalculate.' : ($periodBalancesReady ? 'The adjusted bank balance equals the ledger balance.' : 'The adjusted bank balance does not equal the ledger balance. Check the entered balance or record a supported discrepancy with evidence.') }}</p></div>@can('update', $activePeriod)<form method="POST" action="{{ route('bank-reconciliation.periods.update', $activePeriod) }}" class="grid gap-3 rounded-lg border bg-white p-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">@csrf @method('PATCH')<label class="text-sm font-semibold">Starting bank balance<input name="opening_balance" type="number" min="0" step="0.01" value="{{ $activePeriod->opening_balance }}" required class="mt-1 w-full rounded border-slate-300 text-sm"></label><label class="text-sm font-semibold">Remaining bank balance<input name="bank_ending_balance" type="number" min="0" step="0.01" value="{{ $activePeriod->bank_ending_balance }}" required class="mt-1 w-full rounded border-slate-300 text-sm"></label><button type="submit" class="secondary-button">Check balances</button></form>@endcan</section>
            <section id="period-step-5" data-period-panel="5" class="period-step-panel hidden space-y-3"><h4 class="text-base font-bold text-slate-900">Step 5: Submit for Admin review</h4><div class="rounded-lg border {{ $activePeriod->isReadyForReview() || $activePeriod->isApproved() ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-900' }} p-3 text-sm"><strong>{{ $activePeriod->isReadyForReview() || $activePeriod->isApproved() ? 'Complete:' : 'Blocked:' }}</strong> {{ $activePeriod->isApproved() ? 'This period is approved and locked.' : ($activePeriod->isReadyForReview() ? 'This period is waiting for independent Admin review.' : 'Resolve the blockers below before submitting.') }}</div>@if($activePeriod->isDraft())<ul class="space-y-2 text-sm">@if($unmatchedPeriodTransactionCount)<li class="flex items-center justify-between gap-3 rounded border border-amber-200 bg-amber-50 p-3"><span><strong>Matching:</strong> {{ $unmatchedPeriodTransactionCount }} bank transaction(s) are unmatched.</span><button type="button" data-period-jump="2" class="font-semibold text-blue-700">Match transactions</button></li>@endif @if($unresolvedDiscrepancyCount)<li class="flex items-center justify-between gap-3 rounded border border-amber-200 bg-amber-50 p-3"><span><strong>Discrepancies:</strong> {{ $unresolvedDiscrepancyCount }} item(s) are unresolved.</span><button type="button" data-period-jump="3" class="font-semibold text-blue-700">Review discrepancies</button></li>@endif @if(!$periodBalancesReady)<li class="flex items-center justify-between gap-3 rounded border border-amber-200 bg-amber-50 p-3"><span><strong>Balances:</strong> Difference must be PHP 0.00{{ $periodDifference !== null ? ' (currently PHP '.number_format((float) $periodDifference, 2).')' : '' }}.</span><button type="button" data-period-jump="4" class="font-semibold text-blue-700">Check balances</button></li>@endif @if($periodSubmitReady)<li class="rounded border border-emerald-200 bg-emerald-50 p-3 text-emerald-800">All submission requirements are complete. Submit using the footer button.</li>@endif</ul>@endif</section>
        </div>
        <div data-period-actions class="hidden">@can('submit', $activePeriod)<form method="POST" action="{{ route('bank-reconciliation.periods.submit', $activePeriod) }}">@csrf<button type="submit" class="primary-button" @disabled(!$periodSubmitReady)>Submit for Admin Review</button></form>@endcan @can('approve', $activePeriod)<form method="POST" action="{{ route('bank-reconciliation.periods.approve', $activePeriod) }}">@csrf<button type="submit" class="primary-button">Approve &amp; Lock Period</button></form><form method="POST" action="{{ route('bank-reconciliation.periods.reject', $activePeriod) }}" class="flex gap-2"><input name="reason" required placeholder="Reason for correction" class="w-48 rounded border-slate-300 text-sm"><button type="submit" class="secondary-button text-rose-700">Reject for Correction</button></form>@endcan</div>
    </section>
</template>
@endif
<div class="flex min-h-0 flex-col gap-4">
    <div class="flex flex-wrap justify-end gap-2">
        @can('import', \App\Models\BankLedgerEntry::class)<button type="button" data-dialog-open="bank-import-modal" class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white">Upload Bank Statement</button>@endcan
        @if($reconciliationPeriods->contains(fn ($period) => $period->status === 'draft'))
        <label class="flex items-center gap-2 text-sm font-semibold text-slate-700"><span class="sr-only">Working period</span><select data-period-selector aria-label="Working reconciliation period" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm">@forelse($reconciliationPeriods as $periodOption)<option value="{{ $periodOption->id }}" @selected($activePeriod?->id === $periodOption->id)>{{ $periodOption->period_start?->format('M Y') }} · {{ ucfirst(str_replace('_', ' ', $periodOption->status)) }}</option>@empty<option value="" selected disabled>No reconciliation periods available</option>@endforelse</select></label>
        @endif
        @can('prepare', \App\Models\BankReconciliation::class)<button type="button" data-dialog-open="new-bank-period-modal" class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm font-semibold text-blue-700">New Reconciliation Period</button>@endcan
        @can('viewAny', \App\Models\BankLedgerEntry::class)<button type="button" data-dialog-open="bank-period-modal" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Manage Reconciliation Period</button>@endcan
        @if($latestReconciliationPeriod && ! $latestReconciliationPeriod->isApproved())<button type="button" data-dialog-open="bank-discrepancy-modal" class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm font-semibold text-blue-700">Discrepancy Reports</button>@endif
        @if($activePeriod)<a href="{{ route('reports.reconciliation.pdf', ['period_id' => $activePeriod->id]) }}" class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm font-semibold text-blue-700">Generate Bank Reconciliation Statement</a>@endif
        <button type="button" data-dialog-open="bank-history-modal" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">View History</button>
    </div>
    <div class="hidden" aria-hidden="true">
        <button type="button" data-bank-filter-card="deposit" aria-pressed="false" class="bank-summary-card group rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-left transition hover:border-emerald-400 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"><span class="flex items-center justify-between"><span class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Incoming · Credit</span><span class="text-xs font-semibold text-emerald-700 opacity-0 transition group-hover:opacity-100" data-card-hint>Filter</span></span><span class="mt-1 block text-2xl font-bold text-emerald-950">&#8369;{{ number_format($bankTransactions->where('transaction_type', 'deposit')->sum('amount'), 2) }}</span></button>
        <button type="button" data-bank-filter-card="withdrawal" aria-pressed="false" class="bank-summary-card group rounded-xl border border-rose-200 bg-rose-50 p-4 text-left transition hover:border-rose-400 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-rose-500"><span class="flex items-center justify-between"><span class="text-xs font-semibold uppercase tracking-wide text-rose-700">Outgoing · Debit</span><span class="text-xs font-semibold text-rose-700 opacity-0 transition group-hover:opacity-100" data-card-hint>Filter</span></span><span class="mt-1 block text-2xl font-bold text-rose-950">&#8369;{{ number_format($bankTransactions->where('transaction_type', 'withdrawal')->sum('amount'), 2) }}</span></button>
    </div>
    <div class="hidden">
        <article class="rounded-xl border border-emerald-200 bg-emerald-50 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Incoming · Credit</p><p class="mt-1 text-2xl font-bold text-emerald-950">&#8369;{{ number_format($bankTransactions->where('transaction_type', 'deposit')->sum('amount'), 2) }}</p></article>
        <article class="rounded-xl border border-rose-200 bg-rose-50 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-rose-700">Outgoing · Debit</p><p class="mt-1 text-2xl font-bold text-rose-950">&#8369;{{ number_format($bankTransactions->where('transaction_type', 'withdrawal')->sum('amount'), 2) }}</p></article>
    </div>
    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" data-reconciliation-board>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Confirm activity</p><h2 class="mt-1 text-lg font-bold text-slate-900">Match system records to LANDBANK</h2><p class="mt-1 text-sm text-slate-600">Select one LANDBANK transaction and its valid system record(s). Confirm Match stays disabled until the records and amounts match.</p></div>
            <div class="flex flex-wrap items-center gap-2"><div class="flex rounded-lg border border-slate-200 bg-slate-50 p-1" role="tablist" aria-label="Transaction direction"><button type="button" data-board-tab="deposit" aria-selected="true" class="rounded-md px-3 py-2 text-sm font-semibold transition hover:bg-blue-50 hover:text-blue-700">Incoming</button><button type="button" data-board-tab="withdrawal" aria-selected="false" class="rounded-md px-3 py-2 text-sm font-semibold transition hover:bg-blue-50 hover:text-blue-700">Outgoing</button></div><div class="flex rounded-lg border border-slate-200 bg-white p-1" role="group" aria-label="Matching status"><button type="button" data-board-filter="needs" class="rounded-md bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700">Needs Matching</button><button type="button" data-board-filter="all" class="rounded-md px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-blue-50 hover:text-blue-700">All</button><button type="button" data-board-filter="matched" class="rounded-md px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-blue-50 hover:text-blue-700">Matched</button></div></div>
        </div>
        <div class="mt-4 grid gap-4 xl:grid-cols-2">
            <section class="min-w-0 rounded-lg border border-slate-200" aria-labelledby="system-records-heading"><div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-4 py-3"><div><h3 id="system-records-heading" class="font-semibold text-slate-900">System Records</h3><p class="text-xs text-slate-500">Verified incoming donations or approved fund payments</p></div><span data-board-system-count class="rounded-full bg-white px-2 py-1 text-xs font-semibold text-slate-600"></span></div><div class="bank-board-scroll"><table class="min-w-full text-left text-sm"><thead class="sticky top-0 z-10 bg-white text-xs uppercase tracking-wide text-slate-500"><tr><th class="w-10 px-4 py-3">&nbsp;</th><th class="px-4 py-3">Record</th><th class="px-4 py-3 text-right">Amount</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">&nbsp;</th></tr></thead><tbody data-board-system-body></tbody></table><p data-board-system-empty class="hidden p-6 text-center text-sm text-slate-500">No eligible system records are available for this direction.</p></div></section>
            <section class="min-w-0 rounded-lg border border-slate-200" aria-labelledby="landbank-transactions-heading"><div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-4 py-3"><div><h3 id="landbank-transactions-heading" class="font-semibold text-slate-900">LANDBANK Transactions</h3><p class="text-xs text-slate-500">Imported statement rows for the selected period</p></div><span data-board-bank-count class="rounded-full bg-white px-2 py-1 text-xs font-semibold text-slate-600"></span></div><div class="bank-board-scroll"><table class="min-w-full text-left text-sm"><thead class="sticky top-0 z-10 bg-white text-xs uppercase tracking-wide text-slate-500"><tr><th class="w-10 px-4 py-3">&nbsp;</th><th class="px-4 py-3">Transaction</th><th class="px-4 py-3 text-right">Amount</th><th class="px-4 py-3">Status</th></tr></thead><tbody data-board-bank-body></tbody></table><p data-board-bank-empty class="hidden p-6 text-center text-sm text-slate-500">No imported LANDBANK transactions are available for this direction.</p></div></section>
        </div>
<div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-blue-100 bg-blue-50/60 p-3"><div class="min-w-0 space-y-1"><p data-board-selection class="text-sm font-semibold text-blue-900">Select one LANDBANK transaction and its matching system record(s).</p><p data-board-amount-summary class="text-xs text-blue-800">Bank amount: PHP 0.00 · System amount: PHP 0.00 · Difference: PHP 0.00</p><p data-board-selection-help class="text-xs text-slate-600">Confirm Match is disabled until one valid bank transaction and matching system record(s) are selected.</p></div><button type="button" data-board-clear class="primary-button reconciliation-confirm-match" disabled>Confirm Match</button></div>
    </section>
    <section class="flex min-h-0 flex-1 flex-col" data-bank-transaction-table>
        <div class="flex flex-wrap items-center justify-between gap-2 pb-3"><button type="button" data-bank-all-filter class="hidden rounded-full border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100">Show all transactions</button><div class="ml-auto flex w-full flex-wrap gap-2 sm:w-auto sm:justify-end"><input data-bank-search aria-label="Search transactions" placeholder="Search description or reference" class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm sm:w-64"><select data-bank-type aria-label="Filter transaction type" class="min-h-10 max-w-full rounded-lg border border-slate-300 bg-white px-3 py-2 pr-10 text-sm"><option value="all">All types</option><option value="deposit">Incoming</option><option value="withdrawal">Outgoing</option></select><select data-bank-status aria-label="Filter transaction status" class="min-h-10 max-w-full rounded-lg border border-slate-300 bg-white px-3 py-2 pr-10 text-sm"><option value="all">All statuses</option><option value="unmatched">Unmatched</option><option value="reconciled">Reconciled</option></select></div></div>
        <div class="min-w-0"><x-data-table caption="Bank transactions" min-width="49rem" title="Bank transactions">
<x-slot:head><x-data-table.heading>Date</x-data-table.heading>
<x-data-table.heading>Transaction</x-data-table.heading>
<x-data-table.heading>Reference</x-data-table.heading>
<x-data-table.heading align="right">Amount</x-data-table.heading>
<x-data-table.heading>Status</x-data-table.heading>
<x-data-table.heading>Discrepancy Status</x-data-table.heading>
</x-slot:head>

        @forelse($bankTransactions as $entry)
            <tr data-bank-row data-type="{{ $entry->transaction_type }}" data-status="{{ $entry->status }}" data-search="{{ strtolower($entry->description.' '.$entry->reference_number) }}" data-dialog-open="bank-entry-{{ $entry->id }}" tabindex="0" role="button" class="cursor-pointer hover:bg-blue-50"><td class="px-4 py-3">{{ $entry->transaction_date?->format('M d, Y') }}</td><td class="px-4 py-3 font-semibold">{{ $entry->description }}<span class="block text-xs font-normal text-slate-500">{{ $entry->transaction_type === 'deposit' ? 'Incoming · Credit' : 'Outgoing · Debit' }}</span></td><td class="px-4 py-3">{{ $entry->reference_number ?: '-' }}</td><td class="data-table__number font-semibold">&#8369;{{ number_format((float) $entry->amount, 2) }}</td><td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', $entry->status) }}</td></tr>
        @empty
            <x-data-table.empty :columns="6" title="No bank transactions are available for this workspace." description="Import a bank statement to start reviewing transactions." />
        @endforelse

</x-data-table></div>
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
    @if($latestReconciliationPeriod && ! $latestReconciliationPeriod->isApproved())
    <dialog id="bank-discrepancy-modal" class="bank-modal">
        <div class="bank-modal-shell">
            <header class="bank-modal-header">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Reconciliation review</p>
                    <h2>Discrepancy Reports</h2>
                    <p class="mt-1 text-sm font-normal text-slate-500">Review report decisions separately from matching and final period approval.</p>
                </div>
                <button type="button" data-dialog-close="bank-discrepancy-modal" aria-label="Close">&times;</button>
            </header>
            <div class="bank-modal-body space-y-4">
        @php
            $discrepancyReports = $latestReconciliationPeriod->discrepancies;
            $pendingReviewCount = $discrepancyReports->filter(fn ($report) => ! in_array($report->admin_review_status, ['accepted', 'correction_requested'], true))->count();
            $acceptedReviewCount = $discrepancyReports->where('admin_review_status', 'accepted')->count();
            $correctionReviewCount = $discrepancyReports->where('admin_review_status', 'correction_requested')->count();
            $discrepancyReportData = $discrepancyReports->map(fn ($report) => ['type' => $report->typeLabel(), 'explanation' => $report->explanation, 'amount' => 'PHP '.number_format((float) $report->amount, 2), 'evidence' => $report->evidence_reference ?: ($report->evidence_path ? 'Attached file' : 'Not provided'), 'reporter' => $report->reporter?->name ?: 'Not recorded', 'reviewer' => $report->adminReviewer?->name ?: 'Not reviewed', 'reviewed_at' => $report->admin_reviewed_at?->format('M d, Y h:i A') ?: 'Not reviewed', 'decision' => $report->admin_review_status === 'accepted' ? 'Accepted' : ($report->admin_review_status === 'correction_requested' ? 'Correction Required' : 'Pending Review'), 'remarks' => $report->admin_review_note ?: 'No Admin remarks recorded.', 'resolution_status' => ucfirst((string) $report->resolution_status), 'resolution_explanation' => $report->resolution_explanation, 'resolution_evidence' => $report->resolution_evidence_path ? 'Attached resolution evidence' : null, 'resolution_evidence_url' => $report->resolution_evidence_path ? route('bank-reconciliation.discrepancies.resolution-evidence', [$latestReconciliationPeriod, $report]) : null, 'resolved_by' => $report->resolver?->name ?: null, 'resolved_at' => $report->resolved_at?->format('M d, Y h:i A'), 'resolve_url' => route('bank-reconciliation.discrepancies.resolve', [$latestReconciliationPeriod, $report])]);
        @endphp
        <div class="grid grid-cols-3 gap-2" aria-label="Discrepancy report summary">
            <button type="button" data-discrepancy-filter="pending" class="discrepancy-filter-card rounded-lg border border-amber-200 bg-amber-50 p-2 text-left text-xs" aria-pressed="false"><span class="block font-semibold text-amber-800">Pending Review</span><span class="mt-1 block text-lg font-bold text-amber-950">{{ $pendingReviewCount }}</span></button>
            <button type="button" data-discrepancy-filter="accepted" class="discrepancy-filter-card rounded-lg border border-emerald-200 bg-emerald-50 p-2 text-left text-xs" aria-pressed="false"><span class="block font-semibold text-emerald-800">Accepted</span><span class="mt-1 block text-lg font-bold text-emerald-950">{{ $acceptedReviewCount }}</span></button>
            <button type="button" data-discrepancy-filter="correction_requested" class="discrepancy-filter-card rounded-lg border border-rose-200 bg-rose-50 p-2 text-left text-xs" aria-pressed="false"><span class="block font-semibold text-rose-800">Correction Required</span><span class="mt-1 block text-lg font-bold text-rose-950">{{ $correctionReviewCount }}</span></button>
        </div>
        <label class="block text-sm font-semibold text-slate-800">Search reports<input type="search" data-discrepancy-search placeholder="Search type, explanation, reporter, or amount" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></label>
        <script type="application/json" id="discrepancy-report-data">@json($discrepancyReportData)</script>
        <section data-discrepancy-panel class="space-y-4 rounded-xl border border-amber-200 bg-amber-50/60 p-4">
            <div class="flex items-start justify-between gap-3">
                <div><h3 class="text-base font-semibold text-slate-900">{{ auth()->user()->can('reviewDiscrepancy', $latestReconciliationPeriod) ? 'Resolve Discrepancies' : 'Manage Discrepancies' }}</h3><p class="mt-1 text-xs leading-5 text-slate-600">{{ auth()->user()->can('reviewDiscrepancy', $latestReconciliationPeriod) ? 'Review Staff-submitted explanations and evidence separately from final period approval.' : 'View submitted reports and update an item when Admin requests correction.' }}</p></div>
                <span class="rounded-full {{ auth()->user()->can('reviewDiscrepancy', $latestReconciliationPeriod) ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800' }} px-2.5 py-1 text-xs font-semibold">{{ auth()->user()->can('reviewDiscrepancy', $latestReconciliationPeriod) ? 'Admin review' : 'Draft period' }}</span>
            </div>
            @if($errors->hasAny(['type', 'classification', 'amount', 'explanation', 'evidence_reference', 'resolution_status', 'adjustment_amount', 'carry_forward']))
                <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700" role="alert">Please correct the highlighted discrepancy fields before recording it.</div>
            @endif
            <div class="space-y-2" data-discrepancy-list>
                @forelse($latestReconciliationPeriod->discrepancies as $discrepancy)
                    <article class="rounded-lg border border-white bg-white p-3 text-sm"><div class="flex flex-wrap items-center justify-between gap-2"><strong>{{ $discrepancy->typeLabel() }}</strong><div class="flex flex-wrap gap-1.5"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold capitalize">{{ $discrepancy->resolution_status }}</span><span class="rounded-full px-2 py-1 text-xs font-semibold capitalize {{ $discrepancy->admin_review_status === 'accepted' ? 'bg-emerald-100 text-emerald-800' : ($discrepancy->admin_review_status === 'correction_requested' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') }}">{{ str_replace('_', ' ', $discrepancy->admin_review_status ?: 'pending') }}</span></div></div><p class="mt-1 text-slate-600">{{ $discrepancy->explanation }}</p><p class="mt-1 text-xs text-slate-500">PHP {{ number_format((float) $discrepancy->amount, 2) }} · Reported by: {{ $discrepancy->reporter?->name ?: 'Not recorded' }} · Evidence: {{ $discrepancy->evidence_reference ?: ($discrepancy->evidence_path ? 'Attached file' : 'Not provided') }}</p>@if($discrepancy->admin_review_note)<p class="mt-1 text-xs text-slate-500">Admin note: {{ $discrepancy->admin_review_note }}</p>@endif<div class="mt-2 flex flex-wrap items-center gap-2">@if($discrepancy->resolution_status === 'unresolved' && auth()->user()->can('prepare', \App\Models\BankReconciliation::class))<button type="button" data-edit-discrepancy data-edit-action="{{ route('bank-reconciliation.discrepancies.update', [$latestReconciliationPeriod, $discrepancy]) }}" data-edit-type="{{ $discrepancy->type }}" data-edit-custom-type="{{ $discrepancy->custom_type }}" data-edit-classification="{{ $discrepancy->classification }}" data-edit-amount="{{ $discrepancy->amount }}" data-edit-explanation="{{ $discrepancy->explanation }}" data-edit-reference="{{ $discrepancy->evidence_reference }}" class="text-xs font-semibold text-blue-700">Update report</button>@endif @can('reviewDiscrepancy', $latestReconciliationPeriod)<form method="POST" action="{{ route('bank-reconciliation.discrepancies.review', [$latestReconciliationPeriod, $discrepancy, 'accepted']) }}">@csrf<button type="submit" class="rounded border border-emerald-200 px-2 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">Accept Explanation</button></form><form method="POST" action="{{ route('bank-reconciliation.discrepancies.review', [$latestReconciliationPeriod, $discrepancy, 'correction_requested']) }}" class="flex items-center gap-1">@csrf<input name="note" placeholder="Correction note (optional)" class="w-44 rounded border-slate-300 px-2 py-1 text-xs"><button type="submit" class="rounded border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50">Request Correction</button></form>@endcan</div></article>
                @empty
                    <p class="rounded-lg border border-dashed border-amber-300 bg-white p-3 text-sm text-slate-600">No discrepancies have been recorded for this period yet.</p>
                @endforelse
            </div>
            @if(false)
            @can('prepare', \App\Models\BankReconciliation::class)
                <form id="discrepancy-create-form" method="POST" action="{{ route('bank-reconciliation.discrepancies.store', $latestReconciliationPeriod) }}" enctype="multipart/form-data" class="space-y-3 rounded-lg border border-white bg-white p-3" data-discrepancy-form>
                    @csrf
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="space-y-1 text-sm font-semibold text-slate-800">Discrepancy type<select name="type" required class="mt-1 w-full rounded border-slate-300 text-sm"><option value="">Select a type</option><option value="bank_charge">Bank Charge</option><option value="outstanding_check">Outstanding Check</option><option value="deposit_in_transit">Deposit in Transit</option><option value="cash_not_deposited">Cash Not Yet Deposited</option><option value="recording_error">Recording Error</option></select><span class="block text-xs font-normal leading-5 text-slate-500">Choose the reason the bank and records differ.</span></label>
                        <label class="space-y-1 text-sm font-semibold text-slate-800">Classification<select name="classification" required class="mt-1 w-full rounded border-slate-300 text-sm"><option value="">Select a classification</option><option value="timing_difference">Timing Difference</option><option value="accounting_adjustment">Accounting Adjustment</option></select><span class="block text-xs font-normal leading-5 text-slate-500">Timing items can carry forward; accounting adjustments affect the adjusted balance.</span></label>
                        <label class="space-y-1 text-sm font-semibold text-slate-800">Amount<input name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount') }}" required class="mt-1 w-full rounded border-slate-300 text-sm"><span class="block text-xs font-normal leading-5 text-slate-500">Enter the exact difference shown on the statement.</span></label>
                        <label class="space-y-1 text-sm font-semibold text-slate-800">Evidence reference <span class="font-normal text-slate-500">(Optional)</span><input name="evidence_reference" value="{{ old('evidence_reference') }}" placeholder="e.g. CHG-26001 or CHECK-102" class="mt-1 w-full rounded border-slate-300 text-sm"><input type="file" name="supporting_evidence" accept=".pdf,.jpg,.jpeg,.png" class="mt-2 block w-full rounded border border-slate-300 bg-white px-2 py-1.5 text-sm"><span class="block text-xs font-normal leading-5 text-slate-500">Attach evidence when available; timing items still require a usable reference before approval.</span></label>
                    </div>
                    <label class="block space-y-1 text-sm font-semibold text-slate-800">Explanation<textarea name="explanation" rows="3" required class="mt-1 w-full rounded border-slate-300 text-sm" placeholder="Explain what happened and what should be investigated.">{{ old('explanation') }}</textarea></label>
                    <div class="grid gap-3 sm:grid-cols-2"><label class="space-y-1 text-sm font-semibold text-slate-800">Resolution status<select name="resolution_status" required class="mt-1 w-full rounded border-slate-300 text-sm"><option value="unresolved" @selected(old('resolution_status', 'unresolved') === 'unresolved')>Unresolved — needs investigation</option><option value="accounted" @selected(old('resolution_status') === 'accounted')>Accounted — documented adjustment</option><option value="resolved" @selected(old('resolution_status') === 'resolved')>Resolved — evidence confirms the cause</option></select><span class="block text-xs font-normal leading-5 text-slate-500">New discrepancies default to unresolved. Do not close an item without evidence.</span></label><label class="space-y-1 text-sm font-semibold text-slate-800">Adjustment amount <input name="adjustment_amount" type="number" step="0.01" value="{{ old('adjustment_amount') }}" placeholder="Only for accounting adjustments" class="mt-1 w-full rounded border-slate-300 text-sm"><span class="block text-xs font-normal leading-5 text-slate-500">Leave blank for timing differences.</span></label></div>
                    <label class="flex items-start gap-2 text-sm text-slate-700"><input type="hidden" name="carry_forward" value="0"><input type="checkbox" name="carry_forward" value="1" @checked(old('carry_forward')) class="mt-1"><span><strong>Carry forward</strong><span class="block text-xs leading-5 text-slate-500">Use for an unresolved timing difference that should be checked in the next period.</span></span></label>
                </form>
            @endcan
            @endif
        </section>
            </div>
            <footer class="bank-modal-footer">
                <button type="button" data-dialog-close="bank-discrepancy-modal" class="secondary-button">Close</button>
            </footer>
        </div>
    </dialog>
    <dialog id="bank-discrepancy-details-modal" class="bank-modal">
        <div class="bank-modal-shell">
            <header class="bank-modal-header"><div><p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Discrepancy report</p><h2 data-discrepancy-detail-title>Report details</h2></div><button type="button" data-dialog-close="bank-discrepancy-details-modal" aria-label="Close">&times;</button></header>
            <div class="bank-modal-body space-y-4"><dl class="grid gap-4 text-sm sm:grid-cols-2"><div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Discrepancy type</dt><dd data-detail-field="type" class="mt-1 font-semibold text-slate-900"></dd></div><div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Amount</dt><dd data-detail-field="amount" class="mt-1 font-semibold text-slate-900"></dd></div><div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Admin decision</dt><dd data-detail-field="decision" class="mt-1 font-semibold text-slate-900"></dd></div><div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Decision date</dt><dd data-detail-field="reviewed_at" class="mt-1 text-slate-700"></dd></div><div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reviewer</dt><dd data-detail-field="reviewer" class="mt-1 text-slate-700"></dd></div><div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Evidence</dt><dd data-detail-field="evidence" class="mt-1 text-slate-700"></dd></div></dl><section class="rounded-lg border border-slate-200 bg-slate-50 p-3"><h3 class="text-sm font-semibold text-slate-900">Explanation</h3><p data-detail-field="explanation" class="mt-1 whitespace-pre-wrap text-sm text-slate-700"></p></section><section class="rounded-lg border border-blue-200 bg-blue-50 p-3"><h3 class="text-sm font-semibold text-blue-900">Admin remarks</h3><p data-detail-field="remarks" class="mt-1 whitespace-pre-wrap text-sm text-blue-900"></p></section></div>
            <footer class="bank-modal-footer"><button type="button" data-dialog-close="bank-discrepancy-details-modal" class="secondary-button">Close</button></footer>
        </div>
    </dialog>
    <dialog id="bank-discrepancy-resolve-modal" class="bank-modal">
        <form method="POST" enctype="multipart/form-data" class="bank-modal-shell" data-discrepancy-resolve-form>
            @csrf
            <header class="bank-modal-header discrepancy-modal-header"><div><p class="discrepancy-modal-kicker">Verified corrective action</p><h2>Resolve Discrepancy</h2><p class="mt-1 text-sm font-normal text-slate-500">Document what was corrected. This does not clear a bank transaction or change reconciliation balances.</p></div><button type="button" data-dialog-close="bank-discrepancy-resolve-modal" aria-label="Close">&times;</button></header>
            <div class="bank-modal-body discrepancy-modal-body space-y-4"><section class="discrepancy-modal-summary"><div class="min-w-0"><strong data-resolve-summary-type>Discrepancy</strong><span data-resolve-summary-amount>Amount: —</span><span data-resolve-summary-decision>Admin decision: Accepted</span></div></section><section class="discrepancy-modal-section"><div class="discrepancy-modal-section-title"><h3>Resolution Details</h3></div><label class="discrepancy-modal-field">Explain the corrective action<textarea name="resolution_explanation" required rows="4" data-resolution-explanation placeholder="Describe what was corrected and how it was verified."></textarea></label></section><section class="discrepancy-modal-section"><div class="discrepancy-modal-section-title"><h3>Supporting Evidence <span>(Optional)</span></h3></div><label class="discrepancy-upload-zone" data-resolution-dropzone><input type="file" name="resolution_evidence" accept=".pdf,.jpg,.jpeg,.png" class="sr-only" data-resolution-file><strong>Drop a file here or browse</strong><span>PDF, JPG, or PNG · max 10 MB</span></label><p data-resolution-file-preview hidden class="mt-2 rounded bg-slate-50 p-2 text-xs text-slate-700"></p><button type="button" data-resolution-file-remove hidden class="text-xs font-semibold text-rose-700">Remove evidence</button></section></div>
            <footer class="bank-modal-footer discrepancy-modal-footer"><button type="button" data-dialog-close="bank-discrepancy-resolve-modal" class="secondary-button">Cancel</button><button type="submit" class="primary-button">Save Resolution</button></footer>
        </form>
    </dialog>
    @endif
    @if($activePeriod?->isDraft())
    <dialog id="unmatched-system-record-modal" class="bank-modal">
        <form method="POST" action="{{ route('bank-reconciliation.discrepancies.store', $activePeriod) }}" enctype="multipart/form-data" class="bank-modal-shell" data-unmatched-record-form data-create-action="{{ route('bank-reconciliation.discrepancies.store', $activePeriod) }}" data-discrepancy-context="all">
            @csrf
            <input type="hidden" name="_method" data-unmatched-method value="POST">
            <header class="bank-modal-header discrepancy-modal-header"><div class="flex min-w-0 items-start gap-3"><span class="discrepancy-modal-icon discrepancy-modal-icon--header"><x-icon name="document" class="h-6 w-6" /></span><div class="min-w-0"><p class="discrepancy-modal-kicker">Outstanding System Record</p><h2>Report Unmatched Record</h2><p class="mt-1 text-sm font-normal text-slate-500">You are documenting why no matching LANDBANK transaction is available.</p></div></div><button type="button" data-dialog-close="unmatched-system-record-modal" aria-label="Close" class="discrepancy-modal-close">&times;</button></header>
            <div class="bank-modal-body discrepancy-modal-body">
                <section class="discrepancy-modal-summary" aria-label="Selected record summary"><span class="discrepancy-modal-icon"><x-icon name="banknotes" class="h-5 w-5" /></span><div class="min-w-0"><strong data-unmatched-record-label>Selected system record</strong><span data-unmatched-record-amount>Amount: PHP 0.00</span><span class="discrepancy-modal-note"><x-icon name="alert-circle" class="h-3.5 w-3.5" />No bank transaction will be created or cleared by this action.</span></div></section>
                <section class="discrepancy-modal-section"><div class="discrepancy-modal-section-title"><span class="discrepancy-modal-icon"><x-icon name="document" class="h-4 w-4" /></span><h3>Discrepancy Details</h3></div><label class="discrepancy-modal-field">Discrepancy Type<select name="type" data-unmatched-type required><option value="">Select a reason</option><option value="bank_charge" data-description="A fee or service charge deducted by the bank.">Bank Charge</option><option value="deposit_in_transit" data-description="A recorded deposit that has not appeared in the bank yet.">Deposit in Transit</option><option value="cash_not_deposited" data-description="Cash recorded in the system that has not been deposited yet.">Cash Not Yet Deposited</option><option value="outstanding_check" data-description="A recorded payment that has not cleared the bank yet.">Outstanding Check</option><option value="recording_error" data-description="The bank or system record may contain an incorrect amount or detail.">Recording Error</option><option value="unidentified_transaction" data-description="A bank transaction that cannot yet be linked to a system record.">Unidentified Transaction</option><option value="other" data-description="A different reason that you will describe below.">Others (Please Specify)</option></select><span data-unmatched-reason-help class="discrepancy-modal-help">Choose the reason that best describes why the record has no valid match.</span><span class="hidden discrepancy-custom-type-wrap" data-custom-type-wrap><span class="discrepancy-custom-type-label">Specify Discrepancy Type</span><input name="custom_type" data-custom-type placeholder="e.g. Duplicate bank posting"><span class="discrepancy-modal-help">Use a short, clear name for the reason.</span></span></label></section>
                <input type="hidden" name="classification" data-unmatched-classification value="timing_difference">
                <input type="hidden" name="amount" data-unmatched-amount value="">
                <input type="hidden" name="resolution_status" value="unresolved">
                <input type="hidden" name="carry_forward" data-unmatched-carry-forward value="1">
                <input type="hidden" name="bank_ledger_entry_id" data-unmatched-bank-entry>
                <input type="hidden" name="system_record_type" data-unmatched-system-type>
                <input type="hidden" name="system_record_id" data-unmatched-system-id>
                <section class="discrepancy-modal-section"><div class="discrepancy-modal-section-title"><span class="discrepancy-modal-icon"><x-icon name="edit" class="h-4 w-4" /></span><h3>Explanation</h3></div><label class="discrepancy-modal-field"><textarea name="explanation" required rows="4" placeholder="Describe what happened, what should be checked, and the next review step..."></textarea><span class="discrepancy-modal-help">Include what happened, what should be checked, and the next review step (e.g., verify with the donor, check supporting documents, etc.).</span></label></section>
                <section class="discrepancy-modal-section" data-evidence-uploader><div class="discrepancy-modal-section-title"><span class="discrepancy-modal-icon"><x-icon name="upload" class="h-4 w-4" /></span><h3>Supporting Evidence <span>(Optional)</span></h3></div><input type="file" name="supporting_evidence" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="sr-only" data-evidence-input><button type="button" class="discrepancy-upload-zone" data-evidence-dropzone><x-icon name="upload" class="h-7 w-7" /><strong>Drag and drop a file here, or <u>browse</u> to upload</strong><span>Allowed file types: PDF, JPG, PNG.</span></button><p class="hidden discrepancy-upload-error" data-evidence-error role="alert"></p><div class="hidden discrepancy-upload-list" data-evidence-list></div><label class="discrepancy-modal-field discrepancy-evidence-reference">Evidence reference <span>(Optional)</span><input name="evidence_reference" placeholder="Optional bank reference or document name"></label></section>
                <section class="discrepancy-next-step"><span class="discrepancy-modal-icon"><x-icon name="alert-circle" class="h-4 w-4" /></span><div><h3>What happens next</h3><ul><li>The record will be flagged as a discrepancy in your bank reconciliation.</li><li>No bank transaction will be created or cleared.</li><li>It will be routed for further review based on your explanation and any supporting evidence.</li></ul></div></section>
            </div>
            <footer class="bank-modal-footer discrepancy-modal-footer"><button type="button" data-dialog-close="unmatched-system-record-modal" class="secondary-button discrepancy-cancel">Cancel</button><button type="submit" data-unmatched-submit class="primary-button discrepancy-submit">Report Discrepancy</button></footer>
        </form>
    </dialog>
    @endif
    @can('import', \App\Models\BankLedgerEntry::class)
    <dialog id="bank-import-modal" class="bank-modal"><form method="POST" action="{{ route('bank-reconciliation.import') }}" enctype="multipart/form-data" class="bank-modal-shell">@csrf<header class="bank-modal-header"><div><p class="text-xs font-semibold uppercase tracking-wide text-blue-700">LANDBANK statement</p><h2>Upload bank statement</h2></div><button type="button" data-dialog-close="bank-import-modal">&times;</button></header><div class="bank-modal-body space-y-4"><div class="grid gap-3 sm:grid-cols-2"><label class="space-y-1 text-sm font-semibold">Reconciliation month<select name="period_id" class="mt-1 w-full rounded border-slate-300 text-sm">@forelse($reconciliationPeriods as $periodOption)<option value="{{ $periodOption->id }}" @selected($activePeriod?->id === $periodOption->id)>{{ $periodOption->period_start?->format('M Y') }} · {{ str_replace('_', ' ', $periodOption->status) }} · {{ $periodOption->bank_account ?: 'Default account' }}</option>@empty<option value="">Create a reconciliation period first</option>@endforelse</select><span class="block text-xs font-normal text-slate-500">Choose a draft period to validate transaction dates.</span></label><label class="space-y-1 text-sm font-semibold">Bank account<select name="bank_account" class="mt-1 w-full rounded border-slate-300 text-sm">@foreach($bankAccounts as $account)<option value="{{ $account }}" @selected($activePeriod?->bank_account === $account)>{{ $account }}</option>@endforeach</select></label></div><p class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900">Upload the original LANDBANK CSV or XLSX export. PDF files are not accepted unless a validated transaction extractor is added.</p><div data-bank-dropzone role="button" tabindex="0" class="rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 p-8 text-center transition hover:border-blue-500 hover:bg-blue-50"><input type="file" name="statement" accept=".csv,.xlsx" required data-bank-file class="sr-only"><p class="font-semibold text-slate-800">Drop statement here or browse files</p><p class="mt-1 text-xs text-slate-500">CSV or XLSX · max 10 MB</p></div><p data-bank-file-info hidden class="rounded border p-3 text-sm"><span data-bank-file-name></span><button type="button" data-bank-remove class="float-right text-rose-700">Remove</button></p><p data-bank-error hidden class="rounded bg-rose-50 p-3 text-sm text-rose-700"></p><p data-bank-preview hidden data-bank-preview-summary class="rounded bg-slate-50 p-3 text-sm"></p></div><footer class="bank-modal-footer"><button type="button" data-dialog-close="bank-import-modal" class="secondary-button">Cancel</button><button type="submit" data-bank-submit disabled class="primary-button">Validate and import</button></footer></form></dialog>
    @endcan

    <dialog id="new-bank-period-modal" class="bank-modal"><form method="POST" action="{{ route('bank-reconciliation.periods.store') }}" class="bank-modal-shell" data-new-period-form>@csrf<header class="bank-modal-header"><div class="flex items-start gap-3"><span class="period-modal-icon" aria-hidden="true">⌂</span><div><h2>New Reconciliation Period</h2><p class="mt-1 text-sm font-normal text-slate-500">Set up the month, account, and balances for reconciliation.</p></div></div><button type="button" data-dialog-close="new-bank-period-modal" aria-label="Close">&times;</button></header><div class="bank-modal-body space-y-4"><div class="period-alert period-alert-info"><span class="period-alert-icon" aria-hidden="true">ⓘ</span><p><strong>Draft periods can be resumed later.</strong> You can add the ending balance once it is available from the LANDBANK statement.</p></div><p data-period-state-message hidden class="period-alert period-alert-warning"><span class="period-alert-icon" aria-hidden="true">⚠</span><span></span></p><section class="period-modal-section"><div class="period-section-heading"><span class="period-section-icon" aria-hidden="true">▣</span><h3>Period Details</h3></div><div class="grid gap-4 p-4 sm:grid-cols-2"><label class="space-y-1 text-sm font-semibold text-slate-800">Month<select name="period_month" required data-period-month class="mt-1 h-11 w-full rounded-lg border-slate-300 bg-white text-sm"><option value="">Select month</option>@for($month = 1; $month <= 12; $month++)<option value="{{ $month }}" @selected($month === now()->month)>{{ now()->setMonth($month)->format('F') }}</option>@endfor</select>@error('period_month')<span class="block text-xs font-normal text-rose-700">{{ $message }}</span>@enderror</label><label class="space-y-1 text-sm font-semibold text-slate-800">Year<select name="period_year" required data-period-year class="mt-1 h-11 w-full rounded-lg border-slate-300 bg-white text-sm"><option value="">Select year</option>@for($year = now()->year - 1; $year <= now()->year + 1; $year++)<option value="{{ $year }}" @selected($year === now()->year)>{{ $year }}</option>@endfor</select>@error('period_year')<span class="block text-xs font-normal text-rose-700">{{ $message }}</span>@enderror</label></div></section><section class="period-modal-section"><div class="period-section-heading"><span class="period-section-icon" aria-hidden="true">⌂</span><h3>Bank Account</h3></div><div class="p-4"><label class="block space-y-1 text-sm font-semibold text-slate-800">Bank Account<select name="bank_account" required data-period-account class="mt-1 h-11 w-full rounded-lg border-slate-300 bg-white text-sm">@foreach($bankAccounts as $account)<option value="{{ $account }}">{{ $account }}</option>@endforeach</select>@error('bank_account')<span class="block text-xs font-normal text-rose-700">{{ $message }}</span>@enderror</label></div></section><section class="period-modal-section"><div class="period-section-heading"><span class="period-section-icon" aria-hidden="true">◎</span><h3>Balances</h3></div><div class="grid gap-4 p-4 sm:grid-cols-2"><label class="space-y-1 text-sm font-semibold text-slate-800">Starting Bank Balance (₱)<span class="period-money-input mt-1"><b>₱</b><input name="opening_balance" type="number" min="0" step="0.01" required data-opening-balance value="{{ old('opening_balance') }}" placeholder="0.00" class="h-11 w-full border-0 bg-transparent text-sm focus:ring-0"></span><span data-opening-help class="block text-xs font-normal text-slate-500">Use the actual LANDBANK statement or verified bank records for this amount.</span>@error('opening_balance')<span class="block text-xs font-normal text-rose-700">{{ $message }}</span>@enderror</label><label class="space-y-1 text-sm font-semibold text-slate-800">Ending Bank Balance (₱)<span class="period-money-input mt-1"><b>₱</b><input name="bank_ending_balance" type="number" min="0" step="0.01" value="{{ old('bank_ending_balance') }}" placeholder="Optional while Draft" class="h-11 w-full border-0 bg-transparent text-sm focus:ring-0"></span><span class="block text-xs font-normal text-slate-500">Optional while Draft. Enter it before submitting for review.</span>@error('bank_ending_balance')<span class="block text-xs font-normal text-rose-700">{{ $message }}</span>@enderror</label><label data-opening-explanation-wrap class="hidden space-y-1 text-sm font-semibold text-slate-800 sm:col-span-2">Explanation for Starting Balance (First-time Setup)<textarea name="opening_balance_explanation" rows="3" placeholder="Example: Opening balance from the first LANDBANK statement." class="mt-1 w-full rounded-lg border-slate-300 text-sm">{{ old('opening_balance_explanation') }}</textarea><span class="block text-xs font-normal text-slate-500">Required for first-time setup when no confirmed previous period exists.</span>@error('opening_balance_explanation')<span class="block text-xs font-normal text-rose-700">{{ $message }}</span>@enderror</label>@error('period_start')<p class="text-sm font-medium text-rose-700 sm:col-span-2">{{ $message }}</p>@enderror</div></section><section class="period-next"><span class="period-section-icon" aria-hidden="true">⚑</span><div><h3>What happens next</h3><ul><li>A draft period is created for the selected month and account.</li><li>You can match and reconcile transactions later.</li><li>You can add the ending balance before submitting for review.</li></ul></div></section></div><footer class="bank-modal-footer"><button type="button" data-dialog-close="new-bank-period-modal" class="secondary-button">Cancel</button><button type="submit" data-period-create-submit class="primary-button">Create Period</button></footer></form></dialog>

    <dialog id="bank-period-modal" class="bank-modal"><div class="bank-modal-shell"><header class="bank-modal-header"><h2>Manage Reconciliation Period</h2><button type="button" data-dialog-close="bank-period-modal">&times;</button></header><div class="bank-modal-body space-y-4">@can('prepare', \App\Models\BankReconciliation::class)<form method="POST" action="{{ route('bank-reconciliation.periods.store') }}" class="space-y-3">@csrf<input name="period_start" type="date" required aria-label="Period start" class="w-full rounded border"><input name="period_end" type="date" required aria-label="Period end" class="w-full rounded border"><input name="opening_balance" type="number" step="0.01" required placeholder="Opening balance" class="w-full rounded border"><input name="bank_ending_balance" type="number" step="0.01" placeholder="Bank ending balance" class="w-full rounded border"><x-button>Create Period</x-button></form>@endcan @if($latestReconciliationPeriod)<div class="rounded border p-4"><p class="font-semibold">{{ $latestReconciliationPeriod->period_start?->format('M d, Y') }} – {{ $latestReconciliationPeriod->period_end?->format('M d, Y') }}</p><p class="mt-2 text-sm">Status: {{ str_replace('_', ' ', $latestReconciliationPeriod->status) }}</p>@can('submit', $latestReconciliationPeriod)<form method="POST" action="{{ route('bank-reconciliation.periods.submit', $latestReconciliationPeriod) }}" class="mt-3">@csrf<x-button>Submit for Review</x-button></form>@endcan @can('approve', $latestReconciliationPeriod)<form method="POST" action="{{ route('bank-reconciliation.periods.approve', $latestReconciliationPeriod) }}" class="mt-2">@csrf<x-button>Approve &amp; Lock</x-button></form>@endcan</div>@else<p class="rounded border border-dashed p-8 text-center text-sm text-slate-500">No reconciliation period has been created yet.</p>@endif</div><footer class="bank-modal-footer"><button type="button" data-dialog-close="bank-period-modal" class="secondary-button">Close</button></footer></div></dialog>
    <dialog id="bank-history-modal" class="bank-modal"><div class="bank-modal-shell"><header class="bank-modal-header"><h2>Reconciliation History</h2><button type="button" data-dialog-close="bank-history-modal">&times;</button></header><div class="bank-modal-body"><input data-history-search aria-label="Search history" placeholder="Search reference or description" class="mb-4 w-full rounded border"><div class="overflow-auto"><x-data-table caption="Reconciled transaction history" min-width="32rem">
<x-slot:head><x-data-table.heading>Date</x-data-table.heading><x-data-table.heading>Reference</x-data-table.heading><x-data-table.heading align="right">Amount</x-data-table.heading></x-slot:head>
@forelse($history->concat($withdrawalHistory)->unique('id') as $entry)<tr data-history-row data-history-search="{{ strtolower($entry->description.' '.$entry->reference_number) }}"><td class="px-3 py-3">{{ $entry->transaction_date?->format('M d, Y') }}</td><td class="px-3 py-3">{{ $entry->reference_number ?: '-' }}</td><td class="px-3 py-3">PHP {{ number_format((float) $entry->amount, 2) }}</td></tr>@empty<x-data-table.empty :columns="3" title="No reconciled transactions yet." />@endforelse
</x-data-table></div></div><footer class="bank-modal-footer"><button type="button" data-dialog-close="bank-history-modal" class="secondary-button">Close</button></footer></div></dialog>
    @foreach($bankTransactions as $entry)
    <dialog id="bank-entry-{{ $entry->id }}" class="bank-modal"><div class="bank-modal-shell"><header class="bank-modal-header"><div><p class="text-xs font-semibold uppercase tracking-wide text-blue-600">Transaction details</p><h2 class="mt-1">{{ $entry->description }}</h2><p class="mt-1 text-sm text-slate-500">{{ $entry->reference_number ?: 'No reference' }}</p></div><button type="button" data-dialog-close="bank-entry-{{ $entry->id }}">&times;</button></header><div class="bank-modal-body"><section aria-label="Transaction information"><dl class="grid gap-4 text-sm sm:grid-cols-2"><div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Date</dt><dd class="mt-1 font-semibold text-slate-900">{{ $entry->transaction_date?->format('M d, Y') }}</dd></div><div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Amount</dt><dd class="mt-1 font-semibold text-slate-900">PHP {{ number_format((float) $entry->amount, 2) }}</dd></div><div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Statement ID</dt><dd class="mt-1 text-slate-700">{{ $entry->statement_id }}</dd></div><div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt><dd class="mt-1 capitalize text-slate-700">{{ str_replace('_', ' ', $entry->status) }}</dd></div></dl></section><p class="mt-5 rounded-lg bg-slate-50 p-3 text-sm text-slate-600">This view is read-only. Confirm matches from the two side-by-side matching tables.</p></div><footer class="bank-modal-footer">@if($activePeriod?->isDraft() && $entry->status !== 'reconciled')<button type="button" data-go-to-matching data-bank-id="{{ $entry->id }}" data-bank-type="{{ $entry->transaction_type }}" class="secondary-button">Go to Matching</button><button type="button" data-report-bank data-bank-id="{{ $entry->id }}" data-bank-amount="{{ (float) $entry->amount }}" data-bank-type="{{ $entry->transaction_type }}" data-bank-description="{{ $entry->description }}" data-bank-reference="{{ $entry->reference_number }}" class="primary-button bg-amber-700 hover:bg-amber-800">Report Discrepancy</button>@endif<button type="button" data-dialog-close="bank-entry-{{ $entry->id }}" class="secondary-button">Close</button></footer></div></dialog>
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
<style>.bank-modal{width:min(100% - 1.5rem,48rem);max-width:none;padding:0;border:0;background:transparent}.bank-modal::backdrop{background:rgb(15 23 42/.6)}.bank-modal-shell{display:flex;max-height:90vh;flex-direction:column;overflow:hidden;border-radius:1rem;background:#fff}.bank-modal-header{display:flex;flex-shrink:0;justify-content:space-between;border-bottom:1px solid #e2e8f0;padding:1.25rem}.bank-modal-body{min-height:0;flex:1;overflow-y:auto;padding:1.25rem}.bank-modal-footer{display:flex;flex-shrink:0;justify-content:flex-end;gap:.5rem;border-top:1px solid #e2e8f0;padding:1rem 1.25rem}.primary-button,.secondary-button{border-radius:.5rem;padding:.625rem 1rem;font-size:.875rem;font-weight:600}.primary-button{background:#1d4ed8;color:#fff}.secondary-button{border:1px solid #cbd5e1}.bank-modal h2{font-size:1.125rem;font-weight:700}.bank-modal-body > p.mt-5{display:none}.bank-modal [data-open-period-after-close]{display:inline-flex;align-items:center;border-radius:.5rem;background:#fff7ed;padding:.625rem .875rem;font-size:.875rem;font-weight:600;color:#b45309;box-shadow:inset 0 0 0 1px #fed7aa}.bank-modal [data-open-period-after-close]:hover{background:#ffedd5}.bank-table-scroll{height:min(58vh,36rem);min-height:18rem;flex:none;overflow-y:auto;overflow-x:auto}.bank-table-scroll thead th{position:sticky;top:0;z-index:10;background:#f8fafc;box-shadow:inset 0 -1px 0 #cbd5e1}.bank-table-footer{position:sticky;bottom:0}.bank-summary-card[aria-pressed="true"]{box-shadow:0 0 0 2px currentColor,0 6px 16px rgb(15 23 42/.1)}.bank-board-scroll{max-height:25rem;min-height:12rem;overflow:auto}.bank-board-scroll tbody tr{cursor:pointer;transition:background-color 120ms ease}.bank-board-scroll tbody tr:hover{background:#eff6ff}[data-board-tab][aria-selected="true"]{background:#2563eb;color:#fff;box-shadow:0 1px 2px rgb(15 23 42/.12)}[data-board-tab][aria-selected="false"]{background:#fff;color:#475569}[data-period-selector],[data-bank-type],[data-bank-status]{padding-right:2.5rem}@media (max-width:640px){.bank-table-scroll{height:min(58vh,30rem);min-height:14rem}.bank-board-scroll{max-height:20rem}.bank-table-footer{align-items:flex-start;flex-direction:column}.bank-table-footer>span:last-child{width:100%;justify-content:space-between}.bank-summary-card{padding:.875rem}.bank-modal-header,.bank-modal-body,.bank-modal-footer{padding:1rem}}</style>
<style>
.discrepancy-modal-header{align-items:flex-start;background:#fff;padding:1.15rem 1.25rem}.discrepancy-modal-kicker{display:inline-flex;border-radius:.35rem;background:#dbeafe;padding:.18rem .5rem;color:#075985;font-size:.68rem;font-weight:800;letter-spacing:.01em}.discrepancy-modal-close{color:#475569;font-size:1.5rem;line-height:1}.discrepancy-modal-close:hover{color:#0f172a}.discrepancy-modal-body{display:flex;flex-direction:column;gap:.65rem;background:#fff;padding:1rem 1.25rem 1.2rem}.discrepancy-modal-icon{display:inline-flex;flex:none;align-items:center;justify-content:center;border-radius:.65rem;background:#dbeafe;color:#2563eb}.discrepancy-modal-icon--header{height:2.75rem;width:2.75rem;border-radius:.75rem}.discrepancy-modal-summary{display:flex;align-items:flex-start;gap:.75rem;border:1px solid #93c5fd;border-radius:.65rem;background:#eff6ff;padding:.75rem .85rem;color:#1e3a8a}.discrepancy-modal-summary>div{display:flex;min-width:0;flex-direction:column;gap:.15rem}.discrepancy-modal-summary strong{overflow:hidden;color:#172554;font-size:.78rem;line-height:1.35;text-overflow:ellipsis;white-space:nowrap}.discrepancy-modal-summary [data-unmatched-record-amount]{font-size:.76rem;font-weight:600}.discrepancy-modal-note{display:flex;align-items:center;gap:.3rem;width:max-content;max-width:100%;border:1px solid #bfdbfe;border-radius:.35rem;background:#fff;padding:.25rem .45rem;color:#1e40af;font-size:.64rem}.discrepancy-modal-section{border:1px solid #dbe4f0;border-radius:.65rem;background:#f8fafc;padding:.7rem .8rem}.discrepancy-modal-section-title{display:flex;align-items:center;gap:.55rem;margin-bottom:.55rem}.discrepancy-modal-section-title h3{color:#172033;font-size:.82rem;font-weight:800}.discrepancy-modal-section-title h3 span{color:#64748b;font-weight:500}.discrepancy-modal-field{display:block;color:#172033;font-size:.68rem;font-weight:800}.discrepancy-modal-field select,.discrepancy-modal-field textarea,.discrepancy-modal-field input{display:block;width:100%;margin-top:.35rem;border:1px solid #b9c5d5;border-radius:.45rem;background:#fff;padding:.55rem .65rem;color:#172033;font-size:.75rem;box-shadow:0 1px 2px rgb(15 23 42/.03)}.discrepancy-modal-field select{padding-right:2rem}.discrepancy-modal-field textarea{min-height:4.2rem;resize:vertical}.discrepancy-modal-field select:focus,.discrepancy-modal-field textarea:focus,.discrepancy-modal-field input:focus{border-color:#60a5fa;outline:2px solid #bfdbfe;outline-offset:0}.discrepancy-modal-help{display:block;margin-top:.25rem;color:#64748b;font-size:.62rem;font-weight:500;line-height:1.4}.discrepancy-upload-zone{display:flex;min-height:4.4rem;width:100%;flex-direction:column;align-items:center;justify-content:center;gap:.1rem;border:1.5px dashed #60a5fa;border-radius:.55rem;background:#eff6ff;padding:.55rem;text-align:center;color:#172033;transition:background .15s,border-color .15s}.discrepancy-upload-zone:hover,.discrepancy-upload-zone.border-blue-600{border-color:#2563eb;background:#dbeafe}.discrepancy-upload-zone svg{color:#2563eb}.discrepancy-upload-zone strong{font-size:.72rem}.discrepancy-upload-zone span{color:#64748b;font-size:.62rem}.discrepancy-upload-error{margin-top:.4rem;border-radius:.4rem;background:#fff1f2;padding:.4rem .55rem;color:#be123c;font-size:.65rem;font-weight:600}.discrepancy-upload-list{margin-top:.45rem}.discrepancy-evidence-reference{margin-top:.6rem;color:#64748b;font-size:.65rem}.discrepancy-evidence-reference input{font-weight:500}.discrepancy-next-step{display:flex;align-items:flex-start;gap:.6rem;border:1px solid #bfdbfe;border-radius:.65rem;background:#eff6ff;padding:.7rem .8rem;color:#1e3a8a}.discrepancy-next-step h3{font-size:.75rem;font-weight:800}.discrepancy-next-step ul{margin-top:.25rem;padding-left:1rem;font-size:.65rem;line-height:1.45;list-style:disc}.discrepancy-modal-footer{background:#fff;padding:.8rem 1.25rem}.discrepancy-cancel{background:#fff;color:#334155}.discrepancy-submit{background:#1d4ed8;color:#fff;box-shadow:0 3px 8px rgb(29 78 216/.2)}.discrepancy-submit:hover{background:#1e40af}.discrepancy-modal-footer .secondary-button,.discrepancy-modal-footer .primary-button{min-width:7.5rem}@media (max-width:640px){.discrepancy-modal-header,.discrepancy-modal-body,.discrepancy-modal-footer{padding-left:.9rem;padding-right:.9rem}.discrepancy-modal-note{width:auto}.discrepancy-modal-summary strong{white-space:normal}.discrepancy-modal-footer{justify-content:stretch}.discrepancy-modal-footer button{flex:1}}
</style>
<style>.discrepancy-custom-type-wrap{display:block;margin-top:.6rem}.discrepancy-custom-type-wrap.hidden{display:none}.discrepancy-custom-type-label{display:block;color:#172033;font-size:.68rem;font-weight:800}.discrepancy-custom-type-label::after{content:' *';color:#dc2626}.discrepancy-custom-type-wrap input{display:block;width:100%;margin-top:.35rem;border:1px solid #b9c5d5;border-radius:.45rem;background:#fff;padding:.55rem .65rem;color:#172033;font-size:.75rem}.discrepancy-custom-type-wrap input:focus{border-color:#60a5fa;outline:2px solid #bfdbfe;outline-offset:0}</style>
<style>.period-step{background:#fff;color:#475569}.period-step[aria-selected="true"]{border-color:#2563eb;background:#eff6ff;color:#1d4ed8;box-shadow:0 0 0 1px #2563eb}.period-step-status{font-size:.68rem;color:#64748b}.period-step[aria-selected="true"] .period-step-status{color:#1d4ed8}.bank-modal-footer{flex-wrap:wrap}.bank-modal-footer form{display:flex;align-items:center;gap:.5rem}.bank-modal-footer form:first-child{margin-right:auto}</style>
<script>
const bankRows=[...document.querySelectorAll('[data-bank-row]')],bankSize=10;let bankPage=1;const bankRefresh=()=>{const q=document.querySelector('[data-bank-search]')?.value.toLowerCase()||'',t=document.querySelector('[data-bank-type]')?.value||'all',s=document.querySelector('[data-bank-status]')?.value||'all',filtered=bankRows.filter(r=>(!q||r.dataset.search.includes(q))&&(t==='all'||r.dataset.type===t)&&(s==='needs_action'?['unmatched','unreconciled'].includes(r.dataset.status):(s==='all'||r.dataset.status===s))),pages=Math.max(1,Math.ceil(filtered.length/bankSize));bankPage=Math.min(bankPage,pages);bankRows.forEach(r=>r.hidden=true);filtered.slice((bankPage-1)*bankSize,bankPage*bankSize).forEach(r=>r.hidden=false);document.querySelector('[data-bank-summary]').textContent=filtered.length?'Showing '+((bankPage-1)*bankSize+1)+'–'+Math.min(bankPage*bankSize,filtered.length)+' of '+filtered.length:'No transactions found';document.querySelector('[data-bank-prev]').disabled=bankPage===1;document.querySelector('[data-bank-next]').disabled=bankPage===pages;const pagesEl=document.querySelector('[data-bank-pages]');pagesEl.replaceChildren(...Array.from({length:pages},(_,i)=>{const b=document.createElement('button');b.type='button';b.textContent=i+1;b.className='rounded border px-2.5 py-1.5';b.addEventListener('click',()=>{bankPage=i+1;bankRefresh()});return b}))};document.querySelectorAll('[data-bank-search],[data-bank-type],[data-bank-status]').forEach(e=>e.addEventListener('input',()=>{bankPage=1;bankRefresh()}));document.querySelector('[data-bank-prev]')?.addEventListener('click',()=>{bankPage--;bankRefresh()});document.querySelector('[data-bank-next]')?.addEventListener('click',()=>{bankPage++;bankRefresh()});bankRows.forEach(r=>r.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();document.getElementById(r.dataset.dialogOpen)?.showModal()}}));bankRefresh();document.querySelector('[data-history-search]')?.addEventListener('input',e=>{const q=e.target.value.toLowerCase();document.querySelectorAll('[data-history-row]').forEach(r=>r.hidden=!r.dataset.historySearch.includes(q))});const file=document.querySelector('[data-bank-file]'),drop=document.querySelector('[data-bank-dropzone]'),preview=document.querySelector('[data-bank-preview]'),submit=document.querySelector('[data-bank-submit]'),error=document.querySelector('[data-bank-error]');drop?.addEventListener('click',()=>file.click());file?.addEventListener('change',()=>{if(!file.files[0])return;document.querySelector('[data-bank-file-name]').textContent=file.files[0].name;document.querySelector('[data-bank-file-info]').hidden=false;document.querySelector('[data-bank-preview-summary]').textContent='File selected and ready for server validation.';preview.hidden=false;submit.disabled=false});document.querySelector('[data-bank-remove]')?.addEventListener('click',()=>{file.value='';document.querySelector('[data-bank-file-info]').hidden=true;preview.hidden=true;submit.disabled=true});
bankRows.forEach((row) => { if (row.dataset.status === 'unreconciled') row.dataset.status = 'unmatched'; });
const bankDiscrepancyData = @json($bankDiscrepancyData);
const discrepancyStatusFor = (report) => !report ? 'No Report' : report.resolution_status === 'Resolved' ? 'Resolved' : report.decision === 'Accepted' ? 'Accepted' : report.decision === 'Correction Required' ? 'Correction Required' : 'Pending Review';
bankRows.forEach((row) => {
    const bankId = row.dataset.bankId || row.dataset.dialogOpen?.replace('bank-entry-', '');
    const report = bankDiscrepancyData[bankId];
    const cell = document.createElement('td'); cell.className = 'px-4 py-3';
    const status = discrepancyStatusFor(report);
    if (!report) { cell.innerHTML = row.dataset.status === 'reconciled' ? '<span class="text-xs text-slate-500">—</span>' : '<span class="text-xs text-slate-500">No Report</span>'; }
    else {
        const button = document.createElement('button'); button.type = 'button'; button.textContent = status; button.dataset.bankDiscrepancyDetails = 'true'; button.className = `rounded-full px-2 py-1 text-xs font-semibold ${status === 'Resolved' || status === 'Accepted' ? 'bg-emerald-100 text-emerald-800' : status === 'Correction Required' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800'}`;
        button.addEventListener('click', (event) => { event.stopPropagation(); window.openDiscrepancyDetails?.(report); }); cell.append(button);
    }
    row.append(cell);
});
document.querySelector('[data-bank-status]')?.dispatchEvent(new Event('input', { bubbles: true }));
document.querySelectorAll('[data-bank-filter-card]').forEach((card) => card.addEventListener('click', () => { const type = card.dataset.bankFilterCard; const select = document.querySelector('[data-bank-type]'); if (select) { select.value = select.value === type ? 'all' : type; select.dispatchEvent(new Event('input', { bubbles: true })); } document.querySelectorAll('[data-bank-filter-card]').forEach((item) => item.setAttribute('aria-pressed', item === card && select?.value === type ? 'true' : 'false')); document.querySelector('[data-bank-all-filter]').classList.toggle('hidden', select?.value === 'all'); }));
document.querySelector('[data-bank-all-filter]')?.addEventListener('click', () => { const select = document.querySelector('[data-bank-type]'); if (select) { select.value = 'all'; select.dispatchEvent(new Event('input', { bubbles: true })); } document.querySelectorAll('[data-bank-filter-card]').forEach((item) => item.setAttribute('aria-pressed', 'false')); document.querySelector('[data-bank-all-filter]').classList.add('hidden'); });
document.querySelector('[data-bank-type]')?.addEventListener('input', (event) => { document.querySelectorAll('[data-bank-filter-card]').forEach((item) => item.setAttribute('aria-pressed', item.dataset.bankFilterCard === event.target.value ? 'true' : 'false')); document.querySelector('[data-bank-all-filter]').classList.toggle('hidden', event.target.value === 'all'); });
document.querySelector('[data-period-selector]')?.addEventListener('change', (event) => { const url = new URL(window.location.href); if (event.target.value) url.searchParams.set('period_id', event.target.value); else url.searchParams.delete('period_id'); window.location.assign(url.toString()); });
const confirmedPeriodRows = @json($confirmedPeriodRows);
const reconciliationPeriodRows = @json($periodRows);
const newPeriodForm = document.querySelector('[data-new-period-form]');
if (newPeriodForm) {
    const month = newPeriodForm.querySelector('[data-period-month]');
    const year = newPeriodForm.querySelector('[data-period-year]');
    const account = newPeriodForm.querySelector('[data-period-account]');
    const opening = newPeriodForm.querySelector('[data-opening-balance]');
    const help = newPeriodForm.querySelector('[data-opening-help]');
    const explanationWrap = newPeriodForm.querySelector('[data-opening-explanation-wrap]');
    const explanation = newPeriodForm.querySelector('[name="opening_balance_explanation"]');
    const stateMessage = newPeriodForm.querySelector('[data-period-state-message]');
    const createButton = newPeriodForm.querySelector('[data-period-create-submit]');
    const accountLabel = newPeriodForm.querySelector('.period-modal-section:nth-of-type(2) label');
    const accountSelect = newPeriodForm.querySelector('[data-period-account]');
    if (accountLabel?.firstChild?.nodeType === Node.TEXT_NODE) accountLabel.firstChild.remove();
    accountSelect?.setAttribute('aria-label', 'Bank Account');
    const makeScrollableDropdown = (select) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'period-select-wrapper mt-1';
        select.parentNode.insertBefore(wrapper, select);
        wrapper.append(select);
        select.classList.add('period-select-native');
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'period-select-trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        const menu = document.createElement('div');
        menu.className = 'period-select-menu';
        menu.hidden = true;
        menu.setAttribute('role', 'listbox');
        const refresh = () => {
            const selected = select.options[select.selectedIndex];
            trigger.textContent = selected?.textContent || 'Select an option';
            menu.querySelectorAll('button').forEach((option) => option.setAttribute('aria-selected', option.dataset.value === select.value ? 'true' : 'false'));
        };
        [...select.options].forEach((option) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'period-select-option';
            button.dataset.value = option.value;
            button.textContent = option.textContent;
            button.setAttribute('role', 'option');
            button.addEventListener('click', () => {
                select.value = option.value;
                select.dispatchEvent(new Event('change', {bubbles: true}));
                menu.hidden = true;
                trigger.setAttribute('aria-expanded', 'false');
            });
            menu.append(button);
        });
        trigger.addEventListener('click', () => {
            menu.hidden = !menu.hidden;
            trigger.setAttribute('aria-expanded', menu.hidden ? 'false' : 'true');
            wrapper.closest('.period-modal-section')?.classList.toggle('period-dropdown-open', !menu.hidden);
        });
        select.addEventListener('change', refresh);
        wrapper.append(trigger, menu);
        refresh();
        document.addEventListener('click', (event) => {
            if (!wrapper.contains(event.target)) {
                menu.hidden = true;
                trigger.setAttribute('aria-expanded', 'false');
                wrapper.closest('.period-modal-section')?.classList.remove('period-dropdown-open');
            }
        });
    };
    [month, year].forEach(makeScrollableDropdown);
    const syncOpeningBalance = () => {
        const periodStart = month.value && year.value ? `${year.value}-${String(month.value).padStart(2, '0')}-01` : null;
        const previousDraft = reconciliationPeriodRows
            .filter((item) => item.status === 'draft' && item.bank_account === account.value && item.period_end && periodStart && item.period_end < periodStart)
            .sort((a, b) => b.period_end.localeCompare(a.period_end))[0];
        const previous = confirmedPeriodRows
            .filter((item) => item.bank_account === account.value && item.period_end && periodStart && item.period_end < periodStart)
            .sort((a, b) => b.period_end.localeCompare(a.period_end))[0];
        if (previousDraft) {
            opening.readOnly = false;
            opening.classList.remove('bg-slate-100', 'text-slate-600');
            help.textContent = 'You can enter an amount to review it, but finish the previous Draft period before creating this one.';
            explanationWrap.classList.add('hidden');
            explanation.required = false;
            stateMessage.querySelector('span:last-child').textContent = `Cannot create this period yet. The previous period ending ${previousDraft.period_end} is still Draft. Complete and submit it before starting a new period.`;
            stateMessage.hidden = false;
            createButton.disabled = false;
            return;
        }
        stateMessage.hidden = true;
        createButton.disabled = false;
        if (previous) {
            opening.value = Number(previous.ending_balance).toFixed(2);
            opening.readOnly = true;
            opening.classList.add('bg-slate-100', 'text-slate-600');
            help.textContent = `Auto-filled from the confirmed ${previous.period_end} period and locked for continuity.`;
            explanationWrap.classList.add('hidden');
            explanation.required = false;
        } else {
            opening.readOnly = false;
            opening.classList.remove('bg-slate-100', 'text-slate-600');
            help.textContent = 'Use the actual LANDBANK statement or verified bank records for this amount.';
            explanationWrap.classList.remove('hidden');
            explanation.required = true;
        }
    };
    [month, year, account].forEach((field) => field?.addEventListener('change', syncOpeningBalance));
    syncOpeningBalance();
    @if($errors->hasAny(['period_month', 'period_year', 'bank_account', 'period_start', 'period_end', 'opening_balance', 'opening_balance_explanation', 'bank_ending_balance']))
    document.getElementById('new-bank-period-modal')?.showModal();
    @endif
}
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
        document.getElementById('bank-period-modal')?.close();
        document.getElementById('bank-discrepancy-modal')?.showModal();
    });
}
const discrepancyTemplate = document.querySelector('#bank-discrepancy-template');
const discrepancyValidationFailed = @json($discrepancyValidationFailed);
if (discrepancyValidationFailed) {
    document.getElementById('bank-discrepancy-modal')?.showModal();
}
const discrepancyModal = document.getElementById('bank-discrepancy-modal');
const discrepancyReports = [...(discrepancyModal?.querySelectorAll('[data-discrepancy-list] article') || [])];
let discrepancyStatusFilter = 'all';
const normalizeReviewStatus = (value) => value.trim().toLowerCase().replace(/\s+/g, '_');
discrepancyReports.forEach((report, index) => {
    const statusBadge = report.querySelectorAll('.rounded-full')[1];
    const rawStatus = normalizeReviewStatus(statusBadge?.textContent || 'pending');
    const status = rawStatus === 'correction_required' ? 'correction_requested' : rawStatus === 'accepted' ? 'accepted' : 'pending';
    report.dataset.discrepancyReviewStatus = status;
    if (statusBadge) statusBadge.textContent = status === 'accepted' ? 'Accepted' : status === 'correction_requested' ? 'Correction Required' : 'Pending Review';
    report.dataset.discrepancyReportIndex = String(index);
});
const applyDiscrepancyFilters = () => {
    const query = (discrepancyModal?.querySelector('[data-discrepancy-search]')?.value || '').trim().toLowerCase();
    discrepancyReports.forEach((report) => {
        const matchesStatus = discrepancyStatusFilter === 'all' || report.dataset.discrepancyReviewStatus === discrepancyStatusFilter;
        const matchesSearch = !query || (report.textContent || '').toLowerCase().includes(query);
        report.hidden = !matchesStatus || !matchesSearch;
    });
};
discrepancyModal?.querySelectorAll('[data-discrepancy-filter]').forEach((button) => button.addEventListener('click', () => {
    discrepancyStatusFilter = button.dataset.discrepancyFilter || 'all';
    discrepancyModal.querySelectorAll('[data-discrepancy-filter]').forEach((filterButton) => filterButton.setAttribute('aria-pressed', filterButton === button ? 'true' : 'false'));
    applyDiscrepancyFilters();
}));
discrepancyModal?.querySelector('[data-discrepancy-search]')?.addEventListener('input', applyDiscrepancyFilters);
const discrepancyDetailData = (() => { try { return JSON.parse(document.getElementById('discrepancy-report-data')?.textContent || '[]'); } catch { return []; } })();
const discrepancyTableRows = [];
if (discrepancyModal && discrepancyReports.length) {
    const table = document.createElement('table');
    table.className = 'w-full min-w-[42rem] text-left text-sm';
    table.innerHTML = '<thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-3 py-2">Discrepancy Type</th><th class="px-3 py-2">Amount</th><th class="px-3 py-2">Resolution Status</th><th class="px-3 py-2">Admin Decision</th><th class="px-3 py-2 text-right">Actions</th></tr></thead>';
    const body = document.createElement('tbody');
    discrepancyReports.forEach((report, index) => {
        const cells = report.querySelectorAll('.rounded-full');
        const row = document.createElement('tr');
        row.dataset.discrepancyDetails = 'true';
        row.dataset.discrepancyReportIndex = String(index);
        row.dataset.discrepancyReviewStatus = report.dataset.discrepancyReviewStatus;
        row.dataset.discrepancySearchText = (report.textContent || '').toLowerCase();
        row.className = 'cursor-pointer border-t border-slate-200 transition hover:bg-blue-50';
        row.innerHTML = `<td class="px-3 py-3 font-semibold text-slate-900">${discrepancyDetailData[index]?.type || 'Discrepancy'}</td><td class="px-3 py-3 font-semibold text-slate-900">${discrepancyDetailData[index]?.amount || '—'}</td><td class="px-3 py-3 capitalize text-slate-700">${cells[0]?.textContent || 'Unresolved'}</td><td class="px-3 py-3 font-semibold ${row.dataset.discrepancyReviewStatus === 'accepted' ? 'text-emerald-700' : row.dataset.discrepancyReviewStatus === 'correction_requested' ? 'text-rose-700' : 'text-amber-700'}">${cells[1]?.textContent || 'Pending Review'}</td><td class="px-3 py-3 text-right"></td>`;
        const actions = report.querySelector('.mt-2')?.cloneNode(true);
        const view = document.createElement('button');
        view.type = 'button'; view.textContent = 'View'; view.dataset.discrepancyDetails = 'true';
        view.dataset.discrepancyReportIndex = String(index);
        view.className = 'mr-2 rounded border border-blue-200 px-2 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-50';
        row.lastElementChild.append(view);
        if (actions) row.lastElementChild.append(actions);
        body.append(row);
        report.remove();
        discrepancyTableRows.push(row);
    });
    table.append(body);
    const list = discrepancyModal.querySelector('[data-discrepancy-list]');
    list?.append(table);
}
const applyDiscrepancyTableFilters = () => {
    const query = (discrepancyModal?.querySelector('[data-discrepancy-search]')?.value || '').trim().toLowerCase();
    discrepancyTableRows.forEach((row) => { row.hidden = !((discrepancyStatusFilter === 'all' || row.dataset.discrepancyReviewStatus === discrepancyStatusFilter) && (!query || row.dataset.discrepancySearchText.includes(query))); });
};
discrepancyModal?.querySelectorAll('[data-discrepancy-filter]').forEach((button) => button.addEventListener('click', applyDiscrepancyTableFilters));
discrepancyModal?.querySelector('[data-discrepancy-search]')?.addEventListener('input', applyDiscrepancyTableFilters);
window.openDiscrepancyDetails = (data) => {
    const detailModal = document.getElementById('bank-discrepancy-details-modal');
    detailModal?.querySelectorAll('[data-detail-field]').forEach((field) => { field.textContent = data[field.dataset.detailField] || 'Not available'; });
    detailModal?.querySelector('[data-resolution-view]')?.remove();
    detailModal?.querySelector('[data-resolve-open]')?.remove();
    const resolutionView = document.createElement('section');
    resolutionView.dataset.resolutionView = 'true';
    resolutionView.className = 'rounded-lg border border-emerald-200 bg-emerald-50 p-3';
    resolutionView.innerHTML = `<h3 class="text-sm font-semibold text-emerald-900">Resolution details</h3><p class="mt-1 whitespace-pre-wrap text-sm text-emerald-900">${data.resolution_explanation || 'No Staff resolution has been saved.'}</p><p class="mt-2 text-xs text-emerald-800">Resolved by: ${data.resolved_by || 'Not resolved'}${data.resolved_at ? ` · ${data.resolved_at}` : ''}</p>${data.resolution_evidence ? `<p class="mt-1 text-xs font-semibold text-emerald-800">${data.resolution_evidence}</p><iframe title="Resolution evidence preview" src="${data.resolution_evidence_url}" class="mt-2 h-48 w-full rounded border border-emerald-200 bg-white"></iframe>` : ''}`;
    detailModal?.querySelector('.bank-modal-body')?.append(resolutionView);
    if (data.decision === 'Accepted' && data.resolution_status === 'Unresolved' && data.resolve_url && @json((bool) auth()->user()->can('resolveDiscrepancy', $latestReconciliationPeriod))) {
        const resolveButton = document.createElement('button');
        resolveButton.type = 'button'; resolveButton.textContent = 'Resolve Discrepancy'; resolveButton.dataset.resolveOpen = 'true';
        resolveButton.className = 'primary-button';
        resolveButton.addEventListener('click', () => {
            const resolveModal = document.getElementById('bank-discrepancy-resolve-modal');
            const form = resolveModal?.querySelector('[data-discrepancy-resolve-form]');
            if (!resolveModal || !form) return;
            form.action = data.resolve_url;
            resolveModal.querySelector('[data-resolve-summary-type]').textContent = data.type || 'Discrepancy';
            resolveModal.querySelector('[data-resolve-summary-amount]').textContent = `Amount: ${data.amount || '—'}`;
            form.querySelector('[data-resolution-explanation]').value = '';
            resolveModal.showModal();
        });
        detailModal?.querySelector('.bank-modal-footer')?.prepend(resolveButton);
    }
    discrepancyModal?.close();
    detailModal?.showModal();
};
discrepancyModal?.addEventListener('click', (event) => {
    const report = event.target.closest?.('[data-discrepancy-details]');
    if (!report || (event.target.closest('form, input, select, textarea, a') && !event.target.closest('button[data-discrepancy-details]'))) return;
    window.openDiscrepancyDetails(discrepancyDetailData[Number(report.dataset.discrepancyReportIndex)] || {});
});
const resolutionFile = document.querySelector('[data-resolution-file]');
const resolutionDropzone = document.querySelector('[data-resolution-dropzone]');
const resolutionPreview = document.querySelector('[data-resolution-file-preview]');
const resolutionRemove = document.querySelector('[data-resolution-file-remove]');
const refreshResolutionFile = () => {
    const file = resolutionFile?.files?.[0];
    if (!file) { if (resolutionPreview) { resolutionPreview.hidden = true; resolutionPreview.textContent = ''; } resolutionRemove?.setAttribute('hidden', ''); return; }
    if (resolutionPreview) { resolutionPreview.hidden = false; resolutionPreview.textContent = `${file.name} · ${(file.size / 1024 / 1024).toFixed(2)} MB`; }
    resolutionRemove?.removeAttribute('hidden');
};
resolutionDropzone?.addEventListener('click', () => resolutionFile?.click());
resolutionFile?.addEventListener('change', refreshResolutionFile);
resolutionRemove?.addEventListener('click', () => { if (resolutionFile) resolutionFile.value = ''; refreshResolutionFile(); });
resolutionDropzone?.addEventListener('dragover', (event) => { event.preventDefault(); resolutionDropzone.classList.add('border-blue-600'); });
resolutionDropzone?.addEventListener('dragleave', () => resolutionDropzone.classList.remove('border-blue-600'));
resolutionDropzone?.addEventListener('drop', (event) => { event.preventDefault(); resolutionDropzone.classList.remove('border-blue-600'); if (resolutionFile && event.dataTransfer.files.length) { resolutionFile.files = event.dataTransfer.files; refreshResolutionFile(); } });
const periodForm = document.querySelector('#bank-period-modal form[action$="/periods"]');
const suggestedPeriodStart = @json($suggestedPeriodStart);
const suggestedPeriodEnd = @json($suggestedPeriodEnd);
if (periodForm) {
    if (!periodForm.querySelector('[name="bank_account"]')) {
        const accountLabel = document.createElement('label');
        accountLabel.className = 'block space-y-1 text-sm font-semibold text-slate-800';
        accountLabel.textContent = 'Bank account';
        const accountSelect = document.createElement('select');
        accountSelect.name = 'bank_account'; accountSelect.required = true; accountSelect.className = 'mt-1 w-full rounded border-slate-300 text-sm';
        @json($bankAccounts).forEach((account) => accountSelect.append(new Option(account, account)));
        accountLabel.append(accountSelect); periodForm.prepend(accountLabel);
    }
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
        if (true) return; // Legacy action templates remain for compatibility but are never injected into read-only transaction details.
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
const unmatchedRecordModal = document.getElementById('unmatched-system-record-modal');
const unmatchedRecordForm = document.querySelector('[data-unmatched-record-form]');
const canReportUnmatched = @json((bool) ($activePeriod?->isDraft() && auth()->user()->can('prepare', \App\Models\BankReconciliation::class)));
const unmatchedMoney = (amount) => 'PHP ' + Number(amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
const clearUnmatchedEvidencePreview = () => {
    const uploader = unmatchedRecordForm?.querySelector('[data-evidence-uploader]');
    uploader?.querySelector('[data-evidence-list]')?.replaceChildren();
    uploader?.querySelector('[data-evidence-list]')?.classList.add('hidden');
    const error = uploader?.querySelector('[data-evidence-error]');
    if (error) { error.textContent = ''; error.classList.add('hidden'); }
};
const discrepancyTypeOptions = {
    incoming: ['deposit_in_transit', 'cash_not_deposited', 'recording_error', 'unidentified_transaction', 'other'],
    outgoing: ['bank_charge', 'outstanding_check', 'recording_error', 'unidentified_transaction', 'other'],
    system: ['deposit_in_transit', 'cash_not_deposited', 'outstanding_check', 'recording_error', 'other'],
    all: ['bank_charge', 'deposit_in_transit', 'cash_not_deposited', 'outstanding_check', 'recording_error', 'unidentified_transaction', 'other'],
};
const syncDiscrepancyTypeOptions = () => {
    const select = unmatchedRecordForm?.querySelector('[data-unmatched-type]');
    const help = unmatchedRecordForm?.querySelector('[data-unmatched-reason-help]');
    const customWrap = unmatchedRecordForm?.querySelector('[data-custom-type-wrap]');
    const customInput = unmatchedRecordForm?.querySelector('[data-custom-type]');
    if (!select) return;
    const allowed = discrepancyTypeOptions[unmatchedRecordForm.dataset.discrepancyContext || 'all'] || discrepancyTypeOptions.all;
    [...select.options].forEach((option) => {
        const visible = !option.value || allowed.includes(option.value);
        option.hidden = !visible;
        option.disabled = !visible;
    });
    const selected = select.selectedOptions[0];
    if (help && selected?.dataset.description) help.textContent = selected.dataset.description;
    const isOther = select.value === 'other';
    customWrap?.classList.toggle('hidden', !isOther);
    if (customInput) customInput.required = isOther;
    if (!isOther && customInput) customInput.value = '';
};
const openUnmatchedRecordModal = (record, direction) => {
    if (!unmatchedRecordModal || !unmatchedRecordForm) return;
    unmatchedRecordForm.reset();
    clearUnmatchedEvidencePreview();
    unmatchedRecordForm.action = unmatchedRecordForm.dataset.createAction;
    unmatchedRecordForm.dataset.discrepancyContext = 'system';
    unmatchedRecordForm.querySelector('[data-unmatched-method]').value = 'POST';
    unmatchedRecordForm.querySelector('[data-unmatched-record-label]').textContent = `${record.label}${record.reference ? ' · ' + record.reference : ''}`;
    unmatchedRecordForm.querySelector('[data-unmatched-record-amount]').textContent = `Amount: ${unmatchedMoney(record.amount)}`;
    unmatchedRecordForm.querySelector('[data-unmatched-amount]').value = Number(record.amount).toFixed(2);
    unmatchedRecordForm.querySelector('[data-unmatched-type]').value = direction === 'withdrawal' ? 'outstanding_check' : 'deposit_in_transit';
    unmatchedRecordForm.querySelector('[data-unmatched-classification]').value = 'timing_difference';
    unmatchedRecordForm.querySelector('[data-unmatched-carry-forward]').value = '1';
    unmatchedRecordForm.querySelector('[data-unmatched-bank-entry]').value = '';
    unmatchedRecordForm.querySelector('[data-unmatched-system-type]').value = direction === 'withdrawal' ? 'cash_disbursement' : 'contribution';
    unmatchedRecordForm.querySelector('[data-unmatched-system-id]').value = record.id;
    unmatchedRecordForm.querySelector('[data-unmatched-submit]').textContent = 'Report Discrepancy';
    unmatchedRecordForm.querySelector('[data-unmatched-reason-help]').textContent = direction === 'withdrawal'
        ? 'Use Outstanding Check when a recorded payment has not cleared the bank. Use Recording Error when the system record needs correction.'
        : 'Deposit in Transit means a deposit was made but has not posted at LANDBANK. Cash Not Yet Deposited means cash is recorded internally but has not yet been deposited.';
    syncDiscrepancyTypeOptions();
    unmatchedRecordModal.showModal();
};
unmatchedRecordForm?.querySelector('[data-unmatched-type]')?.addEventListener('change', (event) => {
    const accounting = event.target.value === 'recording_error';
    unmatchedRecordForm.querySelector('[data-unmatched-classification]').value = accounting ? 'accounting_adjustment' : 'timing_difference';
    unmatchedRecordForm.querySelector('[data-unmatched-carry-forward]').value = accounting ? '0' : '1';
    unmatchedRecordForm.querySelector('[data-unmatched-reason-help]').textContent = event.target.value === 'cash_not_deposited'
        ? 'Cash Not Yet Deposited means cash is recorded internally but has not yet been deposited to LANDBANK.'
        : event.target.value === 'deposit_in_transit'
            ? 'Deposit in Transit means a deposit was made but has not posted at LANDBANK.'
            : event.target.value === 'outstanding_check'
                ? 'Outstanding Check means a recorded payment has not cleared the bank.'
                : 'Recording Error means the system record needs correction or additional review.';
    syncDiscrepancyTypeOptions();
    });
const openBankDiscrepancyModal = (button) => {
    if (!unmatchedRecordModal || !unmatchedRecordForm) return;
    unmatchedRecordForm.reset();
    clearUnmatchedEvidencePreview();
    unmatchedRecordForm.action = unmatchedRecordForm.dataset.createAction;
    unmatchedRecordForm.dataset.discrepancyContext = button.dataset.bankType === 'withdrawal' ? 'outgoing' : 'incoming';
    unmatchedRecordForm.querySelector('[data-unmatched-method]').value = 'POST';
    unmatchedRecordForm.querySelector('[data-unmatched-record-label]').textContent = `${button.dataset.bankDescription || 'LANDBANK transaction'}${button.dataset.bankReference ? ' · ' + button.dataset.bankReference : ''}`;
    unmatchedRecordForm.querySelector('[data-unmatched-record-amount]').textContent = `Amount: ${unmatchedMoney(button.dataset.bankAmount)}`;
    unmatchedRecordForm.querySelector('[data-unmatched-amount]').value = Number(button.dataset.bankAmount || 0).toFixed(2);
    unmatchedRecordForm.querySelector('[data-unmatched-bank-entry]').value = button.dataset.bankId || '';
    unmatchedRecordForm.querySelector('[data-unmatched-system-type]').value = '';
    unmatchedRecordForm.querySelector('[data-unmatched-system-id]').value = '';
    unmatchedRecordForm.querySelector('[data-unmatched-submit]').textContent = 'Report Discrepancy';
    const type = button.dataset.bankType === 'withdrawal' ? 'outstanding_check' : 'unidentified_transaction';
    const select = unmatchedRecordForm.querySelector('[data-unmatched-type]');
    if (![...select.options].some((option) => option.value === type)) {
        const option = new Option('Unidentified Transaction', 'unidentified_transaction');
        select.append(option);
    }
    select.value = type;
    syncDiscrepancyTypeOptions();
    unmatchedRecordForm.querySelector('[data-unmatched-reason-help]').textContent = button.dataset.bankType === 'withdrawal'
        ? 'Use Outstanding Check when the payment has not cleared, or Recording Error when the bank transaction needs investigation.'
        : 'Use Unidentified Transaction when the deposit cannot yet be linked to a verified system record.';
    syncDiscrepancyTypeOptions();
    unmatchedRecordModal.showModal();
};
document.addEventListener('click', (event) => {
    const edit = event.target.closest?.('[data-edit-discrepancy]');
    if (!edit || !unmatchedRecordForm || !unmatchedRecordModal) return;
    unmatchedRecordForm.reset();
    clearUnmatchedEvidencePreview();
    unmatchedRecordForm.action = edit.dataset.editAction;
    unmatchedRecordForm.dataset.discrepancyContext = 'all';
    unmatchedRecordForm.querySelector('[data-unmatched-method]').value = 'PATCH';
    unmatchedRecordForm.querySelector('[data-unmatched-record-label]').textContent = 'Existing discrepancy report';
    unmatchedRecordForm.querySelector('[data-unmatched-record-amount]').textContent = `Amount: ${unmatchedMoney(edit.dataset.editAmount)}`;
    unmatchedRecordForm.querySelector('[data-unmatched-type]').value = edit.dataset.editType;
    unmatchedRecordForm.querySelector('[data-custom-type]').value = edit.dataset.editCustomType || '';
    unmatchedRecordForm.querySelector('[data-unmatched-classification]').value = edit.dataset.editClassification;
    unmatchedRecordForm.querySelector('[data-unmatched-amount]').value = Number(edit.dataset.editAmount).toFixed(2);
    unmatchedRecordForm.querySelector('[data-unmatched-reason-help]').textContent = 'Update the explanation or supporting evidence while this report is unresolved.';
    unmatchedRecordForm.querySelector('[name="explanation"]').value = edit.dataset.editExplanation || '';
    unmatchedRecordForm.querySelector('[name="evidence_reference"]').value = edit.dataset.editReference || '';
    unmatchedRecordForm.querySelector('[data-unmatched-submit]').textContent = 'Update Discrepancy';
    syncDiscrepancyTypeOptions();
    unmatchedRecordModal.showModal();
});
document.addEventListener('click', (event) => {
    const report = event.target.closest?.('[data-report-bank]');
    if (report) {
        document.getElementById('bank-entry-' + report.dataset.bankId)?.close();
        openBankDiscrepancyModal(report);
        return;
    }
    const go = event.target.closest?.('[data-go-to-matching]');
    if (go) {
        document.getElementById('bank-entry-' + go.dataset.bankId)?.close();
        const tab = document.querySelector(`[data-board-tab="${go.dataset.bankType === 'withdrawal' ? 'withdrawal' : 'deposit'}"]`);
        tab?.click();
        document.querySelector('[data-reconciliation-board]')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
});
const board = document.querySelector('[data-reconciliation-board]');
if (board) {
    const boardBanks = @json($boardBankRows);
    const boardSystems = {
        deposit: @json($boardDepositRows),
        withdrawal: @json($boardWithdrawalRows),
    };
    const boardStart = @json($activePeriod?->period_start?->toDateString());
    const boardEnd = @json($activePeriod?->period_end?->toDateString());
    let boardType = 'deposit';
    const selected = {system: new Set(), bank: new Set()};
    const inPeriod = (date) => !boardStart || !date || (date >= boardStart && date <= boardEnd);
    const money = (amount) => 'PHP ' + Number(amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const systemBody = board.querySelector('[data-board-system-body]');
    const bankBody = board.querySelector('[data-board-bank-body]');
    const emptySystem = board.querySelector('[data-board-system-empty]');
    const emptyBank = board.querySelector('[data-board-bank-empty]');
    bankBody.addEventListener('click', (event) => {
        if (event.target.closest('input, button, a, label')) return;
        const row = event.target.closest('tr');
        const bankId = row?.querySelector('[data-board-select="bank"]')?.value;
        if (bankId) document.getElementById('bank-entry-' + bankId)?.showModal();
    });
    const updateBoardState = () => {
        const matchesFilter = (item) => boardFilter === 'all' || (boardFilter === 'matched' ? item.status === 'matched' : item.status !== 'matched');
        const systems = boardSystems[boardType].filter((item) => inPeriod(item.date) && matchesFilter(item));
        const banks = boardBanks.filter((item) => item.type === boardType && inPeriod(item.date) && matchesFilter(item));
        const systemTotal = [...selected.system].reduce((sum, id) => sum + Number(systems.find((item) => String(item.id) === String(id))?.amount || 0), 0);
        const bankTotal = [...selected.bank].reduce((sum, id) => sum + Number(banks.find((item) => String(item.id) === String(id))?.amount || 0), 0);
        const bankSelectionCount = selected.bank.size;
        const systemSelection = systems.filter((item) => selected.system.has(String(item.id)));
        const validSystemSelection = systemSelection.length === selected.system.size && systemSelection.length > 0;
        const validDirection = boardType === 'deposit' ? systemSelection.length === 1 : systemSelection.length > 0;
        const exact = bankSelectionCount === 1 && validSystemSelection && validDirection && Math.abs(systemTotal - bankTotal) < 0.005;
        const difference = bankTotal - systemTotal;
        board.querySelector('[data-board-system-count]').textContent = systems.length + ' eligible';
        board.querySelector('[data-board-bank-count]').textContent = banks.length + (boardFilter === 'matched' ? ' matched' : boardFilter === 'all' ? ' total' : ' needing match');
        board.querySelector('[data-board-amount-summary]').textContent = `Bank amount: ${money(bankTotal)} · System amount: ${money(systemTotal)} · Difference: ${money(difference)}`;
        const help = board.querySelector('[data-board-selection-help]');
        const selection = board.querySelector('[data-board-selection]');
        if (bankSelectionCount === 0) {
            selection.textContent = 'Select one LANDBANK transaction and its matching system record(s).';
            help.textContent = 'Confirm Match is disabled until one valid bank transaction and matching system record(s) are selected.';
        } else if (bankSelectionCount > 1) {
            selection.textContent = 'Only one LANDBANK transaction may be selected.';
            help.textContent = 'Confirm Match is disabled because multiple bank transactions are selected. Select only one.';
        } else if (!validSystemSelection || !validDirection) {
            selection.textContent = `Selected bank ${money(bankTotal)}. Choose a valid matching system record.`;
            help.textContent = boardType === 'deposit' ? 'Confirm Match is disabled because one verified donation must be selected for an incoming deposit.' : 'Confirm Match is disabled because at least one eligible approved disbursement must be selected.';
        } else if (!exact) {
            selection.textContent = `Selected bank ${money(bankTotal)} and system records ${money(systemTotal)}.`;
            help.textContent = `Confirm Match is disabled because the amounts differ by ${money(Math.abs(difference))}. Amounts must match exactly.`;
        } else {
            selection.textContent = `${selected.system.size} matching system record(s) selected.`;
            help.textContent = 'Amounts match. Confirm Match will update both tables and keep an audit record.';
        }
        board.querySelector('[data-board-clear]').disabled = !exact;
    };
    const checkbox = (kind, item) => { const input = document.createElement('input'); input.type = 'checkbox'; input.dataset.boardSelect = kind; input.value = item.id; input.checked = selected[kind].has(String(item.id)); input.addEventListener('change', () => { if (input.checked) { if (kind === 'bank') { selected.bank.clear(); bankBody.querySelectorAll('[data-board-select="bank"]').forEach((other) => { if (other !== input) other.checked = false; }); } selected[kind].add(String(item.id)); } else selected[kind].delete(String(item.id)); updateBoardState(); }); return input; };
    const renderBoard = () => {
        selected.system.clear(); selected.bank.clear(); systemBody.replaceChildren(); bankBody.replaceChildren();
        const matchesFilter = (item) => boardFilter === 'all' || (boardFilter === 'matched' ? item.status === 'matched' : item.status !== 'matched');
        const systems = boardSystems[boardType].filter((item) => inPeriod(item.date) && matchesFilter(item));
        const banks = boardBanks.filter((item) => item.type === boardType && inPeriod(item.date) && matchesFilter(item));
        systems.forEach((item) => { const row = document.createElement('tr'); row.className = 'border-t border-slate-100'; const select = document.createElement('td'); select.className = 'px-4 py-3'; const input = checkbox('system', item); input.disabled = item.status === 'matched'; select.append(input); const detail = document.createElement('td'); detail.className = 'px-4 py-3'; const label = document.createElement('strong'); label.textContent = item.label; const meta = document.createElement('span'); meta.className = 'block text-xs text-slate-500'; meta.textContent = `${item.detail || ''} · ${item.date || 'No date'}`; detail.append(label, meta); const amount = document.createElement('td'); amount.className = 'px-4 py-3 text-right font-semibold'; amount.textContent = money(item.amount); const status = document.createElement('td'); status.className = 'px-4 py-3 text-xs font-semibold capitalize ' + (item.status === 'matched' ? 'text-emerald-700' : 'text-slate-600'); status.textContent = item.status === 'matched' ? 'Matched' : item.status; row.append(select, detail, amount, status); if (canReportUnmatched && item.status !== 'matched') { const actionCell = document.createElement('td'); actionCell.className = 'px-4 py-3 text-right'; const action = document.createElement('button'); action.type = 'button'; action.className = 'text-xs font-semibold text-amber-700 hover:text-amber-900'; action.textContent = 'Report Unmatched Record'; action.addEventListener('click', (event) => { event.stopPropagation(); openUnmatchedRecordModal(item, boardType); }); actionCell.append(action); row.append(actionCell); } systemBody.append(row); });
        banks.forEach((item) => { const row = document.createElement('tr'); row.className = 'border-t border-slate-100'; const select = document.createElement('td'); select.className = 'px-4 py-3'; const input = checkbox('bank', item); input.disabled = item.status === 'matched'; select.append(input); const detail = document.createElement('td'); detail.className = 'px-4 py-3'; const label = document.createElement('strong'); label.textContent = item.description; const meta = document.createElement('span'); meta.className = 'block text-xs text-slate-500'; meta.textContent = `${item.reference || 'No reference'} · ${item.date || 'No date'}`; detail.append(label, meta); const amount = document.createElement('td'); amount.className = 'px-4 py-3 text-right font-semibold'; amount.textContent = money(item.amount); const status = document.createElement('td'); status.className = 'px-4 py-3 text-xs font-semibold ' + (item.status === 'matched' ? 'text-emerald-700' : 'text-amber-700'); status.textContent = item.status === 'matched' ? 'Matched' : item.status; row.append(select, detail, amount, status); bankBody.append(row); });
        emptySystem.classList.toggle('hidden', systems.length > 0); emptyBank.classList.toggle('hidden', banks.length > 0); updateBoardState();
    };
    let boardFilter = 'needs';
    const setBoardTab = (tab) => { boardType = tab.dataset.boardTab; board.querySelectorAll('[data-board-tab]').forEach((item) => { const active = item === tab; item.setAttribute('aria-selected', active ? 'true' : 'false'); item.classList.toggle('bg-blue-600', active); item.classList.toggle('text-white', active); item.classList.toggle('shadow-sm', active); item.classList.toggle('bg-white', !active); item.classList.toggle('text-slate-600', !active); item.classList.toggle('text-blue-700', !active); }); renderBoard(); };
    board.querySelectorAll('[data-board-tab]').forEach((tab) => tab.addEventListener('click', () => setBoardTab(tab)));
    board.querySelectorAll('[data-board-filter]').forEach((filter) => filter.addEventListener('click', () => { boardFilter = filter.dataset.boardFilter; board.querySelectorAll('[data-board-filter]').forEach((item) => { const active = item === filter; item.classList.toggle('bg-blue-50', active); item.classList.toggle('text-blue-700', active); item.classList.toggle('text-slate-600', !active); }); renderBoard(); }));
    board.querySelector('[data-board-clear]').addEventListener('click', async () => { const bank = boardBanks.find((item) => String(item.id) === [...selected.bank][0]); if (!bank) return; const form = document.createElement('form'); form.method = 'POST'; form.action = boardType === 'deposit' ? bank.matchUrl : bank.outgoingFinalizeUrl; form.hidden = true; const token = document.querySelector('input[name="_token"]')?.value; [[' _token', token], ['bank_ledger_entry_id', bank.id], ['withdrawal_id', bank.id]].forEach(([name, value]) => { if (name.trim() !== ' _token' || token) { const input = document.createElement('input'); input.type = 'hidden'; input.name = name.trim(); input.value = value ?? ''; form.append(input); } });
        if (boardType === 'deposit') { const input = document.createElement('input'); input.type = 'hidden'; input.name = 'contribution_id'; input.value = [...selected.system][0]; form.append(input); } else { selected.system.forEach((id) => { const input = document.createElement('input'); input.type = 'hidden'; input.name = 'disbursement_ids[]'; input.value = id; form.append(input); }); }
        document.body.append(form);
        const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html'} });
        if (!response.ok) { board.querySelector('[data-board-selection-help]').textContent = 'The match could not be confirmed. Review the selected records and try again.'; return; }
        bank.status = 'matched';
        boardSystems[boardType].filter((item) => selected.system.has(String(item.id))).forEach((item) => { item.status = 'matched'; });
        selected.system.clear(); selected.bank.clear();
        board.querySelector('[data-board-selection]').textContent = 'Match confirmed. Select the next LANDBANK transaction and system record(s).';
        board.querySelector('[data-board-selection-help]').textContent = 'The matched records remain available under All and Matched.';
        renderBoard();
    });
    const systemHeaderRow = board.querySelector('[data-board-system-body]')?.closest('table')?.querySelector('thead tr');
    if (systemHeaderRow) { const discrepancyHeader = document.createElement('th'); discrepancyHeader.className = 'px-4 py-3'; discrepancyHeader.textContent = 'Discrepancy Status'; systemHeaderRow.append(discrepancyHeader); }
    const decorateSystemDiscrepancyRows = () => {
        const visibleSystems = boardSystems[boardType].filter((item) => inPeriod(item.date) && (boardFilter === 'all' || (boardFilter === 'matched' ? item.status === 'matched' : item.status !== 'matched')));
        [...systemBody.children].forEach((row, index) => {
            if (row.dataset.discrepancyDecorated) return;
            row.dataset.discrepancyDecorated = 'true';
            const item = visibleSystems[index];
            const cell = document.createElement('td'); cell.className = 'px-4 py-3';
            if (item?.discrepancy) { const button = document.createElement('button'); button.type = 'button'; button.textContent = item.discrepancy.status; button.className = 'rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800'; button.addEventListener('click', (event) => { event.stopPropagation(); window.openDiscrepancyDetails?.(item.discrepancy.data); }); cell.append(button); }
            else cell.textContent = '—';
            row.append(cell);
        });
    };
    new MutationObserver(decorateSystemDiscrepancyRows).observe(systemBody, {childList: true});
    renderBoard();
    decorateSystemDiscrepancyRows();
}
</script>
<style>
.period-modal-icon,.period-section-icon{display:inline-flex;align-items:center;justify-content:center;flex:none;color:#2563eb}.period-modal-icon{width:2.25rem;height:2.25rem;border-radius:.75rem;background:#eff6ff;font-size:1.45rem;font-weight:700}.period-section-icon{width:1.75rem;height:1.75rem;border-radius:.5rem;background:#dbeafe;font-size:1rem;font-weight:700}.period-modal-section{position:relative;overflow:visible;border:1px solid #dbeafe;border-radius:.8rem;background:#fff}.period-modal-section.period-dropdown-open{z-index:30}.period-section-heading{display:flex;align-items:center;gap:.65rem;padding:.6rem 1rem;background:linear-gradient(90deg,#eff6ff,#eaf2ff);color:#172554}.period-section-heading h3,.period-next h3{font-size:.95rem;font-weight:700}.period-alert{display:flex;align-items:flex-start;gap:.65rem;border-radius:.7rem;padding:.75rem 1rem;font-size:.875rem;line-height:1.35}.period-alert-info{border:1px solid #93c5fd;background:#eff6ff;color:#1e3a8a}.period-alert-warning{border:1px solid #fcd34d;background:#fffbeb;color:#78350f}.period-alert-icon{font-size:1.15rem;line-height:1;color:#2563eb}.period-alert-warning .period-alert-icon{color:#d97706}.period-money-input{display:flex;align-items:center;overflow:hidden;border:1px solid #cbd5e1;border-radius:.5rem;background:#fff}.period-money-input b{padding-left:.75rem;color:#475569}.period-money-input input{padding-left:.45rem}.period-next{display:flex;align-items:flex-start;gap:.75rem;border-radius:.8rem;background:#eff6ff;padding:1rem;color:#1e3a8a}.period-next ul{margin-top:.2rem;list-style:disc;padding-left:1.1rem;font-size:.8rem;line-height:1.55}@media (max-width:640px){.period-section-heading{padding:.6rem .8rem}.period-modal-section>div:not(.period-section-heading){padding:.8rem}.period-next{padding:.8rem}}
</style>
<style>
.period-select-wrapper{position:relative}.period-select-native{position:absolute!important;width:1px!important;height:1px!important;overflow:hidden!important;opacity:0!important;pointer-events:none!important}.period-select-trigger{display:flex;align-items:center;justify-content:space-between;width:100%;height:2.75rem;border:1px solid #cbd5e1;border-radius:.5rem;background:#fff;padding:0 .75rem;text-align:left;font-size:.875rem;color:#0f172a}.period-select-trigger::after{content:'⌄';font-size:1rem;color:#64748b}.period-select-trigger:focus{outline:2px solid #93c5fd;outline-offset:1px}.period-select-menu{position:absolute;top:calc(100% + .25rem);left:0;right:0;z-index:60;max-height:12rem;overflow-y:auto;border:1px solid #cbd5e1;border-radius:.5rem;background:#fff;padding:.25rem;box-shadow:0 12px 24px rgb(15 23 42/.16)}.period-select-option{display:block;width:100%;border-radius:.35rem;padding:.55rem .65rem;text-align:left;font-size:.875rem;color:#1e293b}.period-select-option:hover,.period-select-option[aria-selected="true"]{background:#eff6ff;color:#1d4ed8}.period-modal-section:nth-of-type(3) .period-money-input{min-height:2.75rem}.period-modal-section:nth-of-type(2) label{font-size:0}.period-modal-section:nth-of-type(2) label select,.period-modal-section:nth-of-type(2) label .period-select-wrapper,.period-modal-section:nth-of-type(2) label span{font-size:.875rem}.period-modal-section:nth-of-type(2) label span.block{font-size:.75rem}@media (max-width:640px){.bank-modal{width:calc(100vw - 3rem);max-width:calc(100vw - 3rem);margin:0.75rem 0}.period-select-menu{max-height:10rem}}
</style>
<style>
.reconciliation-confirm-match:disabled,
.reconciliation-confirm-match[disabled] {
    cursor: not-allowed;
    background: #94a3b8;
    color: #e2e8f0;
    opacity: 1;
}
.reconciliation-confirm-match:not(:disabled) {
    cursor: pointer;
    background: #1d4ed8;
    color: #fff;
}
.reconciliation-confirm-match:not(:disabled):hover {
    background: #1e40af;
}
</style>
@endsection
