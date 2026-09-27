@props(['index', 'row' => [], 'categories'])

@php($selectedSubcategory = old("items.{$index}.subcategory", $row['subcategory'] ?? ''))

<div class="rounded-md border border-slate-200 bg-slate-50 p-3" data-item-row>
    <div class="grid gap-3 md:grid-cols-7">
        <div class="md:col-span-2">
            <x-form.label for="items_{{ $index }}_name">Item Name</x-form.label>
            <input id="items_{{ $index }}_name" name="items[{{ $index }}][name]" value="{{ old("items.{$index}.name", $row['name'] ?? '') }}" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <x-form.error name="items.{{ $index }}.name" />
        </div>
        <div>
            <x-form.label for="items_{{ $index }}_category">Category</x-form.label>
            <select id="items_{{ $index }}_category" name="items[{{ $index }}][category]" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" data-item-category>
                <option value="">Select</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->key }}" @selected(old("items.{$index}.category", $row['category'] ?? '') === $category->key)>{{ $category->label }}</option>
                @endforeach
                <option value="__other__" @selected(old("items.{$index}.category", $row['category'] ?? '') === '__other__')>Other</option>
            </select>
            <x-form.error name="items.{{ $index }}.category" />
        </div>
        <div data-item-custom-category hidden>
            <x-form.label for="items_{{ $index }}_custom_category">Specify Category</x-form.label>
            <input id="items_{{ $index }}_custom_category" name="items[{{ $index }}][custom_category]" value="{{ old("items.{$index}.custom_category", $row['custom_category'] ?? '') }}" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <x-form.error name="items.{{ $index }}.custom_category" />
        </div>
        <div>
            <x-form.label for="items_{{ $index }}_subcategory">Subcategory</x-form.label>
            <select id="items_{{ $index }}_subcategory" name="items[{{ $index }}][subcategory]" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-slate-100 disabled:text-slate-500" data-item-subcategory data-selected-subcategory="{{ $selectedSubcategory }}" disabled>
                <option value="">Select category first</option>
            </select>
            <x-form.error name="items.{{ $index }}.subcategory" />
        </div>
        <div data-item-custom-subcategory hidden>
            <x-form.label for="items_{{ $index }}_custom_subcategory">Specify Subcategory</x-form.label>
            <input id="items_{{ $index }}_custom_subcategory" name="items[{{ $index }}][custom_subcategory]" value="{{ old("items.{$index}.custom_subcategory", $row['custom_subcategory'] ?? '') }}" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <x-form.error name="items.{{ $index }}.custom_subcategory" />
        </div>
        <div>
            <x-form.label for="items_{{ $index }}_quantity">Quantity</x-form.label>
            <input id="items_{{ $index }}_quantity" name="items[{{ $index }}][quantity]" type="number" step="0.01" min="0.01" value="{{ old("items.{$index}.quantity", $row['quantity'] ?? '') }}" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <x-form.error name="items.{{ $index }}.quantity" />
        </div>
        <div>
            <x-form.label for="items_{{ $index }}_unit">Unit</x-form.label>
            <select id="items_{{ $index }}_unit" name="items[{{ $index }}][unit]" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Select</option>
                @foreach (config('donations.units') as $value => $label)
                    <option value="{{ $value }}" @selected(old("items.{$index}.unit", $row['unit'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <x-form.error name="items.{{ $index }}.unit" />
        </div>
        <div>
            <x-form.label for="items_{{ $index }}_condition">Condition</x-form.label>
            <select id="items_{{ $index }}_condition" name="items[{{ $index }}][condition]" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Select</option>
                @foreach (config('donations.item_conditions') as $value => $label)
                    <option value="{{ $value }}" @selected(old("items.{$index}.condition", $row['condition'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <x-form.error name="items.{{ $index }}.condition" />
        </div>
    </div>
    <div class="mt-3 grid gap-3 md:grid-cols-[1fr_auto]">
        <div>
            <x-form.label for="items_{{ $index }}_estimated_value">Estimated Value</x-form.label>
            <input id="items_{{ $index }}_estimated_value" name="items[{{ $index }}][estimated_value]" type="number" step="0.01" min="0" value="{{ old("items.{$index}.estimated_value", $row['estimated_value'] ?? '') }}" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <x-form.error name="items.{{ $index }}.estimated_value" />
        </div>
        <button type="button" class="self-end rounded-md border border-red-200 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50" data-remove-item-row>Remove</button>
    </div>
</div>
