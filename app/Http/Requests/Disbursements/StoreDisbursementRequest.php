<?php

namespace App\Http\Requests\Disbursements;

use App\Models\CashDisbursement;
use Illuminate\Foundation\Http\FormRequest;

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
            'requested_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'supporting_reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'purpose' => trim((string) $this->input('purpose')),
            'recipient' => trim((string) $this->input('recipient')),
            'notes' => $this->nullableTrim('notes'),
            'supporting_reference' => $this->nullableTrim('supporting_reference'),
        ]);
    }

    private function nullableTrim(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
