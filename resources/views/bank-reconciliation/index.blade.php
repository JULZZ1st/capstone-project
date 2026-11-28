@extends('layouts.app')
@section('title', 'Bank Reconciliation | '.config('dashboard.barangay_name'))
@section('page-title', 'Bank Reconciliation')
@section('page-subtitle', 'Manage bank statements, transaction matching, and reconciliation periods.')

@section('content')
<div class="space-y-6" data-reconciliation-overview>
    <section class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-end">
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

    <div class="min-w-0">
        <x-data-table caption="Reconciliation periods" min-width="40rem">
            <x-slot:head>
                <x-data-table.heading>Period</x-data-table.heading>
                <x-data-table.heading class="hidden sm:table-cell">Bank</x-data-table.heading>
                <x-data-table.heading>Status</x-data-table.heading>
                <x-data-table.heading align="right"><span class="sr-only">Action</span></x-data-table.heading>
            </x-slot:head>
            @forelse($reconciliationPeriods as $period)
                @php
                    $statusLabel = $period->isApproved() ? 'Approved & Locked' : ($period->isReadyForReview() ? 'Pending Review' : 'Draft');
                    $statusClass = $period->isApproved() ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : ($period->isReadyForReview() ? 'bg-amber-50 text-amber-700 ring-amber-200' : 'bg-slate-100 text-slate-700 ring-slate-200');
                    $isAdmin = auth()->user()->hasRole('admin');
                    $actionLabel = $isAdmin
                        ? ($period->isApproved() ? 'View' : ($period->isReadyForReview() ? 'Review' : 'Open'))
                        : 'Open';
                @endphp
                <tr class="transition hover:bg-slate-50/80">
                    <td class="px-4 py-4 sm:px-5">
                        <p class="font-semibold text-slate-950">{{ $period->period_start?->format('M Y') ?? $period->date?->format('M Y') ?? 'Period not set' }}</p>
                        <p class="mt-0.5 text-xs text-slate-500 sm:hidden">{{ $period->bank_account ?: config('bank_reconciliation.bank_accounts.0', 'Bank account not set') }}</p>
                    </td>
                    <td class="hidden px-4 py-4 text-slate-600 sm:table-cell">{{ $period->bank_account ?: config('bank_reconciliation.bank_accounts.0', 'Not set') }}</td>
                    <td class="px-4 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusClass }}">{{ $statusLabel }}</span></td>
                    <td class="px-4 py-4 text-right sm:px-5"><a href="{{ route('bank-reconciliation.periods.show', $period) }}" class="inline-flex items-center gap-1 rounded-lg px-2.5 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50">{{ $actionLabel }} <x-icon name="chevron-right" class="h-3.5 w-3.5" /></a></td>
                </tr>
            @empty
                <x-data-table.empty :columns="4" title="No reconciliation periods yet" description="Create a reconciliation period to begin matching bank activity." />
            @endforelse
        </x-data-table>
    </div>
</div>

@can('prepare', \App\Models\BankReconciliation::class)
    @include('bank-reconciliation.partials.new-period-modal')
@endcan
@endsection
