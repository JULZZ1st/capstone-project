@php
    $categoryLabels = collect(config('donations.item_main_categories'));
    $unitLabels = collect(config('donations.units'));
    $conditionLabels = collect(config('donations.item_conditions'));
@endphp

<div
    class="space-y-3"
    data-items-table
    data-old-items='@json(old('items', []))'
    data-categories='@json($categoryLabels)'
    data-units='@json($unitLabels)'
    data-conditions='@json($conditionLabels)'
>
    <div class="flex items-center justify-between gap-3">
        <p class="text-sm font-semibold text-slate-950">Donated Items</p>
        <button type="button" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-blue-200 bg-blue-50 px-3 text-sm font-semibold text-blue-700 transition hover:border-blue-300 hover:bg-blue-100" data-add-item>
            <x-icon name="plus" class="h-4 w-4" />
            Add Item
        </button>
    </div>

    <x-form.error name="items" />
    <p class="hidden rounded-lg bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700" data-items-error role="alert">At least one donated item is required.</p>

    <div class="overflow-x-auto rounded-lg border border-slate-200" data-items-empty-state hidden>
        <div class="flex flex-col items-center justify-center gap-1.5 px-4 py-8 text-center">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-400"><x-icon name="cube" class="h-4 w-4" /></span>
            <p class="text-sm font-medium text-slate-600">No items added yet.</p>
            <p class="text-xs text-slate-400">Click "Add Item" to add a donated item.</p>
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200" data-items-table-wrapper hidden>
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Item</th>
                    <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Category</th>
                    <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Quantity</th>
                    <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Unit</th>
                    <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Condition</th>
                    <th scope="col" class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Est. Total Value (PHP)</th>
                    <th scope="col" class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white" data-items-table-body></tbody>
        </table>
    </div>

    <div data-item-hidden-inputs></div>
</div>
