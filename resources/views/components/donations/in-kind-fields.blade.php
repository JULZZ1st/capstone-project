@props(['active' => false, 'categories', 'subcategoriesByCategory'])

@php($rows = old('items', [['name' => null, 'category' => null, 'subcategory' => null, 'quantity' => null, 'unit' => null, 'condition' => null, 'estimated_value' => null, 'notes' => null]]))

<section
    class="space-y-4 rounded-lg border border-slate-200 p-4"
    data-contribution-section="in_kind"
    data-subcategories='@json($subcategoriesByCategory)'
    @hidden(! $active)
>
    <div class="flex items-center justify-between gap-3">
        <h3 class="text-sm font-semibold text-slate-950">In-Kind Goods</h3>
        <button type="button" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" data-add-item-row>Add Item Row</button>
    </div>
    <x-form.error name="items" />
    <div class="space-y-3" data-item-rows>
        @foreach ($rows as $index => $row)
            <x-donations.item-row :index="$index" :row="$row" :categories="$categories" />
        @endforeach
    </div>
    <div class="max-w-xs">
        <x-form.label for="in_kind_received_at">Date Received</x-form.label>
        <input id="in_kind_received_at" name="received_at" type="date" value="{{ old('received_at', now()->toDateString()) }}" required class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
        <x-form.error name="received_at" />
    </div>
</section>
