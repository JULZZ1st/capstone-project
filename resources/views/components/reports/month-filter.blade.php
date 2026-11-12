@props(['year', 'month', 'tab' => 'financial-ledgers'])

<form method="GET" action="{{ route('reports.index') }}" class="ui-filter-bar sticky top-16 z-10">
    <input type="hidden" name="tab" value="{{ $tab }}">

    <div>
        <label for="report-month" class="block text-xs font-semibold uppercase text-slate-500">Month</label>
        <select id="report-month" name="month" class="report-filter-select mt-1 focus:border-blue-500 focus:ring-blue-500">
            @foreach (range(1, 12) as $monthOption)
                <option value="{{ $monthOption }}" @selected($month === $monthOption)>{{ date('F', mktime(0, 0, 0, $monthOption, 1)) }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="report-year" class="block text-xs font-semibold uppercase text-slate-500">Year</label>
        <select id="report-year" name="year" class="report-filter-select mt-1 focus:border-blue-500 focus:ring-blue-500">
            @foreach (range(now()->year - 3, now()->year) as $yearOption)
                <option value="{{ $yearOption }}" @selected($year === $yearOption)>{{ $yearOption }}</option>
            @endforeach
        </select>
    </div>

    <x-button class="w-auto">Apply</x-button>
</form>
