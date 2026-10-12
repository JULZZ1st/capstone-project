<?php

namespace App\Http\Requests\BankReconciliation;

use App\Models\BankReconciliation;
use Illuminate\Foundation\Http\FormRequest;

class CreateReconciliationPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('prepare', BankReconciliation::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'bank_ending_balance' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
