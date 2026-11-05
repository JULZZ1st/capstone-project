@extends('layouts.app')

@section('title', 'Fund Request Details')
@section('page-title', 'Fund Requests')
@section('page-subtitle', 'Review request details before approval or rejection.')

@section('content')
    <div class="space-y-6">
        <div>
            <a href="{{ route('disbursements.index') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-800">Back to request history</a>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">Fund Request Details #{{ $disbursement->id }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ $disbursement->purpose }}</p>
                </div>
                @php($statusLabel = $disbursement->isVoided()
                    ? 'Cancelled'
                    : ($disbursement->status === 'pending'
                        ? 'Pending Approval'
                        : ($disbursement->status === 'approved' ? 'Approved Fund Request' : ucfirst($disbursement->status))))
                <span class="w-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ $statusLabel }}</span>
                <span class="sr-only">{{ $disbursement->status }}</span>
            </div>

            @can('update', $disbursement)
                <div class="mt-4 flex justify-end">
                    <button type="button" data-open-edit-fund-request class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Edit Fund Request</button>
                </div>
            @endcan

            <div class="mt-4 flex flex-col gap-2 rounded-lg border border-blue-100 bg-blue-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-blue-950">Ready for physical signatures</p>
                    <p class="mt-1 text-xs text-blue-800">Generate the Annex 12 voucher now. Record the Treasurer's physical certification before Admin review; signed upload and payment remain separate steps.</p>
                </div>
                <a href="{{ route('disbursements.voucher', $disbursement) }}" class="inline-flex w-fit shrink-0 items-center justify-center rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-200" data-testid="generate-disbursement-voucher">Generate Disbursement Voucher</a>
            </div>

            @if ($disbursement->signed_voucher_status === 'missing' && $disbursement->signed_voucher_request_reason)
                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" data-testid="signed-voucher-missing-notice">
                    <p class="font-semibold">Admin requested a signed voucher correction</p>
                    <p class="mt-1">{{ $disbursement->signed_voucher_request_reason }}</p>
                </div>
            @endif

            <section class="mt-5 border-t border-slate-100 pt-5" aria-labelledby="signed-voucher-heading">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 id="signed-voucher-heading" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Signed Voucher</h3>
                        @if ($disbursement->latestVoucher)
                            <p class="mt-1 text-sm font-medium text-slate-900">{{ $disbursement->latestVoucher->original_name }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ strtoupper(pathinfo($disbursement->latestVoucher->original_name, PATHINFO_EXTENSION)) }} · {{ $disbursement->latestVoucher->review_status === 'pending_review' ? 'Pending Admin review' : ucfirst(str_replace('_', ' ', $disbursement->latestVoucher->review_status)) }}</p>
                        @else
                            <p class="mt-1 text-sm text-slate-600">No signed voucher has been uploaded.</p>
                        @endif
                    </div>
                    @if ($disbursement->latestVoucher)
                        <button type="button" data-signed-voucher-preview data-preview-url="{{ route('disbursements.signed-voucher.preview', $disbursement) }}" data-preview-mime="{{ $disbursement->latestVoucher->mime_type }}" class="inline-flex w-fit items-center justify-center rounded-lg border border-blue-200 bg-white px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50" data-testid="view-signed-voucher">View Signed Voucher</button>
                    @endif
                </div>

                @can('uploadSignedVoucher', $disbursement)
                    <form method="POST" action="{{ route('disbursements.signed-voucher.upload', $disbursement) }}" enctype="multipart/form-data" class="mt-4 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4" data-testid="signed-voucher-upload-form">
                        @csrf
                        <label for="signed_voucher" class="block text-sm font-semibold text-slate-800">Upload Signed Voucher</label>
                        <p class="mt-1 text-xs text-slate-500">Upload the physically signed Annex 12 voucher as PDF, JPG, or PNG. Maximum size: 10 MB.</p>
                        <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center">
                            <input id="signed_voucher" name="signed_voucher" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-2.5 file:py-1.5 file:text-xs file:font-semibold file:text-blue-700">
                            <button type="submit" class="inline-flex w-fit shrink-0 items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Submit Signed Voucher</button>
                        </div>
                        <x-form.error name="signed_voucher" />
                    </form>
                @endcan
            </section>

            <div class="mt-5">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Request Details</h3>
                <dl class="mt-3 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Requested Date</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-950">{{ ($disbursement->requested_date ?? $disbursement->created_at)->format('M d, Y') }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Recipient / Payee</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-950">{{ $disbursement->recipient ?? 'Not provided' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Amount</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-950">PHP {{ number_format((float) $disbursement->amount, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Payee Address</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-950">{{ $disbursement->payee_address ?: 'Not provided' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Payee TIN</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-950">{{ $disbursement->payee_tin ?: 'Not provided' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fund Source</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-950">{{ $disbursement->fund_source ?: 'Not provided' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">ObR No.</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-950">{{ $disbursement->obr_number ?: 'Not provided' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">DV No.</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-950">{{ $disbursement->dv_number ?: 'Not assigned for historical record' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Requested By</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-950">{{ $disbursement->requester?->name ?? 'Unknown requester' }}</dd>
                </div>
                <div>
                </dl>
            </div>

            <div class="mt-6 border-t border-slate-100 pt-5" data-testid="actual-payment-section">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Actual Fund Disbursement</h3>
                        <p class="mt-1 text-sm text-slate-600">Approval authorizes the request; this section records when funds were actually released.</p>
                    </div>
                    <span class="w-fit rounded-full px-2.5 py-1 text-xs font-semibold {{ $disbursement->payment_status === 'disbursed' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">{{ $disbursement->payment_status === 'disbursed' ? 'Disbursed' : 'Not Disbursed' }}</span>
                </div>

                @if ($disbursement->payment)
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-testid="payment-details">
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Payment Date</dt><dd class="mt-1 text-sm font-medium text-slate-950">{{ $disbursement->payment->payment_date->format('M d, Y') }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Amount Released</dt><dd class="mt-1 text-sm font-medium text-slate-950">PHP {{ number_format((float) $disbursement->payment->amount, 2) }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Method</dt><dd class="mt-1 text-sm font-medium text-slate-950">{{ match($disbursement->payment->payment_method) { 'bank_transfer' => 'Bank Transfer', 'check' => 'Check', default => 'Cash' } }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reference</dt><dd class="mt-1 text-sm font-medium text-slate-950">{{ $disbursement->payment->reference_number ?: 'Not provided' }}</dd></div>
                    </dl>
                    <p class="mt-3 text-xs text-slate-500">Released by {{ $disbursement->payment->releaser?->name ?? 'Unknown user' }} on {{ $disbursement->payment->released_at->format('M d, Y h:i A') }}.</p>
                @else
                    <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <div class="flex flex-col gap-1 sm:flex-row sm:justify-between">
                            <p class="text-sm font-semibold text-slate-900">Remaining balance</p>
                            <p class="text-sm font-semibold text-slate-900">PHP {{ number_format($disbursement->remainingBalance(), 2) }} of PHP {{ number_format((float) $disbursement->amount, 2) }}</p>
                        </div>
                        @can('recordPayment', $disbursement)
                            <form method="POST" action="{{ route('disbursements.payment.store', $disbursement) }}" class="mt-4 space-y-4" data-testid="record-payment-form">
                                @csrf
                                <p class="text-xs text-slate-500">The current workflow records one full payment only. Partial payments are not supported.</p>
                                <div class="grid gap-4 sm:grid-cols-3">
                                    <div>
                                        <label for="payment_date" class="block text-sm font-medium text-slate-700">Payment Date</label>
                                        <input id="payment_date" name="payment_date" type="date" value="{{ old('payment_date', now()->toDateString()) }}" required class="mt-1 block h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                                        <x-form.error name="payment_date" />
                                    </div>
                                    <div>
                                        <label for="payment_amount" class="block text-sm font-medium text-slate-700">Amount Released</label>
                                        <input id="payment_amount" name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount', number_format((float) $disbursement->amount, 2, '.', '')) }}" required class="mt-1 block h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                                        <x-form.error name="amount" />
                                    </div>
                                    <div>
                                        <label for="payment_method" class="block text-sm font-medium text-slate-700">Payment Method</label>
                                        <select id="payment_method" name="payment_method" required class="mt-1 block h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                                            <option value="cash" @selected(old('payment_method') === 'cash')>Cash</option>
                                            <option value="check" @selected(old('payment_method') === 'check')>Check</option>
                                            <option value="bank_transfer" @selected(old('payment_method') === 'bank_transfer')>Bank Transfer</option>
                                        </select>
                                        <x-form.error name="payment_method" />
                                    </div>
                                </div>
                                <div>
                                    <label for="payment_reference" class="block text-sm font-medium text-slate-700">Payment / Check Reference <span class="font-normal text-slate-500">(required for Check or Bank Transfer)</span></label>
                                    <input id="payment_reference" name="reference_number" type="text" value="{{ old('reference_number') }}" class="mt-1 block h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                                    <x-form.error name="reference_number" />
                                </div>
                                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Record Actual Payment</button>
                            </form>
                        @elseif ($disbursement->status !== 'approved')
                            <p class="mt-3 text-sm text-slate-600">Actual payment can be recorded only after Admin approval.</p>
                        @endif
                    </div>
                @endif
            </div>

            <div class="mt-6 border-t border-slate-100 pt-5">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Additional Information</h3>
                <dl class="mt-3 grid gap-4 md:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Notes / Remarks (Optional)</dt>
                        <dd class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $disbursement->notes ?? 'Not provided' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Submitted</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-950">{{ $disbursement->created_at->format('M d, Y h:i A') }}</dd>
                    </div>
                </dl>
            </div>

            @if ($disbursement->hasTreasurerCertification())
                <div class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4" data-testid="treasurer-certification-status">
                    <p class="text-sm font-semibold text-emerald-900">Treasurer's physical certification recorded</p>
                    <p class="mt-1 text-xs text-emerald-800">Recorded by {{ $disbursement->treasurerCertifier?->name ?? 'Unknown user' }} on {{ $disbursement->treasurer_certified_at?->format('M d, Y h:i A') }}.</p>
                    @if ($disbursement->treasurer_certification_notes)
                        <p class="mt-1 text-xs text-emerald-800">{{ $disbursement->treasurer_certification_notes }}</p>
                    @endif
                </div>
            @can('approve', $disbursement)
                <p class="mt-3 text-sm text-slate-600">Treasurer certification is recorded. Admin may now review this request.</p>
            @endcan
            @elseif ($disbursement->status === 'pending')
                @can('recordTreasurerCertification', $disbursement)
                    <form method="POST" action="{{ route('disbursements.treasurer-certification.store', $disbursement) }}" class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4" data-testid="treasurer-certification-form">
                        @csrf
                        <h3 class="text-sm font-semibold text-amber-950">Record Treasurer's Physical Certification</h3>
                        <p class="mt-1 text-xs text-amber-900">Confirm that the Treasurer physically completed Box B on the printed voucher. This records the certification only; it does not approve or disburse the request.</p>
                        <label class="mt-3 flex items-start gap-2 text-sm text-amber-950"><input type="checkbox" name="certified" value="1" required class="mt-0.5 rounded border-amber-300 text-amber-700 focus:ring-amber-500"><span>I confirm the Treasurer completed the required physical certification.</span></label>
                        <textarea name="notes" rows="2" class="mt-3 block w-full rounded-md border-amber-300 text-sm focus:border-amber-500 focus:ring-amber-500" placeholder="Optional certification note"></textarea>
                        <button type="submit" class="mt-3 inline-flex items-center rounded-lg bg-amber-700 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-800">Record Certification</button>
                    </form>
                @endcan
            @endif

            @if ($disbursement->approved_by)
                <p class="mt-5 text-sm text-slate-600">Approved by {{ $disbursement->approver?->name ?? 'Unknown approver' }} on {{ $disbursement->approved_at?->format('M d, Y h:i A') }}.</p>
            @endif

            @if ($disbursement->rejection_reason)
                <p class="mt-5 text-sm font-semibold text-red-700">Rejected: {{ $disbursement->rejection_reason }}</p>
            @endif

            @if ($disbursement->isVoided())
                <p class="mt-5 text-sm font-semibold text-red-700">Cancelled by {{ $disbursement->voider?->name ?? 'Unknown user' }}. Cancellation Reason: {{ $disbursement->void_reason }}</p>
            @endif
        </section>

        @can('approve', $disbursement)
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-950">Admin decision</h2>
                @if (! $disbursement->hasTreasurerCertification())
                    <p class="mt-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">Staff must record the Treasurer's physical Box B certification before approval.</p>
                @endif
                <div class="mt-4 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    @if ($disbursement->hasTreasurerCertification())
                        <form method="POST" action="{{ route('disbursements.approve', $disbursement) }}">
                            @csrf @method('PATCH')
                            <x-button>Approve</x-button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('disbursements.reject', $disbursement) }}" class="w-full space-y-2 lg:max-w-md">
                        @csrf @method('PATCH')
                        <x-form.label for="rejection_reason">Rejection reason</x-form.label>
                        <textarea id="rejection_reason" name="rejection_reason" rows="3" required class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500">{{ old('rejection_reason') }}</textarea>
                        <x-form.error name="rejection_reason" />
                        <x-button class="w-full">Reject</x-button>
                    </form>
                </div>
                @can('requestMissingSignedVoucher', $disbursement)
                    <div class="mt-5 border-t border-slate-100 pt-4">
                        <h3 class="text-sm font-semibold text-slate-900">Request Missing Documents</h3>
                        <p class="mt-1 text-xs text-slate-500">Use this when a required voucher or certification document is missing or needs correction.</p>
                        <form method="POST" action="{{ route('disbursements.signed-voucher.request', $disbursement) }}" class="mt-3 space-y-2">
                            @csrf @method('PATCH')
                            <label for="signed_voucher_request_reason" class="sr-only">Reason for requesting documents</label>
                            <textarea id="signed_voucher_request_reason" name="reason" rows="3" required placeholder="Tell Staff which signed voucher document is missing or needs correction" class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                            <x-form.error name="reason" />
                            <x-button>Request Missing Documents</x-button>
                        </form>
                    </div>
                @endcan
            </section>
        @endcan
    </div>

    @if ($disbursement->latestVoucher)
        <dialog id="signed-voucher-preview-modal" class="m-auto w-[calc(100%-2rem)] max-w-5xl overflow-hidden rounded-xl border-0 bg-slate-900 p-0 shadow-2xl backdrop:bg-slate-950/70">
            <div class="flex max-h-[90vh] flex-col">
                <div class="flex shrink-0 items-center justify-between gap-4 border-b border-slate-700 bg-slate-900 px-4 py-3 text-white sm:px-6">
                    <div>
                        <h2 class="text-base font-semibold">Signed Voucher Preview</h2>
                        <p class="mt-1 text-xs text-slate-300">Preview only; the original file remains securely stored.</p>
                    </div>
                    <button type="button" data-close-signed-voucher-preview class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white" aria-label="Close signed voucher preview">&times;</button>
                </div>
                <div class="min-h-0 overflow-auto bg-slate-800 p-3 sm:p-6">
                    <iframe class="hidden h-[72vh] min-h-[420px] w-full rounded-lg bg-white" title="Signed voucher PDF preview" data-signed-voucher-pdf></iframe>
                    <img class="hidden max-h-[72vh] max-w-full rounded-lg object-contain mx-auto" alt="Signed voucher preview" data-signed-voucher-image>
                </div>
            </div>
        </dialog>
    @endif

    @can('update', $disbursement)
        <dialog id="edit-fund-request-modal" class="m-auto w-[calc(100%-2rem)] max-w-3xl overflow-hidden rounded-xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/55">
            <form method="POST" action="{{ route('disbursements.update', $disbursement) }}" class="flex max-h-[86vh] flex-col bg-white">
                @csrf @method('PATCH')
                <header class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                    <div><h2 class="text-lg font-semibold text-slate-950">Edit Fund Request</h2><p class="mt-1 text-sm text-slate-500">Changes are allowed while the request is pending.</p></div>
                    <button type="button" data-close-edit-fund-request class="rounded-lg px-2 py-1 text-2xl leading-none text-slate-500 hover:bg-slate-100" aria-label="Close edit fund request">&times;</button>
                </header>
                <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-5">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><label for="edit_amount" class="block text-sm font-medium text-slate-700">Amount</label><input id="edit_amount" name="amount" type="number" step="0.01" min="0.01" required value="{{ $disbursement->amount }}" class="mt-1 block h-11 w-full rounded-lg border-slate-300 text-sm"><x-form.error name="amount" /></div>
                        <div><label for="edit_requested_date" class="block text-sm font-medium text-slate-700">Request Date</label><input id="edit_requested_date" name="requested_date" type="date" required value="{{ $disbursement->requested_date?->format('Y-m-d') }}" class="mt-1 block h-11 w-full rounded-lg border-slate-300 text-sm"><x-form.error name="requested_date" /></div>
                        <div><label for="edit_recipient" class="block text-sm font-medium text-slate-700">Recipient / Payee</label><input id="edit_recipient" name="recipient" required value="{{ $disbursement->recipient }}" class="mt-1 block h-11 w-full rounded-lg border-slate-300 text-sm"><x-form.error name="recipient" /></div>
                        <div><label for="edit_payee_address" class="block text-sm font-medium text-slate-700">Payee Address</label><input id="edit_payee_address" name="payee_address" required value="{{ $disbursement->payee_address }}" class="mt-1 block h-11 w-full rounded-lg border-slate-300 text-sm"><x-form.error name="payee_address" /></div>
                        <div><label for="edit_payee_tin" class="block text-sm font-medium text-slate-700">Payee TIN <span class="font-normal text-slate-500">(Optional)</span></label><input id="edit_payee_tin" name="payee_tin" value="{{ $disbursement->payee_tin }}" class="mt-1 block h-11 w-full rounded-lg border-slate-300 text-sm"></div>
                        <div><label for="edit_fund_source" class="block text-sm font-medium text-slate-700">Fund Source</label><select id="edit_fund_source" name="fund_source" required class="mt-1 block h-11 w-full rounded-lg border-slate-300 text-sm"><option value="">Select Fund Source</option>@foreach (config('disbursements.fund_sources', []) as $fundSource)<option value="{{ $fundSource }}" @selected($disbursement->fund_source === $fundSource)>{{ $fundSource }}</option>@endforeach</select><x-form.error name="fund_source" /></div>
                        <div><label for="edit_obr_number" class="block text-sm font-medium text-slate-700">ObR No. <span class="font-normal text-slate-500">(Optional)</span></label><input id="edit_obr_number" name="obr_number" value="{{ $disbursement->obr_number }}" class="mt-1 block h-11 w-full rounded-lg border-slate-300 text-sm"></div>
                    </div>
                    <div><label for="edit_purpose" class="block text-sm font-medium text-slate-700">Purpose</label><textarea id="edit_purpose" name="purpose" rows="3" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">{{ $disbursement->purpose }}</textarea><x-form.error name="purpose" /></div>
                    <div class="grid gap-4 sm:grid-cols-2"><div><label for="edit_notes" class="block text-sm font-medium text-slate-700">Notes / Remarks (Optional)</label><textarea id="edit_notes" name="notes" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">{{ $disbursement->notes }}</textarea></div></div>
                </div>
                <footer class="flex justify-end gap-3 border-t border-slate-200 bg-slate-50 px-5 py-3"><button type="button" data-close-edit-fund-request class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button><button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Save Changes</button></footer>
            </form>
        </dialog>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const dialog = document.getElementById('edit-fund-request-modal');
                document.querySelector('[data-open-edit-fund-request]')?.addEventListener('click', () => dialog?.showModal());
                document.querySelectorAll('[data-close-edit-fund-request]').forEach((button) => button.addEventListener('click', () => dialog?.close()));
            });
        </script>
    @endcan
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const trigger = document.querySelector('[data-signed-voucher-preview]');
            const dialog = document.getElementById('signed-voucher-preview-modal');
            if (!trigger || !dialog) return;

            const pdf = dialog.querySelector('[data-signed-voucher-pdf]');
            const image = dialog.querySelector('[data-signed-voucher-image]');

            trigger.addEventListener('click', () => {
                const mime = trigger.dataset.previewMime || '';
                pdf.classList.toggle('hidden', mime !== 'application/pdf');
                image.classList.toggle('hidden', mime === 'application/pdf');
                if (mime === 'application/pdf') {
                    pdf.src = trigger.dataset.previewUrl;
                } else {
                    image.src = trigger.dataset.previewUrl;
                }
                dialog.showModal();
            });

            dialog.querySelector('[data-close-signed-voucher-preview]')?.addEventListener('click', () => dialog.close());
            dialog.addEventListener('click', (event) => {
                if (event.target === dialog) dialog.close();
            });
        });
    </script>
@endpush
