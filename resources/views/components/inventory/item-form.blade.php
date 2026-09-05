@props(['item' => null, 'categories', 'units'])

@php
    $subcategoryOptions = $categories
        ->mapWithKeys(fn ($category) => [$category->key => $category->subcategories->map(fn ($subcategory) => [
            'id' => $subcategory->key,
            'label' => $subcategory->label,
        ])->values()])
        ->all();
    $selectedCategoryIsOther = old('category') === '__other__';
    $selectedSubcategory = old('subcategory_selection', $item?->subcategory?->key);
    $fieldClass = 'mt-1 block h-10 w-full rounded-lg border-slate-300 px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500';
@endphp

<div class="space-y-5" data-inventory-item-form data-subcategories='@json($subcategoryOptions)'>
    <section>
        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-800">Item Information</h3>
        <div class="mt-2.5 grid gap-x-4 gap-y-3 sm:grid-cols-2">
            <x-form.input name="name" label="Item Name" :value="$item?->name" required class="h-10 rounded-lg text-sm" />

            <div>
                <x-form.label for="unit">Unit</x-form.label>
                <select id="unit" name="unit" class="{{ $fieldClass }}" required>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->key }}" @selected(old('unit', $item?->unit ?? config('inventory.defaults.unit')) === $unit->key)>{{ $unit->label }}</option>
                    @endforeach
                </select>
                <x-form.error name="unit" />
            </div>

            <div class="sm:col-span-2 rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                <div class="grid gap-x-4 gap-y-3 sm:grid-cols-2">
                    <div>
                        <x-form.label for="category">Category</x-form.label>
                        <select id="category" name="category" data-inventory-category class="{{ $fieldClass }}" required>
                            <option value="">Select category</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->key }}" @selected(old('category', $item?->category) === $category->key)>{{ $category->label }}</option>
                            @endforeach
                            <option value="__other__" @selected($selectedCategoryIsOther)>Other</option>
                        </select>
                        <x-form.error name="category" />
                    </div>

                    <div>
                        <x-form.label for="inventory_subcategory_id">Subcategory</x-form.label>
                        <select id="inventory_subcategory_id" name="subcategory_selection" data-inventory-subcategory data-selected-subcategory="{{ $selectedSubcategory }}" class="{{ $fieldClass }}">
                            <option value="">Select category first</option>
                        </select>
                        <x-form.error name="subcategory_selection" />
                    </div>

                    <div data-inventory-custom-category hidden>
                        <x-form.input name="custom_category" label="Specify Category" :value="old('custom_category')" class="h-10 rounded-lg text-sm" />
                    </div>

                    <div data-inventory-custom-subcategory hidden>
                        <x-form.input name="custom_subcategory" label="Specify Subcategory" :value="old('custom_subcategory')" class="h-10 rounded-lg text-sm" />
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="border-t border-slate-200 pt-4">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-800">Stock Information</h3>
        <div class="mt-2.5 grid gap-x-4 gap-y-3 sm:grid-cols-2">
            @if (! $item)
                <x-form.input name="initial_quantity" label="Initial Quantity" type="number" step="0.01" min="0" required class="h-10 rounded-lg text-sm" />
            @endif

            <x-form.input name="minimum_reserve_quantity" label="Minimum Reserve Quantity" type="number" step="0.01" min="0" :value="$item?->minimum_reserve_quantity" required class="h-10 rounded-lg text-sm" />
            <x-form.input name="estimated_unit_value" label="Estimated Unit Value" type="number" step="0.01" min="0" :value="$item?->estimated_unit_value" class="h-10 rounded-lg text-sm" />
        </div>
    </section>

    <section class="border-t border-slate-200 pt-4">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-800">Storage &amp; Tracking</h3>
        <div class="mt-2.5 grid gap-x-4 gap-y-3 sm:grid-cols-2">
            <x-form.input name="expires_at" label="Expiration Date" type="date" :value="$item?->expires_at?->format('Y-m-d')" class="h-10 rounded-lg text-sm" />
            <x-form.input name="batch_number" label="Batch Number" :value="$item?->batch_number" class="h-10 rounded-lg text-sm" />
            <x-form.input name="storage_location" label="Storage Location" :value="$item?->storage_location" required class="h-10 rounded-lg text-sm" />

            <div>
                <x-form.label for="status">Status</x-form.label>
                <select id="status" name="status" class="{{ $fieldClass }}" required>
                    @foreach (config('inventory.item_statuses') as $key => $status)
                        <option value="{{ $key }}" @selected(old('status', $item?->status ?? config('inventory.defaults.status')) === $key)>{{ $status['label'] }}</option>
                    @endforeach
                </select>
                <x-form.error name="status" />
            </div>
        </div>
    </section>

    <section class="border-t border-slate-200 pt-4">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-800">Additional Information</h3>
        <div class="mt-2.5 space-y-3">
            <div>
                <x-form.label for="description">Description</x-form.label>
                <textarea id="description" name="description" rows="2" class="mt-1 block w-full resize-y rounded-lg border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('description', $item?->description) }}</textarea>
                <x-form.error name="description" />
            </div>

            <div>
                <x-form.label for="notes">Notes</x-form.label>
                <textarea id="notes" name="notes" rows="2" class="mt-1 block w-full resize-y rounded-lg border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('notes', $item?->notes) }}</textarea>
                <x-form.error name="notes" />
            </div>
        </div>
    </section>
</div>
