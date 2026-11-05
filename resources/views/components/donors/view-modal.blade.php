@props(['donor'])

@php($dialogId = 'view-donor-modal-'.$donor->id)

<dialog
    id="{{ $dialogId }}"
    class="m-auto w-[min(920px,calc(100vw-2rem))] max-h-[90vh] rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/55"
    aria-labelledby="{{ $dialogId }}-title"
>
    <div class="flex max-h-[90vh] flex-col bg-white">
        <header class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 bg-white px-5 py-4">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Donor Profile</p>
                <h2 id="{{ $dialogId }}-title" class="mt-1 truncate text-xl font-semibold text-slate-950">{{ $donor->full_name }}</h2>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                    <span class="rounded-full bg-blue-50 px-2.5 py-1 font-semibold text-blue-800">{{ config('donors.types')[$donor->donor_type] ?? ucfirst($donor->donor_type) }}</span>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-700">{{ config('donors.statuses')[$donor->status] ?? ucfirst($donor->status) }}</span>
                </div>
            </div>
            <button type="button" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100" data-dialog-close="{{ $dialogId }}" aria-label="Close donor details">
                <x-icon name="x-circle" class="h-5 w-5" />
            </button>
        </header>

        <div class="min-h-0 flex-1 space-y-6 overflow-y-auto px-5 py-5">
            <section aria-labelledby="{{ $dialogId }}-contact">
                <h3 id="{{ $dialogId }}-contact" class="text-sm font-semibold text-slate-950">Profile Information</h3>
                <dl class="mt-3 grid gap-4 rounded-xl border border-slate-200 bg-slate-50/60 p-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Contact Person</dt>
                        <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ $donor->contact_person ?: 'Not provided' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Contact Number</dt>
                        <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ $donor->contact_number ?: 'Not provided' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Email</dt>
                        <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ $donor->email ?: 'Not provided' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Preferred Contact</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900">{{ $donor->preferred_contact_method ? (config('donors.contact_methods')[$donor->preferred_contact_method] ?? ucfirst($donor->preferred_contact_method)) : 'Not provided' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Address</dt>
                        <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ $donor->address ?: 'Not provided' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Barangay</dt>
                        <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ $donor->barangay ?: $donor->barangay_area ?: 'Not provided' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">City / Municipality</dt>
                        <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ $donor->city_municipality ?: 'Not provided' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Province</dt>
                        <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ $donor->province ?: 'Not provided' }}</dd>
                    </div>
                    @if ($donor->notes)
                        <div class="sm:col-span-2 lg:col-span-3">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Notes</dt>
                            <dd class="mt-1 whitespace-pre-line text-sm text-slate-900">{{ $donor->notes }}</dd>
                        </div>
                    @endif
                </dl>
            </section>
        </div>

        <footer class="flex shrink-0 flex-wrap justify-end gap-3 border-t border-slate-200 bg-slate-50 px-5 py-3">
            <button type="button" class="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-100" data-dialog-close="{{ $dialogId }}">Close</button>
        </footer>
    </div>
</dialog>
