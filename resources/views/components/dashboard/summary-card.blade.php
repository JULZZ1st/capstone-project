@props(['label', 'value', 'description' => null, 'tone' => 'neutral'])

@php
    $toneClasses = match ($tone) {
        'positive' => 'border-emerald-200 bg-emerald-50',
        'warning' => 'border-amber-200 bg-amber-50',
        default => 'border-slate-200 bg-white',
    };

    $descriptionClasses = match ($tone) {
        'positive' => 'text-emerald-700',
        'warning' => 'text-amber-700',
        default => 'text-slate-600',
    };
@endphp

<article class="rounded-lg border {{ $toneClasses }} p-5 shadow-sm">
    <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
    <p class="mt-3 text-3xl font-bold text-slate-950">{{ $value }}</p>
    @if ($description)
        <p class="mt-2 text-sm {{ $descriptionClasses }}">{{ $description }}</p>
    @endif
</article>
