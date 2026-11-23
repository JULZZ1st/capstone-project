@extends('layouts.app')
@section('title', 'Pending Discrepancies | '.config('dashboard.barangay_name'))
@section('page-title', 'Pending Discrepancies')
@section('page-subtitle', 'Review Staff-reported Bank Reconciliation discrepancies.')

@section('content')
<div class="space-y-6" data-pending-discrepancies-page>
    <section class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <a href="{{ route('bank-reconciliation.index') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-blue-700 hover:text-blue-900">&larr; Back to Bank Reconciliation</a>
            <h2 class="mt-3 text-xl font-bold tracking-tight text-slate-950">Pending Discrepancies</h2>
            <p class="mt-1 text-sm text-slate-500">Review discrepancy reports independently from reconciliation period approvals.</p>
        </div>
        <span class="inline-flex self-start rounded-full bg-amber-100 px-3 py-1.5 text-sm font-semibold text-amber-900" data-pending-discrepancy-count>{{ number_format($pendingDiscrepancyReviewCount) }} pending</span>
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="pending-discrepancy-list-heading">
        <div class="border-b border-slate-200 px-4 py-4 sm:px-5">
            <h2 id="pending-discrepancy-list-heading" class="font-bold text-slate-950">Pending Discrepancy Reviews</h2>
            <p class="mt-0.5 text-xs text-slate-500">Select Review to open the corresponding period and report.</p>
        </div>
        @if($pendingDiscrepancyReviews->isEmpty())
            <div class="px-5 py-12 text-center" data-pending-discrepancy-empty>
                <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-emerald-50 text-emerald-600"><x-icon name="check-circle" class="h-5 w-5" /></span>
                <p class="mt-3 font-semibold text-slate-900">No discrepancies are waiting for review</p>
                <p class="mt-1 text-sm text-slate-500">New Staff reports will appear here for an Admin decision.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[44rem] text-left text-sm" data-pending-discrepancy-table>
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3 sm:px-5">Period</th><th class="px-4 py-3">Transaction</th><th class="px-4 py-3 text-right">Amount</th><th class="px-4 py-3 text-right sm:px-5"><span class="sr-only">Action</span></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($pendingDiscrepancyReviews as $discrepancy)
                            @php
                                $period = $discrepancy->period;
                                $record = $discrepancy->bankLedgerEntry ?: $discrepancy->systemRecord;
                                $transactionLabel = $discrepancy->bankLedgerEntry?->description ?: $discrepancy->bankLedgerEntry?->reference_number ?: $record?->purpose ?: $record?->reference_number ?: $discrepancy->typeLabel();
                                $transactionReference = $discrepancy->bankLedgerEntry?->reference_number ?: $record?->supporting_reference ?: $record?->reference_number;
                            @endphp
                            <tr class="transition hover:bg-slate-50/80" data-pending-discrepancy-id="{{ $discrepancy->id }}">
                                <td class="px-4 py-3 sm:px-5"><p class="font-semibold text-slate-950">{{ $period?->period_start?->format('M Y') ?? 'Period not set' }}</p><p class="mt-0.5 max-w-48 truncate text-xs text-slate-500">{{ $period?->bank_account ?: 'Bank account not set' }}</p></td>
                                <td class="px-4 py-3"><p class="max-w-xs truncate font-medium text-slate-800">{{ $transactionLabel }}</p>@if($transactionReference)<p class="mt-0.5 max-w-xs truncate text-xs text-slate-500">{{ $transactionReference }}</p>@endif</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-semibold text-slate-900">PHP {{ number_format((float) $discrepancy->amount, 2) }}</td>
                                <td class="px-4 py-3 text-right sm:px-5"><a href="{{ route('bank-reconciliation.discrepancies.pending.show', $discrepancy) }}" class="inline-flex items-center gap-1 rounded-lg px-2.5 py-2 font-semibold text-blue-700 hover:bg-blue-50">Review <x-icon name="chevron-right" class="h-3.5 w-3.5" /></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($pendingDiscrepancyReviews->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 sm:px-5">{{ $pendingDiscrepancyReviews->links() }}</div>
            @endif
        @endif
    </section>
</div>
@endsection
