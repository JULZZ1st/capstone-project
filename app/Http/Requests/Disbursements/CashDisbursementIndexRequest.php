<?php

namespace App\Http\Requests\Disbursements;

use App\Models\CashDisbursement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CashDisbursementIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', CashDisbursement::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', Rule::in(array_keys(config('disbursements.purposes')))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->nullableTrim('search'),
            'purpose' => $this->nullableLower('purpose'),
            'from' => $this->nullableTrim('from'),
            'to' => $this->nullableTrim('to'),
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
