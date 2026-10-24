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
            <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                <x-icon name="refresh" class="h-4 w-4" />
                Refresh
            </a>
        </div>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Dashboard summary">
            <x-dashboard.summary-card label="Total Cash Ledger" :value="$money($summary['total_cash_ledger'])" description="Verified and approved cash entries." icon="wallet" tone="positive" />
            <x-dashboard.summary-card label="Pending Cash Verification" :value="number_format($summary['pending_cash_verification'])" description="Cash entries awaiting audit." icon="clock" tone="warning" />
            <x-dashboard.summary-card label="In-Kind Batches" :value="number_format($summary['in_kind_batches'])" description="Inventory records currently tracked." icon="cube" tone="info" />
            <x-dashboard.summary-card label="Registered Donors" :value="number_format($summary['registered_donors'])" description="Donor profiles available." icon="users" tone="purple" />
        </section>

        <section class="grid items-stretch gap-4 md:grid-cols-2 xl:grid-cols-3" aria-label="Recent activity">
            <x-dashboard.panel title="Recent Donations" subtitle="Latest donation records." icon="heart" tone="rose" class="flex h-full min-h-0 flex-col">
                <div class="max-h-72 min-h-0 flex-1 overflow-y-auto pr-1" data-dashboard-scroll-list>
                    @if ($dashboard['recent_donations']->isEmpty())
                        <x-dashboard.empty-state icon="heart" message="No donations recorded yet." action="{{ route('donations.index') }}" action-label="View Donations" />
                    @else
                        <ul class="divide-y divide-slate-100">
                            @foreach ($dashboard['recent_donations'] as $donation)
                                <li>
                                    <a href="{{ route('donations.show', $donation) }}" class="flex items-center gap-3 rounded-lg px-2 py-3 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 first:pt-0 last:pb-0">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-semibold text-blue-700">{{ strtoupper(substr((string) ($donation->donor?->full_name ?? '?'), 0, 1)) }}</span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-medium text-slate-950">{{ $donation->donor?->full_name ?? 'Unknown donor' }}</span>
                                            <span class="block truncate text-xs text-slate-600">{{ config("donations.types.{$donation->type}") }} · {{ optional($donation->received_at ?? $donation->created_at)->format('M d, Y') }}</span>
                                        </span>
                                        @if ($donation->type === 'cash')
                                            <span class="shrink-0 text-xs font-semibold text-slate-950">{{ $money($donation->amount) }}</span>
                                        @else
                                            <span class="shrink-0 text-xs font-semibold text-slate-950">{{ number_format($donation->items_count) }} item(s)</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </x-dashboard.panel>

            <x-dashboard.panel title="Recent Requests" subtitle="Latest fund requests." icon="banknotes" tone="info" class="flex h-full min-h-0 flex-col">
                <div class="max-h-72 min-h-0 flex-1 overflow-y-auto pr-1" data-dashboard-scroll-list>
                    @if ($dashboard['disbursements']['recent']->isEmpty())
                        <x-dashboard.empty-state icon="banknotes" message="No requests have been recorded yet." action="{{ route('disbursements.index') }}" action-label="View Requests" />
                    @else
                        <ul class="divide-y divide-slate-100">
                            @foreach ($dashboard['disbursements']['recent'] as $request)
                                <li>
                                    <a href="{{ route('disbursements.show', $request) }}" class="flex items-start gap-3 rounded-lg px-2 py-3 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 first:pt-0 last:pb-0">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-semibold text-blue-700">₱</span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-medium text-slate-950" title="{{ $request->purpose }}">{{ $request->purpose }}</span>
                                            <span class="block truncate text-xs text-slate-600">{{ $request->requester->name }} · {{ ucfirst($request->status) }}</span>
                                        </span>
                                        <span class="shrink-0 text-xs font-semibold text-slate-950">{{ $money($request->amount) }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </x-dashboard.panel>

            <x-dashboard.panel title="System Log Activity" subtitle="Recent operational records." icon="terminal" tone="info" class="flex h-full min-h-0 flex-col">
                <div class="max-h-72 min-h-0 flex-1 overflow-y-auto pr-1" data-dashboard-scroll-list>
                    <x-dashboard.activity-list :items="$dashboard['system_log_activity']" />
                </div>
            </x-dashboard.panel>
        </section>
    </div>
@endsection
