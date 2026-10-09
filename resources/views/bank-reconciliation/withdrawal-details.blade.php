@extends('layouts.app')

@section('title', 'Withdrawal Reconciliation Details | '.config('dashboard.barangay_name'))
@section('page-title', 'Withdrawal Reconciliation Details')
@section('page-subtitle', 'Read-only details for a finalized outgoing bank reconciliation.')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <a href="{{ route('bank-reconciliation.index', ['tab' => 'outgoing']) }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">&larr; Back to Outgoing Funds</a>
            <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1.5 text-xs font-semibold uppercase text-emerald-800">{{ $withdrawal->status }}</span>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div><p class="text-xs uppercase tracking-wide text-slate-500">Transaction Date</p><p class="mt-1 font-semibold text-slate-900">{{ $withdrawal->transaction_date->format('Y-m-d') }}</p></div>
                <div><p class="text-xs uppercase tracking-wide text-slate-500">Bank Reference</p><p class="mt-1 font-semibold text-slate-900">{{ $withdrawal->reference_number ?? '-' }}</p></div>
                <div><p class="text-xs uppercase tracking-wide text-slate-500">Bank Amount</p><p class="mt-1 font-semibold text-slate-900">PHP {{ number_format((float) $withdrawal->amount, 2) }}</p></div>
                <div><p class="text-xs uppercase tracking-wide text-slate-500">Matched Amount</p><p class="mt-1 font-semibold text-slate-900">PHP {{ number_format($matchedTotal, 2) }}</p></div>
                <div class="sm:col-span-2"><p class="text-xs uppercase tracking-wide text-slate-500">Description</p><p class="mt-1 font-semibold text-slate-900">{{ $withdrawal->description }}</p></div>
                <div><p class="text-xs uppercase tracking-wide text-slate-500">Reconciled By</p><p class="mt-1 font-semibold text-slate-900">{{ $withdrawal->reconciler?->name ?? trim(($withdrawal->reconciler?->first_name ?? '').' '.($withdrawal->reconciler?->last_name ?? '')) ?: '-' }}</p></div>
                <div><p class="text-xs uppercase tracking-wide text-slate-500">Reconciled At</p><p class="mt-1 font-semibold text-slate-900">{{ $withdrawal->reconciled_at?->format('Y-m-d H:i') ?? '-' }}</p></div>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Matched Fund Requests</h2>
            </div>
            @forelse ($withdrawal->matchedDisbursements as $disbursement)
                <div class="grid gap-2 border-t border-slate-100 px-5 py-4 text-sm md:grid-cols-[1fr_auto_auto] md:items-center">
                    <div><p class="font-medium text-slate-900">{{ $disbursement->purpose }}</p><p class="mt-1 text-xs text-slate-500">{{ $disbursement->recipient ?? 'No recipient' }}</p></div>
                    <div class="font-semibold text-slate-700">PHP {{ number_format((float) $disbursement->amount, 2) }}</div>
                    <div><span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold uppercase text-emerald-800">{{ $withdrawal->status }}</span></div>
                </div>
            @empty
                <x-dashboard.empty-state message="No fund requests were matched to this withdrawal." class="m-5" />
            @endforelse
        </section>
    </div>
@endsection
