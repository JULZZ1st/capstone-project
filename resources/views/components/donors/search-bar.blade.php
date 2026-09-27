@props(['search' => '', 'selectedType' => null])

<form method="GET" action="{{ route('donors.index') }}" class="flex w-full flex-col gap-3 sm:flex-row sm:items-center lg:max-w-2xl">
    <div class="relative min-w-0 flex-1">
        <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <input
            id="search"
            name="search"
            type="search"
            value="{{ $search }}"
            placeholder="Search donors..."
            class="block h-10 w-full rounded-md border-slate-300 bg-white py-2 pl-9 pr-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
        >
    </div>

    <select
        id="type"
        name="type"
        class="h-10 rounded-md border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:w-40"
        onchange="this.form.submit()"
    >
        <option value="">All types</option>
        @foreach (config('donors.types') as $value => $label)
            <option value="{{ $value }}" @selected($selectedType === $value)>{{ $label }}</option>
        @endforeach
    </select>

    @if ($search !== '' || $selectedType)
        <a href="{{ route('donors.index') }}" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-600 shadow-sm hover:bg-slate-50">
            Clear
        </a>
    @endif
</form>
