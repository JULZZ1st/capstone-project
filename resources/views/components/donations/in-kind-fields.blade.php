@props(['active' => false])

@php($rows = old('items', [['name' => null, 'category' => null, 'quantity' => null, 'unit' => null, 'condition' => null, 'estimated_value' => null, 'notes' => null]]))

<section class="space-y-4 rounded-lg border border-slate-200 p-4" data-contribution-section="in_kind" @hidden(! $active)>
    <div class="flex items-center justify-between gap-3">
        <h3 class="text-sm font-semibold text-slate-950">In-Kind Goods</h3>
        <button type="button" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" data-add-item-row>Add Item Row</button>
    </div>
    <x-form.error name="items" />
    <div class="space-y-3" data-item-rows>
        @foreach ($rows as $index => $row)
            <x-donations.item-row :index="$index" :row="$row" />
        @endforeach
    </div>
</section>
