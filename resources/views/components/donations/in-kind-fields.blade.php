@props(['active' => false, 'donors'])

<section
    class="space-y-4 border-t border-slate-100 pt-4"
    data-contribution-section="in_kind"
    @hidden(! $active)
>
    <div class="flex items-center gap-2">
        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-700"><x-icon name="cube" class="h-4 w-4" /></span>
        <h3 class="text-sm font-semibold text-slate-950">In-Kind Donation Details</h3>
    </div>

    <x-donations.donor-select
        id="in_kind_donor_id"
        :donors="$donors"
        :selected="old('donor_id')"
    />

    <x-donations.items-table />
    <x-donations.add-item-modal />

    <div class="max-w-sm">
        <x-form.label for="in_kind_received_at">Date Received</x-form.label>
        <input id="in_kind_received_at" name="received_at" type="date" value="{{ old('received_at', now()->toDateString()) }}" required class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
        <x-form.error name="received_at" />
    </div>

    <div>
        <x-form.label for="in_kind_notes">Notes <span class="font-normal text-slate-400">(optional)</span></x-form.label>
        <textarea id="in_kind_notes" name="notes" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('notes') }}</textarea>
        <x-form.error name="notes" />
    </div>

    <x-donations.in-kind-documents />
</section>
