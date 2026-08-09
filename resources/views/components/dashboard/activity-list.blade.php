@props(['items'])

@php
    $iconFor = fn (string $event) => match (true) {
        str_contains($event, 'Contribution') => 'gift',
        str_contains($event, 'Inventory') => 'archive',
        str_contains($event, 'disbursed') => 'banknotes',
        default => 'terminal',
    };
@endphp

@if ($items->isEmpty())
    <x-dashboard.empty-state icon="terminal" message="No recent system activity." />
@else
    <ul class="space-y-2.5">
        @foreach ($items as $item)
            <li class="flex items-start gap-3 rounded-xl bg-slate-50 px-3 py-2.5">
                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white text-slate-500 ring-1 ring-slate-200">
                    <x-icon :name="$iconFor($item['event'])" class="h-4 w-4" />
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-slate-900">{{ $item['event'] }}</p>
                    <p class="truncate text-sm text-slate-600">{{ $item['label'] }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">{{ optional($item['created_at'])->diffForHumans() }}</p>
                </div>
            </li>
        @endforeach
    </ul>
@endif
