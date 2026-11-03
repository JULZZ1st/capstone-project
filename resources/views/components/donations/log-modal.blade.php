@props(['donors', 'campaigns', 'categories', 'subcategoriesByCategory'])

@php($selectedType = old('donation_type', 'cash'))

<dialog
    id="log-contribution-dialog"
    class="m-auto w-[min(760px,calc(100vw-2rem))] overflow-hidden rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/50"
    aria-labelledby="log-contribution-title"
    @if($errors->any()) open @endif
>
    <form
        method="POST"
        action="{{ route('donations.store') }}"
        class="flex max-h-[90vh] flex-col"
        data-contribution-form
    >
        @csrf

        <div class="sticky top-0 z-10 flex shrink-0 items-center justify-between border-b border-slate-200 bg-white px-5 py-3">
            <div class="flex items-center gap-3">
                <div>
                    <h2 id="log-contribution-title" class="text-base font-semibold text-slate-950">
                        Log New Contribution
                    </h2>
                </div>
            </div>

            <button
                type="button"
                class="rounded-md px-2 py-1 text-2xl leading-none text-slate-500 hover:bg-slate-100"
                data-dialog-close="log-contribution-dialog"
            >
                &times;
            </button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto">
            <div class="space-y-4 px-5 py-4">

                @if ($donors->isEmpty())
                    <x-alert type="error">
                        No active donors are available. Add a donor profile before logging a contribution.
                    </x-alert>
                @endif

                <div class="grid gap-4 md:grid-cols-2">

                    <div>
                        <x-form.label for="donation_type">
                            Contribution Type
                        </x-form.label>

                        <select
                            id="donation_type"
                            name="donation_type"
                            class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            data-contribution-type
                        >
                            @foreach (config('donations.types') as $value => $label)
                                <option
                                    value="{{ $value }}"
                                    @selected($selectedType === $value)
                                >
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>

                        <x-form.error name="donation_type" />
                    </div>


                    <div>
                        <x-form.label for="donor_id">
                            Donor
                        </x-form.label>

                        <select
                            id="donor_id"
                            name="donor_id"
                            class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            @disabled($donors->isEmpty())
                        >
                            <option value="">
                                Select donor
                            </option>

                            @foreach ($donors as $donor)
                                <option
                                    value="{{ $donor->id }}"
                                    @selected((string) old('donor_id') === (string) $donor->id)
                                >
                                    {{ $donor->full_name }} - {{ $donor->contact_number }}
                                </option>
                            @endforeach
                        </select>

                        <x-form.error name="donor_id" />
                    </div>

                </div>

                <x-donations.cash-fields
                    :active="$selectedType === 'cash'"
                />

                <x-donations.in-kind-fields
                    :active="$selectedType === 'in_kind'"
                    :categories="$categories"
                    :subcategories-by-category="$subcategoriesByCategory"
                />

                <div>
                    <x-form.label for="notes">
                        Notes <span class="font-normal text-slate-400">(optional)</span>
                    </x-form.label>

                    <textarea
                        id="notes"
                        name="notes"
                        rows="3"
                        class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    >{{ old('notes') }}</textarea>

                    <x-form.error name="notes" />

                </div>

            </div>
        </div>

        <div class="sticky bottom-0 z-10 flex shrink-0 flex-col-reverse gap-3 border-t border-slate-200 bg-slate-50 px-5 py-3 sm:flex-row sm:justify-end">

            <button
                type="button"
                class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-white"
                data-dialog-close="log-contribution-dialog"
            >
                Cancel
            </button>

            <x-button
                class="sm:w-auto"
                :disabled="$donors->isEmpty()"
                data-contribution-submit
            >
                <span data-contribution-submit-label>Save Contribution</span>
            </x-button>

        </div>

    </form>
</dialog>
