@props([
    'id',
    'title',
    'subtitle' => null,
    'event' => null,
    'details' => [],
    'date' => null,
])

<dialog id="{{ $id }}" class="m-auto w-[min(560px,calc(100vw-2rem))] rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/55" aria-labelledby="{{ $id }}-title">
    <div class="overflow-hidden bg-white">
        <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">{{ $event ?: 'Activity details' }}</p>
                <h2 id="{{ $id }}-title" class="mt-1 truncate text-lg font-bold text-slate-950">{{ $title }}</h2>
                @if ($subtitle)<p class="mt-1 text-sm text-slate-500">{{ $subtitle }}</p>@endif
            </div>
            <button type="button" class="rounded-lg px-2 py-1 text-2xl leading-none text-slate-400 hover:bg-slate-100 hover:text-slate-700" data-dialog-close="{{ $id }}" aria-label="Close activity details">&times;</button>
        </header>
        <div class="space-y-3 px-5 py-5">
            @foreach ($details as $label => $value)
                <div class="flex items-start justify-between gap-5 border-b border-slate-100 pb-3 last:border-0 last:pb-0">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</dt>
                    <dd class="text-right text-sm font-medium text-slate-900">{{ $value ?: 'Not provided' }}</dd>
                </div>
            @endforeach
            @if ($date)<p class="pt-1 text-xs text-slate-500">Recorded {{ optional($date)->format('M d, Y h:i A') }}</p>@endif
        </div>
        <footer class="flex justify-end border-t border-slate-200 bg-slate-50 px-5 py-3">
            <button type="button" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800" data-dialog-close="{{ $id }}">Close</button>
        </footer>
    </div>
</dialog>
