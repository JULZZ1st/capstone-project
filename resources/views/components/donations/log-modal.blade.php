@props(['donors', 'campaigns'])

@php($selectedType = old('donation_type', 'cash'))

<dialog id="log-contribution-dialog" class="w-[min(940px,calc(100vw-2rem))] rounded-lg p-0 shadow-xl backdrop:bg-slate-950/50" @if($errors->any()) open @endif>
    <form method="POST" action="{{ route('donations.store') }}" class="max-h-[90vh] overflow-y-auto" data-contribution-form>
        @csrf
        <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white px-5 py-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-950">Log New Contribution</h2>
                <p class="text-sm text-slate-500">Record cash or in-kind donations from active donors.</p>
            </div>
            <button type="button" class="rounded-md px-2 py-1 text-2xl leading-none text-slate-500 hover:bg-slate-100" data-dialog-close="log-contribution-dialog">&times;</button>
        </div>

        <div class="space-y-5 px-5 py-5">
            @if ($donors->isEmpty())
                <x-alert type="error">No active donors are available. Add a donor profile before logging a contribution.</x-alert>
            @endif

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <x-form.label for="donation_type">Contribution Type</x-form.label>
                    <select id="donation_type" name="donation_type" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" data-contribution-type>
                        @foreach (config('donations.types') as $value => $label)
                            <option value="{{ $value }}" @selected($selectedType === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-form.error name="donation_type" />
                </div>
                <div>
                    <x-form.label for="donor_id">Donor</x-form.label>
                    <select id="donor_id" name="donor_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" @disabled($donors->isEmpty())>
                        <option value="">Select donor</option>
                        @foreach ($donors as $donor)
                            <option value="{{ $donor->id }}" @selected((string) old('donor_id') === (string) $donor->id)>{{ $donor->full_name }} - {{ $donor->contact_number }}</option>
                        @endforeach
                    </select>
                    <x-form.error name="donor_id" />
                </div>
            </div>

            <div>
                <x-form.label for="campaign_id">Campaign</x-form.label>
                <select id="campaign_id" name="campaign_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">No campaign</option>
                    @foreach ($campaigns as $campaign)
                        <option value="{{ $campaign->id }}" @selected((string) old('campaign_id') === (string) $campaign->id)>{{ $campaign->name }}</option>
                    @endforeach
                </select>
                <x-form.error name="campaign_id" />
            </div>

            <x-donations.cash-fields :active="$selectedType === 'cash'" />
            <x-donations.in-kind-fields :active="$selectedType === 'in_kind'" />

            <div>
                <x-form.label for="notes">Administrative Notes</x-form.label>
                <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('notes') }}</textarea>
                <x-form.error name="notes" />
            </div>
        </div>

        <div class="flex flex-col-reverse gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:justify-end">
            <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-white" data-dialog-close="log-contribution-dialog">Cancel</button>
            <x-button class="sm:w-auto" :disabled="$donors->isEmpty()">Save Contribution</x-button>
        </div>
    </form>
</dialog>
