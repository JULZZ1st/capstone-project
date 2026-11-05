<?php

namespace App\Http\Requests\Disbursements;

use App\Models\CashDisbursement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDisbursementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('disbursement')) ?? false;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'purpose' => ['required', 'string', 'max:2000'],
            'recipient' => ['required', 'string', 'max:255'],
            'payee_address' => ['required', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'city_municipality' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'payee_tin' => ['nullable', 'string', 'max:30'],
            'fund_source' => ['required', 'string', Rule::in(config('disbursements.fund_sources', []))],
            'obr_number' => ['nullable', 'string', 'max:255'],
            'requested_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // The UI intentionally omits this legacy field; do not clear existing values on edit.
            'supporting_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['purpose', 'recipient', 'payee_address', 'fund_source'] as $key) {
            $this->merge([$key => trim((string) $this->input($key))]);
        }

        foreach (['province', 'city_municipality', 'barangay', 'payee_tin', 'obr_number', 'notes'] as $key) {
            $value = trim((string) $this->input($key));
            $this->merge([$key => $value === '' ? null : $value]);
        }
    }
}
