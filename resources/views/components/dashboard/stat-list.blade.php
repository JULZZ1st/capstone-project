@props(['items'])

@if ($items->isEmpty())
    <x-dashboard.empty-state message="No records available." />
@else
    <dl class="space-y-3">
        @foreach ($items as $item)
            <div class="flex items-center justify-between gap-4 rounded-md bg-slate-50 px-3 py-2">
                <dt class="truncate text-sm text-slate-600">{{ $item->status ?? $item->purpose }}</dt>
                <dd class="text-sm font-semibold text-slate-950">
                    {{ isset($item->total) && is_numeric($item->total) ? $item->total : 0 }}
                </dd>
            </div>
        @endforeach
    </dl>
@endif
