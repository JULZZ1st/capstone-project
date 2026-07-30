@props(['item'])

@php
    $categoryLabel = config('inventory.categories.'.$item->category, $item->category);
    $unitLabel = config('inventory.units.'.$item->unit, $item->unit);
    $percentage = $item->stockProgressPercentage();
@endphp

<tr @class(['bg-blue-50/60' => session('highlight_inventory_item') === $item->id])>
    <td class="px-4 py-4 align-top">
        <a href="{{ route('inventory.show', $item) }}" class="font-semibold text-slate-950 hover:text-blue-700">{{ $item->name }}</a>
        <p class="mt-1 max-w-md text-xs text-slate-500">{{ $item->storage_location }}</p>
        @if ($item->batch_number)
            <p class="mt-1 text-xs text-slate-500">Batch {{ $item->batch_number }}</p>
        @endif
    </td>
    <td class="px-4 py-4 align-top">
        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 ring-1 ring-inset ring-slate-200">
            {{ $categoryLabel }}
        </span>
    </td>
    <td class="px-4 py-4 text-right align-top">
        <span class="font-semibold text-slate-950">{{ number_format((float) $item->current_quantity, 2) }}</span>
        <span class="block text-xs text-slate-500">{{ $unitLabel }}</span>
    </td>
    <td class="min-w-44 px-4 py-4 align-top">
        <div class="flex items-center justify-between gap-3">
            <span class="text-xs text-slate-500">Min {{ number_format((float) $item->minimum_reserve_quantity, 2) }}</span>
            <span class="text-xs font-semibold text-slate-700">{{ $percentage }}%</span>
        </div>
        <div class="mt-2 h-2 rounded-full bg-slate-100">
            <div class="h-2 rounded-full {{ $item->stockStatus() === 'adequate' ? 'bg-emerald-600' : 'bg-amber-500' }}" style="width: {{ $percentage }}%"></div>
        </div>
    </td>
    <td class="px-4 py-4 align-top">
        <x-inventory.status-badge :status="$item->stockStatus()" />
    </td>
    <td class="hidden px-4 py-4 text-slate-600 align-top lg:table-cell">
        {{ $item->last_audited_at?->format('M d, Y') ?? 'Not audited' }}
    </td>
    <td class="hidden px-4 py-4 text-slate-600 align-top md:table-cell">
        {{ $item->updated_at?->diffForHumans() }}
    </td>
    <td class="px-4 py-4 text-right align-top">
        <div class="flex justify-end gap-2">
            <a href="{{ route('inventory.show', $item) }}" class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">View</a>
            @can('adjustStock', $item)
                <button type="button" class="rounded-md bg-blue-700 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-800" data-dialog-open="adjust-stock-dialog-{{ $item->id }}">
                    Adjust Stock
                </button>
            @endcan
        </div>
    </td>
</tr>
