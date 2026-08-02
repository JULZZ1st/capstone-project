<?php

namespace App\Http\Requests\Disbursements;

use App\Models\CashDisbursement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCashDisbursementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CashDisbursement::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'disbursed_to' => ['required', 'string', 'max:255'],
            'purpose' => ['required', Rule::in(array_keys(config('disbursements.purposes')))],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'payment_method' => ['nullable', Rule::in(array_keys(config('donations.payment_methods')))],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'disbursed_at' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'disbursed_to' => $this->nullableTrim('disbursed_to'),
            'purpose' => $this->nullableLower('purpose'),
            'payment_method' => $this->nullableLower('payment_method'),
            'reference_number' => $this->nullableTrim('reference_number'),
            'notes' => $this->nullableTrim('notes'),
            'disbursed_at' => $this->nullableTrim('disbursed_at'),
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
