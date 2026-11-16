@props(['search' => '', 'selectedType' => null])

<form method="GET" action="{{ route('donors.index') }}" class="flex w-full min-w-0 flex-col gap-3 sm:flex-row sm:items-center" data-donor-search-form>
    <div class="relative min-w-0 flex-1">
        <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <input
            id="search"
            name="search"
            type="search"
            value="{{ $search }}"
            placeholder="Search donors..."
            autocomplete="off"
            data-donor-live-search
            class="ui-control block h-10 w-full py-2 pl-9 pr-3"
        >
    </div>

    <select
        id="type"
        name="type"
        class="ui-control h-10 pr-10 sm:w-40"
        data-donor-live-filter
    >
        <option value="">All types</option>
        @foreach (config('donors.types') as $value => $label)
            <option value="{{ $value }}" @selected($selectedType === $value)>{{ $label }}</option>
        @endforeach
    </select>

</form>
