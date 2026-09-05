@props(['active' => true])

<section
    class="space-y-4 rounded-lg border border-slate-200 p-4"
    data-contribution-section="cash"
    data-cash-fields
    @hidden(! $active)
>
    <h3 class="text-sm font-semibold text-slate-950">Cash / Electronic Transfer</h3>
    <div class="grid gap-4 md:grid-cols-3">
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
            <x-form.label for="payment_method">Payment Method</x-form.label>
            <select id="payment_method" name="payment_method" data-payment-method class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Select method</option>
                @foreach (config('donations.payment_methods') as $value => $label)
                    <option value="{{ $value }}" @selected(old('payment_method') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <x-form.error name="payment_method" />
        </div>
        <div data-reference-field hidden>
            <x-form.label for="reference_number" data-reference-label>External Payment Reference No.</x-form.label>
            <input
                id="reference_number"
                name="reference_number"
                type="text"
                value="{{ old('reference_number') }}"
                data-reference-number
                placeholder="Enter the reference from the payment provider"
                disabled
                class="mt-1 block w-full rounded-md border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                @error('reference_number') aria-invalid="true" aria-describedby="reference_number-error" @enderror
            >
            <x-form.error name="reference_number" />
        </div>
    </div>
</section>