@props(['filters'])

<form method="GET" action="{{ route('inventory.index') }}" class="grid gap-3 md:grid-cols-[1fr_auto_auto]">
    @if (! empty($filters['category']))
        <input type="hidden" name="category" value="{{ $filters['category'] }}">
    @endif

    <div>
        <x-form.label for="search">Search Inventory</x-form.label>
        <input
            id="search"
            name="search"
            value="{{ $filters['search'] ?? '' }}"
            placeholder="Search by item name, category, batch, location, or notes"
            class="mt-1 block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
        >
    </div>

    <div>
        <x-form.label for="status">Item Status</x-form.label>
        <select id="status" name="status" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <option value="">All statuses</option>
            @foreach (config('inventory.item_statuses') as $key => $status)
                <option value="{{ $key }}" @selected(($filters['status'] ?? null) === $key)>{{ $status['label'] }}</option>
            @endforeach
        </select>
    </div>

    <div class="flex items-end gap-2">
        <x-button class="w-auto">Search</x-button>
        <a href="{{ route('inventory.index') }}" class="rounded-md border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Clear</a>
    </div>
</form>
