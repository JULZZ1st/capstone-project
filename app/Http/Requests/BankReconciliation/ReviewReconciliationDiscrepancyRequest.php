<?php

namespace App\Http\Requests\BankReconciliation;

use Illuminate\Foundation\Http\FormRequest;

class ReviewReconciliationDiscrepancyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        $noteRules = $this->route('decision') === 'correction_requested'
            ? ['required', 'string', 'max:5000']
            : ['nullable', 'string', 'max:5000'];

        return [
            'note' => $noteRules,
            'return_to' => ['nullable', 'in:pending_discrepancies'],
        ];
    }

    public function messages(): array
    {
        return ['note.required' => 'A reason is required when requesting correction.'];
    }
}
