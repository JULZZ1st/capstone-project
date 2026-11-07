<section class="space-y-4 border-t border-slate-100 pt-4" aria-labelledby="in-kind-documents-heading">
    <div>
        <h3 id="in-kind-documents-heading" class="text-sm font-semibold text-slate-950">Donation Documents</h3>
        <p class="text-xs text-slate-500">Optional supporting documents for this donation.</p>
    </div>
    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <x-form.label for="donation_item_list">Donation Item List / Delivery Receipt <span class="font-normal text-slate-400">(optional)</span></x-form.label>
            <input id="donation_item_list" name="donation_item_list" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" data-single-document class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-2.5 file:py-1.5 file:text-xs file:font-semibold file:text-blue-700">
            <div class="mt-1 flex min-w-0 items-center gap-2 text-xs text-slate-500" data-single-document-status><span class="min-w-0 flex-1 truncate" data-single-document-name>Choose a file. Optional, up to 10MB.</span><button type="button" class="hidden shrink-0 rounded-full p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-700 focus:outline-none focus:ring-2 focus:ring-blue-500" data-single-document-remove aria-label="Remove selected Donation Item List">&times;</button></div>
            <p class="sr-only" data-document-help>PDF, JPG, JPEG, or PNG up to 10MB.</p>
            <x-form.error name="donation_item_list" />
        </div>
        <div>
            <x-form.label for="deed_of_donation_acceptance">Deed of Donation and Acceptance <span class="font-normal text-slate-400">(optional)</span></x-form.label>
            <input id="deed_of_donation_acceptance" name="deed_of_donation_acceptance" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" data-single-document class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-2.5 file:py-1.5 file:text-xs file:font-semibold file:text-blue-700">
            <div class="mt-1 flex min-w-0 items-center gap-2 text-xs text-slate-500" data-single-document-status><span class="min-w-0 flex-1 truncate" data-single-document-name>Choose a file. Optional, up to 10MB.</span><button type="button" class="hidden shrink-0 rounded-full p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-700 focus:outline-none focus:ring-2 focus:ring-blue-500" data-single-document-remove aria-label="Remove selected Deed of Donation and Acceptance">&times;</button></div>
            <p class="sr-only" data-document-help>PDF, JPG, JPEG, or PNG up to 10MB.</p>
            <x-form.error name="deed_of_donation_acceptance" />
        </div>
        <div>
            <x-form.label for="par_ics_acknowledgement">PAR / ICS / Acknowledgement Document <span class="font-normal text-slate-400">(optional)</span></x-form.label>
            <input id="par_ics_acknowledgement" name="par_ics_acknowledgement" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" data-single-document class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-2.5 file:py-1.5 file:text-xs file:font-semibold file:text-blue-700">
            <div class="mt-1 flex min-w-0 items-center gap-2 text-xs text-slate-500" data-single-document-status><span class="min-w-0 flex-1 truncate" data-single-document-name>Choose a file. Optional, up to 10MB.</span><button type="button" class="hidden shrink-0 rounded-full p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-700 focus:outline-none focus:ring-2 focus:ring-blue-500" data-single-document-remove aria-label="Remove selected PAR / ICS / Acknowledgement Document">&times;</button></div>
            <p class="sr-only" data-document-help>PDF, JPG, JPEG, or PNG up to 10MB.</p>
            <x-form.error name="par_ics_acknowledgement" />
        </div>
    </div>
    <fieldset class="rounded-lg border border-slate-200 bg-slate-50/70 p-3">
        <legend class="px-1 text-sm font-medium text-slate-800">Did the donor request a Certificate of Donation?</legend>
        <div class="mt-2 flex gap-5 text-sm text-slate-700">
            <label class="inline-flex items-center gap-2"><input type="radio" name="in_kind_certificate_requested" value="0" data-certificate-requested-radio @checked(! old('certificate_requested')) class="border-slate-300 text-blue-600 focus:ring-blue-500"> No</label>
            <label class="inline-flex items-center gap-2"><input type="radio" name="in_kind_certificate_requested" value="1" data-certificate-requested-radio @checked((bool) old('certificate_requested')) class="border-slate-300 text-blue-600 focus:ring-blue-500"> Yes</label>
        </div>
        <p class="mt-2 text-xs text-slate-500">A request is recorded as Pending. Only an authorized Admin can mark it Issued and upload the certificate.</p>
        {{-- A separate radio `name` avoids colliding with Cash's native radio group (same `<form>`); this hidden input is what actually submits as `certificate_requested`. --}}
        <input type="hidden" name="certificate_requested" value="{{ old('certificate_requested') ? '1' : '0' }}" data-certificate-requested-hidden>
        <x-form.error name="certificate_requested" />
    </fieldset>
</section>
