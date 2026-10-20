@extends('layouts.app')

@section('title', 'Bank Reconciliation | '.config('dashboard.barangay_name'))
@section('page-title', 'Bank Reconciliation Workspace')
@section('page-subtitle', 'Audit bank transactions against registered community donations.')

@section('content')
    @php
        $activeTab = request('tab') === 'outgoing' ? 'outgoing' : 'incoming';
    @endphp

    <div class="space-y-6">
        @if (session('success'))
            <x-alert type="success">{{ session('success') }}</x-alert>
        @endif
        
<div class="border-b border-slate-200 pb-4">
    <nav class="flex flex-wrap gap-3" aria-label="Bank reconciliation sections">
        @foreach (['incoming' => '↓ Incoming Funds', 'outgoing' => '↑ Outgoing Funds'] as $tab => $label)
            <a href="{{ route('bank-reconciliation.index', ['tab' => $tab]) }}"
               class="inline-flex items-center justify-center rounded-lg border px-5 py-3 text-sm font-semibold transition {{ $activeTab === $tab ? 'border-blue-600 bg-blue-600 text-white shadow-md' : 'border-slate-200 bg-white text-slate-600 hover:border-blue-300 hover:bg-blue-50' }}">
                {{ $label }}
            </a>
        @endforeach
    </nav>
</div>

        @can('viewAny', App\Models\BankLedgerEntry::class)
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-slate-950">Reconciliation Period</h2>
                        <p class="mt-1 text-sm text-slate-500">Create a period using raw bank balances. Movement totals and the financial ledger balance are calculated from stored records.</p>
                    </div>
                    @can('prepare', App\Models\BankReconciliation::class)
                        <form method="POST" action="{{ route('bank-reconciliation.periods.store') }}" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
                            @csrf
                            <input name="period_start" type="date" required class="rounded-md border-slate-300 text-sm" aria-label="Period start">
                            <input name="period_end" type="date" required class="rounded-md border-slate-300 text-sm" aria-label="Period end">
                            <input name="opening_balance" type="number" step="0.01" min="0" required placeholder="Opening balance" class="rounded-md border-slate-300 text-sm">
                            <input name="bank_ending_balance" type="number" step="0.01" min="0" placeholder="Bank ending balance" class="rounded-md border-slate-300 text-sm">
                            <x-button class="sm:col-span-2 lg:col-span-1">Create Period</x-button>
                        </form>
                    @endcan
                </div>
                @if ($latestReconciliationPeriod)
                    @can('update', $latestReconciliationPeriod)
                        <form method="POST" action="{{ route('bank-reconciliation.periods.update', $latestReconciliationPeriod) }}" class="mt-4 grid gap-2 border-t border-slate-100 pt-4 sm:grid-cols-3">
                            @csrf @method('PATCH')
                            <input name="opening_balance" type="number" step="0.01" min="0" required value="{{ $latestReconciliationPeriod->opening_balance }}" aria-label="Draft opening balance" class="rounded-md border-slate-300 text-sm">
                            <input name="bank_ending_balance" type="number" step="0.01" min="0" required value="{{ $latestReconciliationPeriod->bank_ending_balance }}" aria-label="Draft bank ending balance" class="rounded-md border-slate-300 text-sm">
                            <x-button>Save Draft Balances</x-button>
                        </form>
                    @endcan
                    <div class="mt-4 grid gap-3 border-t border-slate-100 pt-4 text-sm sm:grid-cols-2 lg:grid-cols-5">
                        <div><p class="text-xs text-slate-500">Latest period</p><p class="font-semibold text-slate-900">{{ $latestReconciliationPeriod->period_start?->format('M d, Y') }} – {{ $latestReconciliationPeriod->period_end?->format('M d, Y') }}</p><span class="text-xs font-semibold uppercase text-slate-500">{{ str_replace('_', ' ', $latestReconciliationPeriod->status) }}</span></div>
                        <div><p class="text-xs text-slate-500">Bank movements</p><p class="font-semibold text-slate-900">In PHP {{ number_format((float) $latestReconciliationPeriod->calculated_bank_cash_in, 2) }} / Out PHP {{ number_format((float) $latestReconciliationPeriod->calculated_bank_cash_out, 2) }}</p></div>
                        <div><p class="text-xs text-slate-500">Financial ledger ending</p><p class="font-semibold text-slate-900">PHP {{ number_format((float) $latestReconciliationPeriod->financial_ledger_ending_balance, 2) }}</p></div>
                        <div><p class="text-xs text-slate-500">Raw bank ending</p><p class="font-semibold text-slate-900">{{ $latestReconciliationPeriod->bank_ending_balance === null ? 'Not entered' : 'PHP '.number_format((float) $latestReconciliationPeriod->bank_ending_balance, 2) }}</p></div>
                        <div><p class="text-xs text-slate-500">Difference</p><p class="font-semibold {{ (float) $latestReconciliationPeriod->reconciliation_difference === 0.0 ? 'text-emerald-700' : 'text-amber-700' }}">{{ $latestReconciliationPeriod->reconciliation_difference === null ? 'Not calculated' : 'PHP '.number_format((float) $latestReconciliationPeriod->reconciliation_difference, 2) }}</p></div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2 border-t border-slate-100 pt-3">
                        @can('submit', $latestReconciliationPeriod)
                            <form method="POST" action="{{ route('bank-reconciliation.periods.submit', $latestReconciliationPeriod) }}">@csrf<x-button>Submit for Review</x-button></form>
                        @endcan
                        @can('approve', $latestReconciliationPeriod)
                            <form method="POST" action="{{ route('bank-reconciliation.periods.approve', $latestReconciliationPeriod) }}">@csrf<x-button>Approve &amp; Lock</x-button></form>
                            <form method="POST" action="{{ route('bank-reconciliation.periods.reject', $latestReconciliationPeriod) }}" class="flex gap-2">@csrf<input name="reason" required placeholder="Correction reason" class="rounded-md border-slate-300 text-sm"><x-button type="submit">Return for Correction</x-button></form>
                        @endcan
                        @can('reopen', $latestReconciliationPeriod)
                            <form method="POST" action="{{ route('bank-reconciliation.periods.reopen', $latestReconciliationPeriod) }}" class="flex gap-2">@csrf<input name="reason" required placeholder="Reopen reason" class="rounded-md border-slate-300 text-sm"><x-button type="submit">Reopen for Correction</x-button></form>
                        @endcan
                    </div>
                    @if (auth()->user()->can('create', App\Models\BankLedgerEntry::class) || $latestReconciliationPeriod->discrepancies->isNotEmpty())
                    <div class="mt-4 border-t border-slate-100 pt-4">
                        <h3 class="text-sm font-semibold text-slate-900">Record discrepancy</h3>
                        @can('create', App\Models\BankLedgerEntry::class)
                            <form method="POST" action="{{ route('bank-reconciliation.discrepancies.store', $latestReconciliationPeriod) }}" class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                                @csrf
                                <select name="type" required class="rounded-md border-slate-300 text-sm"><option value="">Discrepancy type</option><option value="unmatched_amount">Unmatched amount</option><option value="bank_charge">Bank charge</option><option value="interest">Interest</option><option value="outstanding_check">Outstanding check</option><option value="deposit_in_transit">Deposit in transit</option><option value="recording_error">Recording error</option></select>
                                <select name="classification" required class="rounded-md border-slate-300 text-sm"><option value="">Classification</option><option value="accounting_adjustment">Accounting adjustment</option><option value="timing_difference">Timing difference</option></select>
                                <input name="amount" type="number" step="0.01" min="0.01" required placeholder="Amount" class="rounded-md border-slate-300 text-sm">
                                <input name="evidence_reference" required placeholder="Evidence/reference" class="rounded-md border-slate-300 text-sm">
                                <textarea name="explanation" required placeholder="Explanation" class="rounded-md border-slate-300 text-sm sm:col-span-2"></textarea>
                                <select name="resolution_status" required class="rounded-md border-slate-300 text-sm"><option value="unresolved">Unresolved</option><option value="resolved">Resolved</option><option value="accounted">Accounted</option></select>
                                <input name="adjustment_amount" type="number" step="0.01" placeholder="Signed adjustment (accounting only)" class="rounded-md border-slate-300 text-sm">
                                <label class="flex items-center gap-2 text-sm text-slate-600"><input name="carry_forward" type="checkbox" value="1"> Carry forward timing item</label>
                                <x-button class="sm:col-span-2 lg:col-span-1">Save discrepancy</x-button>
                            </form>
                        @endcan
                        @if ($latestReconciliationPeriod->discrepancies->isNotEmpty())
                            <div class="mt-3 space-y-2 text-sm">
                                @foreach ($latestReconciliationPeriod->discrepancies as $discrepancy)
                                    <div class="rounded border border-slate-200 p-2"><span class="font-semibold">{{ ucwords(str_replace('_', ' ', $discrepancy->type)) }}</span> — PHP {{ number_format((float) $discrepancy->amount, 2) }} — {{ ucfirst($discrepancy->resolution_status) }}<span class="block text-xs text-slate-500">{{ $discrepancy->explanation }}</span></div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    @endif
                @endif
                <x-form.error name="period_start" />
                <x-form.error name="period_end" />
                <x-form.error name="opening_balance" />
                <x-form.error name="bank_ending_balance" />
                <x-form.error name="period" />
            </section>
        @endcan

        @if ($activeTab === 'incoming')
            @can('import', App\Models\BankLedgerEntry::class)
                <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-slate-950">Import Bank Statement</h2>
                            <p class="mt-1 text-sm text-slate-500">Upload a CSV or XLSX statement to add bank transactions for review.</p>
                        </div>
                        <form method="POST" action="{{ route('bank-reconciliation.import') }}" enctype="multipart/form-data" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            @csrf
                            <input type="hidden" name="return_tab" value="incoming">
                            <input type="file" name="statement" accept=".csv,.txt,.xlsx" required class="block w-full text-sm text-slate-600 sm:w-auto">
                            <x-button class="sm:w-auto">Import Statement</x-button>
                        </form>
                    </div>
                    <x-form.error name="statement" />
                </section>
            @endcan

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
                        <div><p class="text-xs text-slate-500">Donation receipt</p><p class="font-medium text-slate-900">{{ $entry->matchedContribution?->receipt_number ?? '-' }}</p></div>
                        <div><p class="text-xs text-slate-500">Donor</p><p class="text-slate-700">{{ $entry->matchedContribution?->donor?->full_name ?? '-' }}</p></div>
                        <div><p class="text-xs text-slate-500">Matched at</p><p class="text-slate-700">{{ $entry->matched_at?->format('Y-m-d H:i') ?? '-' }}</p></div>
                        <div><p class="text-xs text-slate-500">Matched by</p><p class="text-slate-700">{{ $entry->matcher?->name ?? trim(($entry->matcher?->first_name ?? '').' '.($entry->matcher?->last_name ?? '')) ?: '-' }}</p></div>
                    </div>
                @empty
                    <x-dashboard.empty-state message="No reconciled transactions yet." class="m-5" />
                @endforelse
            </section>
        @else
            @can('create', App\Models\BankLedgerEntry::class)
                <div class="grid gap-6 lg:grid-cols-[1fr_24rem]">
                    <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-base font-semibold text-slate-950">Record Bank Withdrawal</h2>
                        <form method="POST" action="{{ route('bank-reconciliation.withdrawals.store', ['tab' => 'outgoing']) }}" class="mt-4 grid gap-4 md:grid-cols-2">
                            @csrf
                            <x-form.input name="transaction_date" label="Transaction Date" type="date" required />
                            <x-form.input name="reference_number" label="Reference Number" required />
                            <x-form.input name="description" label="Description" required />
                            <x-form.input name="amount" label="Amount" type="number" step="0.01" min="0.01" required />
                            <input type="hidden" name="transaction_type" value="withdrawal">
                            <div class="flex items-end md:col-span-2">
                                <x-button>Record Withdrawal</x-button>
                            </div>
                        </form>
                    </section>

                    <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-base font-semibold text-slate-950">Import Withdrawals</h2>
                        <p class="mt-1 text-sm text-slate-500">CSV or XLSX rows with transaction_type set to withdrawal are added for outgoing review.</p>
                        <form method="POST" action="{{ route('bank-reconciliation.import') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                            @csrf
                            <input type="hidden" name="return_tab" value="outgoing">
                            <input type="file" name="statement" accept=".csv,.txt,.xlsx" required class="block w-full text-sm text-slate-600">
                            <x-button class="w-full">Import Statement</x-button>
                        </form>
                        <x-form.error name="statement" />
                    </section>
                </div>
            @endcan

            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                        <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Bank Withdrawals to Review</h2>
                    </div>
                    <span class="text-xs font-semibold text-slate-500">{{ $withdrawalsToReviewCount }} {{ \Illuminate\Support\Str::plural('entry', $withdrawalsToReviewCount) }}</span>
                </div>

                @if ($withdrawals->isEmpty())
                    <x-dashboard.empty-state message="No bank withdrawals are awaiting review." class="m-5" />
                @else
                    @foreach ($withdrawals as $withdrawal)
                    @php
                        $selectedDisbursements = $withdrawal->matchedDisbursements;
                        $candidateDisbursements = $eligibleDisbursements
                            ->concat($selectedDisbursements)
                            ->unique('id')
                            ->sortByDesc('approved_at')
                            ->values();
                        $selectedIds = $selectedDisbursements->pluck('id')->map(fn ($id) => (string) $id);
                        $selectedTotal = (float) $selectedDisbursements->sum(fn ($disbursement) => (float) $disbursement->amount);
                        $difference = (float) $withdrawal->amount - $selectedTotal;
                    @endphp

                    <div class="border-t border-slate-100 px-5 py-4 text-sm">
                        <div class="grid gap-3 md:grid-cols-5">
                            <div><p class="text-xs text-slate-500">Transaction Date</p><p class="font-medium text-slate-900">{{ $withdrawal->transaction_date->format('Y-m-d') }}</p></div>
                            <div><p class="text-xs text-slate-500">Reference Number</p><p class="font-medium text-slate-900">{{ $withdrawal->reference_number }}</p></div>
                            <div class="md:col-span-2"><p class="text-xs text-slate-500">Description</p><p class="font-medium text-slate-900">{{ $withdrawal->description }}</p></div>
                            <div class="md:text-right"><p class="text-xs text-slate-500">Amount</p><p class="font-semibold text-slate-950">PHP {{ number_format((float) $withdrawal->amount, 2) }}</p></div>
                            <div><p class="text-xs text-slate-500">Transaction Type</p><p class="font-medium text-slate-900">{{ $withdrawal->transaction_type }}</p></div>
                            <div><p class="text-xs text-slate-500">Status</p><span class="mt-1 inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold uppercase text-slate-700">{{ $withdrawal->status }}</span></div>
                        </div>

                        <div class="mt-4 rounded-md border border-slate-200 bg-slate-50 p-4">
                            <div class="grid gap-3 md:grid-cols-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Bank Withdrawal Amount</p>
                                    <p class="mt-1 font-semibold text-slate-950">PHP {{ number_format((float) $withdrawal->amount, 2) }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Selected Disbursements Total</p>
                                    <p class="mt-1 font-semibold text-slate-950" data-selected-total-for="{{ $withdrawal->id }}">PHP {{ number_format($selectedTotal, 2) }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Difference</p>
                                    <p class="mt-1 font-semibold text-slate-950" data-difference-for="{{ $withdrawal->id }}">PHP {{ number_format($difference, 2) }}</p>
                                    <span @class([
                                        'mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => abs($difference) < 0.005,
                                        'bg-red-100 text-red-800' => abs($difference) >= 0.005,
                                    ]) data-status-for="{{ $withdrawal->id }}">
                                        {{ abs($difference) < 0.005 ? 'Ready to Reconcile' : 'Discrepancy' }}
                                    </span>
                                    @if (abs($difference) >= 0.005 && $withdrawal->discrepancy_reason)
                                        <p class="mt-2 text-xs text-red-800"><span class="font-semibold">Reason:</span> {{ $withdrawal->discrepancy_reason }}</p>
                                    @endif
                                </div>
                            </div>

                            @can('matchWithdrawal', $withdrawal)
                                <form method="POST" action="{{ route('bank-reconciliation.withdrawals.matches.store', $withdrawal) }}" class="mt-4 space-y-3" data-withdrawal-match data-withdrawal-id="{{ $withdrawal->id }}" data-withdrawal-amount="{{ (float) $withdrawal->amount }}">
                                    @csrf
                                    @if ($candidateDisbursements->isNotEmpty())
                                        <div class="grid gap-2 lg:grid-cols-2">
                                            @foreach ($candidateDisbursements as $disbursement)
                                                <label class="flex gap-3 rounded-md border border-slate-200 bg-white p-3">
                                                    <input type="checkbox" name="disbursement_ids[]" value="{{ $disbursement->id }}" data-disbursement-amount="{{ (float) $disbursement->amount }}" @checked($selectedIds->contains((string) $disbursement->id)) class="mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                                    <span class="min-w-0">
                                                        <span class="block font-medium text-slate-900">{{ $disbursement->purpose }}</span>
                                                        <span class="mt-1 block text-xs text-slate-500">{{ $disbursement->recipient ?? 'No recipient' }} - Approved {{ $disbursement->approved_at?->format('Y-m-d') ?? 'date unavailable' }}</span>
                                                        <span class="mt-1 block text-xs font-semibold text-slate-700">PHP {{ number_format((float) $disbursement->amount, 2) }}</span>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                        @if (abs($difference) >= 0.005)
                                            <x-form.input name="discrepancy_reason" label="Explanation / Reason" required value="{{ old('discrepancy_reason', $withdrawal->discrepancy_reason) }}" />
                                        @else
                                            <input type="hidden" name="discrepancy_reason" value="">
                                        @endif
                                        <x-form.error name="disbursement_ids" />
                                        <x-form.error name="withdrawal" />
                                        <x-form.error name="discrepancy_reason" />
                                        <div class="flex flex-wrap gap-2">
                                            <x-button>Save Match Selection</x-button>
                                            <x-button
                                                type="submit"
                                                formaction="{{ route('bank-reconciliation.withdrawals.reconcile', $withdrawal) }}"
                                                name="finalize"
                                                value="1"
                                                data-finalize-button
                                                disabled
                                                class="bg-emerald-700 hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"
                                            >Confirm Reconciliation</x-button>
                                        </div>
                                        <x-form.error name="finalize" />
                                    @else
                                        <x-dashboard.empty-state message="No approved unreconciled disbursements are available to match." />
                                    @endif
                                </form>
                            @else
                                @if ($selectedDisbursements->isNotEmpty())
                                    <div class="mt-4 grid gap-2 lg:grid-cols-2">
                                        @foreach ($selectedDisbursements as $disbursement)
                                            <div class="rounded-md border border-slate-200 bg-white p-3">
                                                <p class="font-medium text-slate-900">{{ $disbursement->purpose }}</p>
                                                <p class="mt-1 text-xs text-slate-500">{{ $disbursement->recipient ?? 'No recipient' }}</p>
                                                <p class="mt-1 text-xs font-semibold text-slate-700">PHP {{ number_format((float) $disbursement->amount, 2) }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            @endcan
                        </div>
                    </div>
                    @endforeach
                @endif
            </section>

            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reconciliation History</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Date</th>
                                <th class="px-5 py-3 font-semibold">Bank Reference</th>
                                <th class="px-5 py-3 font-semibold">Type</th>
                                <th class="px-5 py-3 text-right font-semibold">Bank Amount</th>
                                <th class="px-5 py-3 text-right font-semibold">Matched Amount</th>
                                <th class="px-5 py-3 text-right font-semibold">Difference</th>
                                <th class="px-5 py-3 font-semibold">Status</th>
                                <th class="px-5 py-3 font-semibold">Reconciled By</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($withdrawalHistory as $entry)
                                @php
                                    $matchedAmount = $entry->matchedDisbursements->sum(fn ($disbursement) => (float) $disbursement->amount);
                                @endphp
                                <tr class="border-t border-slate-100 align-top">
                                    <td class="whitespace-nowrap px-5 py-4 text-slate-700">{{ $entry->transaction_date->format('Y-m-d') }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 font-medium text-slate-900">{{ $entry->reference_number ?? '-' }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $entry->transaction_type }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right text-slate-700">PHP {{ number_format((float) $entry->amount, 2) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right text-slate-700">PHP {{ number_format($matchedAmount, 2) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right text-slate-700">PHP {{ number_format((float) $entry->amount - $matchedAmount, 2) }}</td>
                                    <td class="px-5 py-4"><span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold uppercase text-emerald-800">{{ $entry->status }}</span></td>
                                    <td class="whitespace-nowrap px-5 py-4 text-slate-700">{{ $entry->reconciler?->name ?? trim(($entry->reconciler?->first_name ?? '').' '.($entry->reconciler?->last_name ?? '')) ?: '-' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right"><a href="{{ route('bank-reconciliation.withdrawals.show', $entry) }}" class="font-semibold text-blue-700 hover:text-blue-900">View Details</a></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-5 py-6"><x-dashboard.empty-state message="No reconciled withdrawals yet." /></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>

    <script>
        document.querySelectorAll('[data-withdrawal-match]').forEach((form) => {
            const withdrawalId = form.dataset.withdrawalId;
            const withdrawalAmount = Number.parseFloat(form.dataset.withdrawalAmount || '0');
            const totalEl = document.querySelector(`[data-selected-total-for="${withdrawalId}"]`);
            const differenceEl = document.querySelector(`[data-difference-for="${withdrawalId}"]`);
            const statusEl = document.querySelector(`[data-status-for="${withdrawalId}"]`);
            const finalizeButton = form.querySelector('[data-finalize-button]');
            const discrepancyReason = form.querySelector('[name="discrepancy_reason"]');
            const formatter = new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            const updateTotals = () => {
                const selectedTotal = Array.from(form.querySelectorAll('input[type="checkbox"]:checked'))
                    .reduce((sum, input) => sum + Number.parseFloat(input.dataset.disbursementAmount || '0'), 0);
                const difference = withdrawalAmount - selectedTotal;
                const ready = Math.abs(difference) < 0.005;

                totalEl.textContent = `PHP ${formatter.format(selectedTotal)}`;
                differenceEl.textContent = `PHP ${formatter.format(difference)}`;
                statusEl.textContent = ready ? 'Ready to Reconcile' : 'Discrepancy';
                statusEl.classList.toggle('bg-emerald-100', ready);
                statusEl.classList.toggle('text-emerald-800', ready);
                statusEl.classList.toggle('bg-red-100', ! ready);
                statusEl.classList.toggle('text-red-800', ! ready);
                if (finalizeButton) {
                    finalizeButton.disabled = ! ready;
                }
                if (discrepancyReason) {
                    discrepancyReason.required = ! ready;
                }
            };

            form.addEventListener('change', updateTotals);
            updateTotals();
        });
    </script>
@endsection
