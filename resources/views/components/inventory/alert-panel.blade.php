@props(['alerts'])

@php
    $groups = [
        'out_of_stock' => ['label' => 'Out of Stock', 'type' => 'error'],
        'critical_stock' => ['label' => 'Critical Stock', 'type' => 'warning'],
        'low_stock' => ['label' => 'Low Stock', 'type' => 'warning'],
        'expired' => ['label' => 'Expired', 'type' => 'error'],
        'expiring_soon' => ['label' => 'Expiring Soon', 'type' => 'info'],
    ];
    $hasAlerts = collect($alerts)->flatten(1)->isNotEmpty();
@endphp

<div class="rounded-lg border border-slate-200 bg-white p-4">
    <div class="mb-3 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-950">Inventory Alerts</h2>
        <span class="text-xs font-medium text-slate-500">Reserve and expiration watchlist</span>
    </div>

    @if (! $hasAlerts)
        <p class="rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-600">No inventory alerts right now.</p>
    @else
        <div class="grid gap-3 lg:grid-cols-5">
            @foreach ($groups as $key => $group)
                <div class="rounded-md border border-slate-200 p-3">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase text-slate-500">{{ $group['label'] }}</p>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">{{ $alerts[$key]->count() }}</span>
                    </div>

                    <div class="mt-3 space-y-2">
                        @forelse ($alerts[$key] as $item)
                            <a href="{{ route('inventory.show', $item) }}" class="block rounded-md bg-slate-50 px-2 py-2 text-sm hover:bg-slate-100">
                                <span class="block truncate font-semibold text-slate-900">{{ $item->name }}</span>
                                <span class="text-xs text-slate-500">{{ number_format((float) $item->current_quantity, 2) }} {{ config('inventory.units.'.$item->unit, $item->unit) }}</span>
                            </a>
                        @empty
                            <p class="text-xs text-slate-500">None</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
