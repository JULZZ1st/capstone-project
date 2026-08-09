@props(['filters'])

@php($selectedCategory = $filters['category'] ?? null)

<div class="-mx-1 overflow-x-auto px-1 pb-1">
    <div class="flex w-max gap-1.5">
        <a
            href="{{ route('inventory.index', array_filter(['search' => $filters['search'] ?? null, 'status' => $filters['status'] ?? null])) }}"
            class="shrink-0 rounded-full px-3.5 py-1.5 text-sm font-semibold transition {{ $selectedCategory === null ? 'bg-blue-600 text-white shadow-sm shadow-blue-950/10' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
        >
            All
        </a>

        @foreach (config('inventory.categories') as $key => $label)
            <a
                href="{{ route('inventory.index', array_filter(['category' => $key, 'search' => $filters['search'] ?? null, 'status' => $filters['status'] ?? null])) }}"
                class="shrink-0 rounded-full px-3.5 py-1.5 text-sm font-semibold transition {{ $selectedCategory === $key ? 'bg-blue-600 text-white shadow-sm shadow-blue-950/10' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
            >
                {{ $label }}
            </a>
        @endforeach
    </div>
</div>
