<?php

namespace App\Http\Requests\Disbursements;

use App\Models\CashDisbursement;
use Illuminate\Foundation\Http\FormRequest;

class RecordTreasurerCertificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('recordTreasurerCertification', $this->route('disbursement')) ?? false;
    }

    public function rules(): array
    {
        return [
            'certified' => ['accepted'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['notes' => trim((string) $this->input('notes')) ?: null]);
    }
}
