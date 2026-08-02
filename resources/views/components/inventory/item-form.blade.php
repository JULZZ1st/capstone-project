@props(['item' => null, 'categories', 'units'])

<div class="grid gap-4 md:grid-cols-2">
    <x-form.input name="name" label="Item Name" :value="$item?->name" required />

    <div>
        <x-form.label for="category">Category</x-form.label>
        <select id="category" name="category" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
            <option value="">Select category</option>
            @foreach ($categories as $category)
                <option value="{{ $category->key }}" @selected(old('category', $item?->category) === $category->key)>{{ $category->label }}</option>
            @endforeach
        </select>
        <x-form.error name="category" />
    </div>

    <div>
        <x-form.label for="unit">Unit</x-form.label>
        <select id="unit" name="unit" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
            @foreach ($units as $unit)
                <option value="{{ $unit->key }}" @selected(old('unit', $item?->unit ?? config('inventory.defaults.unit')) === $unit->key)>{{ $unit->label }}</option>
            @endforeach
        </select>
        <x-form.error name="unit" />
    </div>

    @if (! $item)
        <x-form.input name="initial_quantity" label="Initial Quantity" type="number" step="0.01" min="0" required />
    @endif

    <x-form.input name="minimum_reserve_quantity" label="Minimum Reserve Quantity" type="number" step="0.01" min="0" :value="$item?->minimum_reserve_quantity" required />
    <x-form.input name="estimated_unit_value" label="Estimated Unit Value" type="number" step="0.01" min="0" :value="$item?->estimated_unit_value" />
    <x-form.input name="expires_at" label="Expiration Date" type="date" :value="$item?->expires_at?->format('Y-m-d')" />
    <x-form.input name="batch_number" label="Batch Number" :value="$item?->batch_number" />
    <x-form.input name="storage_location" label="Storage Location" :value="$item?->storage_location" required />

    <div>
        <x-form.label for="status">Status</x-form.label>
        <select id="status" name="status" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
            @foreach (config('inventory.item_statuses') as $key => $status)
                <option value="{{ $key }}" @selected(old('status', $item?->status ?? config('inventory.defaults.status')) === $key)>{{ $status['label'] }}</option>
            @endforeach
        </select>
        <x-form.error name="status" />
    </div>
</div>

<div>
    <x-form.label for="description">Description</x-form.label>
    <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('description', $item?->description) }}</textarea>
    <x-form.error name="description" />
</div>

<div>
    <x-form.label for="notes">Notes</x-form.label>
    <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('notes', $item?->notes) }}</textarea>
    <x-form.error name="notes" />
</div>
