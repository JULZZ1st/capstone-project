@extends('layouts.app')
@section('title', 'Review Discrepancy | '.config('dashboard.barangay_name'))
@section('page-title', 'Review Discrepancy')
@section('page-subtitle', 'Review the Staff explanation and evidence before making a decision.')

@section('content')
@php
    $record = $discrepancy->bankLedgerEntry ?: $discrepancy->systemRecord;
    $transactionLabel = $discrepancy->bankLedgerEntry?->description ?: $discrepancy->bankLedgerEntry?->reference_number ?: $record?->purpose ?: $record?->reference_number ?: $discrepancy->typeLabel();
    $transactionReference = $discrepancy->bankLedgerEntry?->reference_number ?: $record?->supporting_reference ?: $record?->reference_number;
@endphp
<div class="mx-auto max-w-4xl space-y-6" data-pending-discrepancy-review>
    <section>
        <a href="{{ route('bank-reconciliation.discrepancies.pending') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-blue-700 hover:text-blue-900">&larr; Back to Pending Discrepancies</a>
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div><h2 class="text-xl font-bold tracking-tight text-slate-950">{{ $discrepancy->typeLabel() }}</h2><p class="mt-1 text-sm text-slate-500">Reported by {{ $discrepancy->reporter?->name ?: 'Staff' }} for {{ $period->period_start?->format('F Y') }}.</p></div>
            <span class="inline-flex self-start rounded-full bg-amber-100 px-3 py-1.5 text-sm font-semibold text-amber-900">Pending Review</span>
        </div>
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="grid gap-4 border-b border-slate-200 p-4 sm:grid-cols-3 sm:p-5">
            <div class="sm:col-span-2"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Transaction</p><p class="mt-1 font-semibold text-slate-950">{{ $transactionLabel }}</p>@if($transactionReference)<p class="mt-1 text-sm text-slate-500">{{ $transactionReference }}</p>@endif</div>
            <div><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Amount</p><p class="mt-1 text-lg font-bold text-slate-950">PHP {{ number_format((float) $discrepancy->amount, 2) }}</p></div>
            <div><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Period</p><p class="mt-1 font-medium text-slate-800">{{ $period->period_start?->format('M d') }}–{{ $period->period_end?->format('M d, Y') }}</p></div>
            <div class="sm:col-span-2"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Bank account</p><p class="mt-1 font-medium text-slate-800">{{ $period->bank_account }}</p></div>
        </div>
        <div class="space-y-5 p-4 sm:p-5">
            <div><h3 class="text-sm font-bold text-slate-950">Staff explanation</h3><p class="mt-2 whitespace-pre-wrap rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm leading-6 text-slate-700">{{ $discrepancy->explanation }}</p></div>
            <div><h3 class="text-sm font-bold text-slate-950">Evidence</h3>@if($discrepancy->evidence_reference)<p class="mt-2 text-sm text-slate-700">Reference: <strong>{{ $discrepancy->evidence_reference }}</strong></p>@endif @if($discrepancy->evidence_path)<a href="{{ route('bank-reconciliation.discrepancies.evidence', [$period, $discrepancy]) }}" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-2 rounded-lg border border-blue-200 px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50">View attached evidence</a>@elseif(!$discrepancy->evidence_reference)<p class="mt-2 text-sm text-slate-500">No supporting evidence was provided.</p>@endif</div>
        </div>
    </section>

    @if($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700" role="alert">{{ $errors->first() }}</div>@endif
    <section class="grid gap-4 lg:grid-cols-2" aria-label="Admin discrepancy decision">
        <form method="POST" action="{{ route('bank-reconciliation.discrepancies.review', [$period, $discrepancy, 'accepted']) }}" class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4 shadow-sm sm:p-5">
            @csrf
            <input type="hidden" name="return_to" value="pending_discrepancies">
            <h3 class="font-bold text-emerald-950">Accept Explanation</h3><p class="mt-1 text-sm leading-5 text-emerald-800">Confirm that the explanation and available evidence are sufficient for Admin review.</p>
            <label class="mt-4 block text-sm font-semibold text-slate-700">Admin note <span class="font-normal text-slate-500">(optional)</span><textarea name="note" rows="3" class="mt-1 w-full rounded-lg border-slate-300 bg-white text-sm" placeholder="Optional review note"></textarea></label>
            <button type="submit" class="mt-4 inline-flex min-h-10 w-full items-center justify-center rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Accept Explanation</button>
        </form>
        <form method="POST" action="{{ route('bank-reconciliation.discrepancies.review', [$period, $discrepancy, 'correction_requested']) }}" class="rounded-xl border border-rose-200 bg-rose-50/50 p-4 shadow-sm sm:p-5" data-correction-form>
            @csrf
            <input type="hidden" name="return_to" value="pending_discrepancies">
            <h3 class="font-bold text-rose-950">Correction Required</h3><p class="mt-1 text-sm leading-5 text-rose-800">Return the report to Staff with a clear reason describing what must be corrected.</p>
            <label class="mt-4 block text-sm font-semibold text-slate-700">Correction reason <span class="text-rose-600">*</span><textarea name="note" rows="3" required class="mt-1 w-full rounded-lg border-slate-300 bg-white text-sm" placeholder="Explain what Staff must correct">{{ old('note') }}</textarea></label>
            <button type="submit" class="mt-4 inline-flex min-h-10 w-full items-center justify-center rounded-lg bg-rose-700 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-800">Correction Required</button>
        </form>
    </section>
</div>
@endsection
