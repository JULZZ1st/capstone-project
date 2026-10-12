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
            'classification' => ['required', Rule::in(ReconciliationDiscrepancyService::CLASSIFICATIONS)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'explanation' => ['required', 'string', 'max:5000'],
            'evidence_reference' => ['required', 'string', 'max:255'],
            'resolution_status' => ['required', Rule::in(ReconciliationDiscrepancyService::STATUSES)],
            'adjustment_amount' => ['nullable', 'numeric'],
            'carry_forward' => ['nullable', 'boolean'],
        ];
    }
}
