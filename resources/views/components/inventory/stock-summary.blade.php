@props(['item'])

@php
    $percentage = $item->stockProgressPercentage();
    $categoryLabel = config('inventory.categories.'.$item->category, $item->category);
    $unitLabel = config('inventory.units.'.$item->unit, $item->unit);
@endphp

<div class="grid gap-4 md:grid-cols-4">
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase text-slate-500">Current Stock</p>
        <p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format((float) $item->current_quantity, 2) }}</p>
        <p class="text-sm text-slate-500">{{ $unitLabel }}</p>
        <div class="mt-3 border-t border-slate-100 pt-3">
            <p class="text-xs text-slate-500">Estimated value per unit</p>
            @if ($item->estimated_unit_value !== null)
                <p class="mt-1 break-words text-base font-semibold tabular-nums text-slate-950"><x-format.currency :value="$item->estimated_unit_value" /></p>
                <p class="text-xs text-slate-500">per {{ $unitLabel }}</p>
            @else
                <p class="mt-1 text-sm text-slate-500">Not set</p>
            @endif
        </div>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase text-slate-500">Minimum Reserve</p>
        <p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format((float) $item->minimum_reserve_quantity, 2) }}</p>
        <div class="mt-3 h-2 rounded-full bg-slate-100">
            <div class="h-2 rounded-full bg-blue-700" style="width: {{ $percentage }}%"></div>
        </div>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase text-slate-500">Category</p>
        <p class="mt-2 text-base font-semibold text-slate-950">{{ $categoryLabel }}@if ($item->subcategory) · {{ $item->subcategory->label }} @endif</p>
        <p class="text-sm text-slate-500">{{ $item->storage_location }}</p>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase text-slate-500">Last Audited</p>
        <p class="mt-2 text-base font-semibold text-slate-950">{{ $item->last_audited_at?->format('M d, Y') ?? 'Not audited yet' }}</p>
        <p class="text-sm text-slate-500">Updated {{ $item->updated_at?->diffForHumans() }}</p>
    </div>
</div>
