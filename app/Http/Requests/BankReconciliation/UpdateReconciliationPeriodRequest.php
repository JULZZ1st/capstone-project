<?php

namespace App\Http\Requests\BankReconciliation;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReconciliationPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('period')) ?? false;
    }

    public function rules(): array
    {
        return [
            'opening_balance' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'bank_ending_balance' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
        ];
    }
}
