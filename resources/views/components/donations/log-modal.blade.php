@props(['donors', 'campaigns'])

@php($selectedType = old('donation_type', 'cash'))

<dialog
    id="log-contribution-dialog"
    class="m-auto w-[min(860px,calc(100vw-1.5rem))] max-w-full overflow-hidden rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/50"
    aria-labelledby="log-contribution-title"
    @if($errors->any()) open @endif
>
    <form
        method="POST"
        action="{{ route('donations.store') }}"
        enctype="multipart/form-data"
        class="flex max-h-[calc(100vh-2rem)] min-h-0 flex-col"
        data-contribution-form
    >
        @csrf

        <div class="flex shrink-0 items-center justify-between border-b border-slate-200 bg-white px-5 py-3.5 sm:px-6">
            <h2 id="log-contribution-title" class="text-base font-semibold text-slate-950">Add Donation</h2>

            <button
                type="button"
                class="rounded-md px-2 py-1 text-2xl leading-none text-slate-500 hover:bg-slate-100"
                data-dialog-close="log-contribution-dialog"
                aria-label="Close modal"
            >
                &times;
            </button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto">
            <div class="space-y-5 px-5 py-4 sm:px-6 sm:py-5">

                @if ($donors->isEmpty())
                    <x-alert type="error">
                        No active donors are available. Add a donor profile before logging a contribution.
                    </x-alert>
                @endif

                <div class="grid gap-2 sm:grid-cols-2" role="tablist" aria-label="Contribution type">
                    @foreach (config('donations.types') as $value => $label)
                        @php($isCash = $value === 'cash')
                        <button
                            type="button"
                            role="tab"
                            aria-selected="{{ $selectedType === $value ? 'true' : 'false' }}"
                            class="group flex min-h-14 items-center gap-3 rounded-xl border px-4 py-2.5 text-left transition {{ $selectedType === $value ? 'border-blue-500 bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-sm' : 'border-slate-200 bg-slate-50 text-slate-700 hover:border-blue-200 hover:bg-blue-50' }}"
                            data-contribution-tab="{{ $value }}"
                        >
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $selectedType === $value ? 'bg-white/15 text-white' : 'bg-white text-slate-600 shadow-sm' }}">
                                <x-icon :name="$isCash ? 'banknotes' : 'cube'" class="h-5 w-5" />
                            </span>
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold">{{ $isCash ? 'Cash Donation' : 'In-Kind Goods' }}</span>
                                <span class="block truncate text-xs {{ $selectedType === $value ? 'text-blue-100' : 'text-slate-500' }}">{{ $isCash ? 'Record monetary contributions' : 'Record donated goods and items' }}</span>
                            </span>
                        </button>
                    @endforeach
                </div>

                <input id="donation_type" type="hidden" name="donation_type" value="{{ $selectedType }}" data-contribution-type>
                <x-form.error name="donation_type" />

                <x-donations.cash-fields
                    :active="$selectedType === 'cash'"
                    :donors="$donors"
                />

                <x-donations.in-kind-fields
                    :active="$selectedType === 'in_kind'"
                    :donors="$donors"
                />

            </div>
        </div>

        <div class="flex shrink-0 flex-col-reverse gap-3 border-t border-slate-200 bg-slate-50 px-5 py-3 sm:flex-row sm:justify-end sm:px-6">

            <button
                type="button"
                class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-white"
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

@can('create', \App\Models\Donor::class)
    <dialog
        id="quick-create-donor-dialog"
        class="m-auto w-[min(42rem,calc(100vw-1.5rem))] max-w-full overflow-hidden rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/55"
        aria-labelledby="quick-create-donor-title"
    >
        <form method="POST" action="{{ route('donors.store') }}" class="flex max-h-[calc(100vh-2rem)] min-h-0 flex-col bg-white" data-quick-donor-form data-donor-wizard-form novalidate>
            @csrf
            <input type="hidden" name="_modal" value="quick-create">
            <div class="flex shrink-0 items-center justify-between border-b border-slate-200 px-5 py-3.5">
                <div>
                    <h2 id="quick-create-donor-title" class="text-base font-semibold text-slate-950">Add Donor</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Create a donor profile and use it for this contribution.</p>
                </div>
                <button type="button" class="rounded-md px-2 py-1 text-2xl leading-none text-slate-500 hover:bg-slate-100" data-dialog-close="quick-create-donor-dialog" aria-label="Close modal">&times;</button>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto">
                <x-donors.form mode="create" />
                <p class="mx-5 mb-4 hidden rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" data-quick-donor-error role="alert"></p>
            </div>
            <div class="flex shrink-0 items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-5 py-3">
                <button type="button" class="h-9 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50" data-wizard-back disabled>Back</button>
                <div class="flex items-center gap-2">
                    <button type="button" class="h-9 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50" data-dialog-close="quick-create-donor-dialog">Cancel</button>
                    <button type="button" class="h-9 rounded-lg bg-blue-700 px-4 text-sm font-semibold text-white hover:bg-blue-800" data-wizard-next>Next</button>
                    <button type="submit" hidden class="h-9 rounded-lg bg-blue-700 px-4 text-sm font-semibold text-white hover:bg-blue-800 disabled:opacity-50" data-wizard-submit>Save Donor</button>
                </div>
            </div>
        </form>
    </dialog>
@endcan
