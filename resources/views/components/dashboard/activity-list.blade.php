@props(['items'])

@if ($items->isEmpty())
    <x-dashboard.empty-state message="No recent system activity." />
@else
    <ul class="space-y-3">
        @foreach ($items as $item)
            <li class="rounded-md bg-slate-50 px-3 py-2">
                <p class="text-sm font-medium text-slate-900">{{ $item['event'] }}</p>
                <p class="text-sm text-slate-600">{{ $item['label'] }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ optional($item['created_at'])->diffForHumans() }}</p>
            </li>
        @endforeach
    </ul>
@endif
