<?php

namespace App\Http\Requests\BankReconciliation;

use App\Models\BankLedgerEntry;
use App\Models\CashDisbursement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'disbursement_ids.*' => ['integer', 'distinct', 'exists:cash_disbursements,id'],
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
                }
            },
        ];
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
