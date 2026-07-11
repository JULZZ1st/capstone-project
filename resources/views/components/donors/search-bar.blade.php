@props(['search' => '', 'selectedType' => null])

<form method="GET" action="{{ route('donors.index') }}" class="flex flex-col gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm lg:flex-row lg:items-end">
    <div class="flex-1">
        <label for="search" class="block text-sm font-medium text-slate-700">Search donors</label>
        <input
            id="search"
            name="search"
            type="search"
            value="{{ $search }}"
            placeholder="Name, email, contact, address, or area"
            class="mt-1 block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
        >
    </div>

    <div class="lg:w-56">
        <label for="type" class="block text-sm font-medium text-slate-700">Donor type</label>
        <select id="type" name="type" class="mt-1 block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <option value="">All types</option>
            @foreach (config('donors.types') as $value => $label)
                <option value="{{ $value }}" @selected($selectedType === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="flex gap-2">
        <x-button class="w-auto">Search</x-button>
        @if ($search !== '' || $selectedType)
            <a href="{{ route('donors.index') }}" class="inline-flex items-center justify-center rounded-md border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Clear
            </a>
        @endif
    </div>
</form>
