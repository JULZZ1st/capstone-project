@props(['label', 'value', 'description' => null, 'tone' => 'neutral', 'icon' => null])

@php
    $toneStyles = match ($tone) {
        'positive' => ['card' => 'border-emerald-200/70 bg-emerald-50/70', 'badge' => 'bg-emerald-100 text-emerald-700', 'text' => 'text-emerald-700'],
        'warning' => ['card' => 'border-amber-200/70 bg-amber-50/70', 'badge' => 'bg-amber-100 text-amber-700', 'text' => 'text-amber-700'],
        'info' => ['card' => 'border-blue-200/70 bg-blue-50/70', 'badge' => 'bg-blue-100 text-blue-700', 'text' => 'text-blue-700'],
        'purple' => ['card' => 'border-violet-200/70 bg-violet-50/70', 'badge' => 'bg-violet-100 text-violet-700', 'text' => 'text-violet-700'],
        'danger' => ['card' => 'border-rose-200/70 bg-rose-50/70', 'badge' => 'bg-rose-100 text-rose-700', 'text' => 'text-rose-700'],
        default => ['card' => 'border-slate-200 bg-white', 'badge' => 'bg-slate-100 text-slate-600', 'text' => 'text-slate-600'],
    };
@endphp

<article {{ $attributes->merge(['class' => 'rounded-2xl border p-5 shadow-sm transition hover:shadow-md '.$toneStyles['card']]) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-sm font-medium text-slate-600">{{ $label }}</p>
        @if ($icon)
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $toneStyles['badge'] }}">
                <x-icon :name="$icon" class="h-5 w-5" />
            </span>
        @endif
    </div>
    <p class="mt-3 text-3xl font-bold tracking-tight text-slate-950">{{ $value }}</p>
    @if ($description)
        <p class="mt-2 text-sm {{ $toneStyles['text'] }}">{{ $description }}</p>
    @endif
</article>
