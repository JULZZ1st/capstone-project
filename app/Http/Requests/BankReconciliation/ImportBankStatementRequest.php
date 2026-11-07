<?php

namespace App\Http\Requests\BankReconciliation;

use App\Models\BankLedgerEntry;
use App\Models\BankReconciliation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportBankStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('import', BankLedgerEntry::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'statement' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:10240'],
            'period_id' => ['nullable', 'integer', Rule::exists('bank_reconciliations', 'id')],
            'bank_account' => ['nullable', 'string', 'max:255'],
        ];
    }
}
