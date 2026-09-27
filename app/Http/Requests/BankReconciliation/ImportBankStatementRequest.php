<?php

namespace App\Http\Requests\BankReconciliation;

use App\Models\BankLedgerEntry;
use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }
}