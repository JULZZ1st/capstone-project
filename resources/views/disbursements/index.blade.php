@extends('layouts.app')

@section('title', 'Fund Requests')
@section('page-title', 'Fund Requests')
@section('page-subtitle', 'Submit and review cash requests with maker-checker approval.')

@section('content')
    <div class="space-y-4">
        @php
            $canApproveAny = $disbursements->contains(fn ($disbursement) => auth()->user()->can('approve', $disbursement));
        @endphp

        <div class="flex shrink-0 justify-end">
                @can('create', \App\Models\CashDisbursement::class)
                    <button type="button" id="toggle-fund-request-form" aria-haspopup="dialog" aria-controls="create-fund-request-modal" class="inline-flex items-center justify-center rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-200">
                        Create Fund Request
                    </button>
                @endcan
        </div>
            @can('create', \App\Models\CashDisbursement::class)
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

                    @if(false)
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

                                <div class="space-y-2 md:col-span-2">
                                    <label for="payee_address" class="block text-sm font-medium text-slate-700">Payee Address <span class="text-red-600">*</span></label>
                                    <input id="payee_address" name="payee_address" type="text" value="{{ old('payee_address') }}" required class="block h-12 w-full rounded-lg border border-slate-300 bg-slate-50/60 px-3 text-sm text-slate-900 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-200">
                                    <x-form.error name="payee_address" />
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

                            <div class="grid gap-5 md:grid-cols-3">
                                <div class="space-y-2">
                                    <label for="payee_tin" class="block text-sm font-medium text-slate-700">Payee TIN <span class="font-normal text-slate-500">(Optional)</span></label>
                                    <input id="payee_tin" name="payee_tin" type="text" value="{{ old('payee_tin') }}" class="block h-12 w-full rounded-lg border border-slate-300 bg-slate-50/60 px-3 text-sm text-slate-900 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-200">
                                    <x-form.error name="payee_tin" />
                                </div>
                                <div class="space-y-2">
                                    <label for="fund_source" class="block text-sm font-medium text-slate-700">Fund Source <span class="text-red-600">*</span></label>
                                    <select id="fund_source" name="fund_source" required class="block h-12 w-full rounded-lg border border-slate-300 bg-slate-50/60 px-3 text-sm text-slate-900 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-200">
                                        <option value="">Select Fund Source</option>
                                        @foreach (config('disbursements.fund_sources', []) as $fundSource)
                                            <option value="{{ $fundSource }}" @selected(old('fund_source') === $fundSource)>{{ $fundSource }}</option>
                                        @endforeach
                                    </select>
                                    <x-form.error name="fund_source" />
                                </div>
                                <div class="space-y-2">
                                    <label for="obr_number" class="block text-sm font-medium text-slate-700">ObR No. <span class="font-normal text-slate-500">(Optional)</span></label>
                                    <input id="obr_number" name="obr_number" type="text" value="{{ old('obr_number') }}" class="block h-12 w-full rounded-lg border border-slate-300 bg-slate-50/60 px-3 text-sm text-slate-900 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-200">
                                    <x-form.error name="obr_number" />
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="space-y-4 border-t border-slate-200 pt-5">
                            <legend class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Additional Information</legend>
                            <div class="space-y-2">
                                    <label for="notes" class="block text-sm font-medium text-slate-700">Notes / Remarks (Optional)</label>
                                    <textarea id="notes" name="notes" rows="3" class="block w-full rounded-lg border border-slate-300 bg-slate-50/60 px-3 py-2.5 text-sm text-slate-900 shadow-sm transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-200">{{ old('notes') }}</textarea>
                                    <x-form.error name="notes" />
                            </div>
                        </fieldset>
                    </div>
                    @endif

                    @include('disbursements.partials.fund-request-form-fields', [
                        'formId' => 'create-fund-request',
                        'values' => [],
                        'useOld' => true,
                        'locationDefaults' => $fundRequestLocationDefaults ?? [],
                        'fundRequestProvinces' => $fundRequestProvinces ?? collect(),
                    ])

                    <div class="flex shrink-0 justify-end border-t border-slate-200 bg-slate-50/90 px-4 py-3 sm:px-6">
                        <button type="button" data-close-create-fund-request class="mr-3 h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button type="submit" class="h-10 rounded-lg bg-blue-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-200">Save Request</button>
                    </div>
                </form>
                </div>
            </dialog>
            @endcan
            @include('disbursements.partials.fund-request-location-script')
            <div data-fund-request-results>
                <x-disbursements.table :disbursements="$disbursements" />
            </div>
    </div>

    @foreach ($disbursements as $disbursement)
        @include('disbursements.partials.details-modal', ['disbursement' => $disbursement, 'modalId' => 'fund-request-details-modal-'.$disbursement->id, 'directPage' => false])
    @endforeach

    <dialog id="fund-request-detail-modal" class="m-auto w-[calc(100%-2rem)] max-w-3xl overflow-hidden rounded-xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/55">
        <form id="fund-request-detail-form" class="flex max-h-[86vh] flex-col bg-white" method="POST">
            @csrf
            <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-4 py-3 sm:px-6">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Fund Request Details</h2>
                <p class="mt-1 text-sm text-slate-500">Review the selected request.</p>
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

                <div class="space-y-2 sm:col-span-2">
                    <label for="modal_payee_address" class="block text-sm font-medium text-slate-700">Payee Address</label>
                    <input id="modal_payee_address" name="payee_address" type="text" class="block h-11 w-full rounded-lg border border-slate-300 bg-slate-50/60 px-3 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-200">
                </div>

                <div class="space-y-2">
                    <label for="modal_amount" class="block text-sm font-medium text-slate-700">Amount</label>
                    <input id="modal_amount" name="amount" type="number" step="0.01" min="0.01" class="block h-11 w-full rounded-lg border border-slate-300 bg-slate-50/60 px-3 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-200">
                </div>

                <div class="space-y-2">
                    <label for="modal_payee_tin" class="block text-sm font-medium text-slate-700">Payee TIN</label>
                    <input id="modal_payee_tin" name="payee_tin" type="text" class="block h-11 w-full rounded-lg border border-slate-300 bg-slate-50/60 px-3 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-200">
                </div>

                <div class="space-y-2">
                    <label for="modal_fund_source" class="block text-sm font-medium text-slate-700">Fund Source</label>
                    <select id="modal_fund_source" name="fund_source" class="block h-11 w-full rounded-lg border border-slate-300 bg-slate-50/60 px-3 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-200">
                        <option value="">Not provided</option>
                        @foreach (config('disbursements.fund_sources', []) as $fundSource)
                            <option value="{{ $fundSource }}">{{ $fundSource }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-2">
                    <label for="modal_obr_number" class="block text-sm font-medium text-slate-700">ObR No.</label>
                    <input id="modal_obr_number" name="obr_number" type="text" class="block h-11 w-full rounded-lg border border-slate-300 bg-slate-50/60 px-3 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-200">
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
                    <label for="modal_notes" class="block text-sm font-medium text-slate-700">Notes / Remarks (Optional)</label>
                    <textarea id="modal_notes" name="notes" rows="2" class="block w-full rounded-lg border border-slate-300 bg-slate-50/60 px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-200"></textarea>
                </div>
                </div>
            </fieldset>
            </div>

            <div class="flex shrink-0 flex-wrap justify-end gap-3 border-t border-slate-200 bg-slate-50/90 px-4 py-3 sm:px-6">
                <button type="button" data-close-fund-request-modal class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Close</button>
                @if ($canApproveAny)
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
                @endif
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
            const fields = form.querySelectorAll('input:not([form]), textarea:not([form]), select:not([form])');
            const approvalActions = document.getElementById('fund-request-approval-actions');
            const approvalNotice = document.getElementById('fund-request-approval-notice');
            const approvalButton = document.getElementById('approve-fund-request-button');
            const rejectionButton = document.getElementById('reject-fund-request-button');
            const rejectionField = document.getElementById('fund-request-rejection-field');
            const approvalForm = document.getElementById('approve-fund-request-form');
            const rejectionForm = document.getElementById('reject-fund-request-form');

            const closeFundRequestMenus = () => {
                document.querySelectorAll('[data-fund-request-menu-panel]').forEach((panel) => {
                    panel.hidden = true;
                    panel.closest('[data-fund-request-action]')?.querySelector('[data-fund-request-menu-toggle]')?.setAttribute('aria-expanded', 'false');
                });
            };

            const positionFundRequestMenu = (toggle, panel) => {
                const toggleRect = toggle.getBoundingClientRect();
                const panelRect = panel.getBoundingClientRect();
                const gap = 8;
                const top = toggleRect.bottom + gap + panelRect.height <= window.innerHeight - gap
                    ? toggleRect.bottom + gap
                    : toggleRect.top - panelRect.height - gap;
                const left = Math.min(toggleRect.right - panelRect.width, window.innerWidth - panelRect.width - gap);

                panel.style.top = `${Math.max(gap, top)}px`;
                panel.style.left = `${Math.max(gap, left)}px`;
            };

            document.querySelectorAll('[data-fund-request-menu-toggle]').forEach((toggle) => {
                toggle.addEventListener('click', (event) => {
                    event.stopPropagation();
                    const panel = toggle.closest('[data-fund-request-action]')?.querySelector('[data-fund-request-menu-panel]');
                    if (! panel) return;

                    const shouldOpen = panel.hidden;
                    closeFundRequestMenus();
                    if (shouldOpen) {
                        panel.querySelector('[data-open-fund-request-modal]')?.removeAttribute('hidden');
                        panel.querySelector('[data-fund-request-cancel-toggle]')?.removeAttribute('hidden');
                        panel.querySelector('[data-fund-request-cancel-confirm]')?.setAttribute('hidden', 'hidden');
                        panel.hidden = false;
                        toggle.setAttribute('aria-expanded', 'true');
                        positionFundRequestMenu(toggle, panel);
                    }
                });
            });

            document.querySelectorAll('[data-fund-request-cancel-toggle]').forEach((cancelToggle) => {
                cancelToggle.addEventListener('click', (event) => {
                    event.stopPropagation();
                    const panel = cancelToggle.closest('[data-fund-request-menu-panel]');
                    const confirmation = panel?.querySelector('[data-fund-request-cancel-confirm]');
                    if (! panel || ! confirmation) return;

                    confirmation.hidden = false;
                    cancelToggle.hidden = true;
                    panel.querySelector('[data-open-fund-request-modal]')?.setAttribute('hidden', 'hidden');
                    const toggle = cancelToggle.closest('[data-fund-request-action]')?.querySelector('[data-fund-request-menu-toggle]');
                    if (toggle) positionFundRequestMenu(toggle, panel);
                });
            });

            document.addEventListener('click', (event) => {
                if (! event.target.closest('[data-fund-request-menu-panel], [data-fund-request-menu-toggle]')) {
                    closeFundRequestMenus();
                }
            });

            window.addEventListener('resize', closeFundRequestMenus);

            if (toggleButton && createModal) {
                toggleButton.addEventListener('click', () => {
                    createModal.showModal();
                });
            }

            if (createModal?.dataset.reopen === 'true') {
                createModal.showModal();
            }

            if (createModal) {
                document.querySelectorAll('[data-close-create-fund-request]').forEach((button) => {
                    button.addEventListener('click', () => createModal.close());
                });

                createModal.addEventListener('click', (event) => {
                    if (event.target === createModal) {
                        createModal.close();
                    }
                });
            }

            document.querySelectorAll('[data-open-fund-request-modal]').forEach((button) => {
                button.addEventListener('click', () => {
                    const row = button.closest('tr');
                    if (! row || ! modal) return;

                    closeFundRequestMenus();

                    form.dataset.requestId = row.dataset.fundRequestId;
                    document.getElementById('modal_requested_date').value = row.dataset.requestedDate || '';
                    document.getElementById('modal_recipient').value = row.dataset.recipient || '';
                    document.getElementById('modal_payee_address').value = row.dataset.payeeAddress || '';
                    document.getElementById('modal_payee_tin').value = row.dataset.payeeTin || '';
                    document.getElementById('modal_fund_source').value = row.dataset.fundSource || '';
                    document.getElementById('modal_obr_number').value = row.dataset.obrNumber || '';
                    document.getElementById('modal_amount').value = row.dataset.amount || '';
                    document.getElementById('modal_purpose').value = row.dataset.purpose || '';
                    document.getElementById('modal_notes').value = row.dataset.notes || '';
                    const rejectionReason = document.getElementById('modal_rejection_reason');
                    if (rejectionReason) {
                        rejectionReason.value = '';
                    }

                    const isPending = row.dataset.status === 'pending';
                    const canApprove = row.dataset.canApprove === 'true' && row.dataset.treasurerCertified === 'true';
                    const approvalReadyNotice = row.dataset.canApprove === 'true' && ! canApprove;
                     if (approvalActions) {
                         approvalActions.classList.toggle('hidden', ! isPending);
                         approvalActions.classList.toggle('flex', isPending);
                         approvalButton.classList.toggle('hidden', ! canApprove);
                         rejectionButton.classList.toggle('hidden', ! canApprove);
                         rejectionField.classList.toggle('hidden', ! canApprove);
                         approvalNotice.textContent = approvalReadyNotice ? "Staff must record the Treasurer's physical Box B certification before approval." : 'Only a different administrator can approve this pending request.';
                         approvalNotice.classList.toggle('hidden', ! isPending || canApprove);
                         approvalForm.action = row.dataset.approvalUrl;
                         rejectionForm.action = row.dataset.rejectionUrl;
                     }

                    fields.forEach((field) => {
                        field.disabled = true;
                        field.setAttribute('readonly', 'readonly');
                    });

                    modal.showModal();
                });
            });

            document.querySelectorAll('[data-fund-request-scroll] tr[data-fund-request-id]').forEach((row) => {
                row.addEventListener('click', (event) => {
                    if (event.target.closest('[data-fund-request-action]')) return;

                    row.querySelector('[data-open-fund-request-modal]')?.click();
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

@push('scripts')
    @include('disbursements.partials.details-modal-script')
@endpush

@endsection
