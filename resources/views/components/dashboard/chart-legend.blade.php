@props(['items', 'colors' => null])

{{--
    Pairs with x-dashboard.chart: a color-coded legend so chart identity is
    never carried by color alone. $items is an array of ['label' => ..., 'value' => ...].
--}}

@php
    $defaultPalette = ['#2563eb', '#f59e0b', '#f97316', '#ef4444', '#10b981', '#8b5cf6', '#0ea5e9', '#64748b'];
    $palette = $colors ?? $defaultPalette;
@endphp

<ul class="mt-4 space-y-2">
    @foreach ($items as $index => $item)
        <li class="flex items-center gap-2.5 rounded-lg bg-slate-50 px-3 py-2">
            <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $palette[$index % count($palette)] }}"></span>
            <span class="truncate text-sm text-slate-600">{{ $item['label'] }}</span>
            <span class="ml-auto text-sm font-semibold text-slate-950">{{ $item['value'] }}</span>
        </li>
    @endforeach
</ul>
