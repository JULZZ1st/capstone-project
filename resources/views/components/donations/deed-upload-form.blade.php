@props(['contribution'])

@can('uploadDeed', $contribution)
    @if (($contribution->document_review_status === 'missing_deed_admin_review' || (float) $contribution->amount >= 5000)
        && ! $contribution->evidence->contains('document_type', 'deed_of_donation'))
        <section class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
            <h3 class="text-sm font-semibold text-slate-900">Upload Missing Deed of Donation</h3>
            <p class="mt-1 text-sm text-slate-600">Attach the signed deed, then verify the donation after reviewing its documents.</p>
            @if ($contribution->document_request_reason)
                <p class="mt-2 text-sm text-amber-900">{{ $contribution->document_request_reason }}</p>
            @endif
            <form method="POST" action="{{ route('donations.deed.upload', $contribution) }}" enctype="multipart/form-data" class="mt-3 space-y-3">
                @csrf
                @method('PATCH')
                <label for="deed-of-donation-{{ $contribution->id }}" class="block text-sm font-medium text-slate-700">Signed deed (PDF, JPG, or PNG, up to 10 MB)</label>
                <input id="deed-of-donation-{{ $contribution->id }}" type="file" name="deed_of_donation"
                    accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required
                    @if ($errors->deedUpload->has('deed_of_donation')) aria-invalid="true" aria-describedby="deed_of_donation-error-{{ $contribution->id }}" @endif
                    class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-600">
                @error('deed_of_donation', 'deedUpload')
                    <p id="deed_of_donation-error-{{ $contribution->id }}" class="text-sm text-red-700" role="alert">{{ $message }}</p>
                @enderror
                <x-button>Upload Deed</x-button>
            </form>
        </section>
    @endif
@endcan
