<?php

namespace App\Http\Requests\BankReconciliation;

use App\Models\BankLedgerEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReconcileLedgerEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reconcile', BankLedgerEntry::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'bank_ledger_entry_id' => ['required', Rule::exists('bank_ledger_entries', 'id')->where('status', 'unmatched')],
            'contribution_id' => [
                'required',
                Rule::exists('contributions', 'id')
                    ->where('type', 'cash')
                    ->whereIn('payment_method', config('donations.electronic_payment_methods')),
            ],
        ];
    }
}
