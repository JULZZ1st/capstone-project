<?php

namespace App\Http\Requests\BankReconciliation;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReconciliationPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('treasurer') ?? false;
    }

    public function rules(): array
    {
        return [
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'bank_ending_balance' => ['required', 'numeric', 'min:0'],
        ];
    }
}
