<?php

namespace App\Http\Requests\BankReconciliation;

use App\Models\BankLedgerEntry;
use App\Models\CashDisbursement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use App\Support\Workspaces\WorkspaceValidationRules;

class StoreWithdrawalDisbursementMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', BankLedgerEntry::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'disbursement_ids' => ['nullable', 'array'],
            'disbursement_ids.*' => ['integer', 'distinct', WorkspaceValidationRules::exists('cash_disbursements')],

            'discrepancy_reason' => ['nullable', 'string', 'max:5000'],
            'finalize' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $withdrawal = $this->route('withdrawal');

                if (! $withdrawal instanceof BankLedgerEntry || ! $withdrawal->isWithdrawal() || $withdrawal->isReconciled()) {
                    $validator->errors()->add('withdrawal', 'Only unreconciled bank withdrawals can be matched.');

                    return;
                }

                $ids = $this->disbursementIds();

                if ($ids === []) {
                    $this->validateDiscrepancyReason($validator, $withdrawal, 0);

                    if ($this->boolean('finalize') && abs((float) $withdrawal->amount) >= 0.005) {
                        $validator->errors()->add('finalize', 'The withdrawal can only be reconciled when the difference is zero.');
                    }

                    return;
                }

                $validCount = CashDisbursement::query()
                    ->approvedActive()
                    ->whereIn('id', $ids)
                    ->where(function ($query) use ($withdrawal) {
                        $query
                            ->whereDoesntHave('withdrawalMatch')
                            ->orWhereHas('withdrawalMatch', fn ($matchQuery) => $matchQuery->where('bank_ledger_entry_id', $withdrawal->id));
                    })
                    ->count();

                if ($validCount !== count($ids)) {
                    $validator->errors()->add('disbursement_ids', 'Selected disbursements must be approved, active, and not already matched to another withdrawal.');

                    return;
                }

                $selectedTotal = CashDisbursement::query()
                    ->whereIn('id', $ids)
                    ->sum('amount');

                $difference = (float) $withdrawal->amount - (float) $selectedTotal;

                $this->validateDiscrepancyReason($validator, $withdrawal, (float) $selectedTotal);

                if ($this->boolean('finalize') && abs($difference) >= 0.005) {
                    $validator->errors()->add('finalize', 'The withdrawal can only be reconciled when the difference is zero.');
                }
            },
        ];
    }

    private function validateDiscrepancyReason(Validator $validator, BankLedgerEntry $withdrawal, float $selectedTotal): void
    {
        $difference = (float) $withdrawal->amount - $selectedTotal;

        if (abs($difference) >= 0.005 && trim((string) $this->input('discrepancy_reason')) === '') {
            $validator->errors()->add('discrepancy_reason', 'An explanation is required while the withdrawal difference remains unresolved.');
        }
    }

    public function disbursementIds(): array
    {
        return collect($this->input('disbursement_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
