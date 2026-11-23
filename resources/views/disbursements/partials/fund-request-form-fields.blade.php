@php
    $values = $values ?? [];
    $useOld = $useOld ?? false;
    $fieldValue = static function (string $key, mixed $fallback = '') use ($values, $useOld): mixed {
        return $useOld ? old($key, $fallback) : ($values[$key] ?? $fallback);
    };
    $provinceValue = config('disbursements.barangay.province');
    $cityValue = config('disbursements.barangay.city_municipality');
    $barangayValue = config('disbursements.barangay.location_barangay');
@endphp

<div class="min-h-0 flex-1 space-y-6 overflow-y-auto px-4 py-5 sm:px-6" data-fund-request-form-scroll>
    @if($useOld && $errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">
            <p class="font-semibold">Please correct these fields and save again.</p>
            <ul class="mt-2 list-disc pl-5">
                @foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </div>
    @endif
    <section aria-labelledby="{{ $formId }}-payment-heading" class="space-y-4">
        <div>
            <h3 id="{{ $formId }}-payment-heading" class="text-sm font-semibold text-slate-950">Payment Details</h3>
            <p class="mt-1 text-xs text-slate-500">Enter the basic payment information for this request.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="{{ $formId }}-amount" class="block text-sm font-medium text-slate-700">Amount Requested (₱) <span class="text-red-600">*</span></label>
                <input id="{{ $formId }}-amount" name="amount" type="number" step="0.01" min="0.01" value="{{ $fieldValue('amount') }}" required class="mt-1.5 block h-11 w-full rounded-lg border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @if($useOld)<x-form.error name="amount" :id="$formId.'-amount-error'" />@endif
            </div>
            <div>
                <label for="{{ $formId }}-requested-date" class="block text-sm font-medium text-slate-700">Date of Request <span class="text-red-600">*</span></label>
                <input id="{{ $formId }}-requested-date" name="requested_date" type="date" value="{{ $fieldValue('requested_date') }}" required class="mt-1.5 block h-11 w-full rounded-lg border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @if($useOld)<x-form.error name="requested_date" :id="$formId.'-requested_date-error'" />@endif
            </div>
            <div class="sm:col-span-2">
                <label for="{{ $formId }}-recipient" class="block text-sm font-medium text-slate-700">Who will receive the payment? <span class="text-red-600">*</span></label>
                <input id="{{ $formId }}-recipient" name="recipient" type="text" value="{{ $fieldValue('recipient') }}" required placeholder="Enter person or supplier name" class="mt-1.5 block h-11 w-full rounded-lg border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @if($useOld)<x-form.error name="recipient" :id="$formId.'-recipient-error'" />@endif
            </div>
            <div class="sm:col-span-2">
                <label for="{{ $formId }}-payee-address" class="block text-sm font-medium text-slate-700">Recipient's Address <span class="text-red-600">*</span></label>
                <input id="{{ $formId }}-payee-address" name="payee_address" type="text" value="{{ $fieldValue('payee_address') }}" required placeholder="Enter the recipient's complete address" class="mt-1.5 block h-11 w-full rounded-lg border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @if($useOld)<x-form.error name="payee_address" :id="$formId.'-payee_address-error'" />@endif
            </div>
            <div class="sm:col-span-2">
                <label for="{{ $formId }}-emergency-category" class="block text-sm font-medium text-slate-700">Emergency Category <span class="text-red-600">*</span></label>
                <x-form.custom-select id="{{ $formId }}-emergency-category" name="emergency_category" placeholder="" required data-emergency-category-select>
                    @foreach (\App\Http\Requests\Disbursements\StoreDisbursementRequest::EMERGENCY_CATEGORIES as $category)
                        <option value="{{ $category }}" @selected($fieldValue('emergency_category', str_starts_with($formId, 'edit-') ? 'Other Emergency' : '') === $category)>{{ $category }}</option>
                    @endforeach
                </x-form.custom-select>
                <x-form.error name="emergency_category" />
            </div>
            <div class="sm:col-span-2">
                <label for="{{ $formId }}-purpose" class="block text-sm font-medium text-slate-700">Purpose / Description of Emergency <span class="text-red-600">*</span></label>
                <textarea id="{{ $formId }}-purpose" name="purpose" rows="3" required placeholder="e.g. Purchase of relief goods for affected families" class="mt-1.5 block w-full resize-y rounded-lg border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ $fieldValue('purpose') }}</textarea>
                @if($useOld)<x-form.error name="purpose" :id="$formId.'-purpose-error'" />@endif
            </div>
            <div>
                <label for="{{ $formId }}-fund-source" class="block text-sm font-medium text-slate-700">Where will the funds come from? <span class="text-red-600">*</span></label>
                <select id="{{ $formId }}-fund-source" name="fund_source" required class="mt-1.5 block h-11 w-full rounded-lg border-slate-300 bg-white px-3 pr-8 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Select fund source</option>
                    @foreach (config('disbursements.fund_sources', []) as $fundSource)
                        <option value="{{ $fundSource }}" @selected($fieldValue('fund_source') === $fundSource)>{{ $fundSource }}</option>
                    @endforeach
                </select>
                @if($useOld)<x-form.error name="fund_source" :id="$formId.'-fund_source-error'" />@endif
            </div>
            <div>
                <label for="{{ $formId }}-payee-tin" class="block text-sm font-medium text-slate-700">Recipient's TIN <span class="font-normal text-slate-500">(Optional)</span></label>
                <input id="{{ $formId }}-payee-tin" name="payee_tin" type="text" value="{{ $fieldValue('payee_tin') }}" placeholder="Enter TIN if available" class="mt-1.5 block h-11 w-full rounded-lg border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @if($useOld)<x-form.error name="payee_tin" :id="$formId.'-payee_tin-error'" />@endif
            </div>
            <div>
                <label for="{{ $formId }}-obr-number" class="block text-sm font-medium text-slate-700">Obligation Request No. (ObR) <span class="font-normal text-slate-500">(Optional)</span></label>
                <input id="{{ $formId }}-obr-number" name="obr_number" type="text" value="{{ $fieldValue('obr_number') }}" placeholder="Enter ObR number if available" class="mt-1.5 block h-11 w-full rounded-lg border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @if($useOld)<x-form.error name="obr_number" :id="$formId.'-obr_number-error'" />@endif
            </div>
        </div>
    </section>

    <section aria-labelledby="{{ $formId }}-location-heading" class="space-y-4 border-t border-slate-200 pt-5" data-fund-request-location-form data-location-form-id="{{ $formId }}">
        <div>
            <h3 id="{{ $formId }}-location-heading" class="text-sm font-semibold text-slate-950">Barangay Information</h3>
            <p class="mt-1 text-xs text-slate-500">The registered barangay location is automatically applied to this request.</p>
        </div>
        <div class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-3">
            <div><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Barangay</p><p class="mt-1 font-semibold text-slate-900">{{ $barangayValue ?: 'Not configured' }}</p></div>
            <div><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">City / Municipality</p><p class="mt-1 font-semibold text-slate-900">{{ $cityValue ?: 'Not configured' }}</p></div>
            <div><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Province</p><p class="mt-1 font-semibold text-slate-900">{{ $provinceValue ?: 'Not configured' }}</p></div>
        </div>
    </section>

    <section aria-labelledby="{{ $formId }}-additional-heading" class="space-y-4 border-t border-slate-200 pt-5">
        <div>
            <h3 id="{{ $formId }}-additional-heading" class="text-sm font-semibold text-slate-950">Additional Information</h3>
            <p class="mt-1 text-xs text-slate-500">Optional references or notes that help support the request.</p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="{{ $formId }}-notes" class="block text-sm font-medium text-slate-700">Notes / Remarks <span class="font-normal text-slate-500">(Optional)</span></label>
                <textarea id="{{ $formId }}-notes" name="notes" rows="3" placeholder="Enter other relevant information" class="mt-1.5 block w-full resize-y rounded-lg border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ $fieldValue('notes') }}</textarea>
                @if($useOld)<x-form.error name="notes" :id="$formId.'-notes-error'" />@endif
            </div>
        </div>
    </section>
</div>
