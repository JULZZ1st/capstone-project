<?php

namespace App\Http\Requests\BankReconciliation;

use Illuminate\Foundation\Http\FormRequest;

class ResolveReconciliationDiscrepancyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('staff') ?? false;
    }

    public function rules(): array
    {
        return [
            'resolution_explanation' => ['required', 'string', 'max:5000'],
            'resolution_evidence' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }
}
