@props(['message', 'description' => null, 'icon' => 'inbox', 'action' => null, 'actionLabel' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center rounded-lg border border-dashed border-slate-300 bg-white/70 px-4 py-8 text-center']) }}>
    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 text-blue-600 ring-1 ring-blue-100">
        <x-icon :name="$icon" class="h-5 w-5" />
    </span>
    <p class="mt-3 text-sm font-semibold text-slate-950">{{ $message }}</p>
    @if ($description)
        <p class="mt-1 text-xs text-slate-500">{{ $description }}</p>
    @endif
    @if ($action && $actionLabel)
        <a href="{{ $action }}" class="mt-2 inline-flex items-center gap-1 text-sm font-semibold text-blue-700 hover:text-blue-800">
            {{ $actionLabel }} <span aria-hidden="true">&rarr;</span>
        </a>
    @endif
</div>
