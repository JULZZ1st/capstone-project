<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('inventoryItem')) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys(config('inventory.categories')))],
            'description' => ['nullable', 'string', 'max:2000'],
            'unit' => ['required', Rule::in(array_keys(config('inventory.units')))],
            'minimum_reserve_quantity' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'estimated_unit_value' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'expires_at' => ['nullable', 'date'],
            'batch_number' => ['nullable', 'string', 'max:100'],
            'storage_location' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(array_keys(config('inventory.item_statuses')))],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'description', 'estimated_unit_value', 'expires_at', 'batch_number', 'storage_location', 'notes'] as $key) {
            $this->merge([$key => $this->nullableTrim($key)]);
        }

        $this->merge([
            'category' => $this->nullableLower('category'),
            'unit' => $this->nullableLower('unit'),
            'status' => $this->nullableLower('status'),
        ]);
    }

    private function nullableTrim(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }

    private function nullableLower(string $key): ?string
    {
        $value = $this->nullableTrim($key);

        return $value ? strtolower($value) : null;
    }
}
