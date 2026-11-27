@props([
    'filters',
    'types' => config('donations.types'),
    'donors' => collect(),
    'categories' => config('donations.item_main_categories'),
])

<form method="GET" action="{{ route('donations.index') }}" class="grid min-w-0 grid-cols-2 gap-2 lg:grid-cols-[minmax(15rem,1.5fr)_repeat(5,minmax(8rem,1fr))_auto]" data-donation-filter-form>
    <div class="col-span-2 min-w-0 lg:col-span-1">
        <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    id="search"
                    name="search"
                    type="search"
                    value="{{ $filters['search'] ?? '' }}"
                    placeholder="Donor, reference, item or notes"
                    autocomplete="off"
                    data-donation-live-search
                    aria-label="Search donations"
                    class="block h-10 w-full min-w-0 rounded-lg border border-slate-300 py-2 pl-9 pr-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                >
        </div>
    </div>

    <label class="min-w-0">
        <span class="sr-only">Date from</span>
        <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}" aria-label="Date from" class="block h-10 w-full min-w-0 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:ring-blue-500" data-donation-live-filter>
    </label>
    <label class="min-w-0">
        <span class="sr-only">Date to</span>
        <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}" aria-label="Date to" class="block h-10 w-full min-w-0 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:ring-blue-500" data-donation-live-filter>
    </label>
    <select id="donor_id" name="donor_id" aria-label="Donor" class="block h-10 w-full min-w-0 rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-8 text-sm leading-5 text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500" data-donation-live-filter>
        <option value="">All donors</option>
        @foreach ($donors as $donor)
            <option value="{{ $donor->id }}" @selected((string) ($filters['donor_id'] ?? '') === (string) $donor->id)>{{ $donor->full_name }}</option>
        @endforeach
    </select>
    <input id="item" name="item" type="search" value="{{ $filters['item'] ?? '' }}" placeholder="Item" aria-label="Item" autocomplete="off" class="block h-10 w-full min-w-0 rounded-lg border border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" data-donation-live-search>
    <select id="category" name="category" aria-label="Category" class="block h-10 w-full min-w-0 rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-8 text-sm leading-5 text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500" data-donation-live-filter>
        <option value="">All categories</option>
        @foreach ($categories as $value => $label)
            <option value="{{ $value }}" @selected(($filters['category'] ?? '') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <select id="type" name="type" aria-label="Donation type" class="block h-10 w-full min-w-0 rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-8 text-sm leading-5 text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500" data-donation-live-filter>
        <option value="">All types</option>
        @foreach ($types as $value => $label)
            <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <div class="col-span-2 flex min-w-0 flex-wrap items-center justify-end gap-2 lg:col-span-1">
        {{ $slot }}
    </div>
</form>
