@props(['title', 'subtitle' => null, 'icon' => null, 'tone' => 'neutral'])

@php
    $badgeClasses = match ($tone) {
        'positive' => 'bg-emerald-100 text-emerald-700',
        'warning' => 'bg-amber-100 text-amber-700',
        'info' => 'bg-blue-100 text-blue-700',
        'purple' => 'bg-violet-100 text-violet-700',
        'rose' => 'bg-rose-100 text-rose-700',
        default => 'bg-slate-100 text-slate-600',
    };
@endphp

<section {{ $attributes->merge(['class' => 'rounded-2xl border border-slate-200 bg-white p-5 shadow-sm']) }}>
    <div class="mb-4 flex items-start gap-3">
        @if ($icon)
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $badgeClasses }}">
                <x-icon :name="$icon" class="h-5 w-5" />
            </span>
        @endif
        <div class="min-w-0">
            <h2 class="text-base font-semibold text-slate-950">{{ $title }}</h2>
            @if ($subtitle)
                <p class="mt-0.5 text-sm text-slate-500">{{ $subtitle }}</p>
            @endif
        </div>
    </div>

    {{ $slot }}
</section>
