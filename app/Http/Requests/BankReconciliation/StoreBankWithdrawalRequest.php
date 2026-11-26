<?php

namespace App\Http\Requests\BankReconciliation;

use App\Http\Requests\Concerns\ReadsScalarInput;
use App\Models\BankLedgerEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rule;

class StoreBankWithdrawalRequest extends FormRequest
{
    use ReadsScalarInput;

    public function authorize(): bool
    {
        return $this->user()?->can('create', BankLedgerEntry::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'transaction_date' => ['required', 'date'],
            'reference_number' => [
                'required',
                'string',
                'max:100',
            ],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'bank_account' => ['nullable', 'string', Rule::in(config('bank_reconciliation.bank_accounts', []))],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $duplicateExists = BankLedgerEntry::query()
                    ->forBankAccount($this->input('bank_account') ?: config('bank_reconciliation.bank_accounts.0'))
                    ->where('transaction_type', 'withdrawal')
                    ->where('reference_number', $this->input('reference_number'))
                    ->whereDate('transaction_date', $this->input('transaction_date'))
                    ->where('amount', (float) $this->input('amount'))
                    ->exists();

                if ($duplicateExists) {
                    $validator->errors()->add(
                        'reference_number',
                        'A bank withdrawal with the same reference number, date, and amount already exists.',
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'reference_number.unique' => 'A bank withdrawal with the same reference number, date, and amount already exists.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reference_number' => trim((string) $this->scalarInput('reference_number')),
            'description' => trim((string) $this->scalarInput('description')),
        ]);
    }
}
