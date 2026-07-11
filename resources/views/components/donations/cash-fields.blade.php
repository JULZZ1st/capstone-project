@props(['active' => true])

<section class="space-y-4 rounded-lg border border-slate-200 p-4" data-contribution-section="cash" @hidden(! $active)>
    <h3 class="text-sm font-semibold text-slate-950">Cash / Electronic Transfer</h3>
    <div class="grid gap-4 md:grid-cols-3">
        <x-form.input name="amount" label="Donation Amount" type="number" step="0.01" min="0.01" />
        <div>
            <x-form.label for="payment_method">Payment Method</x-form.label>
            <select id="payment_method" name="payment_method" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Select method</option>
                @foreach (config('donations.payment_methods') as $value => $label)
                    <option value="{{ $value }}" @selected(old('payment_method') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <x-form.error name="payment_method" />
        </div>
        <x-form.input name="reference_number" label="Reference / Transaction No." />
    </div>
</section>
