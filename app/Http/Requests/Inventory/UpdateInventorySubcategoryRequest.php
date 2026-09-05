<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventorySubcategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('inventorySubcategory')->category) ?? false;
    }

    public function rules(): array
    {
        return [
            'inventory_category_id' => ['required', 'exists:inventory_categories,id'],
            'key' => [
                'required',
                'string',
                'max:100',
                Rule::unique('inventory_subcategories', 'key')
                    ->where('inventory_category_id', $this->input('inventory_category_id'))
                    ->ignore($this->route('inventorySubcategory')),
            ],
            'label' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'key' => $this->normalizedKey('key'),
            'label' => $this->nullableTrim('label'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    private function normalizedKey(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : str($value)->slug('_')->toString();
    }

    private function nullableTrim(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
