
@props([
    'filters',
    'types' => config('donations.types'),
    'donors' => collect(),
    'categories' => config('donations.item_main_categories'),
])

<form
    method="GET"
    action="{{ route('donations.index') }}"
    class="flex min-w-0 w-full items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4"
    data-donation-filter-form
>
    {{-- Search Bar - Left Side --}}
    <div class="w-[25%] min-w-0 shrink-0">
        <div class="relative">
            <x-icon
                name="search"
                class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
            />

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

    <div class="flex shrink-0 items-center gap-2">

        <label class="w-[185px] shrink-0">
            <span class="sr-only">Date from</span>

            <input
                id="date_from"
                name="date_from"
                type="date"
                value="{{ $filters['date_from'] ?? '' }}"
                aria-label="Date from"
                class="block h-10 w-full min-w-0 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                data-donation-live-filter
            >
        </label>

        <label class="w-[185px] shrink-0">
            <span class="sr-only">Date to</span>

            <input
                id="date_to"
                name="date_to"
                type="date"
                value="{{ $filters['date_to'] ?? '' }}"
                aria-label="Date to"
                class="block h-10 w-full min-w-0 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                data-donation-live-filter
            >
        </label>

        <select
            id="type"
            name="type"
            aria-label="Donation type"
            class="block h-10 w-[150px] shrink-0 rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-8 text-sm leading-5 text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
            data-donation-live-filter
        >
            <option value="">All types</option>

            @foreach ($types as $value => $label)
                <option
                    value="{{ $value }}"
                    @selected(($filters['type'] ?? '') === $value)
                >
                    {{ $label }}
                </option>
            @endforeach
        </select>

        {{-- Action Buttons --}}
        <div class="flex shrink-0 items-center gap-2">
            {{ $slot }}
        </div>

    </div>
</form>
