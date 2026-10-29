@props([
    'id',
    'name',
    'placeholder' => 'Select an option',
    'searchable' => false,
])

<div class="relative min-w-0" data-custom-select data-custom-select-placeholder="{{ $placeholder }}" @if($searchable) data-custom-select-searchable @endif>
    <select
        id="{{ $id }}"
        name="{{ $name }}"
        class="sr-only"
        tabindex="-1"
        aria-hidden="true"
        data-custom-select-native
        {{ $attributes }}
    >
        {{ $slot }}
    </select>

    <button
        type="button"
        class="flex h-10 w-full min-w-0 items-center justify-between gap-3 rounded-lg border border-slate-300 bg-white px-3 text-left text-sm text-slate-700 shadow-sm transition hover:border-slate-400 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
        id="{{ $id }}-trigger"
        aria-haspopup="listbox"
        aria-expanded="false"
        aria-controls="{{ $id }}-menu"
        data-custom-select-trigger
    >
        <span class="min-w-0 truncate" data-custom-select-value>{{ $placeholder }}</span>
        <x-icon name="chevron-down" class="h-4 w-4 shrink-0 text-slate-400 transition-transform" data-custom-select-chevron />
    </button>

    <div
        id="{{ $id }}-menu"
        class="fixed z-[200] hidden max-h-60 overflow-y-auto rounded-lg border border-slate-200 bg-white p-1 shadow-xl ring-1 ring-slate-950/5"
        role="listbox"
        aria-labelledby="{{ $id }}-trigger"
        data-custom-select-menu
    >
        @if($searchable)
            <div class="sticky top-0 z-10 bg-white p-1" data-custom-select-search-wrapper>
                <label class="sr-only" for="{{ $id }}-search">Search {{ $placeholder }}</label>
                <input id="{{ $id }}-search" type="search" placeholder="Search donors" class="h-9 w-full rounded-md border border-slate-200 bg-slate-50 px-3 text-sm text-slate-700 outline-none focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/20" data-custom-select-search>
            </div>
        @endif
        <div class="space-y-0.5" data-custom-select-options></div>
        <p class="hidden px-3 py-2 text-sm text-slate-500" data-custom-select-empty>No matching options.</p>
    </div>
</div>
