@props(['alerts'])

@php
    $groups = [
        'out_of_stock' => ['label' => 'Out of Stock', 'tone' => 'rose'],
        'critical_stock' => ['label' => 'Critical Stock', 'tone' => 'amber'],
        'low_stock' => ['label' => 'Low Stock', 'tone' => 'amber'],
        'expired' => ['label' => 'Expired', 'tone' => 'rose'],
        'expiring_soon' => ['label' => 'Expiring Soon', 'tone' => 'blue'],
    ];
    $toneClasses = [
        'rose' => 'bg-rose-100 text-rose-700',
        'amber' => 'bg-amber-100 text-amber-700',
        'blue' => 'bg-blue-100 text-blue-700',
    ];
    $hasAlerts = collect($alerts)->flatten(1)->isNotEmpty();
@endphp

<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-start gap-3">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $hasAlerts ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">
                <x-icon :name="$hasAlerts ? 'alert-triangle' : 'check-circle'" class="h-5 w-5" />
            </span>
            <div class="min-w-0">
                <h2 class="text-base font-semibold text-slate-950">Inventory Alerts</h2>
                <p class="mt-0.5 text-sm text-slate-500">
                    {{ $hasAlerts ? 'Reserve and expiration watchlist.' : "Doesn't require immediate attention right now." }}
                </p>
            </div>
        </div>

        @unless ($hasAlerts)
            <span class="inline-flex shrink-0 items-center gap-1.5 self-start rounded-full bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-200 sm:self-auto">
                All items are in good condition
                <x-icon name="chevron-right" class="h-4 w-4" />
            </span>
        @endunless
    </div>

    @if ($hasAlerts)
        <div class="mt-4 grid gap-3 lg:grid-cols-5">
            @foreach ($groups as $key => $group)
                <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $group['label'] }}</p>
                        <span class="inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full px-1.5 text-xs font-semibold {{ $toneClasses[$group['tone']] }}">
                            {{ $alerts[$key]->count() }}
                        </span>
                    </div>

                    <div class="mt-3 space-y-1.5">
                        @forelse ($alerts[$key] as $item)
                            <a href="{{ route('inventory.show', $item) }}" class="block rounded-lg bg-white px-2.5 py-2 text-sm ring-1 ring-inset ring-slate-200 transition hover:ring-blue-300">
                                <span class="block truncate font-semibold text-slate-900">{{ $item->name }}</span>
                                <span class="text-xs text-slate-500">{{ number_format((float) $item->current_quantity, 2) }} {{ config('inventory.units.'.$item->unit, $item->unit) }}</span>
                            </a>
                        @empty
                            <p class="px-0.5 text-xs text-slate-400">None</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>
