@extends('layouts.app')

@section('title', 'Dashboard | '.config('dashboard.barangay_name'))
@section('page-title', 'Dashboard')

@section('content')
    @php
        $summary = $dashboard['summary'];
        $money = fn ($amount) => 'PHP '.number_format((float) $amount, 2);
    @endphp

    <div class="space-y-6">
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-dashboard.summary-card
                label="Total Cash Ledger"
                :value="$money($summary['total_cash_ledger'])"
                description="Verified and approved cash entries."
            />
            <x-dashboard.summary-card
                label="Pending Cash Verification"
                :value="number_format($summary['pending_cash_verification'])"
                description="Cash entries awaiting audit."
            />
            <x-dashboard.summary-card
                label="In-Kind Batches"
                :value="number_format($summary['in_kind_batches'])"
                description="Inventory records currently tracked."
            />
            <x-dashboard.summary-card
                label="Registered Donors"
                :value="number_format($summary['registered_donors'])"
                description="Donor profiles available."
            />
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <x-dashboard.panel title="Treasurer Action Items" subtitle="Cash and reconciliation work needing attention.">
                @php($actionItems = $dashboard['treasurer_action_items'])
                <div class="mb-3 flex items-center justify-between rounded-md bg-slate-50 px-3 py-2">
                    <span class="text-sm text-slate-600">Total cash disbursed</span>
                    <span class="text-sm font-semibold text-slate-950">{{ $money($dashboard['disbursements']['total_disbursed']) }}</span>
                </div>
                @if ($actionItems['pending_cash_count'] === 0 && $actionItems['unreconciled_cash_count'] === 0)
                    <x-dashboard.empty-state message="No treasurer action items right now." />
                @else
                    <dl class="space-y-3">
                        <div class="flex items-center justify-between rounded-md bg-slate-50 px-3 py-2">
                            <dt class="text-sm text-slate-600">Pending cash verification</dt>
                            <dd class="text-sm font-semibold text-slate-950">{{ number_format($actionItems['pending_cash_count']) }}</dd>
                        </div>
                        <div class="flex items-center justify-between rounded-md bg-slate-50 px-3 py-2">
                            <dt class="text-sm text-slate-600">Cash entries after latest reconciliation</dt>
                            <dd class="text-sm font-semibold text-slate-950">{{ number_format($actionItems['unreconciled_cash_count']) }}</dd>
                        </div>
                        @if ($actionItems['latest_reconciliation'])
                            <div class="rounded-md bg-green-50 px-3 py-2 text-sm text-green-800">
                                Latest reconciliation: {{ $actionItems['latest_reconciliation']->date }} with closing balance {{ $money($actionItems['latest_reconciliation']->closing_balance) }}.
                            </div>
                        @endif
                    </dl>
                @endif
            </x-dashboard.panel>

            <x-dashboard.panel title="Campaign Contributions / Funding Distribution" subtitle="Verified cash grouped by funding purpose.">
                @if ($dashboard['funding_distribution']->isEmpty())
                    <x-dashboard.empty-state message="No funding distribution available yet." />
                @else
                    <ul class="space-y-3">
                        @foreach ($dashboard['funding_distribution'] as $item)
                            <li class="flex items-center justify-between gap-4 rounded-md bg-slate-50 px-3 py-2">
                                <span class="truncate text-sm text-slate-600">{{ $item->purpose }}</span>
                                <span class="text-sm font-semibold text-slate-950">{{ $money($item->total) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-dashboard.panel>

            <x-dashboard.panel title="Recent Cash Donations Awaiting Audit" subtitle="Latest pending cash entries.">
                @if ($dashboard['recent_cash_awaiting_audit']->isEmpty())
                    <x-dashboard.empty-state message="No cash donations awaiting audit." />
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($dashboard['recent_cash_awaiting_audit'] as $transaction)
                            <li class="py-3 first:pt-0 last:pb-0">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-medium text-slate-950">{{ $transaction->donor_name }}</p>
                                        <p class="text-sm text-slate-600">{{ $transaction->purpose }}</p>
                                    </div>
                                    <p class="text-sm font-semibold text-slate-950">{{ $money($transaction->amount) }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-dashboard.panel>

            <x-dashboard.panel title="System Log Activity" subtitle="Recent operational records derived from current tables.">
                <x-dashboard.activity-list :items="$dashboard['system_log_activity']" />
            </x-dashboard.panel>

            <x-dashboard.panel title="Secretary In-Kind Control Panel" subtitle="Inventory status and recent in-kind records.">
                <div class="grid gap-4 lg:grid-cols-2">
                    <div>
                        <h3 class="mb-2 text-sm font-semibold text-slate-700">Inventory by status</h3>
                        <x-dashboard.stat-list :items="$dashboard['secretary_in_kind_control']['status_counts']" />
                    </div>
                    <div>
                        <h3 class="mb-2 text-sm font-semibold text-slate-700">Recent in-kind records</h3>
                        @if ($dashboard['secretary_in_kind_control']['recent_items']->isEmpty())
                            <x-dashboard.empty-state message="No in-kind donations recorded yet." />
                        @else
                            <ul class="space-y-3">
                                @foreach ($dashboard['secretary_in_kind_control']['recent_items'] as $item)
                                    <li class="rounded-md bg-slate-50 px-3 py-2">
                                        <p class="text-sm font-medium text-slate-950">{{ $item->name }}</p>
                                        <p class="text-sm text-slate-600">{{ $item->category }} - Qty {{ number_format($item->quantity) }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ ucfirst($item->status) }}</p>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </x-dashboard.panel>

            <x-dashboard.panel title="Recent Donations Stream" subtitle="Latest donation records with donor context.">
                @if ($dashboard['recent_donations']->isEmpty())
                    <x-dashboard.empty-state message="No donations recorded yet." />
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($dashboard['recent_donations'] as $donation)
                            <li class="py-3 first:pt-0 last:pb-0">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-medium text-slate-950">{{ $donation->donor?->full_name ?? 'Unknown donor' }}</p>
                                        <p class="text-sm text-slate-600">{{ $donation->donation_type }}</p>
                                    </div>
                                    <p class="text-sm font-semibold text-slate-950">{{ $money($donation->amount) }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-dashboard.panel>

            <x-dashboard.panel title="Recent Cash Disbursements" subtitle="Latest outgoing cash payments recorded.">
                @if ($dashboard['disbursements']['recent']->isEmpty())
                    <x-dashboard.empty-state message="No cash disbursements have been recorded yet." />
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($dashboard['disbursements']['recent'] as $disbursement)
                            <li class="py-3 first:pt-0 last:pb-0">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-medium text-slate-950">{{ $disbursement->disbursed_to }}</p>
                                        <p class="text-sm text-slate-600">{{ config("disbursements.purposes.{$disbursement->purpose}") }}</p>
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
