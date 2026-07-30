@props(['filters', 'actions'])

<form method="GET" action="{{ route('system-security.index') }}" {{ $attributes->merge(['class' => 'flex flex-col gap-2 sm:flex-row sm:items-center']) }}>
    <x-system-security.filter-dropdown :actions="$actions" :selected="$filters['action'] ?? null" />
    <input
        type="search"
        name="search"
        value="{{ $filters['search'] ?? '' }}"
        placeholder="Search logs details..."
        class="h-9 rounded-md border-slate-300 text-xs shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:w-64"
    >
    <button class="rounded-md bg-slate-950 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">
        Search
    </button>
</form>
