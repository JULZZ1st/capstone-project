<?php

namespace App\Http\Requests\BankReconciliation;

use App\Models\BankLedgerEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use App\Support\Workspaces\WorkspaceValidationRules;

class ReconcileLedgerEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reconcile', BankLedgerEntry::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'bank_ledger_entry_id' => ['required', WorkspaceValidationRules::exists('bank_ledger_entries')->where('status', 'unmatched')],
            'contribution_id' => [
                'required',
                WorkspaceValidationRules::exists('contributions')
                    ->where('type', 'cash')
                    ->where('status', 'verified')
                    ->whereIn('payment_method', config('donations.electronic_payment_methods')),
            ],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) return;
            $entry = BankLedgerEntry::query()->find($this->integer('bank_ledger_entry_id'));
            $contribution = \App\Models\Contribution::query()->find($this->integer('contribution_id'));
            if (! $entry || ! in_array($entry->transaction_type, ['deposit', null], true)) {
                $validator->errors()->add('bank_ledger_entry_id', 'Only deposit transactions can be matched to contributions.');
            }
            if ($entry && $contribution && bccomp((string) $entry->amount, (string) $contribution->amount, 2) !== 0) {
                $validator->errors()->add('contribution_id', 'The bank deposit amount must exactly match the contribution amount.');
            }
        }];
    }
}
