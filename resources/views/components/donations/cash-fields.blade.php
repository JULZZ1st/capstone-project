@props(['active' => true, 'donors'])

<section
    class="space-y-4 border-t border-slate-100 pt-4"
    data-contribution-section="cash"
    data-cash-fields
    @hidden(! $active)
>
    <div class="flex items-center gap-2">
        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-700"><x-icon name="banknotes" class="h-4 w-4" /></span>
        <div>
            <h3 class="text-sm font-semibold text-slate-950">Cash Donation Details</h3>
            <p class="text-xs text-slate-500">Enter the details of the cash contribution received.</p>
        </div>
    </div>
    <x-donations.donor-select
        id="cash_donor_id"
        :donors="$donors"
        :selected="old('donor_id')"
    />
    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <x-form.label for="purpose_type">What is the donation for?</x-form.label>
            <x-form.custom-select id="purpose_type" name="purpose_type" placeholder="Select what the donation is for" data-donation-purpose aria-label="What is the donation for?">
                <option value="">Select purpose</option>
                <option value="general" @selected(old('purpose_type', 'general') === 'general')>General Barangay Use</option>
                <option value="specific" @selected(old('purpose_type') === 'specific')>For a Specific Purpose</option>
            </x-form.custom-select>
            <x-form.error name="purpose_type" />
        </div>
        <div data-purpose-name-field hidden>
            <x-form.label for="purpose_name">Specify Purpose</x-form.label>
            <input id="purpose_name" name="purpose_name" type="text" value="{{ old('purpose_name') }}" placeholder="Enter the project or purpose" disabled class="mt-1 block h-10 w-full rounded-lg border-slate-300 px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <x-form.error name="purpose_name" />
        </div>
    </div>
    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <x-form.label for="amount">Donation Amount</x-form.label>
            <div class="relative mt-1">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm font-medium text-slate-500">PHP</span>
                <input
                    id="amount"
                    name="amount"
                    type="number"
                    value="{{ old('amount') }}"
                    step="0.01"
                    min="0.01"
                    inputmode="decimal"
                    placeholder="0.00"
                    class="block w-full rounded-md border-slate-300 py-2 pl-12 pr-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    @error('amount') aria-invalid="true" aria-describedby="amount-error" @enderror
                >
            </div>
            <x-form.error name="amount" />
        </div>
        <div>
            <x-form.label for="payment_method">How was the donation received?</x-form.label>
            <x-form.custom-select id="payment_method" name="payment_method" placeholder="Select how it was received" data-payment-method aria-label="How was the donation received?">
                <option value="">Select method</option>
                @foreach (config('donations.payment_methods') as $value => $label)
                    <option value="{{ $value }}" @selected(old('payment_method') === $value)>{{ $label }}</option>
                @endforeach
            </x-form.custom-select>
            <x-form.error name="payment_method" />
        </div>
        <div>
            <x-form.label for="cash_received_at">Date Received</x-form.label>
            <input id="cash_received_at" name="received_at" type="date" value="{{ old('received_at', now()->toDateString()) }}" required class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <x-form.error name="received_at" />
        </div>
        <div data-payment-method-field="check" hidden>
            <x-form.label for="check_number">Check Number</x-form.label>
            <input id="check_number" name="check_number" type="text" value="{{ old('check_number') }}" placeholder="Enter check number" disabled class="mt-1 block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <x-form.error name="check_number" />
        </div>
        <div data-payment-method-field="check" hidden>
            <x-form.label for="bank_name">Bank Name</x-form.label>
            <input id="bank_name" name="bank_name" type="text" value="{{ old('bank_name') }}" placeholder="Enter bank name" disabled class="mt-1 block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <x-form.error name="bank_name" />
        </div>
        <div data-payment-method-field="bank_transfer" data-reference-field hidden class="md:col-span-2">
            <x-form.label for="reference_number" data-reference-label>Bank Reference Number</x-form.label>
            <input
                id="reference_number"
                name="reference_number"
                type="text"
                value="{{ old('reference_number') }}"
                data-reference-number
                placeholder="Enter bank reference number"
                disabled
                class="mt-1 block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                @error('reference_number') aria-invalid="true" aria-describedby="reference_number-error" @enderror
            >
            <x-form.error name="reference_number" />
        </div>
        <div data-payment-method-field="bank_transfer">
            <x-form.label for="registered_bank_account">Registered Barangay Bank Account</x-form.label>
            <input id="registered_bank_account" name="registered_bank_account" type="text" value="{{ old('registered_bank_account') }}" placeholder="Use the account name/last 4 digits from the approved list" disabled class="mt-1 block h-10 w-full rounded-lg border-slate-300 px-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <p class="mt-1 text-xs text-slate-500">Confirm this against the barangay’s approved account list.</p>
            <x-form.error name="registered_bank_account" />
        </div>
    </div>
    <div>
        <x-form.label for="cash_notes">Notes <span class="font-normal text-slate-400">(optional)</span></x-form.label>
        <textarea id="cash_notes" name="notes" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('notes') }}</textarea>
        <x-form.error name="notes" />
    </div>
    <x-donations.cash-documents />
</section>
