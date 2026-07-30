@extends('layouts.app')

@section('title', 'Bank Reconciliation | '.config('dashboard.barangay_name'))
@section('page-title', 'Bank Reconciliation')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-950">Bank Reconciliation Workspace</h1>
                <p class="mt-1 text-sm text-slate-500">Audit incoming wire transactions against registered community donations.</p>
            </div>

            @if (config('bank_reconciliation.audit_lockout_enabled'))
                <span class="inline-flex items-center gap-1.5 self-start rounded-full border border-emerald-300 px-3 py-1.5 text-xs font-semibold text-emerald-700">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5">
                        <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd" />
                    </svg>
                    Active Audit Lockout Enabled
                </span>
            @endif
        </div>

        <x-bank-reconciliation.audit-banner
            :unreconciled-feed-count="$unreconciledFeedCount"
            :pending-receipts-count="$pendingReceiptsCount"
        />

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center gap-2 border-b border-slate-100 px-5 py-4">
                    <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Live Bank Ledger Feed</h2>
                </div>

                @forelse ($ledgerEntries as $entry)
                    <x-bank-reconciliation.ledger-entry
                        :entry="$entry"
                        :suggestions="$suggestions[$entry->id]"
                        :pending-receipts="$pendingReceipts"
                    />
                @empty
                    <x-dashboard.empty-state message="No unreconciled bank statement entries right now." class="m-5" />
                @endforelse
            </section>

            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center gap-2 border-b border-slate-100 px-5 py-4">
                    <span class="h-2 w-2 rounded-full bg-violet-500"></span>
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pending Receipts Workspace</h2>
                </div>

                @if ($pendingReceipts->isNotEmpty())
                    <p class="flex items-center gap-2 bg-slate-50 px-5 py-3 text-xs text-slate-500">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 flex-none"><path fill-rule="evenodd" d="M4.75 3A1.75 1.75 0 003 4.75v10.5C3 16.216 3.784 17 4.75 17h10.5A1.75 1.75 0 0017 15.25V4.75A1.75 1.75 0 0015.25 3H4.75zM6 7.25a.75.75 0 01.75-.75h6.5a.75.75 0 010 1.5h-6.5A.75.75 0 016 7.25zm.75 2.75a.75.75 0 000 1.5h6.5a.75.75 0 000-1.5h-6.5zm0 3.5a.75.75 0 000 1.5h3.5a.75.75 0 000-1.5h-3.5z" clip-rule="evenodd" /></svg>
                        Select a bank ledger statement row on the left to activate smart match verification.
                    </p>
                @endif

                @forelse ($pendingReceipts as $contribution)
                    <x-bank-reconciliation.receipt-card :contribution="$contribution" />
                @empty
                    <x-dashboard.empty-state message="No electronic receipts are awaiting bank reconciliation." class="m-5" />
                @endforelse
            </section>
        </div>
    </div>
@endsection
