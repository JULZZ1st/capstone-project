@props(['filters'])

@php($selectedCategory = $filters['category'] ?? null)

<div class="overflow-x-auto">
    <div class="flex min-w-max gap-2">
        <a
            href="{{ route('inventory.index', array_filter(['search' => $filters['search'] ?? null, 'status' => $filters['status'] ?? null])) }}"
            class="rounded-md px-3 py-2 text-sm font-semibold {{ $selectedCategory === null ? 'bg-blue-700 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}"
        >
            All
        </a>

        @foreach (config('inventory.categories') as $key => $label)
            <a
                href="{{ route('inventory.index', array_filter(['category' => $key, 'search' => $filters['search'] ?? null, 'status' => $filters['status'] ?? null])) }}"
                class="rounded-md px-3 py-2 text-sm font-semibold {{ $selectedCategory === $key ? 'bg-blue-700 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}"
            >
                {{ $label }}
            </a>
        @endforeach
    </div>
</div>
