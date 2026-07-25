@props(['source'])

<div class="mb-4 last:mb-0">
    <div class="mb-1 flex items-center justify-between text-sm">
        <span class="font-medium text-emerald-800">{{ $source['label'] }}</span>
        <span class="font-semibold text-slate-950">₱{{ number_format($source['amount'], 0) }}</span>
    </div>
    <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
        <div class="h-full rounded-full bg-emerald-600" style="width: {{ $source['percentage'] }}%"></div>
    </div>
</div>
