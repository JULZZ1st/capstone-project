<?php

namespace App\Http\Requests\BankReconciliation;

use App\Models\BankLedgerEntry;
use App\Services\BankReconciliation\ReconciliationDiscrepancyService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateReconciliationDiscrepancyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', BankLedgerEntry::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(ReconciliationDiscrepancyService::TYPES)],
            'custom_type' => ['nullable', 'string', 'max:120', 'required_if:type,other'],
            'classification' => ['required', Rule::in(ReconciliationDiscrepancyService::CLASSIFICATIONS)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'explanation' => ['required', 'string', 'max:5000'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'supporting_evidence' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'bank_ledger_entry_id' => ['nullable', 'integer', 'exists:bank_ledger_entries,id'],
            'system_record_type' => ['nullable', 'required_with:system_record_id', Rule::in(['contribution', 'cash_disbursement'])],
            'system_record_id' => ['nullable', 'integer', 'required_with:system_record_type'],
            'resolution_status' => ['required', Rule::in(ReconciliationDiscrepancyService::STATUSES)],
            'adjustment_amount' => ['nullable', 'numeric'],
            'carry_forward' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('custom_type')) {
            $this->merge(['custom_type' => trim((string) $this->input('custom_type'))]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $type = $this->input('type');
            $context = null;

            if ($this->filled('bank_ledger_entry_id')) {
                $entry = BankLedgerEntry::query()->find($this->integer('bank_ledger_entry_id'));
                $context = $entry?->transaction_type === 'withdrawal' ? 'outgoing' : ($entry ? 'incoming' : null);
            } elseif ($this->filled('system_record_type')) {
                $context = 'system';
            }

            if ($context && ! in_array($type, ReconciliationDiscrepancyService::allowedTypesForContext($context), true)) {
                $validator->errors()->add('type', 'Choose a discrepancy reason that matches this record.');
            }

            if ($type !== 'other' && $this->filled('custom_type')) {
                $validator->errors()->add('custom_type', 'Specify a custom type only when Others is selected.');
            }
        });
    }
}
