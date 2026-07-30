<?php

namespace App\Http\Requests\Inventory;

use App\Models\InventoryItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', InventoryItem::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', Rule::in(array_keys(config('inventory.categories')))],
            'status' => ['nullable', Rule::in(array_keys(config('inventory.item_statuses')))],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->nullableTrim('search'),
            'category' => $this->nullableLower('category'),
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
