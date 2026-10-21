@props(['filters', 'campaigns'])

<form method="GET" action="{{ route('donations.index') }}" class="p-0">
    <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_160px_180px_160px_auto]">
        <div>
            <x-form.label for="search"></x-form.label>
            <div class="relative mt-1.5">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    id="search"
                    name="search"
                    value="{{ $filters['search'] ?? '' }}"
                    placeholder="Search donor, reference, item, notes"
                    class="block w-full rounded-lg border-slate-300 py-2.5 pl-9 pr-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                >
            </div>
        </div>

        <div>
            <x-form.label for="type"></x-form.label>
            <select id="type" name="type" class="mt-1.5 block w-full rounded-lg border-slate-300 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">All types</option>
                @foreach (config('donations.types') as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <x-form.label for="campaign_id"></x-form.label>
            <select id="campaign_id" name="campaign_id" class="mt-1.5 block w-full rounded-lg border-slate-300 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">All campaigns</option>
                @foreach ($campaigns as $campaign)
                    <option value="{{ $campaign->id }}" @selected((string) ($filters['campaign_id'] ?? '') === (string) $campaign->id)>{{ $campaign->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <x-form.label for="status"></x-form.label>
            <select id="status" name="status" class="mt-1.5 block w-full rounded-lg border-slate-300 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">All statuses</option>
                @foreach (config('donations.statuses') as $value => $status)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $status['label'] }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex items-end gap-2">
            <x-button class="w-auto px-5">Filter</x-button>
            <a href="{{ route('donations.index') }}" class="inline-flex w-auto items-center justify-center rounded-md border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
        </div>
    </div>
</form>
