<?php

namespace App\Http\Requests\Inventory;

use App\Models\InventoryCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Support\Workspaces\WorkspaceValidationRules;

class StoreInventorySubcategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', InventoryCategory::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'inventory_category_id' => ['required', WorkspaceValidationRules::exists('inventory_categories')],
            'key' => [
                'required',
                'string',
                'max:100',
                Rule::unique('inventory_subcategories', 'key')
                    ->where('inventory_category_id', $this->input('inventory_category_id')),
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
