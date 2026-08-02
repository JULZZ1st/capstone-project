@props(['disbursement' => null])

<div class="grid gap-4 md:grid-cols-2">
    <x-form.input name="disbursed_to" label="Recipient / Payee" :value="$disbursement?->disbursed_to" required />

    <div>
        <x-form.label for="purpose">Purpose</x-form.label>
        <select id="purpose" name="purpose" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
            <option value="">Select purpose</option>
            @foreach (config('disbursements.purposes') as $key => $label)
                <option value="{{ $key }}" @selected(old('purpose', $disbursement?->purpose) === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <x-form.error name="purpose" />
    </div>

    <x-form.input name="amount" label="Amount" type="number" step="0.01" min="0.01" :value="$disbursement?->amount" required />

    <div>
        <x-form.label for="payment_method">Payment Method</x-form.label>
        <select id="payment_method" name="payment_method" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <option value="">Not provided</option>
            @foreach (config('donations.payment_methods') as $key => $label)
                <option value="{{ $key }}" @selected(old('payment_method', $disbursement?->payment_method) === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <x-form.error name="payment_method" />
    </div>

    <x-form.input name="reference_number" label="Reference Number" :value="$disbursement?->reference_number" />
    <x-form.input name="disbursed_at" label="Date Disbursed" type="date" :value="$disbursement?->disbursed_at?->format('Y-m-d')" />
</div>

<div>
    <x-form.label for="notes">Notes</x-form.label>
    <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('notes', $disbursement?->notes) }}</textarea>
    <x-form.error name="notes" />
</div>
