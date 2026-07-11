@props(['filters', 'campaigns'])

<form method="GET" action="{{ route('donations.index') }}" class="rounded-lg border border-slate-200 bg-white p-4">
    <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_180px_180px_180px_auto]">
        <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search donor, reference, item, notes" class="rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
        <select name="type" class="rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <option value="">All types</option>
            @foreach (config('donations.types') as $value => $label)
                <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="campaign_id" class="rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <option value="">All campaigns</option>
            @foreach ($campaigns as $campaign)
                <option value="{{ $campaign->id }}" @selected((string) ($filters['campaign_id'] ?? '') === (string) $campaign->id)>{{ $campaign->name }}</option>
            @endforeach
        </select>
        <select name="status" class="rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <option value="">All statuses</option>
            @foreach (config('donations.statuses') as $value => $status)
                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $status['label'] }}</option>
            @endforeach
        </select>
        <div class="flex gap-2">
            <x-button class="w-auto">Filter</x-button>
            <a href="{{ route('donations.index') }}" class="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
        </div>
    </div>
</form>
