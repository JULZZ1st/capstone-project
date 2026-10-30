@props([
    'caption',
    'title' => null,
    'description' => null,
    'minWidth' => '48rem',
    'maxHeight' => 'min(65vh, 40rem)',
    'bodyHook' => null,
    'scrollHook' => null,
])

<section {{ $attributes->class(['data-table']) }} data-table>
    @if ($title || $description || isset($toolbar))
        <div class="data-table__toolbar">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="text-base font-semibold text-slate-950">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-1 text-sm leading-5 text-slate-500">{{ $description }}</p>
                @endif
            </div>
            @isset($toolbar)
                {{ $toolbar }}
            @endisset
        </div>
    @endif

    <div class="data-table__scroll" data-table-scroll @if ($scrollHook) {{ $scrollHook }} @endif tabindex="0" role="region" aria-label="{{ $caption }}" style="max-height: {{ $maxHeight }}">
        <table class="data-table__table" style="min-width: {{ $minWidth }}">
            <caption class="sr-only">{{ $caption }}</caption>
            <thead><tr>{{ $head }}</tr></thead>
            <tbody @if ($bodyHook) {{ $bodyHook }} @endif>{{ $slot }}</tbody>
        </table>
    </div>

    <div class="data-table__hint" data-table-hint hidden>
        <x-icon name="chevron-right" class="h-4 w-4 shrink-0" />
        <span>Scroll sideways to see all columns. You can also use the arrow keys.</span>
    </div>
    @isset($footer)
        <div class="data-table__footer">{{ $footer }}</div>
    @endisset
</section>
