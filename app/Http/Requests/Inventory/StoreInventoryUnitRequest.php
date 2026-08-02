<?php

namespace App\Http\Requests\Inventory;

use App\Models\InventoryUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', InventoryUnit::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:100', Rule::unique('inventory_units', 'key')],
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
