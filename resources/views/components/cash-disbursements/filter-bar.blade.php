@props(['filters'])

<form method="GET" action="{{ route('cash-disbursements.index') }}" class="rounded-lg border border-slate-200 bg-white p-4">
    <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_180px_160px_160px_auto]">
        <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search recipient, reference, notes" class="rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
        <select name="purpose" class="rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <option value="">All purposes</option>
            @foreach (config('disbursements.purposes') as $value => $label)
                <option value="{{ $value }}" @selected(($filters['purpose'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
        <div class="flex gap-2">
            <x-button class="w-auto">Filter</x-button>
            <a href="{{ route('cash-disbursements.index') }}" class="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
        </div>
    </div>
</form>
