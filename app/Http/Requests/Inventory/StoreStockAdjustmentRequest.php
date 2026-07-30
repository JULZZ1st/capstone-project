<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('adjustStock', $this->route('inventoryItem')) ?? false;
    }

    public function rules(): array
    {
        return [
            'movement_type' => ['required', Rule::in(array_keys(config('inventory.movement_types')))],
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'reason' => ['required', Rule::in(array_keys(config('inventory.adjustment_reasons')))],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'movement_type' => $this->nullableLower('movement_type'),
            'reason' => $this->nullableLower('reason'),
            'reference_number' => $this->nullableTrim('reference_number'),
            'remarks' => $this->nullableTrim('remarks'),
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
