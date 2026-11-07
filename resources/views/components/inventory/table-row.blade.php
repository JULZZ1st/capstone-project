@props(['item'])

@php
    $categoryLabel = config('inventory.categories.'.$item->category, $item->category);
    $unitLabel = config('inventory.units.'.$item->unit, $item->unit);
    $percentage = $item->stockProgressPercentage();
@endphp

<tr @class(['bg-blue-50/60' => session('highlight_inventory_item') === $item->id, 'hover:bg-slate-50' => session('highlight_inventory_item') !== $item->id])>
    <td>
        <a href="{{ route('inventory.show', $item) }}" class="data-table__primary hover:text-blue-700">{{ $item->name }}</a>
        <span class="data-table__secondary">{{ $categoryLabel }}@if ($item->subcategory) / {{ $item->subcategory->label }}@endif</span>
        <span class="data-table__secondary max-w-56">{{ $item->storage_location }}@if ($item->batch_number) / Batch {{ $item->batch_number }}@endif</span>
    </td>
    <td class="data-table__number">
        <span class="font-semibold text-slate-950">{{ number_format((float) $item->current_quantity, 2) }}</span>
        <span class="block text-xs text-slate-500">{{ $unitLabel }}</span>
    </td>
    <td class="min-w-44 px-4 py-4 align-top">
        <div class="flex items-center justify-between gap-3">
            <span class="text-xs text-slate-500">Min {{ number_format((float) $item->minimum_reserve_quantity, 2) }}</span>
            <span class="text-xs font-semibold text-slate-700">{{ $percentage }}%</span>
        </div>
        <div class="mt-2 h-1.5 rounded-full bg-slate-100">
            <div class="h-1.5 rounded-full {{ $item->stockStatus() === 'adequate' ? 'bg-emerald-500' : 'bg-amber-500' }}" style="width: {{ $percentage }}%"></div>
        </div>
    </td>
    <td class="px-4 py-4 align-top">
        <x-inventory.status-badge :status="$item->stockStatus()" />
    </td>
    <td>
        <span class="block min-w-28">{{ $item->updated_at?->diffForHumans() }}</span>
        <span class="data-table__secondary">Audited: {{ $item->last_audited_at?->format('M d, Y') ?? 'Not audited' }}</span>
    </td>
    <td class="px-5 py-4 text-right align-top">
        <div class="data-table__actions">
            <a href="{{ route('inventory.show', $item) }}" class="data-table__action">View</a>
            @can('adjustStock', $item)
                <button type="button" class="data-table__action" data-dialog-open="adjust-stock-dialog-{{ $item->id }}">
                    Adjust Stock
                </button>
            @endcan
        </div>
    </td>
</tr>
