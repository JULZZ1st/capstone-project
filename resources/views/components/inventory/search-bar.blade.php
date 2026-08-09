@props(['filters'])

<form method="GET" action="{{ route('inventory.index') }}" class="grid gap-3 md:grid-cols-[1fr_auto_auto]">
    @if (! empty($filters['category']))
        <input type="hidden" name="category" value="{{ $filters['category'] }}">
    @endif

    <div>
        <x-form.label for="search">Search Inventory</x-form.label>
        <div class="relative mt-1.5">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
                id="search"
                name="search"
                value="{{ $filters['search'] ?? '' }}"
                placeholder="Search by item name, category, batch, location, or notes"
                class="block w-full rounded-lg border-slate-300 py-2.5 pl-9 pr-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
        </div>
    </div>

    <div class="md:w-48">
        <x-form.label for="status">Item Status</x-form.label>
        <select id="status" name="status" class="mt-1.5 block w-full rounded-lg border-slate-300 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <option value="">All statuses</option>
            @foreach (config('inventory.item_statuses') as $key => $status)
                <option value="{{ $key }}" @selected(($filters['status'] ?? null) === $key)>{{ $status['label'] }}</option>
            @endforeach
        </select>
    </div>

    <div class="flex items-end gap-2">
        <x-button class="w-auto px-5">Search</x-button>
        <a href="{{ route('inventory.index') }}" class="inline-flex w-auto items-center justify-center rounded-md border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Clear</a>
    </div>
</form>
