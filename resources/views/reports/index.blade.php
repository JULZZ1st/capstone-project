@extends('layouts.app')

@section('title', 'Report Generator | '.config('dashboard.barangay_name'))
@section('page-title', 'Report Generator')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-950">Report Generator</h1>
                <p class="mt-1 text-sm text-slate-500">Generate municipal audits and transparency logs for municipal review.</p>
            </div>

            @if ($tab === 'financial-ledgers')
                <a
                    href="{{ route('reports.financial-ledgers.csv', ['year' => $year, 'month' => $month]) }}"
                    class="inline-flex items-center gap-2 rounded-md bg-green-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-green-800"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                        <path d="M10.75 2.75a.75.75 0 00-1.5 0v8.614L6.295 8.235a.75.75 0 10-1.09 1.03l4.25 4.5a.75.75 0 001.09 0l4.25-4.5a.75.75 0 00-1.09-1.03l-2.955 3.129V2.75z" />
                        <path d="M3.5 12.75a.75.75 0 00-1.5 0v2.5A2.75 2.75 0 004.75 18h10.5A2.75 2.75 0 0018 15.25v-2.5a.75.75 0 00-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5z" />
                    </svg>
                    Download Official CSV Report
                </a>
            @endif
        </div>

        <x-reports.tab-nav :active="$tab" :year="$year" :month="$month" />

        @if ($tab === 'financial-ledgers')
            <x-reports.month-filter :year="$year" :month="$month" />

            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-1 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Report Status: Final Auditable Ledger</p>
                        <h2 class="text-sm font-bold uppercase text-slate-950">Barangay Treasurer Cash Audit Summary</h2>
                    </div>
                    <p class="text-xs text-slate-400">Month Cycle: {{ $report['period']->format('F Y') }}</p>
                </div>

                <div class="grid gap-4 border-b border-slate-100 p-5 sm:grid-cols-3">
                    <x-dashboard.summary-card
                        tone="positive"
                        label="Total Reconciled Funds"
                        value="₱{{ number_format($report['summary']['reconciled_total'], 0) }}"
                        description="Cleared in Bank Statement"
                    />
                    <x-dashboard.summary-card
                        tone="warning"
                        label="Total Pending Clearing"
                        value="₱{{ number_format($report['summary']['pending_clearing_total'], 0) }}"
                        description="Direct receipts awaiting audit"
                    />
                    <x-dashboard.summary-card
                        label="Total Recorded Receipts"
                        value="₱{{ number_format($report['summary']['recorded_total'], 0) }}"
                        description="Cash grant operations cumulative"
                    />
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

                    @forelse ($report['ledger'] as $row)
                        <x-reports.ledger-row :row="$row" />
                    @empty
                        <x-dashboard.empty-state message="No cash receipts recorded for this period." />
                    @endforelse
                </div>
            </section>
        @elseif ($tab === 'philanthropy-roster')
            @include('reports.partials.donor-giving-summary', [
                'donorSummary' => $donorSummary,
                'year' => $year,
                'month' => $month,
                'search' => $search,
                'sort' => $sort,
            ])
        @else
            <x-dashboard.panel
                title="In-Kind Audits"
                subtitle="This report is coming soon."
            >
                <x-dashboard.empty-state message="This report category is not available yet." />
            </x-dashboard.panel>
        @endif
    </div>
@endsection
