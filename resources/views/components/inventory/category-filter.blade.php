@props(['filters', 'categories'])

<div>
    <x-form.label for="category">Category</x-form.label>
    <div class="relative mt-1.5">
        <select
            id="category"
            name="category"
            class="block h-11 w-full appearance-none rounded-lg border-slate-300 bg-white px-3.5 pr-10 text-sm shadow-sm transition focus:border-violet-500 focus:ring-violet-500"
        >
            <option value="">All Categories</option>
            @foreach ($categories as $category)
                <option value="{{ $category->key }}" @selected(($filters['category'] ?? null) === $category->key)>
                    {{ $category->label }}
                </option>
            @endforeach
        </select>
        <x-icon name="chevron-down" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
    </div>
</div>
