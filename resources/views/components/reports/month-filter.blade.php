@props(['year', 'month'])

<form method="GET" action="{{ route('reports.index') }}" class="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4">
    <input type="hidden" name="tab" value="financial-ledgers">

    <div>
        <label for="report-month" class="block text-xs font-semibold uppercase text-slate-500">Month</label>
        <select id="report-month" name="month" class="mt-1 rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            @foreach (range(1, 12) as $monthOption)
                <option value="{{ $monthOption }}" @selected($month === $monthOption)>{{ date('F', mktime(0, 0, 0, $monthOption, 1)) }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="report-year" class="block text-xs font-semibold uppercase text-slate-500">Year</label>
        <select id="report-year" name="year" class="mt-1 rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            @foreach (range(now()->year - 3, now()->year) as $yearOption)
                <option value="{{ $yearOption }}" @selected($year === $yearOption)>{{ $yearOption }}</option>
            @endforeach
        </select>
    </div>

    <x-button class="w-auto">Apply</x-button>
</form>
