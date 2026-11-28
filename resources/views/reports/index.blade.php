@extends('layouts.app')

@section('title', 'Report Generator | '.config('dashboard.barangay_name'))
@section('page-title', 'Report Generator')
@section('page-subtitle', 'Compact operational reports built from current system records.')

@section('content')
    @php
        $tabs = [
            'financial' => 'Financial Report',
            'inventory' => 'Inventory Report',
            'audit' => 'Audit Trail Report',
            'reconciliation' => 'Reconciliation Report',
            'financial-ledgers' => 'Financial Ledgers',
            'in-kind-audits' => 'In-Kind Audits',
            'philanthropy-roster' => 'Philanthropy Roster',
        ];
        $tabs = array_intersect_key($tabs, array_flip(($allowedTabs ?? collect())->all()));
        $monthTabs = ['financial', 'inventory', 'financial-ledgers', 'in-kind-audits', 'philanthropy-roster'];
        $periodId = request('period_id', $reconciliationPeriods->first()?->id);
        $exportParams = ['year' => $year, 'month' => $month];
        if ($tab === 'audit') $exportParams = ['from' => $from, 'to' => $to];
        if ($tab === 'reconciliation') $exportParams = ['period_id' => $periodId];
        if ($tab === 'philanthropy-roster') $exportParams += ['search' => $search, 'sort' => $sort];
        $exportRoutes = [
            'financial' => ['reports.financial.pdf', 'reports.financial.csv'],
            'inventory' => ['reports.inventory.pdf', 'reports.inventory.csv'],
            'audit' => ['reports.audit.pdf', 'reports.audit.csv'],
            'reconciliation' => ['reports.reconciliation.pdf', 'reports.reconciliation.csv'],
            'financial-ledgers' => ['reports.financial-ledgers.pdf', 'reports.financial-ledgers.csv'],
            'in-kind-audits' => ['reports.in-kind-audits.pdf', 'reports.in-kind-audits.csv'],
            'philanthropy-roster' => ['reports.philanthropy-roster.pdf', 'reports.philanthropy-roster.csv'],
        ];
    @endphp
    <div class="report-page flex h-full min-h-0 flex-col gap-4">
        <div class="report-toolbar sticky top-0 z-10 flex shrink-0 flex-wrap items-center gap-2">
            <form method="GET" action="{{ route('reports.index') }}" class="flex min-w-0 flex-1 flex-wrap items-center gap-2" data-report-filter-form>
                <div class="min-w-48 flex-1 sm:max-w-64">
                    <label for="report-type" class="sr-only">Report Type</label>
                    <select id="report-type" name="tab" class="report-filter-select w-full" data-report-autosubmit>
                        @foreach ($tabs as $key => $label)<option value="{{ $key }}" @selected($tab === $key)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                @if (in_array($tab, $monthTabs, true))
                    <div><label for="report-month" class="sr-only">Month</label><select id="report-month" name="month" class="report-filter-select" data-report-autosubmit>@foreach(range(1, 12) as $monthOption)<option value="{{ $monthOption }}" @selected($month === $monthOption)>{{ date('F', mktime(0, 0, 0, $monthOption, 1)) }}</option>@endforeach</select></div>
                    <div><label for="report-year" class="sr-only">Year</label><select id="report-year" name="year" class="report-filter-select" data-report-autosubmit>@foreach(range(now()->year - 3, now()->year) as $yearOption)<option value="{{ $yearOption }}" @selected($year === $yearOption)>{{ $yearOption }}</option>@endforeach</select></div>
                @elseif ($tab === 'audit')
                    <div><label for="audit-from" class="block text-xs font-semibold text-slate-600">From</label><input id="audit-from" name="from" type="date" value="{{ $from }}" class="report-filter-input mt-1" data-report-autosubmit></div>
                    <div><label for="audit-to" class="block text-xs font-semibold text-slate-600">To</label><input id="audit-to" name="to" type="date" value="{{ $to }}" class="report-filter-input mt-1" data-report-autosubmit></div>
                @elseif ($tab === 'reconciliation' && $reconciliationPeriods->isNotEmpty())
                    <div class="min-w-64 flex-1"><label for="reconciliation-period" class="block text-xs font-semibold text-slate-600">Period</label><select id="reconciliation-period" name="period_id" class="report-filter-select mt-1 w-full" data-report-autosubmit>@foreach($reconciliationPeriods as $periodOption)<option value="{{ $periodOption->id }}" @selected((int)$periodId === $periodOption->id)>{{ $periodOption->period_start?->format('Y-m-d') }} – {{ $periodOption->period_end?->format('Y-m-d') }}</option>@endforeach</select></div>
                @endif
                @if ($tab === 'philanthropy-roster')
                    <div class="min-w-48 flex-1"><label for="report-search" class="block text-xs font-semibold text-slate-600">Search Donor</label><input id="report-search" name="search" type="search" value="{{ $search }}" class="report-filter-input mt-1 w-full" placeholder="Name, email, or phone" data-report-search></div>
                    <div><label for="report-sort" class="block text-xs font-semibold text-slate-600">Sort</label><select id="report-sort" name="sort" class="report-filter-select mt-1" data-report-autosubmit><option value="highest" @selected(!$sort || $sort === 'highest')>Highest Giving</option><option value="lowest" @selected($sort === 'lowest')>Lowest Giving</option><option value="most_contributions" @selected($sort === 'most_contributions')>Most Contributions</option><option value="latest" @selected($sort === 'latest')>Latest Donors</option></select></div>
                @endif
            </form>
            @if ($tab !== 'reconciliation' || $reconciliationPeriods->isNotEmpty())
                <details class="relative shrink-0">
                    <summary class="ui-button ui-button--primary min-h-10 cursor-pointer list-none px-4">Export</summary>
                    <div class="absolute right-0 z-30 mt-2 w-36 rounded-lg border border-slate-200 bg-white p-1 shadow-lg">
                        <a href="{{ route($exportRoutes[$tab][0], $exportParams) }}" class="block rounded-md px-3 py-2 text-sm font-medium text-slate-700 hover:bg-blue-50 hover:text-blue-700">PDF</a>
                        <a href="{{ route($exportRoutes[$tab][1], $exportParams) }}" class="block rounded-md px-3 py-2 text-sm font-medium text-slate-700 hover:bg-blue-50 hover:text-blue-700">CSV</a>
                    </div>
                </details>
            @endif
        </div>
        @if ($tab === 'financial')
            <section class="space-y-5 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Financial Report</p>
                        <h2 class="text-lg font-bold text-slate-950">{{ $report['period']->format('F Y') }}</h2>
                    </div>
                    <span class="text-xs text-slate-500">Incoming, outgoing, and reconciliation activity</span>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                    @foreach ([['Incoming Funds Recorded', $report['summary']['recorded_total']], ['Approved Outgoing Funds', $report['summary']['disbursement_total']], ['Actual Payments Released', $report['summary']['actual_payment_total']], ['Reconciled Incoming Funds', $report['summary']['reconciled_total']], ['Pending Reconciliation', $report['summary']['pending_clearing_total']], ['Net After Approved Funds', $report['summary']['net_total']]] as [$label, $value])
                        <div class="rounded-lg border border-slate-100 bg-slate-50 p-3">
                            <p class="text-xs font-semibold text-slate-500">{{ $label }}</p>
                            <p class="mt-2 text-lg font-bold text-slate-950">₱{{ number_format($value, 2) }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="min-w-0">
                    <x-data-table caption="Detailed transaction table" min-width="55rem" class="report-table" style="--report-table-offset: 27rem">
<x-slot:head><x-data-table.heading>Type</x-data-table.heading>
<x-data-table.heading>Donor / Requester</x-data-table.heading>
<x-data-table.heading>Purpose</x-data-table.heading>
<x-data-table.heading>Method</x-data-table.heading>
<x-data-table.heading align="right">Amount</x-data-table.heading>
<x-data-table.heading>Date</x-data-table.heading>
<x-data-table.heading>Status</x-data-table.heading>
</x-slot:head>

                            @forelse ($report['ledger'] as $row)
                                <tr class="border-t border-slate-100"><td class="p-3">{{ $row['entry_type'] }}</td><td class="p-3">{{ $row['donor_name'] }}</td><td class="p-3">{{ $row['purpose'] }}</td><td class="p-3">{{ $row['payment_method_label'] }}</td><td class="data-table__number">₱{{ number_format($row['amount'], 2) }}</td><td class="p-3">{{ $row['received_at']->format('M d, Y') }}</td><td class="p-3">{{ $row['status_label'] }}</td></tr>
                            @empty
                                <x-data-table.empty :columns="7" title="No financial transactions found for this period." />
                            @endforelse

</x-data-table>
                </div>
            </section>
        @elseif ($tab === 'inventory')
            <section class="space-y-5 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Inventory Report</p>
                    <h2 class="text-lg font-bold text-slate-950">{{ $inventoryReport['period']->format('F Y') }}</h2>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([['In-kind Received', $inventoryReport['movement_totals']['in_kind_received']], ['Stock-in', $inventoryReport['movement_totals']['stock_in']], ['Stock-out', $inventoryReport['movement_totals']['stock_out']], ['Current Stock', $inventoryReport['movement_totals']['current_stock']], ['Low Items', $inventoryReport['movement_totals']['low_stock']], ['Critical Items', $inventoryReport['movement_totals']['critical_stock']], ['Expired Items', $inventoryReport['movement_totals']['expired']]] as [$label, $value])
                        <div class="rounded-lg border border-slate-100 bg-slate-50 p-3"><p class="text-xs font-semibold text-slate-500">{{ $label }}</p><p class="mt-2 text-lg font-bold text-slate-950">{{ is_float($value) ? number_format($value, 2) : number_format($value) }}</p></div>
                    @endforeach
                </div>
                <div class="min-w-0">
                    <x-data-table caption="Inventory report records" min-width="47rem" class="report-table" style="--report-table-offset: 27rem">
<x-slot:head><x-data-table.heading>Item</x-data-table.heading>
<x-data-table.heading>Category</x-data-table.heading>
<x-data-table.heading align="right">Current Stock</x-data-table.heading>
<x-data-table.heading align="right">Minimum Reserve</x-data-table.heading>
<x-data-table.heading>Status</x-data-table.heading>
<x-data-table.heading>Expires</x-data-table.heading>
</x-slot:head>

                            @forelse ($inventoryReport['items'] as $item)
                                <tr class="border-t border-slate-100"><td class="p-3">{{ $item->name }}</td><td class="p-3">{{ config('inventory.categories.'.$item->category, $item->category) }}</td><td class="data-table__number">{{ number_format((float) $item->current_quantity, 2) }} {{ $item->unit }}</td><td class="data-table__number">{{ number_format((float) $item->minimum_reserve_quantity, 2) }}</td><td class="p-3">{{ ucwords(str_replace('_', ' ', $item->stockStatus())) }}</td><td class="p-3">{{ $item->expires_at?->format('M d, Y') ?? '—' }}</td></tr>
                            @empty
                                <x-data-table.empty :columns="6" title="No inventory items found." />
                            @endforelse

</x-data-table>
                </div>
            </section>
        @elseif ($tab === 'audit')
            <section class="space-y-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div><p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Audit Trail Report</p><h2 class="text-lg font-bold text-slate-950">Recorded security activity</h2></div>
                <div class="min-w-0">
                    <x-data-table caption="Audit activity records" min-width="48rem" class="report-table" style="--report-table-offset: 20rem">
<x-slot:head><x-data-table.heading>User</x-data-table.heading>
<x-data-table.heading>Action</x-data-table.heading>
<x-data-table.heading>Module</x-data-table.heading>
<x-data-table.heading>Date / Time</x-data-table.heading>
<x-data-table.heading>Details</x-data-table.heading>
</x-slot:head>

                            @forelse ($auditReport['events'] as $event)
                                <tr class="border-t border-slate-100"><td class="p-3">{{ $event['user'] }}</td><td class="p-3">{{ $event['action'] }}</td><td class="p-3">{{ $event['module'] }}</td><td class="p-3">{{ $event['occurred_at']?->format('M d, Y H:i') }}</td><td class="p-3">{{ $event['summary'] }}</td></tr>
                            @empty
                                <x-data-table.empty :columns="5" title="No audit activity found for this date range." />
                            @endforelse

</x-data-table>
                </div>
            </section>
        @elseif ($tab === 'reconciliation')
            <section class="space-y-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                @if ($reconciliationPeriods->isNotEmpty())
                    <form method="GET" action="{{ route('reports.index') }}" class="hidden">
                        <input type="hidden" name="tab" value="reconciliation">
                        <div>
                            <label for="reconciliation-period" class="block text-xs font-semibold uppercase text-slate-500">Period</label>
                            <select id="reconciliation-period" name="period_id" class="report-filter-select mt-1">
                                @foreach ($reconciliationPeriods as $periodOption)
                                    <option value="{{ $periodOption->id }}" @selected((int) request('period_id', $reconciliationPeriods->first()->id) === $periodOption->id)>
                                        {{ $periodOption->period_start?->format('Y-m-d') }} – {{ $periodOption->period_end?->format('Y-m-d') }} ({{ str_replace('_', ' ', $periodOption->status) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <x-button class="w-auto">Apply</x-button>
                    </form>
                    <p class="text-sm text-slate-600">Select a period above to download the complete reconciliation report, including balances, movements, outstanding items, and differences.</p>
                @else
                    <p class="text-sm text-slate-600">No reconciliation periods are available.</p>
                @endif
            </section>
        @elseif ($tab === 'financial-ledgers')
            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-1 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div><p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Report Status: Final Auditable Ledger</p><h2 class="text-sm font-bold uppercase text-slate-950">Barangay Financial Audit Summary</h2></div>
                    <p class="text-xs text-slate-400">Month Cycle: {{ $report['period']->format('F Y') }}</p>
                </div>
                <div class="grid gap-4 border-b border-slate-100 p-5 sm:grid-cols-3">
                    <x-dashboard.summary-card tone="positive" label="Total Reconciled Funds" value="₱{{ number_format($report['summary']['reconciled_total'], 0) }}" description="Cleared in Bank Statement" />
                    <x-dashboard.summary-card tone="warning" label="Total Pending Clearing" value="₱{{ number_format($report['summary']['pending_clearing_total'], 0) }}" description="Direct receipts awaiting audit" />
                    <x-dashboard.summary-card label="Total Recorded Receipts" value="₱{{ number_format($report['summary']['recorded_total'], 0) }}" description="Cash grant operations cumulative" />
                </div>
                <div class="border-b border-slate-100 p-5">
                    <h3 class="mb-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Cash Intake Breakdown by Wire Source</h3>
                    @forelse ($report['wire_sources'] as $source)
                        <x-reports.wire-source-bar :source="$source" />
                    @empty
                        <x-dashboard.empty-state message="No cash receipts recorded for this period." />
                    @endforelse
                </div>
                <div class="p-5">
                    <h3 class="mb-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Settled Cash Receipts Ledger</h3>
                    @if ($report['ledger']->isEmpty())
                        <x-dashboard.empty-state message="No cash receipts recorded for this period." />
                    @else
                        <div class="report-scroll-area" style="--report-table-offset: 34rem">
                            @foreach ($report['ledger'] as $row)
                                <x-reports.ledger-row :row="$row" />
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        @elseif ($tab === 'philanthropy-roster')
            @include('reports.partials.donor-giving-summary', compact('donorSummary', 'year', 'month', 'search', 'sort'))
        @elseif ($tab === 'in-kind-audits')
            @include('reports.partials.in-kind-audit', ['report' => $inKindAudit])
        @endif
    </div>
@endsection
