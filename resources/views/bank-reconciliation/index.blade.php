@extends('layouts.app')
@section('title', 'Bank Reconciliation | '.config('dashboard.barangay_name'))
@section('page-title', 'Bank Reconciliation')
@section('page-subtitle', 'Manage bank statements, transaction matching, and reconciliation periods.')

@section('content')
<div class="space-y-6" data-reconciliation-overview>
    <section class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-xl font-bold tracking-tight text-slate-950">Bank Reconciliation</h2>
            <p class="mt-1 text-sm text-slate-500">Manage bank statements, transaction matching, and reconciliation periods.</p>
        </div>
        @can('prepare', \App\Models\BankReconciliation::class)
            <button type="button" data-dialog-open="new-bank-period-modal" class="inline-flex h-10 shrink-0 items-center justify-center gap-2 self-start rounded-full bg-slate-950 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">
                <x-icon name="plus" class="h-4 w-4" />
                New Reconciliation
            </button>
        @endcan
    </section>

    @if(auth()->user()->hasRole('admin'))
        <div class="flex justify-end">
            <a href="{{ route('bank-reconciliation.discrepancies.pending') }}" class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-5 py-2 text-sm font-semibold text-amber-900 shadow-sm transition hover:border-amber-300 hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 sm:w-auto" data-view-pending-discrepancies>
                <x-icon name="alert-circle" class="h-4 w-4" />
                View Pending Discrepancies ({{ number_format($pendingDiscrepancyReviewCount) }})
            </a>
        </div>
    @endif

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="reconciliation-periods-heading">
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-4 sm:px-5">
            <div>
                <h2 id="reconciliation-periods-heading" class="font-bold text-slate-950">Reconciliation Periods</h2>
                <p class="mt-0.5 text-xs text-slate-500">Open a period to manage statements, matches, discrepancies, and approvals.</p>
            </div>
            <span class="text-xs font-medium text-slate-500">{{ $reconciliationPeriods->count() }} {{ \Illuminate\Support\Str::plural('period', $reconciliationPeriods->count()) }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-4 py-3 sm:px-5">Period</th>
                        <th scope="col" class="hidden px-4 py-3 sm:table-cell">Bank</th>
                        <th scope="col" class="px-4 py-3">Status</th>
                        <th scope="col" class="px-4 py-3 text-right sm:px-5"><span class="sr-only">Action</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($reconciliationPeriods as $period)
                        @php
                            $statusLabel = $period->isApproved() ? 'Approved & Locked' : ($period->isReadyForReview() ? 'Pending Review' : 'Draft');
                            $statusClass = $period->isApproved() ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : ($period->isReadyForReview() ? 'bg-amber-50 text-amber-700 ring-amber-200' : 'bg-slate-100 text-slate-700 ring-slate-200');
                        @endphp
                        <tr class="transition hover:bg-slate-50/80">
                            <td class="px-4 py-4 sm:px-5">
                                <p class="font-semibold text-slate-950">{{ $period->period_start?->format('M Y') ?? $period->date?->format('M Y') ?? 'Period not set' }}</p>
                                <p class="mt-0.5 text-xs text-slate-500 sm:hidden">{{ $period->bank_account ?: config('bank_reconciliation.bank_accounts.0', 'Bank account not set') }}</p>
                            </td>
                            <td class="hidden px-4 py-4 text-slate-600 sm:table-cell">{{ $period->bank_account ?: config('bank_reconciliation.bank_accounts.0', 'Not set') }}</td>
                            <td class="px-4 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusClass }}">{{ $statusLabel }}</span></td>
                            <td class="px-4 py-4 text-right sm:px-5"><a href="{{ route('bank-reconciliation.periods.show', $period) }}" class="inline-flex items-center gap-1 rounded-lg px-2.5 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50">Open <x-icon name="chevron-right" class="h-3.5 w-3.5" /></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-12 text-center"><span class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-500"><x-icon name="document" class="h-5 w-5" /></span><p class="mt-3 font-semibold text-slate-900">No reconciliation periods yet</p><p class="mt-1 text-sm text-slate-500">Create a reconciliation period to begin matching bank activity.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

@can('prepare', \App\Models\BankReconciliation::class)
    @include('bank-reconciliation.partials.new-period-modal')
@endcan
@endsection
