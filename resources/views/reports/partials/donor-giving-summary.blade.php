@props(['donorSummary', 'year', 'month', 'search', 'sort'])

<div class="space-y-4">
    <form method="GET" action="{{ route('reports.index') }}" class="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4">
        <input type="hidden" name="tab" value="philanthropy-roster">

        <div>
            <label for="donor-summary-search" class="block text-xs font-semibold uppercase text-slate-500">Search donor</label>
            <input
                id="donor-summary-search"
                name="search"
                type="search"
                value="{{ $search }}"
                placeholder="Name, email, or phone"
                class="mt-1 block rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
        </div>

        <div>
            <label for="donor-summary-month" class="block text-xs font-semibold uppercase text-slate-500">Month</label>
            <select id="donor-summary-month" name="month" class="mt-1 rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @foreach (range(1, 12) as $monthOption)
                    <option value="{{ $monthOption }}" @selected($month === $monthOption)>{{ date('F', mktime(0, 0, 0, $monthOption, 1)) }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="donor-summary-year" class="block text-xs font-semibold uppercase text-slate-500">Year</label>
            <select id="donor-summary-year" name="year" class="mt-1 rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @foreach (range(now()->year - 3, now()->year) as $yearOption)
                    <option value="{{ $yearOption }}" @selected($year === $yearOption)>{{ $yearOption }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="donor-summary-sort" class="block text-xs font-semibold uppercase text-slate-500">Sort by</label>
            <select id="donor-summary-sort" name="sort" class="mt-1 rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="highest" @selected(! $sort || $sort === 'highest')>Highest Giving</option>
                <option value="lowest" @selected($sort === 'lowest')>Lowest Giving</option>
                <option value="most_contributions" @selected($sort === 'most_contributions')>Most Contributions</option>
                <option value="latest" @selected($sort === 'latest')>Latest Donors</option>
            </select>
        </div>

        <x-button class="w-auto">Apply</x-button>
    </form>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-1 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Report Status: Philanthropy Roster</p>
                <h2 class="text-sm font-bold uppercase text-slate-950">Donor Giving Summary</h2>
            </div>
            <p class="text-xs text-slate-400">{{ $donorSummary['total_donors'] }} donor(s) with recorded giving</p>
        </div>

        @if ($donorSummary['rows']->isEmpty())
            <div class="p-5">
                <x-dashboard.empty-state message="No donor giving activity found for this period." />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-slate-100 text-left">
                            <th class="p-3 font-semibold text-slate-600">Donor</th>
                            <th class="p-3 font-semibold text-slate-600">Contact</th>
                            <th class="p-3 font-semibold text-slate-600">Contributions</th>
                            <th class="p-3 text-right font-semibold text-slate-600">Cash</th>
                            <th class="p-3 text-right font-semibold text-slate-600">In-Kind (est.)</th>
                            <th class="p-3 text-right font-semibold text-slate-600">Combined</th>
                            <th class="p-3 font-semibold text-slate-600">First / Last Gift</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($donorSummary['rows'] as $row)
                            <tr class="border-t border-slate-100">
                                <td class="p-3 font-medium text-slate-950">{{ $row['donor_name'] }}</td>
                                <td class="p-3 text-slate-600">
                                    <div>{{ $row['email'] ?: 'No email' }}</div>
                                    <div class="text-xs text-slate-400">{{ $row['phone'] }}</div>
                                </td>
                                <td class="p-3 text-slate-600">{{ number_format($row['total_donation_count']) }}</td>
                                <td class="p-3 text-right text-slate-950">&#8369;{{ number_format($row['total_cash_donations'], 2) }}</td>
                                <td class="p-3 text-right text-slate-950">&#8369;{{ number_format($row['total_in_kind_donations'], 2) }}</td>
                                <td class="p-3 text-right font-semibold text-slate-950">&#8369;{{ number_format($row['combined_donation_value'], 2) }}</td>
                                <td class="p-3 text-xs text-slate-500">{{ $row['first_donation_date'] ?? '—' }} &rarr; {{ $row['last_donation_date'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
