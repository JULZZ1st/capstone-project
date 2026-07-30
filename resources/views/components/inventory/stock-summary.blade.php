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
        <p class="mt-2 text-base font-semibold text-slate-950">{{ $categoryLabel }}</p>
        <p class="text-sm text-slate-500">{{ $item->storage_location }}</p>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase text-slate-500">Last Audited</p>
        <p class="mt-2 text-base font-semibold text-slate-950">{{ $item->last_audited_at?->format('M d, Y') ?? 'Not audited yet' }}</p>
        <p class="text-sm text-slate-500">Updated {{ $item->updated_at?->diffForHumans() }}</p>
    </div>
</div>
