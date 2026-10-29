@props(['filters'])

<form method="GET" action="{{ route('donations.index') }}" class="grid w-full gap-3 sm:grid-cols-[minmax(0,1fr)_10rem_11rem]" data-donation-filter-form>
    <div class="min-w-0">
        <x-form.label for="search" class="sr-only">Search contributions</x-form.label>
        <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    id="search"
                    name="search"
                    type="search"
                    value="{{ $filters['search'] ?? '' }}"
                    placeholder="Search donor, reference, item, notes"
                    autocomplete="off"
                    data-donation-live-search
                    class="block h-10 w-full rounded-md border-slate-300 bg-white py-2 pl-9 pr-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                >
            </div>
    </div>

    <div>
        <x-form.label for="type" class="sr-only">Type filter</x-form.label>
        <select id="type" name="type" class="block h-10 w-full rounded-md border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" data-donation-live-filter>
            <option value="">All types</option>
            @foreach (config('donations.types') as $value => $label)
                <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <x-form.label for="status" class="sr-only">Status filter</x-form.label>
        <select id="status" name="status" class="block h-10 w-full rounded-md border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" data-donation-live-filter>
            <option value="">All statuses</option>
            @foreach (config('donations.statuses') as $value => $status)
                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $status['label'] }}</option>
            @endforeach
        </select>
    </div>
</form>
