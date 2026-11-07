<?php

namespace App\Http\Requests\Disbursements;

use App\Models\CashDisbursement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDisbursementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CashDisbursement::class) ?? false;
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
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'purpose' => trim((string) $this->input('purpose')),
            'recipient' => trim((string) $this->input('recipient')),
            'payee_address' => trim((string) $this->input('payee_address')),
            'province' => $this->nullableTrim('province'),
            'city_municipality' => $this->nullableTrim('city_municipality'),
            'barangay' => $this->nullableTrim('barangay'),
            'payee_tin' => $this->nullableTrim('payee_tin'),
            'fund_source' => trim((string) $this->input('fund_source')),
            'obr_number' => $this->nullableTrim('obr_number'),
            'notes' => $this->nullableTrim('notes'),
        ]);
    }

    private function nullableTrim(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
