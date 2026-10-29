<section class="space-y-4 border-t border-slate-100 pt-4" aria-labelledby="cash-documents-heading">
    <div>
        <h3 id="cash-documents-heading" class="text-sm font-semibold text-slate-950">Official Receipt</h3>
        <p class="text-xs text-slate-500">Enter the official receipt details and upload the receipt before saving.</p>
    </div>
    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <x-form.label for="official_receipt_number">Official Receipt Number <span class="text-red-600" aria-hidden="true">*</span></x-form.label>
            <input id="official_receipt_number" name="official_receipt_number" type="text" value="{{ old('official_receipt_number') }}" placeholder="Enter official receipt number" required class="mt-1 block h-10 w-full rounded-lg border-slate-300 px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <x-form.error name="official_receipt_number" />
        </div>
        <div>
            <x-form.label for="official_receipt_document">Upload Official Receipt <span class="text-red-600" aria-hidden="true">*</span></x-form.label>
            <input id="official_receipt_document" name="official_receipt_document" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required data-single-document class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-2.5 file:py-1.5 file:text-xs file:font-semibold file:text-blue-700">
            <div class="mt-1 flex min-w-0 items-center gap-2 text-xs text-slate-500" data-single-document-status><span class="min-w-0 flex-1 truncate" data-single-document-name>Choose a file. Required, up to 10MB.</span><button type="button" class="hidden shrink-0 rounded-full p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-700 focus:outline-none focus:ring-2 focus:ring-blue-500" data-single-document-remove aria-label="Remove selected Official Receipt">&times;</button></div>
            <p class="sr-only" data-document-help>Required. PDF, JPG, JPEG, or PNG up to 10MB.</p>
            <x-form.error name="official_receipt_document" />
        </div>
    </div>
    <div class="border-t border-slate-100 pt-4">
        <h3 class="text-sm font-semibold text-slate-950">Additional Documents</h3>
        <p class="text-xs text-slate-500">The Deed is optional below PHP 5,000. At PHP 5,000 or above, upload it or record the donor's documented exception.</p>
    </div>
    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <x-form.label for="deed_of_donation">Deed of Donation <span data-document-required-indicator class="hidden text-red-600" aria-hidden="true">*</span><span data-document-optional-indicator class="font-normal text-slate-400">(optional)</span></x-form.label>
            <input id="deed_of_donation" name="deed_of_donation" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" data-conditional-document data-single-document class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-2.5 file:py-1.5 file:text-xs file:font-semibold file:text-blue-700">
            <div class="mt-1 flex min-w-0 items-center gap-2 text-xs text-slate-500" data-single-document-status><span class="min-w-0 flex-1 truncate" data-single-document-name>Choose a file. Optional below PHP 5,000.</span><button type="button" class="hidden shrink-0 rounded-full p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-700 focus:outline-none focus:ring-2 focus:ring-blue-500" data-single-document-remove aria-label="Remove selected Deed of Donation">&times;</button></div>
            <p class="sr-only" data-document-help>Optional below PHP 5,000. PDF, JPG, JPEG, or PNG up to 10MB.</p>
            <x-form.error name="deed_of_donation" />
        </div>
        <div>
            <x-form.label for="certificate_of_acceptance">Certificate of Acceptance <span class="text-red-600" aria-hidden="true">*</span></x-form.label>
            <input id="certificate_of_acceptance" name="certificate_of_acceptance" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" data-conditional-document data-single-document class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-2.5 file:py-1.5 file:text-xs file:font-semibold file:text-blue-700">
            <div class="mt-1 flex min-w-0 items-center gap-2 text-xs text-slate-500" data-single-document-status><span class="min-w-0 flex-1 truncate" data-single-document-name>Choose a file. Required, up to 10MB.</span><button type="button" class="hidden shrink-0 rounded-full p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-700 focus:outline-none focus:ring-2 focus:ring-blue-500" data-single-document-remove aria-label="Remove selected Certificate of Acceptance">&times;</button></div>
            <p class="sr-only" data-document-help>Required. PDF, JPG, JPEG, or PNG up to 10MB.</p>
            <x-form.error name="certificate_of_acceptance" />
        </div>
    </div>
    <fieldset class="rounded-lg border border-amber-200 bg-amber-50/60 p-3" data-deed-exception-section hidden>
        <label class="inline-flex items-start gap-2 text-sm font-medium text-slate-800">
            <input id="deed_declined" name="deed_declined" type="checkbox" value="1" data-deed-declined class="mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
            <span>Donor declined to sign a Deed of Donation.</span>
        </label>
        <p class="mt-1 text-xs text-amber-800">This saves the donation as Pending and sends it to Admin Review. It does not automatically waive the documentation requirement.</p>
        <div class="mt-3" data-deed-exception-reason hidden>
            <x-form.label for="deed_decline_reason">Reason / Explanation <span class="text-red-600" aria-hidden="true">*</span></x-form.label>
            <textarea id="deed_decline_reason" name="deed_decline_reason" rows="2" placeholder="Explain why the donor declined to sign" disabled class="mt-1 block w-full rounded-lg border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('deed_decline_reason') }}</textarea>
            <x-form.error name="deed_decline_reason" />
        </div>
        <x-form.error name="deed_declined" />
    </fieldset>
    <fieldset class="rounded-lg border border-slate-200 bg-slate-50/70 p-3">
        <legend class="px-1 text-sm font-medium text-slate-800">Did the donor request a Certificate of Donation?</legend>
        <div class="mt-2 flex gap-5 text-sm text-slate-700">
            <label class="inline-flex items-center gap-2"><input type="radio" name="certificate_requested" value="0" @checked(! old('certificate_requested')) class="border-slate-300 text-blue-600 focus:ring-blue-500"> No</label>
            <label class="inline-flex items-center gap-2"><input type="radio" name="certificate_requested" value="1" @checked((bool) old('certificate_requested')) class="border-slate-300 text-blue-600 focus:ring-blue-500"> Yes</label>
        </div>
        <p class="mt-2 text-xs text-slate-500">A request is recorded as Pending. Only an authorized Admin can mark it Issued and upload the certificate.</p>
        <div class="mt-3" data-certificate-tin-field hidden>
            <x-form.label for="donor_tin">Donor TIN <span class="font-normal text-slate-400">(if available)</span></x-form.label>
            <input id="donor_tin" name="donor_tin" type="text" value="{{ old('donor_tin') }}" placeholder="Enter TIN if provided for certificate processing" disabled class="mt-1 block h-10 w-full rounded-lg border-slate-300 px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <x-form.error name="donor_tin" />
        </div>
        <x-form.error name="certificate_requested" />
    </fieldset>
    <div data-bank-proof-section hidden>
        <x-donations.supporting-evidence
            input-name="bank_transfer_proof"
            error-name="bank_transfer_proof"
            title="Bank Deposit/Transfer Proof"
            description="Upload a valid deposit slip or transfer confirmation before Admin verification."
            :show-optional="false"
        />
    </div>
</section>
