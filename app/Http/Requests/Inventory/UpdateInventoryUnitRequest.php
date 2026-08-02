<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('inventoryUnit')) ?? false;
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:100', Rule::unique('inventory_units', 'key')->ignore($this->route('inventoryUnit'))],
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
