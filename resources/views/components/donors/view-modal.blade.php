@props(['donor'])

@php($dialogId = 'view-donor-modal-'.$donor->id)

<dialog
    id="{{ $dialogId }}"
    class="m-auto w-[min(920px,calc(100vw-2rem))] max-h-[90vh] overflow-hidden rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/55"
    aria-labelledby="{{ $dialogId }}-title"
>
    <div class="flex max-h-[90vh] flex-col bg-white supports-[height:100dvh]:max-h-[90dvh]">
        <header class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 bg-white px-5 py-5 sm:px-6">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Donor Profile</p>
                <h2 id="{{ $dialogId }}-title" class="mt-1 text-xl font-semibold leading-7 text-slate-950 [overflow-wrap:anywhere]">{{ $donor->full_name }}</h2>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                    <span class="rounded-full bg-blue-50 px-2.5 py-1 font-semibold text-blue-800">{{ config('donors.types')[$donor->donor_type] ?? ucfirst($donor->donor_type) }}</span>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-700">{{ config('donors.statuses')[$donor->status] ?? ucfirst($donor->status) }}</span>
                </div>
            </div>
            <button type="button" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500" data-dialog-close="{{ $dialogId }}" aria-label="Close donor details">
                <x-icon name="x-circle" class="h-5 w-5" />
            </button>
        </header>

        <div class="min-h-0 flex-1 space-y-5 overflow-y-auto overscroll-contain bg-slate-50 px-5 py-5 sm:px-6">
            <section aria-labelledby="{{ $dialogId }}-contact">
                <h3 id="{{ $dialogId }}-contact" class="text-sm font-semibold text-slate-950">Profile Information</h3>
                <dl class="mt-3 grid gap-x-6 gap-y-5 rounded-xl border border-slate-200 bg-white p-4 text-sm leading-6 sm:grid-cols-2 sm:p-5">
                    @if ($donor->donor_type === 'organization' || $donor->contact_person)
                        <div class="min-w-0 sm:col-span-2">
                            <dt class="text-xs font-medium text-slate-500">Contact Person</dt>
                            <dd class="mt-1 font-medium text-slate-900 [overflow-wrap:anywhere]">{{ $donor->contact_person ?: 'Not provided' }}</dd>
                        </div>
                    @endif
                    <div class="min-w-0">
                        <dt class="text-xs font-medium text-slate-500">Contact Number</dt>
                        <dd class="mt-1 font-medium text-slate-900 [overflow-wrap:anywhere]">{{ $donor->contact_number ?: 'Not provided' }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-xs font-medium text-slate-500">Email</dt>
                        <dd class="mt-1 font-medium text-slate-900 [overflow-wrap:anywhere]">{{ $donor->email ?: 'Not provided' }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-xs font-medium text-slate-500">Preferred Contact</dt>
                        <dd class="mt-1 font-medium text-slate-900 [overflow-wrap:anywhere]">{{ $donor->preferred_contact_method ? (config('donors.contact_methods')[$donor->preferred_contact_method] ?? ucfirst($donor->preferred_contact_method)) : 'Not provided' }}</dd>
                    </div>
                    @if ($donor->donor_type === 'organization' || $donor->tin)
                        <div class="min-w-0">
                            <dt class="text-xs font-medium text-slate-500">TIN</dt>
                            <dd class="mt-1 font-medium text-slate-900 [overflow-wrap:anywhere]">{{ $donor->tin ?: 'Not provided' }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            <section aria-labelledby="{{ $dialogId }}-address">
                <h3 id="{{ $dialogId }}-address" class="text-sm font-semibold text-slate-950">Address Information</h3>
                <dl class="mt-3 grid gap-x-6 gap-y-5 rounded-xl border border-slate-200 bg-white p-4 text-sm leading-6 sm:grid-cols-2 sm:p-5">
                    <div class="min-w-0 sm:col-span-2">
                        <dt class="text-xs font-medium text-slate-500">Street / Purok / House No.</dt>
                        <dd class="mt-1 font-medium text-slate-900 [overflow-wrap:anywhere]">{{ $donor->address ?: 'Not provided' }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-xs font-medium text-slate-500">Barangay</dt>
                        <dd class="mt-1 font-medium text-slate-900 [overflow-wrap:anywhere]">{{ $donor->barangay ?: $donor->barangay_area ?: 'Not provided' }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-xs font-medium text-slate-500">City / Municipality</dt>
                        <dd class="mt-1 font-medium text-slate-900 [overflow-wrap:anywhere]">{{ $donor->city_municipality ?: 'Not provided' }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-xs font-medium text-slate-500">Province</dt>
                        <dd class="mt-1 font-medium text-slate-900 [overflow-wrap:anywhere]">{{ $donor->province ?: 'Not provided' }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-xs font-medium text-slate-500">Region</dt>
                        <dd class="mt-1 font-medium text-slate-900 [overflow-wrap:anywhere]">{{ $donor->region ?: 'Not provided' }}</dd>
                    </div>
                </dl>
            </section>

            <section aria-labelledby="{{ $dialogId }}-notes">
                <h3 id="{{ $dialogId }}-notes" class="text-sm font-semibold text-slate-950">Notes</h3>
                <p class="mt-3 whitespace-pre-line rounded-xl border border-slate-200 bg-white p-4 text-sm leading-6 text-slate-700 [overflow-wrap:anywhere] sm:p-5">{{ $donor->notes ?: 'No notes provided.' }}</p>
            </section>
        </div>

        <footer class="flex shrink-0 justify-end border-t border-slate-200 bg-white px-5 py-3 sm:px-6">
            <button type="button" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 sm:w-auto" data-dialog-close="{{ $dialogId }}">Close</button>
        </footer>
    </div>
</dialog>
