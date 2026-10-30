<dialog
    id="add-item-dialog"
    class="m-auto w-[min(560px,calc(100vw-1.5rem))] max-w-full overflow-hidden rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/50"
    aria-labelledby="add-item-title"
>
    <div class="flex max-h-[calc(100vh-2rem)] min-h-0 flex-col bg-white">
        <div class="flex shrink-0 items-center justify-between border-b border-slate-200 px-5 py-3.5">
            <h2 id="add-item-title" class="text-base font-semibold text-slate-950" data-item-modal-title>Add Item</h2>
            <button type="button" class="rounded-md px-2 py-1 text-2xl leading-none text-slate-500 hover:bg-slate-100" data-dialog-close="add-item-dialog" aria-label="Close modal">&times;</button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
            <div
                class="grid min-w-0 gap-3 sm:grid-cols-2"
                data-item-row
                data-expiration-categories='@json(config('donations.item_expiration_categories'))'
                data-equipment-categories='@json(config('donations.item_equipment_categories'))'
            >
                <div class="min-w-0 sm:col-span-2">
                    <x-form.label for="item_modal_name">Item Name</x-form.label>
                    <input id="item_modal_name" type="text" autofocus class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" data-item-field="name">
                </div>
                <div class="min-w-0 sm:col-span-2">
                    <x-form.label for="item_modal_category">Category</x-form.label>
                    <x-form.custom-select id="item_modal_category" name="item_modal_category" placeholder="Select" data-item-category data-item-field="category" aria-label="Category">
                        <option value="">Select</option>
                        @foreach (config('donations.item_main_categories') as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-form.custom-select>
                </div>
                <div class="min-w-0 sm:col-span-2" data-item-category-description hidden>
                    <x-form.label for="item_modal_category_description">Specify Category</x-form.label>
                    <input id="item_modal_category_description" type="text" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" data-item-field="category_description">
                </div>
                <div class="min-w-0">
                    <x-form.label for="item_modal_quantity">Quantity</x-form.label>
                    <input id="item_modal_quantity" type="number" step="0.01" min="0.01" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" data-item-field="quantity">
                </div>
                <div class="min-w-0">
                    <x-form.label for="item_modal_unit">Unit</x-form.label>
                    <x-form.custom-select id="item_modal_unit" name="item_modal_unit" placeholder="Select" data-item-field="unit" aria-label="Unit">
                        <option value="">Select</option>
                        @foreach (config('donations.units') as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-form.custom-select>
                </div>
                <div class="min-w-0 sm:col-span-2">
                    <x-form.label for="item_modal_condition">Condition</x-form.label>
                    <x-form.custom-select id="item_modal_condition" name="item_modal_condition" placeholder="Select" data-item-field="condition" aria-label="Condition">
                        <option value="">Select</option>
                        @foreach (config('donations.item_conditions') as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-form.custom-select>
                </div>

                <div class="min-w-0 sm:col-span-2" data-item-expiration hidden>
                    <x-form.label for="item_modal_expiration_date">Expiration Date <span class="font-normal text-slate-400">(optional)</span></x-form.label>
                    <input id="item_modal_expiration_date" type="date" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" data-item-field="expiration_date">
                </div>

                <div class="min-w-0 contents" data-item-equipment-fields hidden>
                    <div class="min-w-0">
                        <x-form.label for="item_modal_brand">Brand <span class="font-normal text-slate-400">(optional)</span></x-form.label>
                        <input id="item_modal_brand" type="text" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" data-item-field="brand">
                    </div>
                    <div class="min-w-0">
                        <x-form.label for="item_modal_model">Model <span class="font-normal text-slate-400">(optional)</span></x-form.label>
                        <input id="item_modal_model" type="text" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" data-item-field="model">
                    </div>
                    <div class="min-w-0 sm:col-span-2">
                        <x-form.label for="item_modal_serial_number">Serial Number <span class="font-normal text-slate-400">(optional)</span></x-form.label>
                        <input id="item_modal_serial_number" type="text" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-slate-100 disabled:text-slate-400" data-item-field="serial_number">
                        <p class="mt-1 text-xs text-slate-400" data-item-serial-hint hidden>Serial numbers only apply to a single unit &mdash; set Quantity to 1 to record one.</p>
                    </div>
                </div>

                <div class="min-w-0 sm:col-span-2">
                    <x-form.label for="item_modal_estimated_value">Estimated Total Value (PHP) <span class="font-normal text-slate-400">(optional)</span></x-form.label>
                    <input id="item_modal_estimated_value" type="number" step="0.01" min="0" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" data-item-field="estimated_value">
                </div>
            </div>

            <p class="mt-3 hidden rounded-lg bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700" data-item-modal-error role="alert"></p>
        </div>

        <div class="flex shrink-0 items-center justify-end gap-3 border-t border-slate-200 bg-slate-50 px-5 py-3">
            <button type="button" class="h-9 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50" data-dialog-close="add-item-dialog">Cancel</button>
            <button type="button" class="h-9 rounded-lg bg-blue-700 px-4 text-sm font-semibold text-white hover:bg-blue-800" data-item-modal-submit>Add Item</button>
        </div>
    </div>
</dialog>
