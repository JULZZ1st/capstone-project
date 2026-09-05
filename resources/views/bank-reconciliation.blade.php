@extends('layouts.app')

@section('title', 'Bank Reconciliation | '.config('dashboard.barangay_name'))
@section('page-title', 'Bank Reconciliation Workspace')
@section('page-subtitle', 'Audit incoming wire transactions against registered community donations.')

@section('content')
    <div class="space-y-6">
        @if (session('success'))
            <x-alert type="success">{{ session('success') }}</x-alert>
        @endif
        @can('import', App\Models\BankLedgerEntry::class)
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-slate-950">Import Bank Statement</h2>
                        <p class="mt-1 text-sm text-slate-500">Upload a CSV or XLSX statement to add bank transactions for review.</p>
                    </div>
                    <form method="POST" action="{{ route('bank-reconciliation.import') }}" enctype="multipart/form-data" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        @csrf
                        <input type="file" name="statement" accept=".csv,.txt,.xlsx" required class="block w-full text-sm text-slate-600 sm:w-auto">
                        <x-button class="sm:w-auto">Import Statement</x-button>
                    </form>
                </div>
                <x-form.error name="statement" />
            </section>
        @endcan
        @if (config('bank_reconciliation.audit_lockout_enabled'))
            <div class="flex justify-end">
                <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-300 bg-white px-3 py-1.5 text-xs font-semibold text-emerald-700 shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5">
                        <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd" />
                    </svg>
                    Active Audit Lockout Enabled
                </span>
            </div>
        @endif
        <x-bank-reconciliation.audit-banner
            :unreconciled-feed-count="$unreconciledFeedCount"
            :pending-receipts-count="$pendingReceiptsCount"
        />

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center gap-2 border-b border-slate-100 px-5 py-4">
                    <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Bank Transactions to Review</h2>
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
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Donation Receipts to Match</h2>
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

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reconciliation History</h2>
            </div>
            @forelse ($history as $entry)
                <div class="grid gap-2 border-t border-slate-100 px-5 py-4 text-sm md:grid-cols-5">
                    <div><p class="text-xs text-slate-500">Bank transaction</p><p class="font-medium text-slate-900">{{ $entry->description }}</p></div>
                    <div><p class="text-xs text-slate-500">Donation receipt</p><p class="font-medium text-slate-900">{{ $entry->matchedContribution?->receipt_number ?? '—' }}</p></div>
                    <div><p class="text-xs text-slate-500">Donor</p><p class="text-slate-700">{{ $entry->matchedContribution?->donor?->full_name ?? '—' }}</p></div>
                    <div><p class="text-xs text-slate-500">Matched at</p><p class="text-slate-700">{{ $entry->matched_at?->format('Y-m-d H:i') ?? '—' }}</p></div>
                    <div><p class="text-xs text-slate-500">Matched by</p><p class="text-slate-700">{{ $entry->matcher?->name ?? trim(($entry->matcher?->first_name ?? '').' '.($entry->matcher?->last_name ?? '')) ?: '—' }}</p></div>
                </div>
            @empty
                <x-dashboard.empty-state message="No reconciled transactions yet." class="m-5" />
            @endforelse
        </section>
    </div>
@endsection
