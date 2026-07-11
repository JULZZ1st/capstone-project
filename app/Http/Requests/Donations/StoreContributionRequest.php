<?php

namespace App\Http\Requests\Donations;

use App\Models\Contribution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Contribution::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'donation_type' => ['required', Rule::in(array_keys(config('donations.types')))],
            'donor_id' => ['required', 'exists:donors,id'],
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
            'amount' => ['required_if:donation_type,cash', 'nullable', 'numeric', 'min:0.01', 'max:99999999.99'],
            'payment_method' => ['required_if:donation_type,cash', 'nullable', Rule::in(array_keys(config('donations.payment_methods')))],
            'reference_number' => [
                Rule::requiredIf(fn () => $this->isElectronicPayment()),
                'nullable',
                'string',
                'max:100',
                Rule::unique('contributions', 'reference_number')->where(fn ($query) => $query->where('payment_method', $this->input('payment_method'))),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required_if:donation_type,in_kind', 'array', 'min:1'],
            'items.*.name' => ['required_if:donation_type,in_kind', 'string', 'max:255'],
            'items.*.category' => ['required_if:donation_type,in_kind', Rule::in(array_keys(config('donations.item_categories')))],
            'items.*.quantity' => ['required_if:donation_type,in_kind', 'numeric', 'min:0.01', 'max:999999.99'],
            'items.*.unit' => ['required_if:donation_type,in_kind', Rule::in(array_keys(config('donations.units')))],
            'items.*.condition' => ['required_if:donation_type,in_kind', Rule::in(array_keys(config('donations.item_conditions')))],
            'items.*.estimated_value' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->map(function ($item) {
                $item = is_array($item) ? $item : [];

                return [
                    'name' => $this->nullableValue(Arr::get($item, 'name')),
                    'category' => $this->nullableLower(Arr::get($item, 'category')),
                    'quantity' => $this->nullableValue(Arr::get($item, 'quantity')),
                    'unit' => $this->nullableLower(Arr::get($item, 'unit')),
                    'condition' => $this->nullableLower(Arr::get($item, 'condition')),
                    'estimated_value' => $this->nullableValue(Arr::get($item, 'estimated_value')),
                    'notes' => $this->nullableValue(Arr::get($item, 'notes')),
                ];
            })
            ->filter(fn ($item) => collect($item)->filter(fn ($value) => $value !== null)->isNotEmpty())
            ->values()
            ->all();

        $this->merge([
            'donation_type' => $this->nullableLower($this->input('donation_type')),
            'campaign_id' => $this->nullableValue($this->input('campaign_id')),
            'amount' => $this->nullableValue($this->input('amount')),
            'payment_method' => $this->nullableLower($this->input('payment_method')),
            'reference_number' => $this->nullableValue($this->input('reference_number')),
            'notes' => $this->nullableValue($this->input('notes')),
            'items' => $items,
        ]);
    }

    private function isElectronicPayment(): bool
    {
        return $this->input('donation_type') === 'cash'
            && in_array($this->input('payment_method'), config('donations.electronic_payment_methods'), true);
    }

    private function nullableValue(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableLower(mixed $value): ?string
    {
        $value = $this->nullableValue($value);

        return $value ? Str::lower($value) : null;
    }
}
