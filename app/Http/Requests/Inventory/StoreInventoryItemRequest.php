<?php

namespace App\Http\Requests\Inventory;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', InventoryItem::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys(InventoryCategory::options()))],
            'description' => ['nullable', 'string', 'max:2000'],
            'unit' => ['required', Rule::in(array_keys(InventoryUnit::options()))],
            'initial_quantity' => ['required', 'numeric', 'min:0', 'max:999999.99'],
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
        $this->merge([
            'name' => $this->nullableTrim('name'),
            'category' => $this->nullableLower('category'),
            'description' => $this->nullableTrim('description'),
            'unit' => $this->nullableLower('unit'),
            'estimated_unit_value' => $this->nullableTrim('estimated_unit_value'),
            'expires_at' => $this->nullableTrim('expires_at'),
            'batch_number' => $this->nullableTrim('batch_number'),
            'storage_location' => $this->nullableTrim('storage_location'),
            'notes' => $this->nullableTrim('notes'),
            'status' => $this->nullableLower('status') ?? config('inventory.defaults.status'),
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
