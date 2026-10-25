<?php

namespace App\Http\Requests\Donations;

use App\Models\Contribution;
use App\Models\InventoryCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Support\Workspaces\WorkspaceValidationRules;

class StoreContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Contribution::class) ?? false;
    }

    public function rules(): array
    {
        $isInKind = $this->input('donation_type') === 'in_kind';

        return [
            'donation_type' => ['required', Rule::in(array_keys(config('donations.types')))],
            'donor_id' => ['required', WorkspaceValidationRules::exists('donors')],
            'received_at' => ['nullable', 'date_format:Y-m-d'],
            'amount' => ['required_if:donation_type,cash', 'nullable', 'numeric', 'min:0.01', 'max:99999999.99'],
            'payment_method' => ['required_if:donation_type,cash', 'nullable', Rule::in(array_keys(config('donations.payment_methods')))],
            'reference_number' => [
                Rule::requiredIf(fn () => $this->requiresExternalReference()),
                'nullable',
                'string',
                'max:100',
                WorkspaceValidationRules::unique('contributions', 'reference_number')->where(fn ($query) => $query->where('payment_method', $this->input('payment_method'))),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'items' => $isInKind ? ['required', 'array', 'min:1'] : ['array'],
            'items.*.name' => ['required_if:donation_type,in_kind', 'string', 'max:255'],
            'items.*.category' => ['required_if:donation_type,in_kind', Rule::in([...array_keys(InventoryCategory::options()), '__other__'])],
            'items.*.custom_category' => ['required_if:items.*.category,__other__', 'nullable', 'string', 'max:255'],
            'items.*.subcategory' => ['nullable', 'string', 'max:100'],
            'items.*.custom_subcategory' => ['required_if:items.*.subcategory,__other__', 'nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required_if:donation_type,in_kind', 'numeric', 'min:0.01', 'max:999999.99'],
            'items.*.unit' => ['required_if:donation_type,in_kind', Rule::in(array_keys(config('donations.units')))],
            'items.*.condition' => ['required_if:donation_type,in_kind', Rule::in(array_keys(config('donations.item_conditions')))],
            'items.*.estimated_value' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('donation_type') !== 'in_kind') {
                return;
            }

            $categories = InventoryCategory::query()
                ->active()
                ->with(['subcategories' => fn ($query) => $query->active()->orderBy('label')])
                ->get()
                ->keyBy('key');

            foreach ($this->input('items', []) as $index => $item) {
                $categoryKey = Arr::get($item, 'category');
                $subcategoryKey = Arr::get($item, 'subcategory');

                if ($categoryKey === '__other__' && blank(Arr::get($item, 'custom_category'))) {
                    $validator->errors()->add("items.{$index}.custom_category", 'Specify a category.');
                }

                if ($subcategoryKey === '__other__' && blank(Arr::get($item, 'custom_subcategory'))) {
                    $validator->errors()->add("items.{$index}.custom_subcategory", 'Specify a subcategory.');
                }

                if (! $categoryKey || ! $categories->has($categoryKey)) {
                    continue;
                }

                $subcategories = $categories[$categoryKey]->subcategories->keyBy('key');
                $field = "items.{$index}.subcategory";

                if ($subcategories->isNotEmpty() && blank($subcategoryKey)) {
                    $validator->errors()->add($field, 'The subcategory field is required when the selected category has subcategories.');

                    continue;
                }

                if (filled($subcategoryKey) && ! $subcategories->has($subcategoryKey)) {
                    if ($subcategoryKey === '__other__') {
                        continue;
                    }

                    $validator->errors()->add($field, 'The selected subcategory is invalid for the selected category.');
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $donationType = $this->nullableLower($this->input('donation_type'));

        $items = $donationType === 'in_kind'
            ? collect($this->input('items', []))
                ->map(function ($item) {
                    $item = is_array($item) ? $item : [];

                    return [
                        'name' => $this->nullableValue(Arr::get($item, 'name')),
                        'category' => $this->nullableLower(Arr::get($item, 'category')),
                        'custom_category' => $this->nullableValue(Arr::get($item, 'custom_category')),
                        'subcategory' => $this->nullableLower(Arr::get($item, 'subcategory')),
                        'custom_subcategory' => $this->nullableValue(Arr::get($item, 'custom_subcategory')),
                        'quantity' => $this->nullableValue(Arr::get($item, 'quantity')),
                        'unit' => $this->nullableLower(Arr::get($item, 'unit')),
                        'condition' => $this->nullableLower(Arr::get($item, 'condition')),
                        'estimated_value' => $this->nullableValue(Arr::get($item, 'estimated_value')),
                        'notes' => $this->nullableValue(Arr::get($item, 'notes')),
                    ];
                })
                ->filter(fn ($item) => collect($item)->filter(fn ($value) => $value !== null)->isNotEmpty())
                ->values()
                ->all()
            : [];

        $this->merge([
            'donation_type' => $donationType,
            'amount' => $this->nullableValue($this->input('amount')),
            'payment_method' => $this->nullableLower($this->input('payment_method')),
            'reference_number' => $this->nullableValue($this->input('reference_number')),
            'notes' => $this->nullableValue($this->input('notes')),
            'items' => $items,
        ]);
    }

    private function requiresExternalReference(): bool
    {
        return $this->input('donation_type') === 'cash'
            && filled($this->input('payment_method'))
            && $this->input('payment_method') !== 'cash';
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
