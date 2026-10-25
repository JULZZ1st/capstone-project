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

            <section aria-labelledby="{{ $dialogId }}-history">
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <h3 id="{{ $dialogId }}-history" class="text-sm font-semibold text-slate-950">Donation History</h3>
                        <p class="mt-1 text-xs text-slate-500">{{ number_format($donor->contributions_count) }} total {{ \Illuminate\Support\Str::plural('record', $donor->contributions_count) }}</p>
                    </div>
                    @if ($donor->contributions_count > $donor->contributions->count())
                        <p class="text-xs text-slate-500">Showing the latest {{ $donor->contributions->count() }} records.</p>
                    @endif
                </div>
                <div class="mt-3 max-h-72 overflow-auto rounded-xl border border-slate-200">
                    <table class="min-w-full text-left text-sm">
                        <thead class="sticky top-0 z-10 bg-slate-100 text-xs uppercase tracking-wide text-slate-500 shadow-sm">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-semibold">Date</th>
                                <th scope="col" class="px-4 py-3 font-semibold">Type</th>
                                <th scope="col" class="px-4 py-3 font-semibold">Amount</th>
                                <th scope="col" class="px-4 py-3 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($donor->contributions as $contribution)
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $contribution->received_at?->format('M d, Y') ?? 'Not provided' }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ config("donations.types.{$contribution->type}") ?? ucfirst($contribution->type) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ $contribution->amount ? 'PHP '.number_format((float) $contribution->amount, 2) : 'Not provided' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        @if ($contribution->voided_at)
                                            <span class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-800">Voided</span>
                                        @else
                                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ config("donations.statuses.{$contribution->status}.label") ?? ucfirst($contribution->status) }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">No donation history for this donor yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <footer class="flex shrink-0 flex-wrap justify-between gap-3 border-t border-slate-200 bg-slate-50 px-5 py-3">
            <a href="{{ route('donors.show', $donor) }}" class="inline-flex h-10 items-center rounded-lg px-3 text-sm font-semibold text-blue-700 hover:bg-blue-50">Open full history</a>
            <button type="button" class="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-100" data-dialog-close="{{ $dialogId }}">Close</button>
        </footer>
    </div>
</dialog>
