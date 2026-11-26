<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\Concerns\ReadsScalarInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Support\Workspaces\WorkspaceValidationRules;

class UpdateInventorySubcategoryRequest extends FormRequest
{
    use ReadsScalarInput;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('inventorySubcategory')->category) ?? false;
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
                    ->where('inventory_category_id', $this->input('inventory_category_id'))
                    ->ignore($this->route('inventorySubcategory')),
            ],
            'label' => ['required', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'key' => $this->normalizedKey('key'),
            'label' => $this->nullableTrim('label'),
        ]);
    }

    private function normalizedKey(string $key): ?string
    {
        $value = trim((string) $this->scalarInput($key));

        return $value === '' ? null : str($value)->slug('_')->toString();
    }

    private function nullableTrim(string $key): ?string
    {
        $value = trim((string) $this->scalarInput($key));

        return $value === '' ? null : $value;
    }
}
