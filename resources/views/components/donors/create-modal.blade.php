@props(['open' => false])

@php
    $selectedDonorType = old('donor_type', config('donors.defaults.type'));
@endphp

<dialog id="create-donor-modal" class="m-auto w-[calc(100%-2rem)] max-w-4xl overflow-hidden rounded-xl p-0 shadow-2xl backdrop:bg-slate-950/55" @if($open) open @endif>
    <form method="POST" action="{{ route('donors.store') }}" class="flex max-h-[92vh] flex-col bg-white" data-donor-create-form>
        @csrf
        <input type="hidden" name="_modal" value="create">
        @if (session('duplicate_donor_detected'))
            <input type="hidden" name="_confirm_duplicate" value="1">
        @endif

        <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-7">
            <div class="flex min-w-0 items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-100">
                    <x-icon name="users" class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <h2 class="text-lg font-semibold text-slate-950">Add New Donor Profile</h2>
                    <p class="mt-0.5 text-sm text-slate-500">Create a donor profile for future donation records.</p>
                </div>
            </div>
            <button type="button" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500" data-dialog-close="create-donor-modal" aria-label="Close modal">
                <x-icon name="x-circle" class="h-5 w-5" />
            </button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4 sm:px-7">
            <div class="space-y-5">
                @if (session('warning'))
                    <x-alert type="warning">{{ session('warning') }}</x-alert>
                @endif

                <section>
                    <h3 class="text-xs font-semibold uppercase text-slate-800">Basic Information</h3>
                    <div class="mt-2.5 grid gap-x-4 gap-y-3 sm:grid-cols-2">
                        <div>
                            <label for="donor_type" class="block text-xs font-medium text-slate-700">Donor Type</label>
                            <select id="donor_type" name="donor_type" class="mt-1 block h-10 w-full rounded-lg border-slate-300 px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required data-donor-type>
                                @foreach (config('donors.types') as $value => $label)
                                    <option value="{{ $value }}" @selected($selectedDonorType === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-form.error name="donor_type" />
                        </div>

                        <div data-organization-name-field @if($selectedDonorType !== 'organization') hidden @endif>
                            <label for="organization_name" class="block text-xs font-medium text-slate-700">Organization Name</label>
                            <input id="organization_name" name="organization_name" type="text" value="{{ old('organization_name', old('full_name')) }}" autocomplete="organization" required class="mt-1 block h-10 w-full rounded-lg border-slate-300 px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" @if($selectedDonorType !== 'organization') disabled @endif @error('organization_name') aria-invalid="true" aria-describedby="organization_name-error" @enderror>
                            <x-form.error name="organization_name" />
                            <x-form.error name="full_name" />
                        </div>

                        <div data-individual-name-field @if($selectedDonorType === 'organization') hidden @endif>
                            <label for="first_name" class="block text-xs font-medium text-slate-700">First Name</label>
                            <input id="first_name" name="first_name" type="text" value="{{ old('first_name') }}" autocomplete="given-name" required class="mt-1 block h-10 w-full rounded-lg border-slate-300 px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" @if($selectedDonorType === 'organization') disabled @endif @error('first_name') aria-invalid="true" aria-describedby="first_name-error" @enderror>
                            <x-form.error name="first_name" />
                        </div>

                        <div data-individual-name-field @if($selectedDonorType === 'organization') hidden @endif>
                            <label for="last_name" class="block text-xs font-medium text-slate-700">Last Name</label>
                            <input id="last_name" name="last_name" type="text" value="{{ old('last_name') }}" autocomplete="family-name" required class="mt-1 block h-10 w-full rounded-lg border-slate-300 px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" @if($selectedDonorType === 'organization') disabled @endif @error('last_name') aria-invalid="true" aria-describedby="last_name-error" @enderror>
                            <x-form.error name="last_name" />
                        </div>
                    </div>
                </section>

                <section class="border-t border-slate-200 pt-4">
                    <h3 class="text-xs font-semibold uppercase text-slate-800">Contact Information</h3>
                    <div class="mt-2.5 grid gap-x-4 gap-y-3 sm:grid-cols-2">
                        <x-form.input name="contact_number" label="Contact Number" required autocomplete="tel" class="h-10 rounded-lg text-sm" />
                        <x-form.input name="email" label="Email" type="email" autocomplete="email" class="h-10 rounded-lg text-sm" />

                        <div data-contact-person-field @if($selectedDonorType !== 'organization') hidden @endif>
                            <label for="contact_person" class="block text-xs font-medium text-slate-700">Contact Person</label>
                            <input id="contact_person" name="contact_person" type="text" value="{{ old('contact_person') }}" class="mt-1 block h-10 w-full rounded-lg border-slate-300 px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" @if($selectedDonorType !== 'organization') disabled @endif @error('contact_person') aria-invalid="true" aria-describedby="contact_person-error" @enderror>
                            <x-form.error name="contact_person" />
                        </div>

                        <div>
                            <label for="preferred_contact_method" class="block text-xs font-medium text-slate-700">Preferred Contact Method</label>
                            <select id="preferred_contact_method" name="preferred_contact_method" class="mt-1 block h-10 w-full rounded-lg border-slate-300 px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Not specified</option>
                                @foreach (config('donors.contact_methods') as $value => $label)
                                    <option value="{{ $value }}" @selected(old('preferred_contact_method') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-form.error name="preferred_contact_method" />
                        </div>
                    </div>
                </section>

                <section class="border-t border-slate-200 pt-4">
                    <h3 class="text-xs font-semibold uppercase text-slate-800">Location</h3>
                    <div class="mt-2.5 grid gap-x-4 gap-y-3 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="address" class="block text-xs font-medium text-slate-700">Address</label>
                            <textarea id="address" name="address" rows="2" class="mt-1 block h-16 w-full resize-none rounded-lg border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>{{ old('address') }}</textarea>
                            <x-form.error name="address" />
                        </div>

                        <x-form.input name="barangay_area" label="Barangay / Area" class="h-10 rounded-lg text-sm" />
                    </div>
                </section>

                <section class="border-t border-slate-200 pt-4">
                    <h3 class="text-xs font-semibold uppercase text-slate-800">Additional Information</h3>
                    <div class="mt-2.5">
                        <label for="notes" class="block text-xs font-medium text-slate-700">Notes</label>
                        <textarea id="notes" name="notes" rows="2" class="mt-1 block h-16 w-full resize-none rounded-lg border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('notes') }}</textarea>
                        <x-form.error name="notes" />
                    </div>
                </section>
            </div>
        </div>

        <div class="flex shrink-0 items-center justify-between gap-3 border-t border-slate-200 bg-slate-50/70 px-5 py-3.5 sm:px-7">
            <button type="button" class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2" data-dialog-close="create-donor-modal">Cancel</button>
            <x-button class="h-10 w-auto rounded-lg px-5">Save Donor Profile</x-button>
        </div>
    </form>
</dialog>
