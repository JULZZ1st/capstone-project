@props(['title', 'subtitle' => null])

<section {{ $attributes->merge(['class' => 'rounded-lg border border-slate-200 bg-white p-5 shadow-sm']) }}>
    <div class="mb-4">
        <h2 class="text-base font-semibold text-slate-950">{{ $title }}</h2>
        @if ($subtitle)
            <p class="mt-1 text-sm text-slate-500">{{ $subtitle }}</p>
        @endif
    </div>

    {{ $slot }}
</section>
