@props(['filters', 'types' => config('donations.types')])

<form method="GET" action="{{ route('donations.index') }}" class="flex min-w-0 flex-col gap-2 sm:flex-row sm:items-center sm:justify-between" data-donation-filter-form>
    <div class="min-w-0 flex-1 sm:max-w-md lg:max-w-xl">
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

    <div class="flex min-w-0 flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
            <select id="type" name="type" aria-label="Donation type" class="block h-10 w-full min-w-0 rounded-lg border border-slate-300 bg-white py-2 pl-3 pr-10 text-sm leading-5 text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 sm:w-40" data-donation-live-filter>
                <option value="">All types</option>
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        {{ $slot }}
    </div>
</form>
