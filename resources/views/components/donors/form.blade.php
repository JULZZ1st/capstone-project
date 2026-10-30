@props(['donor' => null, 'mode' => 'create', 'startStep' => 0])

@php
    $prefix = $mode === 'edit' && $donor ? 'edit_'.$donor->id.'_' : '';
    $donorType = old('donor_type', $donor->donor_type ?? config('donors.defaults.type'));
    $isOrganization = $donorType === 'organization';
    $barangay = old('barangay', $donor->barangay ?? $donor->barangay_area ?? null);
    $stepTitles = ['Basic & Contact Information', 'Address Information', 'Additional Information'];
    $stepShortLabels = ['Basic & Contact', 'Address', 'Additional'];

    $isFreshCreateForm = $mode === 'create' && ! $donor
        && old('region') === null
        && old('province') === null
        && old('city_municipality') === null
        && old('barangay') === null;

    $centralVisayasName = 'Region VII (Central Visayas)';
    $selectedRegionName = $mode === 'create' ? $centralVisayasName : old('region', $donor->region ?? $centralVisayasName);
    $selectedProvinceName = $mode === 'create' ? old('province', 'Cebu') : old('province', $donor->province ?? 'Cebu');
    $selectedCityName = old('city_municipality', $donor->city_municipality ?? null);
    $selectedBarangayName = $barangay;

    $addressOptions = app(\App\Services\Donors\ResolveDonorAddressOptions::class)
        ->handle($selectedRegionName, $selectedProvinceName, $selectedCityName, $selectedBarangayName);

    // A matched city with no matched province can only come from the
    // province-independent (HUC/ICC) branch of the resolver, so this is a
    // reliable "no province applies" signal rather than "no match yet".
    $provinceIsNoneApplicable = $addressOptions['region'] && ! $addressOptions['province'] && $addressOptions['city'];

    $modalKey = $mode === 'edit' && $donor ? 'edit-'.$donor->id : 'create';
    if ($errors->any() && old('_modal') === $modalKey) {
        foreach ([
            ['donor_type', 'first_name', 'middle_name', 'last_name', 'suffix', 'organization_name', 'full_name', 'status', 'contact_number', 'email', 'preferred_contact_method', 'contact_person', 'tin'],
            ['address', 'barangay', 'city_municipality', 'province', 'region'],
            ['notes'],
        ] as $stepIndex => $fields) {
            if ($errors->hasAny($fields)) {
                $startStep = $stepIndex;
                break;
            }
        }
    }
@endphp

<div data-donor-form data-wizard-start-step="{{ $startStep }}" class="flex min-h-0 flex-1 flex-col">
    <nav aria-label="Donor profile steps" class="grid grid-cols-3 gap-1 border-b border-slate-200 px-4 py-3.5 sm:gap-2 sm:px-6" data-wizard-progress>
        @foreach ($stepShortLabels as $index => $label)
            <span class="flex min-w-0 items-center justify-center gap-1.5 text-xs font-medium text-slate-500 sm:gap-2" data-wizard-indicator="{{ $index }}">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-500" data-wizard-number>{{ $index + 1 }}</span>
                <span class="hidden truncate sm:inline">{{ $label }}</span>
            </span>
        @endforeach
    </nav>

    <div class="flex items-center justify-between px-4 pt-3.5 sm:px-6">
        <p class="text-xs font-medium text-slate-500">Step <span data-wizard-current>1</span> of {{ count($stepTitles) }}</p>
        <p class="text-sm font-semibold text-slate-800" data-wizard-title>{{ $stepTitles[$startStep] ?? $stepTitles[0] }}</p>
    </div>
    <div class="px-4 pt-3 sm:px-6">
        <div class="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800 shadow-sm" data-wizard-error role="alert" hidden>
            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-amber-500 text-xs font-bold text-white">!</span>
            <span>Please complete the required fields before continuing.</span>
        </div>
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-4 sm:px-6">
    <section data-wizard-step data-wizard-step-title="Basic & Contact Information">
        <div>
            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-800">Donor Details</h3>
            <div class="mt-3 grid gap-x-4 gap-y-4 sm:grid-cols-2">
                <div>
                    <label for="{{ $prefix }}donor_type" class="block text-sm font-medium text-slate-700">Donor Type</label>
                    <select id="{{ $prefix }}donor_type" name="donor_type" data-donor-type class="mt-1.5 block h-11 w-full rounded-lg border-slate-300 px-3 pr-10 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        @foreach (config('donors.types') as $value => $label)
                            <option value="{{ $value }}" @selected($donorType === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-form.error name="donor_type" />
                </div>

                @if ($mode === 'edit')
                    <div>
                        <label for="{{ $prefix }}status" class="block text-sm font-medium text-slate-700">Status</label>
                        <select id="{{ $prefix }}status" name="status" class="mt-1.5 block h-11 w-full rounded-lg border-slate-300 px-3 pr-10 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @foreach (config('donors.statuses') as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $donor->status ?? config('donors.defaults.status')) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-form.error name="status" />
                    </div>
                @endif

                <div data-organization-name-field @if (! $isOrganization) hidden @endif>
                    <label for="{{ $prefix }}organization_name" class="block text-sm font-medium text-slate-700">Organization Name</label>
                    <input id="{{ $prefix }}organization_name" name="organization_name" type="text" value="{{ old('organization_name', $donor->full_name ?? '') }}" class="mt-1.5 block h-11 w-full rounded-lg border-slate-300 px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" @disabled(! $isOrganization) @required($isOrganization)>
                    <x-form.error name="organization_name" />
                </div>

                <div data-individual-name-field @if ($isOrganization) hidden @endif>
                    <label for="{{ $prefix }}first_name" class="block text-sm font-medium text-slate-700">First Name</label>
                    <input id="{{ $prefix }}first_name" name="first_name" type="text" value="{{ old('first_name', $donor->first_name ?? '') }}" autocomplete="given-name" data-required-for-individual="true" class="mt-1.5 block h-11 w-full rounded-lg border-slate-300 px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" @disabled($isOrganization) @required(! $isOrganization)>
                    <x-form.error name="first_name" />
                </div>

                <div data-individual-name-field @if ($isOrganization) hidden @endif>
                    <label for="{{ $prefix }}middle_name" class="block text-sm font-medium text-slate-700">Middle Name <span class="font-normal text-slate-400">(optional)</span></label>
                    <input id="{{ $prefix }}middle_name" name="middle_name" type="text" value="{{ old('middle_name', $donor->middle_name ?? '') }}" class="mt-1.5 block h-11 w-full rounded-lg border-slate-300 px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" @disabled($isOrganization)>
                    <x-form.error name="middle_name" />
                </div>

                <div data-individual-name-field @if ($isOrganization) hidden @endif>
                    <label for="{{ $prefix }}last_name" class="block text-sm font-medium text-slate-700">Last Name</label>
                    <input id="{{ $prefix }}last_name" name="last_name" type="text" value="{{ old('last_name', $donor->last_name ?? '') }}" autocomplete="family-name" data-required-for-individual="true" class="mt-1.5 block h-11 w-full rounded-lg border-slate-300 px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" @disabled($isOrganization) @required(! $isOrganization)>
                    <x-form.error name="last_name" />
                </div>

                <div data-individual-name-field @if ($isOrganization) hidden @endif>
                    <label for="{{ $prefix }}suffix" class="block text-sm font-medium text-slate-700">Suffix <span class="font-normal text-slate-400">(optional)</span></label>
                    <select id="{{ $prefix }}suffix" name="suffix" class="mt-1.5 block h-11 w-full rounded-lg border-slate-300 px-3 pr-10 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" @disabled($isOrganization)>
                        <option value="" @selected(old('suffix', $donor->suffix ?? '') === '')>None</option>
                        @foreach (['jr' => 'Jr.', 'sr' => 'Sr.', 'ii' => 'II', 'iii' => 'III', 'iv' => 'IV'] as $value => $label)
                            <option value="{{ $value }}" @selected(strtolower((string) old('suffix', $donor->suffix ?? '')) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-form.error name="suffix" />
                </div>

                <div data-organization-name-field @if (! $isOrganization) hidden @endif>
                    <label for="{{ $prefix }}contact_person" class="block text-sm font-medium text-slate-700">Authorized Representative Name</label>
                    <input id="{{ $prefix }}contact_person" name="contact_person" type="text" value="{{ old('contact_person', $donor->contact_person ?? '') }}" class="mt-1.5 block h-11 w-full rounded-lg border-slate-300 px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" @disabled(! $isOrganization) @required($isOrganization)>
                    <x-form.error name="contact_person" />
                </div>
            </div>
        </div>

        <div class="mt-5 border-t border-slate-100 pt-4">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-800">Contact Details</h3>
            <div class="mt-3 grid gap-x-4 gap-y-4 sm:grid-cols-2">
                <x-form.input name="contact_number" label="Contact Number" :value="$donor->contact_number ?? null" required autocomplete="tel" class="h-11 rounded-lg text-sm" />
                <x-form.input name="email" label="Email" type="email" :value="$donor->email ?? null" autocomplete="email" class="h-11 rounded-lg text-sm" />
                <div data-organization-optional-field @if (! $isOrganization) hidden @endif>
                    <x-form.input name="tin" label="TIN" :value="$donor->tin ?? null" class="h-11 rounded-lg text-sm" @disabled(! $isOrganization) />
                </div>

                <div>
                    <label for="{{ $prefix }}preferred_contact_method" class="block text-sm font-medium text-slate-700">Preferred Contact Method</label>
                    <select id="{{ $prefix }}preferred_contact_method" name="preferred_contact_method" class="mt-1.5 block h-11 w-full rounded-lg border-slate-300 px-3 pr-10 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Not specified</option>
                        @foreach (['phone' => 'Call', 'sms' => 'Text', 'email' => 'Email'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('preferred_contact_method', $donor->preferred_contact_method ?? null) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-form.error name="preferred_contact_method" />
                </div>
            </div>
        </div>
    </section>

    <section data-wizard-step data-wizard-step-title="Address Information" hidden>
        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-800">Address Information</h3>
        <p class="mt-1 text-sm text-slate-500">Select the donor's Cebu city or municipality and barangay. Street / Purok / House No. is optional.</p>
        <input type="hidden" id="{{ $prefix }}region" name="region" value="{{ $selectedRegionName }}">
        <div class="mt-3 grid gap-x-4 gap-y-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700">Region</label>
                <div class="mt-1.5 flex h-11 items-center rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm text-slate-600">Central Visayas</div>
            </div>

            <div>
                <label for="{{ $prefix }}province" class="block text-sm font-medium text-slate-700">Province</label>
                <select id="{{ $prefix }}province" name="province" data-address-province class="mt-1.5 block h-11 w-full rounded-lg border-slate-300 px-3 pr-10 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-slate-100" @disabled(! $addressOptions['region'])>
                    <option value="" @selected(! $addressOptions['province'] && ! $provinceIsNoneApplicable)>Select Province</option>
                    @foreach ($addressOptions['provinces'] as $province)
                        <option value="{{ $province->name }}" data-code="{{ $province->code }}" @selected($addressOptions['province']?->code === $province->code)>{{ $province->name }}</option>
                    @endforeach
                </select>
                <x-form.error name="province" />
            </div>

            <div>
                <label for="{{ $prefix }}city_municipality" class="block text-sm font-medium text-slate-700">City / Municipality</label>
                <select id="{{ $prefix }}city_municipality" name="city_municipality" data-address-city class="mt-1.5 block h-11 w-full rounded-lg border-slate-300 px-3 pr-10 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-slate-100" @disabled(! $addressOptions['region'])>
                    <option value="" @selected(! $addressOptions['city'])>Select City / Municipality</option>
                    @foreach ($addressOptions['cities'] as $city)
                        <option value="{{ $city->name }}" data-code="{{ $city->code }}" @selected($addressOptions['city']?->code === $city->code)>{{ $city->name }}</option>
                    @endforeach
                </select>
                <x-form.error name="city_municipality" />
            </div>

            <div>
                <label for="{{ $prefix }}barangay" class="block text-sm font-medium text-slate-700">Barangay</label>
                <select id="{{ $prefix }}barangay" name="barangay" data-address-barangay class="mt-1.5 block h-11 w-full rounded-lg border-slate-300 px-3 pr-10 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-slate-100" @disabled(! $addressOptions['city'])>
                    <option value="" @selected(! $addressOptions['barangay'])>Select Barangay</option>
                    @foreach ($addressOptions['barangays'] as $barangayOption)
                        <option value="{{ $barangayOption->name }}" data-code="{{ $barangayOption->code }}" @selected($addressOptions['barangay']?->code === $barangayOption->code)>{{ $barangayOption->name }}</option>
                    @endforeach
                </select>
                <x-form.error name="barangay" />
            </div>

            <div class="sm:col-span-2">
                <label for="{{ $prefix }}address" class="block text-sm font-medium text-slate-700">Street / Purok / House No.</label>
                <textarea id="{{ $prefix }}address" name="address" rows="2" placeholder="Optional — e.g. Purok 3, 123 Rizal St." class="mt-1.5 block min-h-16 w-full resize-y rounded-lg border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('address', $donor->address ?? '') }}</textarea>
                <x-form.error name="address" />
            </div>
        </div>
    </section>

    <section data-wizard-step data-wizard-step-title="Additional Information" hidden>
        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-800">Additional Information</h3>
        <p class="mt-1 text-sm text-slate-500">Optional — anything else worth noting about this donor.</p>
        <div class="mt-3">
            <label for="{{ $prefix }}notes" class="block text-sm font-medium text-slate-700">Notes</label>
            <textarea id="{{ $prefix }}notes" name="notes" rows="3" class="mt-1.5 block min-h-20 w-full resize-y rounded-lg border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('notes', $donor->notes ?? '') }}</textarea>
            <x-form.error name="notes" />
        </div>
    </section>
    </div>
</div>
