@extends('layouts.app')

@section('title', 'Dashboard | '.config('dashboard.barangay_name'))
@section('page-title', 'Dashboard')

@section('content')
    @php
        $summary = $dashboard['summary'];
        $money = fn ($amount) => 'PHP '.number_format((float) $amount, 2);
        $greetingUser = auth()->user();
        $greetingName = $greetingUser?->first_name ?: $greetingUser?->name ?: 'there';
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-slate-500">{{ now()->format('l, F j, Y') }}</p>
                <h2 class="mt-1 text-2xl font-bold text-slate-950">Welcome back, {{ $greetingName }}! <span aria-hidden="true">👋</span></h2>
                <p class="mt-1 text-sm text-slate-500">Here's what's happening across donation operations today.</p>
            </div>
            <a
                href="{{ route('dashboard') }}"
                class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
            >
                <x-icon name="refresh" class="h-4 w-4" />
                Refresh
            </a>
        </div>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-dashboard.summary-card
                label="Total Cash Ledger"
                :value="$money($summary['total_cash_ledger'])"
                description="Verified and approved cash entries."
                icon="wallet"
                tone="positive"
            />
            <x-dashboard.summary-card
                label="Pending Cash Verification"
                :value="number_format($summary['pending_cash_verification'])"
                description="Cash entries awaiting audit."
                icon="clock"
                tone="warning"
            />
            <x-dashboard.summary-card
                label="In-Kind Batches"
                :value="number_format($summary['in_kind_batches'])"
                description="Inventory records currently tracked."
                icon="cube"
                tone="info"
            />
            <x-dashboard.summary-card
                label="Registered Donors"
                :value="number_format($summary['registered_donors'])"
                description="Donor profiles available."
                icon="users"
                tone="purple"
            />
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <x-dashboard.panel title="Treasurer Action Items" subtitle="Cash and reconciliation work needing attention." icon="alert-triangle" tone="warning">
                @php($actionItems = $dashboard['treasurer_action_items'])
                <div class="mb-3 flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2.5">
                    <span class="text-sm text-slate-600">Total cash disbursed</span>
                    <span class="text-sm font-semibold text-slate-950">{{ $money($dashboard['disbursements']['total_disbursed']) }}</span>
                </div>
                @if ($actionItems['pending_cash_count'] === 0 && $actionItems['unreconciled_ledger_count'] === 0 && $actionItems['bank_withdrawals_awaiting_reconciliation'] === 0)
                    <x-dashboard.empty-state icon="alert-triangle" message="No treasurer action items right now." action="{{ route('bank-reconciliation.index') }}" action-label="View Today" />
                @else
                    <dl class="space-y-3">
                        <div class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2.5">
                            <dt class="text-sm text-slate-600">Pending cash verification</dt>
                            <dd class="text-sm font-semibold text-slate-950">{{ number_format($actionItems['pending_cash_count']) }}</dd>
                        </div>
                        <div class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2.5">
                            <dt class="text-sm text-slate-600">Bank ledger entries awaiting review</dt>
                            <dd class="text-sm font-semibold text-slate-950">{{ number_format($actionItems['unreconciled_ledger_count']) }}</dd>
                        </div>
                        <a href="{{ route('bank-reconciliation.index', ['tab' => 'outgoing']) }}" class="flex items-center justify-between gap-4 rounded-xl bg-slate-50 px-3 py-2.5 transition hover:bg-slate-100">
                            <span class="text-sm text-slate-600">Bank Withdrawals Awaiting Reconciliation</span>
                            <span class="text-sm font-semibold text-slate-950">{{ number_format($actionItems['bank_withdrawals_awaiting_reconciliation']) }}</span>
                        </a>
                        <a href="{{ route('bank-reconciliation.index', ['tab' => 'outgoing']) }}" class="flex items-center justify-between gap-4 rounded-xl bg-slate-50 px-3 py-2.5 transition hover:bg-slate-100">
                            <span class="text-sm text-slate-600">Discrepancies Requiring Review</span>
                            <span class="text-sm font-semibold text-slate-950">{{ number_format($actionItems['discrepancies_requiring_review']) }}</span>
                        </a>
                        <a href="{{ route('bank-reconciliation.index', ['tab' => 'outgoing']) }}" class="flex items-center justify-between gap-4 rounded-xl bg-slate-50 px-3 py-2.5 transition hover:bg-slate-100">
                            <span class="text-sm text-slate-600">Pending Liquidation / Unresolved Difference</span>
                            <span class="text-sm font-semibold text-slate-950">{{ $money($actionItems['unresolved_difference_amount']) }}</span>
                        </a>
                        @if ($actionItems['latest_reconciliation'])
                            <div class="rounded-xl bg-emerald-50 px-3 py-2.5 text-sm text-emerald-800">
                                Latest reconciliation: {{ $actionItems['latest_reconciliation']->matched_at->format('Y-m-d') }} for {{ $money($actionItems['latest_reconciliation']->amount) }}.
                            </div>
                        @endif
                    </dl>
                @endif
            </x-dashboard.panel>

            <x-dashboard.panel title="Campaign Contributions / Funding Distribution" subtitle="Verified cash grouped by funding purpose." icon="megaphone" tone="purple">
                @if ($dashboard['funding_distribution']->isEmpty())
                    <x-dashboard.empty-state icon="megaphone" message="No funding distribution available yet." action="{{ route('donations.index') }}" action-label="View Campaigns" />
                @else
                    <x-dashboard.chart
                        id="funding-distribution-chart"
                        type="bar"
                        :horizontal="true"
                        :labels="$dashboard['funding_distribution']->pluck('label')->all()"
                        :values="$dashboard['funding_distribution']->pluck('total')->all()"
                        :colors="['#7c3aed']"
                        value-prefix="PHP "
                        :height="max(140, $dashboard['funding_distribution']->count() * 48)"
                        aria-label="Verified cash contributions grouped by campaign"
                    />
                    <ul class="mt-4 space-y-2">
                        @foreach ($dashboard['funding_distribution'] as $item)
                            <li class="flex items-center justify-between gap-4 rounded-xl bg-slate-50 px-3 py-2">
                                <span class="truncate text-sm text-slate-600">{{ $item->label }}</span>
                                <span class="text-sm font-semibold text-slate-950">{{ $money($item->total) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-dashboard.panel>

            <x-dashboard.panel title="Recent Cash Donations Awaiting Audit" subtitle="Latest pending cash entries." icon="search" tone="rose">
                @if ($dashboard['recent_cash_awaiting_audit']->isEmpty())
                    <x-dashboard.empty-state icon="search" message="No cash donations awaiting audit." action="{{ route('donations.index') }}" action-label="View Pending" />
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($dashboard['recent_cash_awaiting_audit'] as $contribution)
                            <li class="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-rose-100 text-sm font-semibold text-rose-700">
                                    {{ strtoupper(substr((string) ($contribution->donor?->full_name ?? '?'), 0, 1)) }}
                                </span>
                                <div class="flex flex-1 items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-slate-950">{{ $contribution->donor?->full_name ?? 'Unknown donor' }}</p>
                                        <p class="text-sm text-slate-600">{{ config("donations.payment_methods.{$contribution->payment_method}") ?? 'Cash' }}</p>
                                    </div>
                                    <p class="shrink-0 text-sm font-semibold text-slate-950">{{ $money($contribution->amount) }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-dashboard.panel>

            <x-dashboard.panel title="System Log Activity" subtitle="Recent operational records derived from current tables." icon="terminal" tone="info">
                <x-dashboard.activity-list :items="$dashboard['system_log_activity']" />
            </x-dashboard.panel>

            <x-dashboard.panel title="Secretary In-Kind Control Panel" subtitle="Inventory status and recent in-kind records." icon="clipboard-list" tone="positive">
                <div class="grid gap-6 lg:grid-cols-2">
                    <div>
                        <h3 class="mb-3 text-sm font-semibold text-slate-700">Inventory by status</h3>
                        @php($statusCounts = $dashboard['secretary_in_kind_control']['status_counts'])
                        @if ($statusCounts->isEmpty())
                            <x-dashboard.empty-state icon="cube" message="No inventory records yet." />
                        @else
                            @php($statusColors = ['#10b981', '#f59e0b', '#64748b', '#ef4444', '#0ea5e9'])
                            <x-dashboard.chart
                                id="inventory-status-chart"
                                type="doughnut"
                                :labels="$statusCounts->map(fn ($row) => ucfirst($row->status))->all()"
                                :values="$statusCounts->pluck('total')->all()"
                                :colors="$statusColors"
                                :height="180"
                                aria-label="Inventory items grouped by status"
                            />
                            <x-dashboard.chart-legend
                                :items="$statusCounts->map(fn ($row) => ['label' => ucfirst($row->status), 'value' => number_format($row->total)])->all()"
                                :colors="$statusColors"
                            />
                        @endif
                    </div>
                    <div>
                        <h3 class="mb-3 text-sm font-semibold text-slate-700">Recent in-kind records</h3>
                        @if ($dashboard['secretary_in_kind_control']['recent_items']->isEmpty())
                            <x-dashboard.empty-state icon="archive" message="No in-kind donations recorded yet." action="{{ route('inventory.index') }}" action-label="View Inventory" />
                        @else
                            <ul class="space-y-2.5">
                                @foreach ($dashboard['secretary_in_kind_control']['recent_items'] as $item)
                                    <li class="rounded-xl bg-slate-50 px-3 py-2.5">
                                        <p class="text-sm font-medium text-slate-950">{{ $item->name }}</p>
                                        <p class="text-sm text-slate-600">{{ $item->category }} &middot; Qty {{ number_format($item->quantity) }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ ucfirst($item->status) }}</p>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </x-dashboard.panel>

            <x-dashboard.panel title="Recent Donations Stream" subtitle="Latest donation records with donor context." icon="heart" tone="rose">
                @if ($dashboard['recent_donations']->isEmpty())
                    <x-dashboard.empty-state icon="heart" message="No donations recorded yet." action="{{ route('donations.index') }}" action-label="View Donations" />
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($dashboard['recent_donations'] as $donation)
                            <li class="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm font-semibold text-blue-700">
                                    {{ strtoupper(substr((string) ($donation->donor?->full_name ?? '?'), 0, 1)) }}
                                </span>
                                <div class="flex flex-1 items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-slate-950">{{ $donation->donor?->full_name ?? 'Unknown donor' }}</p>
                                        <p class="text-sm text-slate-600">{{ config("donations.types.{$donation->type}") }}</p>
                                    </div>
                                    @if ($donation->type === 'cash')
                                        <p class="shrink-0 text-sm font-semibold text-slate-950">{{ $money($donation->amount) }}</p>
                                    @else
                                        <p class="shrink-0 text-sm font-semibold text-slate-950">{{ number_format($donation->items_count) }} item(s)</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-dashboard.panel>

            <x-dashboard.panel title="Recent Cash Disbursements" subtitle="Latest outgoing cash payments recorded." icon="banknotes" tone="info">
                @if ($dashboard['disbursements']['recent']->isEmpty())
                    <x-dashboard.empty-state icon="banknotes" message="No cash disbursements have been recorded yet." action="{{ route('disbursements.index') }}" action-label="Record Disbursement" />
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($dashboard['disbursements']['recent'] as $disbursement)
                            <li class="py-3 first:pt-0 last:pb-0">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-medium text-slate-950">{{ $disbursement->purpose }}</p>
                                        <p class="text-sm text-slate-600">{{ $disbursement->requester->name }} &middot; {{ ucfirst($disbursement->status) }}</p>
                                    </div>
                                    <p class="text-sm font-semibold text-slate-950">{{ $money($disbursement->amount) }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-dashboard.panel>
        </section>
    </div>
@endsection
