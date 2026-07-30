@props(['item'])

@php($open = old('_modal') === 'adjust-'.$item->id)

<dialog id="adjust-stock-dialog-{{ $item->id }}" class="w-full max-w-xl rounded-lg p-0 shadow-xl backdrop:bg-slate-950/50" @if($open) open @endif>
    <form method="POST" action="{{ route('inventory.adjustments.store', $item) }}" class="bg-white">
        @csrf
        <input type="hidden" name="_modal" value="adjust-{{ $item->id }}">

        <div class="flex items-start justify-between border-b border-slate-200 px-6 py-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-950">Adjust Stock</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $item->name }}</p>
            </div>
            <button type="button" class="rounded-md px-2 py-1 text-sm text-slate-500 hover:bg-slate-100" data-dialog-close="adjust-stock-dialog-{{ $item->id }}">Close</button>
        </div>

        <div class="space-y-4 px-6 py-5">
            <div class="rounded-md bg-slate-50 p-3 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-slate-500">Current stock</span>
                    <span class="font-semibold text-slate-950">{{ number_format((float) $item->current_quantity, 2) }} {{ config('inventory.units.'.$item->unit, $item->unit) }}</span>
                </div>
                <div class="mt-2 flex items-center justify-between gap-3">
                    <span class="text-slate-500">Status</span>
                    <x-inventory.status-badge :status="$item->stockStatus()" />
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <x-form.label for="movement_type_{{ $item->id }}">Movement Type</x-form.label>
                    <select id="movement_type_{{ $item->id }}" name="movement_type" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        @foreach (config('inventory.movement_types') as $key => $label)
                            <option value="{{ $key }}" @selected(old('_modal') === 'adjust-'.$item->id && old('movement_type') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @if($open)<x-form.error name="movement_type" />@endif
                </div>

                <div>
                    <x-form.label for="quantity_{{ $item->id }}">Quantity</x-form.label>
                    <input
                        id="quantity_{{ $item->id }}"
                        name="quantity"
                        type="number"
                        step="0.01"
                        min="0.01"
                        value="{{ $open ? old('quantity') : '' }}"
                        class="mt-1 block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        required
                    >
                    @if($open)<x-form.error name="quantity" />@endif
                </div>
            </div>

            <div>
                <x-form.label for="reason_{{ $item->id }}">Reason</x-form.label>
                <select id="reason_{{ $item->id }}" name="reason" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                    <option value="">Select reason</option>
                    @foreach (config('inventory.adjustment_reasons') as $key => $label)
                        <option value="{{ $key }}" @selected(old('_modal') === 'adjust-'.$item->id && old('reason') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                @if($open)<x-form.error name="reason" />@endif
            </div>

            <div>
                <x-form.label for="reference_number_{{ $item->id }}">Reference Number</x-form.label>
                <input
                    id="reference_number_{{ $item->id }}"
                    name="reference_number"
                    value="{{ $open ? old('reference_number') : '' }}"
                    class="mt-1 block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                >
                @if($open)<x-form.error name="reference_number" />@endif
            </div>

            <div>
                <x-form.label for="remarks_{{ $item->id }}">Remarks</x-form.label>
                <textarea id="remarks_{{ $item->id }}" name="remarks" rows="3" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ $open ? old('remarks') : '' }}</textarea>
                @if($open)<x-form.error name="remarks" />@endif
            </div>
        </div>

        <div class="flex justify-end gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4">
            <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-white" data-dialog-close="adjust-stock-dialog-{{ $item->id }}">Cancel</button>
            <x-button class="w-auto">Save Adjustment</x-button>
        </div>
    </form>
</dialog>
