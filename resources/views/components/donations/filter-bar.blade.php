@props(['filters'])

<form method="GET" action="{{ route('donations.index') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" data-donation-filter-form>
    <div class="grid min-w-0 items-end gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(14rem,1fr)_11rem_11rem_auto]">
        <div class="min-w-0">
            <label for="search" class="mb-1.5 block text-xs font-medium text-slate-600">Search donations</label>
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
                    class="block h-10 w-full min-w-0 rounded-lg border border-slate-300 py-2 pl-9 pr-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                >
            </div>
        </div>

        <div>
            <label for="type" class="mb-1.5 block text-xs font-medium text-slate-600">Type</label>
            <select id="type" name="type" class="block h-10 w-full rounded-lg border border-slate-300 px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" data-donation-live-filter>
                <option value="">All types</option>
                @foreach (config('donations.types') as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="status" class="mb-1.5 block text-xs font-medium text-slate-600">Status</label>
            <select id="status" name="status" class="block h-10 w-full rounded-lg border border-slate-300 px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" data-donation-live-filter>
                <option value="">All statuses</option>
                @foreach (config('donations.statuses') as $value => $status)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $status['label'] }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex items-center gap-2">
            <x-button class="h-10 w-full gap-2 px-4 xl:w-auto" title="Apply filters">
                <x-icon name="filter" class="h-4 w-4" />
                <span>Filter</span>
            </x-button>
            <a href="{{ route('donations.index') }}" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-600 shadow-sm hover:bg-slate-50" title="Reset filters" aria-label="Reset filters">
                <x-icon name="reset" class="h-4 w-4" />
            </a>
        </div>
    </div>
    <p class="mt-3 text-xs leading-5 text-slate-500">
        Cash starts as Pending until an authorized reviewer verifies it. In-kind donations start as Received; records may later be marked Distributed or Cancelled.
    </p>
</form>
