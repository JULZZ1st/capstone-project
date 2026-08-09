@props(['message', 'icon' => 'inbox', 'action' => null, 'actionLabel' => null])

<div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50/60 px-4 py-8 text-center">
    <span class="flex h-11 w-11 items-center justify-center rounded-full bg-white text-slate-400 ring-1 ring-slate-200">
        <x-icon :name="$icon" class="h-5 w-5" />
    </span>
    <p class="mt-3 text-sm text-slate-500">{{ $message }}</p>
    @if ($action && $actionLabel)
        <a href="{{ $action }}" class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-blue-700 hover:text-blue-800">
            {{ $actionLabel }} <span aria-hidden="true">&rarr;</span>
        </a>
    @endif
</div>
