@props(['title', 'description' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col gap-1 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between']) }}>
    <div>
        <h2 class="text-xs font-bold uppercase tracking-wide text-slate-950">{{ $title }}</h2>
        @if ($description)
            <p class="mt-1 text-xs text-slate-500">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            {{ $actions }}
        </div>
    @endisset
</div>
