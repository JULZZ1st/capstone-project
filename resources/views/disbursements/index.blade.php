@extends('layouts.app')

@section('title', 'Fund Requests')
@section('page-title', 'Fund Requests')
@section('page-subtitle', 'Submit and review cash requests with maker-checker approval.')

@section('content')
    <div class="space-y-6">

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <h2 class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Request history</h2>
                <button type="button" id="toggle-fund-request-form" aria-haspopup="dialog" aria-controls="create-fund-request-modal" class="inline-flex items-center justify-center rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-200">
                    Create Fund Request
                </button>
            </div>
            <dialog id="create-fund-request-modal" data-reopen="{{ old('_modal') === 'create' ? 'true' : 'false' }}" class="m-auto w-[calc(100%-2rem)] max-w-3xl overflow-hidden rounded-xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/55">
                <div class="flex max-h-[86vh] flex-col bg-white">
                    <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-4 py-3 sm:px-6">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">Create Fund Request</h2>
                            <p class="mt-1 text-sm text-slate-500">Enter the request details for review.</p>
                        </div>
                        <button type="button" data-close-create-fund-request class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500" aria-label="Close modal">
                            <span aria-hidden="true" class="text-xl leading-none">&times;</span>
                        </button>
                    </div>
                <form method="POST" action="{{ route('disbursements.store') }}" class="flex min-h-0 flex-1 flex-col">
                    @csrf
                    <input type="hidden" name="_modal" value="create">

                    <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-4 py-4 sm:px-6">
                        <fieldset class="space-y-4">
                            <legend class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Request Details</legend>

                            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                                <div class="space-y-2">
                                    <label for="amount" class="block text-sm font-medium text-slate-700">Amount</label>
                                    <input id="amount" name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount') }}" required class="block h-12 w-full rounded-lg border border-slate-300 bg-slate-50/60 px-3 text-sm text-slate-900 shadow-sm transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-200">
                                    <x-form.error name="amount" />
                                </div>

                                <div class="space-y-2">
                                    <label for="recipient" class="block text-sm font-medium text-slate-700">Recipient / Payee</label>
                                    <input id="recipient" name="recipient" type="text" value="{{ old('recipient') }}" required class="block h-12 w-full rounded-lg border border-slate-300 bg-slate-50/60 px-3 text-sm text-slate-900 shadow-sm transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-200">
                                    <x-form.error name="recipient" />
                                </div>

                                <div class="space-y-2">
                                    <label for="requested_date" class="block text-sm font-medium text-slate-700">Requested Date</label>
                                    <div class="relative">
                                        <input id="requested_date" name="requested_date" type="date" value="{{ old('requested_date') }}" required class="block h-12 w-full rounded-lg border border-slate-300 bg-slate-50/60 px-3 pr-10 text-sm text-slate-900 shadow-sm transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-200">
                                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path d="M5.25 2.5a.75.75 0 0 1 .75.75V3h8v-.75a.75.75 0 0 1 1.5 0V3A2.5 2.5 0 0 1 18 5.5v9A2.5 2.5 0 0 1 15.5 17h-11A2.5 2.5 0 0 1 2 14.5v-9A2.5 2.5 0 0 1 4.5 3v-.75a.75.75 0 0 1 .75-.75ZM3.5 7.5v7A1 1 0 0 0 4.5 15.5h11a1 1 0 0 0 1-1v-7h-13Zm2 2.5h2v2h-2v-2Zm4 0h2v2h-2v-2Zm4 0h2v2h-2v-2Z"/>
                                            </svg>
                                        </span>
                                    </div>
                                    <x-form.error name="requested_date" />
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="purpose" class="block text-sm font-medium text-slate-700">Purpose</label>
                                <textarea id="purpose" name="purpose" rows="3" required class="block w-full rounded-lg border border-slate-300 bg-slate-50/60 px-3 py-2.5 text-sm text-slate-900 shadow-sm transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-200">{{ old('purpose') }}</textarea>
                                <x-form.error name="purpose" />
                            </div>
                        </fieldset>

                        <fieldset class="space-y-4 border-t border-slate-200 pt-5">
                            <legend class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Additional Information</legend>

                            <div class="grid gap-5 md:grid-cols-2">
                                <div class="space-y-2">
                                    <label for="supporting_reference" class="block text-sm font-medium text-slate-700">Supporting Reference (Optional)</label>
                                    <input id="supporting_reference" name="supporting_reference" type="text" value="{{ old('supporting_reference') }}" placeholder="e.g. Purchase Request No., Receipt No., Check No., or supporting document reference" class="block h-12 w-full rounded-lg border border-slate-300 bg-slate-50/60 px-3 text-sm text-slate-900 shadow-sm transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-200">
                                    <x-form.error name="supporting_reference" />
                                </div>

                                <div class="space-y-2">
                                    <label for="notes" class="block text-sm font-medium text-slate-700">Notes / Remarks (Optional)</label>
                                    <textarea id="notes" name="notes" rows="3" class="block w-full rounded-lg border border-slate-300 bg-slate-50/60 px-3 py-2.5 text-sm text-slate-900 shadow-sm transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-200">{{ old('notes') }}</textarea>
                                    <x-form.error name="notes" />
                                </div>
                            </div>
                        </fieldset>
                    </div>

                    <div class="flex shrink-0 justify-end border-t border-slate-200 bg-slate-50/90 px-4 py-3 sm:px-6">
                        <button type="button" data-close-create-fund-request class="mr-3 h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button type="submit" class="h-10 rounded-lg bg-blue-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-200">Submit Request</button>
                    </div>
                </form>
                </div>
            </dialog>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-3">Date</th>
                                <th class="px-5 py-3">Recipient / Payee</th>
                                <th class="px-5 py-3">Purpose</th>
                                <th class="px-5 py-3 text-right">Amount</th>
                                <th class="px-5 py-3">Requested By</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($disbursements as $disbursement)
                                @php
                                    $statusLabel = $disbursement->isVoided()
                                        ? 'Cancelled'
                                        : ($disbursement->status === 'pending'
                                            ? 'Pending Approval'
                                            : ($disbursement->status === 'approved' ? 'Approved Fund Request' : ucfirst($disbursement->status)));
                                @endphp
                                <tr
                                    data-fund-request-id="{{ $disbursement->id }}"
                                    data-requested-date="{{ ($disbursement->requested_date ?? $disbursement->created_at)->format('Y-m-d') }}"
                                    data-amount="{{ $disbursement->amount }}"
                                    data-recipient="{{ $disbursement->recipient ?? '' }}"
                                    data-purpose="{{ str_replace(['"','\\'], ['&quot;','\\\\'], $disbursement->purpose) }}"
                                    data-supporting-reference="{{ $disbursement->supporting_reference ?? '' }}"
                                    data-notes="{{ str_replace(['"','\\'], ['&quot;','\\\\'], $disbursement->notes ?? '') }}"
                                    data-status="{{ $disbursement->status }}"
                                    data-approval-url="{{ route('disbursements.approve', $disbursement) }}"
                                    data-rejection-url="{{ route('disbursements.reject', $disbursement) }}"
                                    data-can-approve="{{ auth()->user()->can('approve', $disbursement) ? 'true' : 'false' }}"
                                >
                                    <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ ($disbursement->requested_date ?? $disbursement->created_at)->format('M d, Y') }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $disbursement->recipient ?? 'Not provided' }}</td>
                                    <td class="px-5 py-4 text-slate-700">
                                        <p>{{ $disbursement->purpose }}</p>
                                        @if ($disbursement->supporting_reference)
                                            <p class="mt-1 text-xs text-slate-500">Ref: {{ $disbursement->supporting_reference }}</p>
                                        @endif
                                        @if ($disbursement->notes)
                                            <p class="mt-1 text-xs text-slate-500">{{ $disbursement->notes }}</p>
                                        @endif
                                        @if ($disbursement->isVoided())
                                            <p class="mt-1 text-xs font-semibold text-red-700">Cancellation Reason: {{ $disbursement->void_reason }}</p>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right font-semibold text-slate-950">PHP {{ number_format((float) $disbursement->amount, 2) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $disbursement->requester->name }}</td>
                                    <td class="px-5 py-4">
                                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ $statusLabel }}</span>
                                        <span class="sr-only">{{ $disbursement->status }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex items-center justify-end gap-3">
                                            <button type="button" data-open-fund-request-modal="{{ $disbursement->id }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">View</button>
                                            @can('void', $disbursement)
                                                <details class="relative">
                                                    <summary class="cursor-pointer rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">Cancel</summary>
                                                    <form method="POST" action="{{ route('disbursements.void', $disbursement) }}" class="absolute right-0 z-10 mt-2 w-72 space-y-2 rounded border border-slate-100 bg-white p-3 shadow-lg">
                                                        @csrf @method('PATCH')
                                                        <x-form.label for="void_reason_{{ $disbursement->id }}">Cancellation Reason</x-form.label>
                                                        <textarea id="void_reason_{{ $disbursement->id }}" name="void_reason" rows="2" required class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"></textarea>
                                                        <x-button class="w-full">Confirm Cancellation</x-button>
                                                    </form>
                                                </details>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-10 text-center text-sm text-slate-500">No fund requests have been submitted.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
        </section>
    </div>

    <dialog id="fund-request-detail-modal" class="m-auto w-[calc(100%-2rem)] max-w-3xl overflow-hidden rounded-xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/55">
        <form id="fund-request-detail-form" class="flex max-h-[86vh] flex-col bg-white" method="POST">
            @csrf
            <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-4 py-3 sm:px-6">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Fund Request Details</h2>
                <p class="mt-1 text-sm text-slate-500">Review and update the selected request.</p>
            </div>
            <button type="button" data-close-fund-request-modal class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500" aria-label="Close modal">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 0 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/>
                </svg>
            </button>
            </div>

            <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-4 py-4 sm:px-6">
            <fieldset class="space-y-4">
                <legend class="text-xs font-semibold uppercase tracking-wide text-slate-500">Request Details</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                    <label for="modal_requested_date" class="block text-sm font-medium text-slate-700">Requested Date</label>
                    <input id="modal_requested_date" name="requested_date" type="date" class="block h-11 w-full rounded-lg border border-slate-300 bg-slate-50/60 px-3 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-200">
                </div>

                <div class="space-y-2">
                    <label for="modal_recipient" class="block text-sm font-medium text-slate-700">Recipient / Payee</label>
                    <input id="modal_recipient" name="recipient" type="text" class="block h-11 w-full rounded-lg border border-slate-300 bg-slate-50/60 px-3 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-200">
                </div>

                <div class="space-y-2">
                    <label for="modal_amount" class="block text-sm font-medium text-slate-700">Amount</label>
                    <input id="modal_amount" name="amount" type="number" step="0.01" min="0.01" class="block h-11 w-full rounded-lg border border-slate-300 bg-slate-50/60 px-3 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-200">
                </div>

                    <div class="space-y-2 sm:col-span-2">
                        <label for="modal_purpose" class="block text-sm font-medium text-slate-700">Purpose</label>
                        <textarea id="modal_purpose" name="purpose" rows="3" class="block w-full rounded-lg border border-slate-300 bg-slate-50/60 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-200"></textarea>
                    </div>
                </div>
            </fieldset>

            <fieldset class="space-y-4 border-t border-slate-200 pt-4">
                <legend class="text-xs font-semibold uppercase tracking-wide text-slate-500">Additional Information</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                    <label for="modal_supporting_reference" class="block text-sm font-medium text-slate-700">Supporting Reference (Optional)</label>
                    <input id="modal_supporting_reference" name="supporting_reference" type="text" class="block h-11 w-full rounded-lg border border-slate-300 bg-slate-50/60 px-3 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-200">
                </div>

                <div class="space-y-2">
                    <label for="modal_notes" class="block text-sm font-medium text-slate-700">Notes / Remarks (Optional)</label>
                    <textarea id="modal_notes" name="notes" rows="2" class="block w-full rounded-lg border border-slate-300 bg-slate-50/60 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-200"></textarea>
                </div>
                </div>
            </fieldset>
            </div>

            <div class="flex shrink-0 flex-wrap justify-end gap-3 border-t border-slate-200 bg-slate-50/90 px-4 py-3 sm:px-6">
                <button type="button" data-close-fund-request-modal class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Close</button>
                <button type="button" id="toggle-fund-request-edit" class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100">Edit</button>
                <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Save Changes</button>
                <div id="fund-request-approval-actions" class="hidden w-full flex-wrap items-end justify-end gap-3 border-t border-slate-200 pt-3">
                    <p id="fund-request-approval-notice" class="mr-auto hidden text-sm text-amber-700">Only a different administrator can approve this pending request.</p>
                    <div class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-end sm:justify-end">
                        <div id="fund-request-rejection-field" class="hidden min-w-56 flex-1">
                            <label for="modal_rejection_reason" class="mb-1 block text-xs font-semibold text-slate-600">Rejection reason</label>
                            <textarea id="modal_rejection_reason" name="rejection_reason" form="reject-fund-request-form" rows="2" required class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-100"></textarea>
                        </div>
                        <button id="reject-fund-request-button" type="submit" form="reject-fund-request-form" class="hidden rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-50">Reject Request</button>
                        <button id="approve-fund-request-button" type="submit" form="approve-fund-request-form" class="hidden rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700">Approve Request</button>
                    </div>
                </div>
            </div>
        </form>
        <form id="approve-fund-request-form" method="POST" class="hidden">
            @csrf
            @method('PATCH')
        </form>
        <form id="reject-fund-request-form" method="POST" class="hidden">
            @csrf
            @method('PATCH')
        </form>
    </dialog>
@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toggleButton = document.getElementById('toggle-fund-request-form');
            const createModal = document.getElementById('create-fund-request-modal');
            const modal = document.getElementById('fund-request-detail-modal');
            const form = document.getElementById('fund-request-detail-form');
            const editToggle = document.getElementById('toggle-fund-request-edit');
            const fields = form.querySelectorAll('input:not([form]), textarea:not([form])');
            const approvalActions = document.getElementById('fund-request-approval-actions');
            const approvalNotice = document.getElementById('fund-request-approval-notice');
            const approvalButton = document.getElementById('approve-fund-request-button');
            const rejectionButton = document.getElementById('reject-fund-request-button');
            const rejectionField = document.getElementById('fund-request-rejection-field');
            const approvalForm = document.getElementById('approve-fund-request-form');
            const rejectionForm = document.getElementById('reject-fund-request-form');

            if (toggleButton && createModal) {
                toggleButton.addEventListener('click', () => {
                    createModal.showModal();
                });
            }

            if (createModal?.dataset.reopen === 'true') {
                createModal.showModal();
            }

            document.querySelectorAll('[data-close-create-fund-request]').forEach((button) => {
                button.addEventListener('click', () => createModal.close());
            });

            createModal.addEventListener('click', (event) => {
                if (event.target === createModal) {
                    createModal.close();
                }
            });

            const activateEditMode = () => {
                fields.forEach((field) => {
                    field.removeAttribute('readonly');
                    field.disabled = false;
                });
                editToggle.textContent = 'Editing';
                editToggle.disabled = true;
            };

            if (editToggle) {
                editToggle.addEventListener('click', activateEditMode);
            }

            document.querySelectorAll('[data-open-fund-request-modal]').forEach((button) => {
                button.addEventListener('click', () => {
                    const row = button.closest('tr');
                    if (! row || ! modal) return;

                    form.dataset.requestId = row.dataset.fundRequestId;
                    document.getElementById('modal_requested_date').value = row.dataset.requestedDate || '';
                    document.getElementById('modal_recipient').value = row.dataset.recipient || '';
                    document.getElementById('modal_amount').value = row.dataset.amount || '';
                    document.getElementById('modal_purpose').value = row.dataset.purpose ? decodeURIComponent(escape(row.dataset.purpose)) : '';
                    document.getElementById('modal_supporting_reference').value = row.dataset.supportingReference || '';
                    document.getElementById('modal_notes').value = row.dataset.notes ? decodeURIComponent(escape(row.dataset.notes)) : '';
                    document.getElementById('modal_rejection_reason').value = '';

                    const isPending = row.dataset.status === 'pending';
                    const canApprove = row.dataset.canApprove === 'true';
                    approvalActions.classList.toggle('hidden', ! isPending);
                    approvalActions.classList.toggle('flex', isPending);
                    approvalButton.classList.toggle('hidden', ! canApprove);
                    rejectionButton.classList.toggle('hidden', ! canApprove);
                    rejectionField.classList.toggle('hidden', ! canApprove);
                    approvalNotice.classList.toggle('hidden', ! isPending || canApprove);
                    approvalForm.action = row.dataset.approvalUrl;
                    rejectionForm.action = row.dataset.rejectionUrl;

                    fields.forEach((field) => {
                        field.disabled = true;
                        field.setAttribute('readonly', 'readonly');
                    });

                    editToggle.disabled = false;
                    editToggle.textContent = 'Edit';
                    modal.showModal();
                });
            });

            document.querySelectorAll('[data-close-fund-request-modal]').forEach((button) => {
                button.addEventListener('click', () => {
                    if (modal && modal.open) {
                        modal.close();
                    }
                });
            });

            form.addEventListener('submit', (event) => {
                event.preventDefault();
                modal.close();
            });
        });
    </script>
@endpush

@endsection
