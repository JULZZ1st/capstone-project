@props(['donors', 'id', 'selected' => null])

<div class="flex items-end gap-2">
    <div class="min-w-0 flex-1">
        <x-form.label :for="$id">Donor</x-form.label>
        <x-form.custom-select
            :id="$id"
            name="donor_id"
            placeholder="Search and select a donor..."
            searchable
            :disabled="$donors->isEmpty()"
            data-donor-select-native
            aria-label="Donor"
        >
            <option value="">Search and select a donor...</option>
            @foreach ($donors as $donor)
                <option value="{{ $donor->id }}" @selected((string) $selected === (string) $donor->id)>{{ $donor->full_name }} - {{ $donor->contact_number }}</option>
            @endforeach
        </x-form.custom-select>
        <x-form.error name="donor_id" />
    </div>
    @can('create', \App\Models\Donor::class)
        <button
            type="button"
            class="mb-0.5 inline-flex h-10 shrink-0 items-center gap-1.5 rounded-lg border border-blue-200 bg-blue-50 px-3 text-sm font-semibold text-blue-700 transition hover:border-blue-300 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-50"
            data-quick-donor-open
        >
            <x-icon name="plus" class="h-4 w-4" />
            <span class="hidden sm:inline">Add donor</span>
            <span class="sm:hidden">Add</span>
        </button>
    @endcan
</div>
