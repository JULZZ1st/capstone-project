@php
    $values = $values ?? [];
    $useOld = $useOld ?? false;
    $fieldValue = static function (string $key, mixed $fallback = '') use ($values, $useOld): mixed {
        return $useOld ? old($key, $fallback) : ($values[$key] ?? $fallback);
    };
    $provinceValue = $fieldValue('province', $locationDefaults['province'] ?? '');
    $cityValue = $fieldValue('city_municipality', $locationDefaults['city_municipality'] ?? '');
    $barangayValue = $fieldValue('barangay', $locationDefaults['barangay'] ?? '');
@endphp

<div class="min-h-0 flex-1 space-y-6 overflow-y-auto px-4 py-5 sm:px-6">
    <section aria-labelledby="{{ $formId }}-payment-heading" class="space-y-4">
        <div>
            <h3 id="{{ $formId }}-payment-heading" class="text-sm font-semibold text-slate-950">Payment Details</h3>
            <p class="mt-1 text-xs text-slate-500">Enter the basic payment information for this request.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="{{ $formId }}-amount" class="block text-sm font-medium text-slate-700">Amount Requested (₱) <span class="text-red-600">*</span></label>
                <input id="{{ $formId }}-amount" name="amount" type="number" step="0.01" min="0.01" value="{{ $fieldValue('amount') }}" required class="mt-1.5 block h-11 w-full rounded-lg border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <x-form.error name="amount" />
            </div>
            <div>
                <label for="{{ $formId }}-requested-date" class="block text-sm font-medium text-slate-700">Date of Request <span class="text-red-600">*</span></label>
                <input id="{{ $formId }}-requested-date" name="requested_date" type="date" value="{{ $fieldValue('requested_date') }}" required class="mt-1.5 block h-11 w-full rounded-lg border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <x-form.error name="requested_date" />
            </div>
            <div class="sm:col-span-2">
                <label for="{{ $formId }}-recipient" class="block text-sm font-medium text-slate-700">Who will receive the payment? <span class="text-red-600">*</span></label>
                <input id="{{ $formId }}-recipient" name="recipient" type="text" value="{{ $fieldValue('recipient') }}" required placeholder="Enter person or supplier name" class="mt-1.5 block h-11 w-full rounded-lg border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <x-form.error name="recipient" />
            </div>
            <div class="sm:col-span-2">
                <label for="{{ $formId }}-payee-address" class="block text-sm font-medium text-slate-700">Recipient's Address <span class="text-red-600">*</span></label>
                <input id="{{ $formId }}-payee-address" name="payee_address" type="text" value="{{ $fieldValue('payee_address') }}" required placeholder="Enter the recipient's complete address" class="mt-1.5 block h-11 w-full rounded-lg border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <x-form.error name="payee_address" />
            </div>
            <div class="sm:col-span-2">
                <label for="{{ $formId }}-purpose" class="block text-sm font-medium text-slate-700">Purpose of Payment <span class="text-red-600">*</span></label>
                <textarea id="{{ $formId }}-purpose" name="purpose" rows="3" required placeholder="e.g. Purchase of relief goods for affected families" class="mt-1.5 block w-full resize-y rounded-lg border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ $fieldValue('purpose') }}</textarea>
                <x-form.error name="purpose" />
            </div>
            <div>
                <label for="{{ $formId }}-fund-source" class="block text-sm font-medium text-slate-700">Where will the funds come from? <span class="text-red-600">*</span></label>
                <select id="{{ $formId }}-fund-source" name="fund_source" required class="mt-1.5 block h-11 w-full rounded-lg border-slate-300 bg-white px-3 pr-8 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Select fund source</option>
                    @foreach (config('disbursements.fund_sources', []) as $fundSource)
                        <option value="{{ $fundSource }}" @selected($fieldValue('fund_source') === $fundSource)>{{ $fundSource }}</option>
                    @endforeach
                </select>
                <x-form.error name="fund_source" />
            </div>
            <div>
                <label for="{{ $formId }}-payee-tin" class="block text-sm font-medium text-slate-700">Recipient's TIN <span class="font-normal text-slate-500">(Optional)</span></label>
                <input id="{{ $formId }}-payee-tin" name="payee_tin" type="text" value="{{ $fieldValue('payee_tin') }}" placeholder="Enter TIN if available" class="mt-1.5 block h-11 w-full rounded-lg border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <x-form.error name="payee_tin" />
            </div>
            <div>
                <label for="{{ $formId }}-obr-number" class="block text-sm font-medium text-slate-700">Obligation Request No. (ObR) <span class="font-normal text-slate-500">(Optional)</span></label>
                <input id="{{ $formId }}-obr-number" name="obr_number" type="text" value="{{ $fieldValue('obr_number') }}" placeholder="Enter ObR number if available" class="mt-1.5 block h-11 w-full rounded-lg border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <x-form.error name="obr_number" />
            </div>
        </div>
    </section>

    <section aria-labelledby="{{ $formId }}-location-heading" class="space-y-4 border-t border-slate-200 pt-5" data-fund-request-location-form data-location-form-id="{{ $formId }}">
        <div>
            <h3 id="{{ $formId }}-location-heading" class="text-sm font-semibold text-slate-950">Barangay Information</h3>
            <p class="mt-1 text-xs text-slate-500">Select the location used on the Disbursement Voucher. City and barangay options follow the selected province.</p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="{{ $formId }}-province" class="block text-sm font-medium text-slate-700">Province <span class="text-red-600">*</span></label>
                <x-form.custom-select id="{{ $formId }}-province" name="province" placeholder="Select province" searchable required data-location-province data-selected-value="{{ $provinceValue }}">
                    <option value="">Select province</option>
                    @foreach ($fundRequestProvinces ?? [] as $province)
                        <option value="{{ $province->name }}" data-code="{{ $province->code }}" @selected(strcasecmp((string) $provinceValue, $province->name) === 0)>{{ $province->name }}</option>
                    @endforeach
                </x-form.custom-select>
                <x-form.error name="province" />
            </div>
            <div>
                <label for="{{ $formId }}-city" class="block text-sm font-medium text-slate-700">City / Municipality <span class="text-red-600">*</span></label>
                <x-form.custom-select id="{{ $formId }}-city" name="city_municipality" placeholder="Select city / municipality" searchable required data-location-city data-selected-value="{{ $cityValue }}" disabled>
                    <option value="">Select city / municipality</option>
                </x-form.custom-select>
                <x-form.error name="city_municipality" />
            </div>
            <div class="sm:col-span-2">
                <label for="{{ $formId }}-barangay" class="block text-sm font-medium text-slate-700">Barangay <span class="text-red-600">*</span></label>
                <x-form.custom-select id="{{ $formId }}-barangay" name="barangay" placeholder="Select barangay" searchable required data-location-barangay data-selected-value="{{ $barangayValue }}" disabled>
                    <option value="">Select barangay</option>
                </x-form.custom-select>
                <x-form.error name="barangay" />
            </div>
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
                <x-form.error name="notes" />
            </div>
        </div>
    </section>
</div>
