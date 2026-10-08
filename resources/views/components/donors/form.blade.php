@props(['donor' => null, 'mode' => 'create'])

@php
    $prefix = $mode === 'edit' && $donor ? 'edit_'.$donor->id.'_' : '';
    $donorType = old('donor_type', $donor->donor_type ?? config('donors.defaults.type'));
    $isOrganization = $donorType === 'organization';
    $barangay = old('barangay', $donor->barangay ?? $donor->barangay_area ?? null);
@endphp

<div data-donor-form class="space-y-5">
    <section>
        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-800">Basic Information</h3>
        <div class="mt-2.5 grid gap-x-4 gap-y-3 sm:grid-cols-2">
            <div>
                <label for="{{ $prefix }}donor_type" class="block text-xs font-medium text-slate-700">Donor Type</label>
                <select id="{{ $prefix }}donor_type" name="donor_type" data-donor-type class="mt-1 block h-10 w-full rounded-lg border-slate-300 px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                    @foreach (config('donors.types') as $value => $label)
                        <option value="{{ $value }}" @selected($donorType === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-form.error name="donor_type" />
            </div>

            @if ($mode === 'edit')
                <div>
                    <label for="{{ $prefix }}status" class="block text-xs font-medium text-slate-700">Status</label>
                    <select id="{{ $prefix }}status" name="status" class="mt-1 block h-10 w-full rounded-lg border-slate-300 px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @foreach (config('donors.statuses') as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $donor->status ?? config('donors.defaults.status')) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-form.error name="status" />
                </div>
            @endif

            <div data-organization-name-field @if (! $isOrganization) hidden @endif>
                <label for="{{ $prefix }}organization_name" class="block text-xs font-medium text-slate-700">Organization Name</label>
                <input id="{{ $prefix }}organization_name" name="organization_name" type="text" value="{{ old('organization_name', $donor->full_name ?? '') }}" class="mt-1 block h-10 w-full rounded-lg border-slate-300 px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" @disabled(! $isOrganization) @required($isOrganization)>
                <x-form.error name="organization_name" />
            </div>

            <div data-individual-name-field @if ($isOrganization) hidden @endif>
                <label for="{{ $prefix }}first_name" class="block text-xs font-medium text-slate-700">First Name</label>
                <input id="{{ $prefix }}first_name" name="first_name" type="text" value="{{ old('first_name', $donor->first_name ?? '') }}" autocomplete="given-name" class="mt-1 block h-10 w-full rounded-lg border-slate-300 px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" @disabled($isOrganization) @required(! $isOrganization)>
                <x-form.error name="first_name" />
            </div>

            <div data-individual-name-field @if ($isOrganization) hidden @endif>
                <label for="{{ $prefix }}last_name" class="block text-xs font-medium text-slate-700">Last Name</label>
                <input id="{{ $prefix }}last_name" name="last_name" type="text" value="{{ old('last_name', $donor->last_name ?? '') }}" autocomplete="family-name" class="mt-1 block h-10 w-full rounded-lg border-slate-300 px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" @disabled($isOrganization) @required(! $isOrganization)>
                <x-form.error name="last_name" />
            </div>
        </div>
    </section>

    <section class="border-t border-slate-200 pt-4">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-800">Contact Information</h3>
        <div class="mt-2.5 grid gap-x-4 gap-y-3 sm:grid-cols-2">
            <x-form.input name="contact_number" label="Contact Number" :value="$donor->contact_number ?? null" required autocomplete="tel" class="h-10 rounded-lg text-sm" />
            <x-form.input name="email" label="Email" type="email" :value="$donor->email ?? null" autocomplete="email" class="h-10 rounded-lg text-sm" />

            @if (array_key_exists('organization', config('donors.types')))
                <div data-contact-person-field @if (! $isOrganization) hidden @endif>
                    <label for="{{ $prefix }}contact_person" class="block text-xs font-medium text-slate-700">Contact Person</label>
                    <input id="{{ $prefix }}contact_person" name="contact_person" type="text" value="{{ old('contact_person', $donor->contact_person ?? '') }}" class="mt-1 block h-10 w-full rounded-lg border-slate-300 px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" @disabled(! $isOrganization)>
                    <x-form.error name="contact_person" />
                </div>
            @endif

            <div>
                <label for="{{ $prefix }}preferred_contact_method" class="block text-xs font-medium text-slate-700">Preferred Contact Method</label>
                <select id="{{ $prefix }}preferred_contact_method" name="preferred_contact_method" class="mt-1 block h-10 w-full rounded-lg border-slate-300 px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Not specified</option>
                    @foreach (config('donors.contact_methods') as $value => $label)
                        <option value="{{ $value }}" @selected(old('preferred_contact_method', $donor->preferred_contact_method ?? null) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-form.error name="preferred_contact_method" />
            </div>
        </div>
    </section>

    <section class="border-t border-slate-200 pt-4">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-800">Address</h3>
        <div class="mt-2.5 grid gap-x-4 gap-y-3 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="{{ $prefix }}address" class="block text-xs font-medium text-slate-700">Street / Address</label>
                <textarea id="{{ $prefix }}address" name="address" rows="2" class="mt-1 block min-h-16 w-full resize-y rounded-lg border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>{{ old('address', $donor->address ?? '') }}</textarea>
                <x-form.error name="address" />
            </div>
            <x-form.input name="barangay" label="Barangay" :value="$barangay" class="h-10 rounded-lg text-sm" />
            <x-form.input name="city_municipality" label="City / Municipality" :value="$donor->city_municipality ?? null" class="h-10 rounded-lg text-sm" />
            <x-form.input name="province" label="Province" :value="$donor->province ?? null" class="h-10 rounded-lg text-sm" />
        </div>
    </section>

    <section class="border-t border-slate-200 pt-4">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-800">Additional Information</h3>
        <div class="mt-2.5">
            <label for="{{ $prefix }}notes" class="block text-xs font-medium text-slate-700">Notes</label>
            <textarea id="{{ $prefix }}notes" name="notes" rows="2" class="mt-1 block min-h-16 w-full resize-y rounded-lg border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('notes', $donor->notes ?? '') }}</textarea>
            <x-form.error name="notes" />
        </div>
    </section>
</div>
