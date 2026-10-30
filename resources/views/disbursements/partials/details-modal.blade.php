@php
    $modalId = $modalId ?? 'fund-request-details-modal-'.$disbursement->id;
    $editModalId = $modalId.'-edit';
    $previewModalId = $modalId.'-preview';
    $cancelModalId = $modalId.'-cancel';
    $directPage = $directPage ?? false;
    $auditEvents = $disbursement->getRelation('auditEvents') ?? collect();
    $statusLabel = $disbursement->isVoided()
        ? 'Cancelled'
        : ($disbursement->status === 'pending' ? 'Pending Approval' : ($disbursement->status === 'approved' ? 'Approved' : ucfirst($disbursement->status)));
    $disbursementStatus = $disbursement->payment ? 'Disbursed' : ($disbursement->status === 'approved' ? 'Approved' : 'Not Disbursed');
@endphp

<dialog id="{{ $modalId }}" class="m-auto w-[calc(100%-1rem)] max-w-6xl overflow-hidden rounded-2xl border-0 bg-slate-100 p-0 shadow-2xl backdrop:bg-slate-950/60" data-fund-request-details-dialog @if($directPage) open @endif>
    <div class="flex max-h-[calc(100vh-2rem)] min-h-0 flex-col">
        <header class="sticky top-0 z-20 shrink-0 border-b border-slate-200 bg-white px-5 py-4 sm:px-7">
            <div class="flex items-start justify-between gap-4">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-600 ring-1 ring-blue-100"><x-icon name="document" class="h-6 w-6" /></span>
                    <div class="min-w-0">
                        <h2 class="truncate text-xl font-bold tracking-tight text-slate-950">Fund Request Details</h2>
                        <p class="mt-0.5 text-sm font-medium text-slate-500">Fund Request #{{ $disbursement->id }}</p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-3">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 ring-1 ring-amber-100"><x-icon name="clock" class="h-3.5 w-3.5" />{{ $statusLabel }}</span>
                    @if($directPage)
                        <a href="{{ route('disbursements.index') }}" class="hidden rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 sm:inline-flex">Back</a>
                    @else
                        <button type="button" data-close-fund-request-details class="flex h-9 w-9 items-center justify-center rounded-lg text-2xl leading-none text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Close Fund Request Details">&times;</button>
                    @endif
                </div>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-6">
                <div class="rounded-xl border border-slate-200 bg-white px-3 py-3"><p class="text-[11px] font-medium text-slate-500">Payee / Recipient</p><p class="mt-1 truncate text-sm font-bold text-slate-900" title="{{ $disbursement->recipient }}">{{ $disbursement->recipient ?: 'Not provided' }}</p></div>
                <div class="rounded-xl border border-slate-200 bg-white px-3 py-3"><p class="text-[11px] font-medium text-slate-500">Amount</p><p class="mt-1 truncate text-sm font-bold text-slate-900">PHP {{ number_format((float) $disbursement->amount, 2) }}</p></div>
                <div class="rounded-xl border border-slate-200 bg-white px-3 py-3"><p class="text-[11px] font-medium text-slate-500">Request Date</p><p class="mt-1 truncate text-sm font-bold text-slate-900">{{ ($disbursement->requested_date ?? $disbursement->created_at)->format('M d, Y') }}</p></div>
                <div class="rounded-xl border border-slate-200 bg-white px-3 py-3"><p class="text-[11px] font-medium text-slate-500">Requested By</p><p class="mt-1 truncate text-sm font-bold text-slate-900" title="{{ $disbursement->requester?->name }}">{{ $disbursement->requester?->name ?: 'Unknown' }}</p></div>
                <div class="rounded-xl border border-slate-200 bg-white px-3 py-3"><p class="text-[11px] font-medium text-slate-500">DV Number</p><p class="mt-1 truncate text-sm font-bold text-slate-900">{{ $disbursement->dv_number ?: 'Not yet assigned' }}</p></div>
                <div class="rounded-xl border border-slate-200 bg-white px-3 py-3"><p class="text-[11px] font-medium text-slate-500">Disbursement Status</p><p class="mt-1 truncate text-sm font-bold text-slate-900">{{ $disbursementStatus }}</p></div>
            </div>
        </header>

        <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-6 sm:py-5">
            <div class="grid gap-4 xl:grid-cols-[minmax(0,1.55fr)_minmax(280px,0.72fr)]">
                <main class="min-w-0 space-y-4">
                    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
                            <div class="flex items-center gap-2"><span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600"><x-icon name="document" class="h-4 w-4" /></span><h3 class="text-base font-bold text-slate-900">Request Information</h3></div>
                        </div>
                        <dl class="mt-4 grid gap-4 md:grid-cols-3">
                            <div class="md:col-span-3"><dt class="text-xs font-semibold text-slate-500">Purpose / Particulars</dt><dd class="mt-1 whitespace-pre-line text-sm text-slate-800">{{ $disbursement->purpose ?: 'Not provided' }}</dd></div>
                            <div class="md:col-span-3"><dt class="text-xs font-semibold text-slate-500">Notes / Remarks</dt><dd class="mt-1 whitespace-pre-line text-sm text-slate-800">{{ $disbursement->notes ?: 'Not provided' }}</dd></div>
                        </dl>
                        @can('void', $disbursement)<button type="button" data-open-cancel-review="{{ $modalId }}" class="mt-4 text-xs font-semibold text-red-700 hover:text-red-900">Cancel Request</button>@endcan
                    </section>

                    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <div class="flex items-center gap-2 border-b border-slate-100 pb-3"><span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600"><x-icon name="wallet" class="h-4 w-4" /></span><h3 class="text-base font-bold text-slate-900">Payee &amp; Fund Details</h3></div>
                        <dl class="mt-4 grid gap-x-5 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div><dt class="text-xs font-semibold text-slate-500">Payee Address</dt><dd class="mt-1 text-sm text-slate-800">{{ $disbursement->payee_address ?: 'Not provided' }}</dd></div>
                            <div><dt class="text-xs font-semibold text-slate-500">Payee TIN</dt><dd class="mt-1 text-sm text-slate-800">{{ $disbursement->payee_tin ?: 'Not provided' }}</dd></div>
                            <div><dt class="text-xs font-semibold text-slate-500">Fund Source</dt><dd class="mt-1 text-sm text-slate-800">{{ $disbursement->fund_source ?: 'Not provided' }}</dd></div>
                            <div><dt class="text-xs font-semibold text-slate-500">ObR No.</dt><dd class="mt-1 text-sm text-slate-800">{{ $disbursement->obr_number ?: 'Not provided' }}</dd></div>
                        </dl>
                    </section>

                    <section class="rounded-xl border border-blue-200 bg-blue-50/70 p-4 shadow-sm sm:p-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div><div class="flex items-center gap-2"><span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white text-blue-600"><x-icon name="document" class="h-4 w-4" /></span><h3 class="text-base font-bold text-blue-950">COA Annex 12 Voucher</h3></div><p class="mt-2 max-w-xl text-xs text-blue-800">Download the populated voucher, print it, and complete the required physical certifications and signatures.</p></div>
                            <a href="{{ route('disbursements.voucher', $disbursement) }}" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700" data-testid="generate-disbursement-voucher-{{ $disbursement->id }}"><x-icon name="download" class="h-4 w-4" />Generate Disbursement Voucher</a>
                        </div>
                        <div class="mt-4 border-t border-blue-200 pt-3">
                            @if($disbursement->hasTreasurerCertification())
                                <p class="flex items-center gap-2 text-xs text-emerald-800"><x-icon name="check-circle" class="h-4 w-4" /><span><strong>Treasurer Box B certification recorded</strong> by {{ $disbursement->treasurerCertifier?->name ?: 'Unknown user' }} on {{ $disbursement->treasurer_certified_at?->format('M d, Y h:i A') }}.</span></p>
                            @else
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-xs font-bold text-amber-900">Treasurer Box B certification required</p><p class="mt-1 text-xs text-amber-800">Record the physical certification before Admin review. This does not approve or disburse the request.</p></div>@can('recordTreasurerCertification', $disbursement)<form method="POST" action="{{ route('disbursements.treasurer-certification.store', $disbursement) }}" class="flex shrink-0 items-center gap-2" data-testid="treasurer-certification-form-{{ $disbursement->id }}">@csrf<input type="checkbox" name="certified" value="1" required class="rounded border-amber-300 text-amber-700"><input type="hidden" name="notes" value="Physical Box B certification recorded"><button type="submit" class="rounded-lg border border-amber-300 bg-white px-3 py-2 text-xs font-bold text-amber-800 hover:bg-amber-50">Record Certification</button></form>@endcan</div>
                            @endif
                        </div>
                    </section>

                    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><div class="flex items-center gap-2"><span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600"><x-icon name="upload" class="h-4 w-4" /></span><h3 class="text-base font-bold text-slate-900">Signed Voucher</h3></div><p class="mt-2 text-xs text-slate-500">Upload the fully signed voucher after approval and the Captain completes Box C.</p></div>@if($disbursement->latestVoucher)<button type="button" data-open-voucher-preview="{{ $previewModalId }}" class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-blue-200 px-3 py-2 text-xs font-bold text-blue-700 hover:bg-blue-50">View Signed Voucher</button>@endif</div>
                        @if($disbursement->latestVoucher)<p class="mt-4 text-sm font-semibold text-slate-900">{{ $disbursement->latestVoucher->original_name }}</p><p class="mt-1 text-xs text-slate-500">{{ ucfirst(str_replace('_', ' ', $disbursement->latestVoucher->review_status)) }} · {{ strtoupper(pathinfo($disbursement->latestVoucher->original_name, PATHINFO_EXTENSION)) }}</p>@else<p class="mt-4 text-sm text-slate-500">No signed voucher uploaded.</p>@endif
                        @can('uploadSignedVoucher', $disbursement)<form method="POST" action="{{ route('disbursements.signed-voucher.upload', $disbursement) }}" enctype="multipart/form-data" class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4" data-testid="signed-voucher-upload-form-{{ $disbursement->id }}">@csrf<label for="signed_voucher_{{ $disbursement->id }}" class="block text-sm font-semibold text-slate-800">Choose a file or drag and drop</label><input id="signed_voucher_{{ $disbursement->id }}" name="signed_voucher" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required class="mt-2 block w-full text-xs text-slate-600"><p class="mt-1 text-xs text-slate-500">PDF, JPG or PNG (Max 10MB)</p><button type="submit" class="mt-3 rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white hover:bg-slate-800">Submit Signed Voucher</button><x-form.error name="signed_voucher" /></form>@endcan
                    </section>
                </main>

                <aside class="min-w-0 space-y-4">
                    @can('approve', $disbursement)
                        <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><div class="flex items-center gap-2"><span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600"><x-icon name="shield-check" class="h-4 w-4" /></span><h3 class="text-base font-bold text-slate-900">Admin Review</h3></div>@if(!$disbursement->hasTreasurerCertification())<p class="mt-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-900">Treasurer Box B certification is required before approval.</p>@endif<div class="mt-4 flex flex-wrap gap-2">@if($disbursement->hasTreasurerCertification())<form method="POST" action="{{ route('disbursements.approve', $disbursement) }}">@csrf @method('PATCH')<button type="submit" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-800">Approve</button></form>@endif<button type="button" data-open-reject-review="{{ $modalId }}" class="rounded-lg border border-red-200 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-50">Reject</button></div>@can('requestMissingSignedVoucher', $disbursement)<button type="button" data-open-missing-review="{{ $modalId }}" class="mt-3 text-xs font-bold text-amber-700 hover:text-amber-900">Request Missing Documents</button>@endcan</section>
                    @endcan

                    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><div class="flex items-center justify-between gap-3"><div class="flex items-center gap-2"><span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600"><x-icon name="clock" class="h-4 w-4" /></span><h3 class="text-base font-bold text-slate-900">Audit History</h3></div><span class="text-xs font-bold text-blue-600">{{ $auditEvents->count() }} event{{ $auditEvents->count() === 1 ? '' : 's' }}</span></div>@if($auditEvents->isEmpty())<p class="mt-4 text-sm text-slate-500">No audit events recorded.</p>@else<ul class="mt-4 max-h-80 space-y-4 overflow-y-auto">@foreach($auditEvents as $event)<li class="relative border-l-2 border-slate-200 pl-4"><span class="absolute -left-[5px] top-0 h-2 w-2 rounded-full bg-blue-600 ring-4 ring-white"></span><p class="text-xs font-bold text-slate-900">{{ $event->actionLabel() }}</p><p class="mt-1 text-xs leading-5 text-slate-600">{{ $event->summary }}</p><p class="mt-1 text-[11px] text-slate-400">{{ $event->occurred_at?->format('M d, Y h:i A') }}</p></li>@endforeach</ul>@endif</section>

                </aside>
            </div>
        </div>

        <footer class="sticky bottom-0 z-20 flex shrink-0 justify-end gap-2 border-t border-slate-200 bg-white px-5 py-3 sm:px-7">@can('update', $disbursement)<button type="button" data-open-fund-request-edit="{{ $editModalId }}" class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-white px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50"><x-icon name="pencil" class="h-4 w-4" />Edit Request</button>@endcan @if(!$directPage)<button type="button" data-close-fund-request-details class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Close</button>@else<a href="{{ route('disbursements.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back</a>@endif</footer>
    </div>

    @can('update', $disbursement)
        <dialog id="{{ $editModalId }}" class="m-auto w-[calc(100%-2rem)] max-w-3xl rounded-xl border-0 p-0 shadow-2xl backdrop:bg-slate-950/60" data-fund-request-nested-dialog><form method="POST" action="{{ route('disbursements.update', $disbursement) }}" class="flex max-h-[90vh] flex-col bg-white">@csrf @method('PATCH')<header class="flex shrink-0 items-center justify-between border-b border-slate-200 px-5 py-4"><h3 class="text-lg font-bold text-slate-950">Edit Fund Request</h3><button type="button" data-close-nested-dialog class="text-2xl text-slate-500" aria-label="Close Edit Fund Request">&times;</button></header>@include('disbursements.partials.fund-request-form-fields', ['formId' => 'edit-fund-request-'.$disbursement->id, 'values' => ['amount' => $disbursement->amount, 'requested_date' => $disbursement->requested_date?->format('Y-m-d'), 'recipient' => $disbursement->recipient, 'payee_address' => $disbursement->payee_address, 'payee_tin' => $disbursement->payee_tin, 'fund_source' => $disbursement->fund_source, 'obr_number' => $disbursement->obr_number, 'province' => $disbursement->province, 'city_municipality' => $disbursement->city_municipality, 'barangay' => $disbursement->barangay, 'purpose' => $disbursement->purpose, 'notes' => $disbursement->notes], 'useOld' => false, 'fundRequestProvinces' => $fundRequestProvinces ?? collect()])<footer class="flex shrink-0 justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3"><button type="button" data-close-nested-dialog class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">Cancel</button><button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Save Changes</button></footer></form></dialog>
    @endcan

    @if($disbursement->latestVoucher)<dialog id="{{ $previewModalId }}" class="m-auto w-[calc(100%-1.5rem)] max-w-5xl rounded-xl border-0 bg-slate-900 p-0 shadow-2xl backdrop:bg-slate-950/70" data-fund-request-nested-dialog><div class="flex max-h-[90vh] flex-col"><header class="flex shrink-0 items-center justify-between border-b border-slate-700 px-4 py-3 text-white"><h3 class="text-base font-bold">Signed Voucher Preview</h3><button type="button" data-close-nested-dialog class="text-2xl text-slate-300" aria-label="Close Signed Voucher Preview">&times;</button></header><div class="min-h-0 overflow-auto p-4"><iframe src="{{ route('disbursements.signed-voucher.preview', $disbursement) }}" class="h-[72vh] min-h-[420px] w-full rounded bg-white" title="Signed voucher PDF or image preview"></iframe></div></div></dialog>@endif

    @can('approve', $disbursement)
        <dialog id="{{ $modalId }}-reject" class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-xl border-0 p-0 shadow-2xl" data-fund-request-nested-dialog><form method="POST" action="{{ route('disbursements.reject', $disbursement) }}" class="bg-white">@csrf @method('PATCH')<header class="border-b border-slate-200 px-5 py-4"><h3 class="text-lg font-bold text-slate-950">Reject Fund Request</h3></header><div class="p-5"><label class="text-sm font-medium">Rejection reason</label><textarea name="rejection_reason" rows="4" required class="mt-2 w-full rounded-lg border-slate-300 text-sm"></textarea></div><footer class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3"><button type="button" data-close-nested-dialog class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">Cancel</button><button type="submit" class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white">Reject</button></footer></form></dialog>
        @can('requestMissingSignedVoucher', $disbursement)<dialog id="{{ $modalId }}-missing" class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-xl border-0 p-0 shadow-2xl" data-fund-request-nested-dialog><form method="POST" action="{{ route('disbursements.signed-voucher.request', $disbursement) }}" class="bg-white">@csrf @method('PATCH')<header class="border-b border-slate-200 px-5 py-4"><h3 class="text-lg font-bold text-slate-950">Request Missing Documents</h3></header><div class="p-5"><p class="text-sm text-slate-600">Tell Staff what must be corrected or uploaded.</p><textarea name="reason" rows="4" required class="mt-3 w-full rounded-lg border-slate-300 text-sm" placeholder="Missing or incorrect document details"></textarea></div><footer class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3"><button type="button" data-close-nested-dialog class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">Cancel</button><button type="submit" class="rounded-lg bg-amber-700 px-4 py-2 text-sm font-semibold text-white">Request Documents</button></footer></form></dialog>@endcan
    @endcan

    @can('void', $disbursement)<dialog id="{{ $cancelModalId }}" class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-xl border-0 p-0 shadow-2xl" data-fund-request-nested-dialog><form method="POST" action="{{ route('disbursements.void', $disbursement) }}" class="bg-white">@csrf @method('PATCH')<header class="border-b border-slate-200 px-5 py-4"><h3 class="text-lg font-bold text-slate-950">Cancel Fund Request</h3></header><div class="p-5"><label for="void_reason_{{ $disbursement->id }}" class="text-sm font-medium">Cancellation reason</label><textarea id="void_reason_{{ $disbursement->id }}" name="void_reason" rows="4" required class="mt-2 w-full rounded-lg border-slate-300 text-sm"></textarea></div><footer class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3"><button type="button" data-close-nested-dialog class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">Keep Request</button><button type="submit" class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white">Confirm Cancellation</button></footer></form></dialog>@endcan
</dialog>
